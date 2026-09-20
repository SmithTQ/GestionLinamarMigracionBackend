<?php

namespace App\Http\Controllers\Api\V1\Forms;

use App\Http\Controllers\Controller;
use App\Models\FormSubmissionFile;
use App\Models\User;
use App\Services\Authorization\OperationalScopeService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FormSubmissionFileController extends Controller
{
    public function show(Request $request, FormSubmissionFile $file)
    {
        $file->load('submission.order');
        $order = $file->submission?->order;
        $this->authorizeFile($request, $order);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name, ['Content-Type' => $file->mime_type]);
    }

    public function replace(Request $request, int $order, FormSubmissionFile $file)
    {
        $file->load('submission.order');
        $model = $file->submission?->order;
        $this->authorizeFile($request, $model);
        abort_if(in_array($model->status, ['delivered', 'cancelled'], true), 422, 'El pedido ya no admite cambios.');
        abort_unless($file->submission->order_id === $model->id, 404);
        $request->validate(['file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('forms.file_max_size_kb', 5120)]]);

        /** @var UploadedFile $uploaded */
        $uploaded = $request->file('file');
        $disk = config('forms.file_disk', 'local');
        $path = $uploaded->store('form-submissions/'.$file->submission_id, $disk);
        $oldDisk = $file->disk;
        $oldPath = $file->path;
        $file->update(['disk' => $disk, 'path' => $path, 'original_name' => Str::of(basename($uploaded->getClientOriginalName()))->replaceMatches('/[^A-Za-z0-9._-]/', '_')->limit(255, '')->toString(), 'mime_type' => $uploaded->getMimeType() ?: 'application/octet-stream', 'size' => $uploaded->getSize(), 'hash' => hash_file('sha256', $uploaded->getRealPath())]);
        Storage::disk($oldDisk)->delete($oldPath);

        return response()->json(['codigo' => 200, 'mensaje' => 'Archivo reemplazado.', 'datos' => ['id' => $file->id, 'name' => $file->original_name, 'mime_type' => $file->mime_type, 'size' => $file->size, 'url' => url('/api/v1/form-submission-files/'.$file->id)]]);
    }

    public function destroy(Request $request, FormSubmissionFile $file)
    {
        $file->load('submission.order');
        $order = $file->submission?->order;
        $this->authorizeFile($request, $order);
        abort_if(in_array($order->status, ['delivered', 'cancelled'], true), 422, 'El pedido ya no admite cambios.');
        Storage::disk($file->disk)->delete($file->path);
        $file->delete();

        return response()->json(['codigo' => 200, 'mensaje' => 'Archivo eliminado.', 'datos' => null]);
    }

    private function authorizeFile(Request $request, $order): void
    {
        abort_unless($order, 404);
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($order->campaign && $order->branch && app(OperationalScopeService::class)->canAccessCampaign($actor, $order->campaign) && app(OperationalScopeService::class)->canAccessBranch($actor, $order->branch), 403, 'No tienes acceso al archivo del pedido.');
    }
}
