<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductIdentityService
{
    public function create(array $data): Product
    {
        $image = $data['image'] ?? null;
        unset($data['image']);

        $autoSku = blank($data['sku'] ?? null);
        $autoSlug = blank($data['slug'] ?? null);
        $lastException = null;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $storedFiles = [];

            try {
                return DB::transaction(function () use ($data, $image, $autoSku, $autoSlug, &$storedFiles): Product {
                    $payload = $data;
                    $payload['sku'] = $autoSku ? $this->nextSku() : $payload['sku'];
                    $payload['slug'] = $autoSlug ? $this->nextSlug($payload['name']) : $payload['slug'];

                    if ($image !== null) {
                        $storedFiles = $this->storeImage($image);
                        $payload['image_path'] = $storedFiles['original_path'];
                        $payload['image_thumbnail_path'] = $storedFiles['thumbnail_path'];
                        $payload['image_url'] = $this->imageUrl($storedFiles['original_path']);
                    }

                    return Product::create($payload);
                });
            } catch (QueryException $exception) {
                $lastException = $exception;
                $this->deleteFiles($storedFiles);

                if (! $this->isUniqueViolation($exception) || (! $autoSku && ! $autoSlug)) {
                    throw $exception;
                }
            }
        }

        if ($lastException !== null) {
            throw $lastException;
        }

        throw new \RuntimeException('No se pudo generar una identidad única para el producto.');
    }

    public function update(Product $product, array $data, $image = null): Product
    {
        unset($data['image']);
        $storedFiles = [];
        $oldFiles = array_filter([$product->image_path, $product->image_thumbnail_path]);

        try {
            $updated = DB::transaction(function () use ($product, $data, $image, &$storedFiles): Product {
                $payload = $data;

                if ($image !== null) {
                    $storedFiles = $this->storeImage($image);
                    $payload['image_path'] = $storedFiles['original_path'];
                    $payload['image_thumbnail_path'] = $storedFiles['thumbnail_path'];
                    $payload['image_url'] = $this->imageUrl($storedFiles['original_path']);
                }

                $product->update($payload);

                return $product->fresh('subcategory');
            });
        } catch (\Throwable $exception) {
            $this->deleteFiles($storedFiles);

            throw $exception;
        }

        if ($storedFiles !== []) {
            $this->deleteFiles($oldFiles);
        }

        return $updated;
    }

    private function nextSku(): string
    {
        $nextNumber = ((int) Product::withTrashed()->lockForUpdate()->max('id')) + 1;

        return 'PROD-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }

    private function nextSlug(string $name): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'producto';
        $slug = $base;
        $suffix = 2;

        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function storeImage($image): array
    {
        $extension = match ($image->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new \InvalidArgumentException('Tipo de imagen no permitido.'),
        };
        $name = (string) Str::uuid();
        $originalPath = 'products/original/'.$name.'.'.$extension;
        $thumbnailPath = 'products/thumbs/'.$name.'.webp';
        $disk = Storage::disk($this->disk());
        $storedPaths = [];

        try {
            $thumbnail = $this->thumbnailContents($image);
            if ($disk->putFileAs('products/original', $image, $name.'.'.$extension) === false) {
                throw new \RuntimeException('No se pudo almacenar la imagen del producto.');
            }
            $storedPaths[] = $originalPath;
            if ($disk->put($thumbnailPath, $thumbnail) === false) {
                throw new \RuntimeException('No se pudo almacenar el thumbnail del producto.');
            }
            $storedPaths[] = $thumbnailPath;
        } catch (\Throwable $exception) {
            $this->deleteFiles($storedPaths);
            throw $exception;
        }

        return ['original_path' => $originalPath, 'thumbnail_path' => $thumbnailPath];
    }

    private function thumbnailContents($image): string
    {
        if (! function_exists('imagewebp')) {
            throw new \RuntimeException('El servidor no tiene soporte GD/WebP para generar thumbnails.');
        }

        $source = @imagecreatefromstring((string) file_get_contents($image->getRealPath()));
        if ($source === false) {
            throw new \RuntimeException('No se pudo leer la imagen para generar el thumbnail.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $maxSize = 480;
        $scale = min(1, $maxSize / $width, $maxSize / $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);
        $hasAlpha = in_array($image->getMimeType(), ['image/png', 'image/webp'], true);

        if ($hasAlpha) {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            imagefill($thumbnail, 0, 0, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
        } else {
            $background = imagecolorallocate($thumbnail, 255, 255, 255);
            imagefill($thumbnail, 0, 0, $background);
        }

        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        ob_start();
        $encoded = imagewebp($thumbnail, null, 82);
        $contents = ob_get_clean();
        imagedestroy($source);
        imagedestroy($thumbnail);

        if (! $encoded || $contents === false || $contents === '') {
            throw new \RuntimeException('No se pudo codificar el thumbnail del producto.');
        }

        return $contents;
    }

    private function imageUrl(string $path): string
    {
        return Storage::disk($this->disk())->url($path);
    }

    private function deleteFiles(array $paths): void
    {
        $paths = array_values(array_filter($paths));
        if ($paths !== []) {
            Storage::disk($this->disk())->delete($paths);
        }
    }

    private function disk(): string
    {
        return (string) config('filesystems.product_disk', 'public');
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23000'
            || str_contains(strtolower($exception->getMessage()), 'duplicate');
    }
}
