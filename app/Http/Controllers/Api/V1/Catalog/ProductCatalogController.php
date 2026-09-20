<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Products\AssignCampaignProductsRequest;
use App\Http\Requests\Api\V1\Products\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Products\StoreProductRequest;
use App\Http\Requests\Api\V1\Products\StoreSubcategoryRequest;
use App\Http\Requests\Api\V1\Products\UpdateCategoryRequest;
use App\Http\Requests\Api\V1\Products\UpdateProductRequest;
use App\Http\Requests\Api\V1\Products\UpdateSubcategoryRequest;
use App\Http\Resources\Api\V1\ProductCategoryResource;
use App\Http\Resources\Api\V1\ProductResource;
use App\Http\Resources\Api\V1\ProductSubcategoryResource;
use App\Models\Campaign;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSubcategory;
use App\Models\User;
use App\Services\Authorization\OperationalScopeService;
use App\Services\Catalog\ProductIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Productos', description: 'Catálogo de productos y asignación por campaña')]
class ProductCatalogController extends Controller
{
    #[OA\Get(path: '/api/v1/product-categories', operationId: 'listProductCategories', tags: ['Productos'], summary: 'Listar categorías', responses: [new OA\Response(response: 200, description: 'Categorías obtenidas')])]
    public function categories(Request $request): JsonResponse
    {
        $items = ProductCategory::query()->where('is_active', true)->when($request->filled('search'), fn ($query) => $query->where(fn ($sub) => $sub->where('name', 'like', '%'.$request->string('search')->toString().'%')->orWhere('slug', 'like', '%'.$request->string('search')->toString().'%')))->with(['subcategories' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->with('products')])->tap(fn ($query) => $this->applySorting($query, $request, ['sort_order' => 'sort_order', 'name' => 'name', 'slug' => 'slug', 'created_at' => 'created_at'], 'sort_order'))->get();

        return response()->json(['codigo' => 200, 'mensaje' => 'Categorías obtenidas.', 'datos' => ProductCategoryResource::collection($items)]);
    }

    #[OA\Post(path: '/api/v1/product-categories', operationId: 'createProductCategory', tags: ['Productos'], summary: 'Crear categoría', responses: [new OA\Response(response: 201, description: 'Categoría creada')])]
    public function storeCategory(StoreCategoryRequest $request): JsonResponse
    {
        return response()->json(['codigo' => 201, 'mensaje' => 'Categoría creada.', 'datos' => new ProductCategoryResource(ProductCategory::create($request->validated()))], 201);
    }

    #[OA\Patch(path: '/api/v1/product-categories/{category}', operationId: 'updateProductCategory', tags: ['Productos'], summary: 'Actualizar categoría', responses: [new OA\Response(response: 200, description: 'Categoría actualizada')])]
    public function updateCategory(UpdateCategoryRequest $request, int $category): JsonResponse
    {
        $item = ProductCategory::findOrFail($category);
        $item->update($request->validated());

        return response()->json(['codigo' => 200, 'mensaje' => 'Categoría actualizada.', 'datos' => new ProductCategoryResource($item->fresh())]);
    }

    #[OA\Delete(path: '/api/v1/product-categories/{category}', operationId: 'deleteProductCategory', tags: ['Productos'], summary: 'Desactivar categoría', responses: [new OA\Response(response: 200, description: 'Categoría desactivada')])]
    public function deleteCategory(int $category): JsonResponse
    {
        $item = ProductCategory::findOrFail($category);
        $item->update(['is_active' => false]);
        $item->delete();

        return response()->json(['codigo' => 200, 'mensaje' => 'Categoría desactivada.', 'datos' => null]);
    }

    #[OA\Get(path: '/api/v1/product-subcategories', operationId: 'listProductSubcategories', tags: ['Productos'], summary: 'Listar subcategorías', responses: [new OA\Response(response: 200, description: 'Subcategorías obtenidas')])]
    public function subcategories(Request $request): JsonResponse
    {
        $items = ProductSubcategory::query()->where('is_active', true)->with('category')->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))->when($request->filled('search'), fn ($query) => $query->where(fn ($sub) => $sub->where('name', 'like', '%'.$request->string('search')->toString().'%')->orWhere('slug', 'like', '%'.$request->string('search')->toString().'%')))->tap(fn ($query) => $this->applySorting($query, $request, ['sort_order' => 'sort_order', 'name' => 'name', 'slug' => 'slug', 'created_at' => 'created_at'], 'sort_order'))->paginate(min($request->integer('per_page', 50), 100));

        return $this->paginatedResponse('Subcategorías obtenidas.', $items, ProductSubcategoryResource::class);
    }

    #[OA\Post(path: '/api/v1/product-subcategories', operationId: 'createProductSubcategory', tags: ['Productos'], summary: 'Crear subcategoría', responses: [new OA\Response(response: 201, description: 'Subcategoría creada')])]
    public function storeSubcategory(StoreSubcategoryRequest $request): JsonResponse
    {
        return response()->json(['codigo' => 201, 'mensaje' => 'Subcategoría creada.', 'datos' => new ProductSubcategoryResource(ProductSubcategory::create($request->validated()))], 201);
    }

    #[OA\Patch(path: '/api/v1/product-subcategories/{subcategory}', operationId: 'updateProductSubcategory', tags: ['Productos'], summary: 'Actualizar subcategoría', responses: [new OA\Response(response: 200, description: 'Subcategoría actualizada')])]
    public function updateSubcategory(UpdateSubcategoryRequest $request, int $subcategory): JsonResponse
    {
        $item = ProductSubcategory::findOrFail($subcategory);
        $item->update($request->validated());

        return response()->json(['codigo' => 200, 'mensaje' => 'Subcategoría actualizada.', 'datos' => new ProductSubcategoryResource($item->fresh())]);
    }

    #[OA\Delete(path: '/api/v1/product-subcategories/{subcategory}', operationId: 'deleteProductSubcategory', tags: ['Productos'], summary: 'Desactivar subcategoría', responses: [new OA\Response(response: 200, description: 'Subcategoría desactivada')])]
    public function deleteSubcategory(int $subcategory): JsonResponse
    {
        $item = ProductSubcategory::findOrFail($subcategory);
        $item->update(['is_active' => false]);
        $item->delete();

        return response()->json(['codigo' => 200, 'mensaje' => 'Subcategoría desactivada.', 'datos' => null]);
    }

    #[OA\Get(path: '/api/v1/products', operationId: 'listProducts', tags: ['Productos'], summary: 'Listar productos', responses: [new OA\Response(response: 200, description: 'Productos obtenidos')])]
    public function products(Request $request, OperationalScopeService $scope): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401, 'Debes iniciar sesión para consultar productos.');
        $branchId = $scope->validatedBranchId($actor, $request->integer('branch_id') ?: null);
        $items = Product::query()->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId), fn ($query) => $query->whereIn('branch_id', $scope->visibleBranches($actor)->select('branches.id')))->where('is_active', true)->with(['subcategory.category', 'branch'])->when($request->filled('search'), fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$request->string('search')->toString().'%')->orWhere('sku', 'like', '%'.$request->string('search')->toString().'%')->orWhere('slug', 'like', '%'.$request->string('search')->toString().'%')))->when($request->filled('subcategory_id'), fn ($query) => $query->where('subcategory_id', $request->integer('subcategory_id')))->when($request->filled('category_id'), fn ($query) => $query->whereHas('subcategory', fn ($relation) => $relation->where('category_id', $request->integer('category_id'))))->when($request->filled('unit'), fn ($query) => $query->where('unit', $request->string('unit')->toString()))->when($request->filled('base_price_from'), fn ($query) => $query->where('base_price', '>=', $request->input('base_price_from')))->when($request->filled('base_price_to'), fn ($query) => $query->where('base_price', '<=', $request->input('base_price_to')))->tap(fn ($query) => $this->applySorting($query, $request, ['sort_order' => 'sort_order', 'name' => 'name', 'sku' => 'sku', 'base_price' => 'base_price', 'created_at' => 'created_at'], 'sort_order'))->paginate(min($request->integer('per_page', 50), 100));

        return $this->paginatedResponse('Productos obtenidos.', $items, ProductResource::class);
    }

    #[OA\Post(
        path: '/api/v1/products',
        operationId: 'createProduct',
        tags: ['Productos'],
        summary: 'Crear producto',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['name'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'Caja de rosas'),
                        new OA\Property(property: 'branch_id', type: 'integer', example: 2),
                        new OA\Property(property: 'subcategory_id', type: 'integer', nullable: true, example: 1),
                        new OA\Property(property: 'sku', type: 'string', nullable: true, example: 'PROD-000001'),
                        new OA\Property(property: 'slug', type: 'string', nullable: true, example: 'caja-de-rosas'),
                        new OA\Property(property: 'description', type: 'string', nullable: true),
                        new OA\Property(property: 'unit', type: 'string', example: 'unidad'),
                        new OA\Property(property: 'base_price', type: 'number', format: 'float', nullable: true, example: 59.90),
                        new OA\Property(property: 'sort_order', type: 'integer', example: 0),
                        new OA\Property(property: 'image', type: 'string', format: 'binary', nullable: true),
                    ],
                ),
            ),
        ),
        responses: [new OA\Response(response: 201, description: 'Producto creado')],
    )]
    public function storeProduct(StoreProductRequest $request, ProductIdentityService $identity, OperationalScopeService $scope): JsonResponse
    {
        $this->ensureBranchAccess($request->user(), (int) $request->validated('branch_id'), $scope);
        $product = $identity->create($request->validated());

        return response()->json(['codigo' => 201, 'mensaje' => 'Producto creado.', 'datos' => new ProductResource($product->load(['subcategory', 'branch']))], 201);
    }

    #[OA\Patch(
        path: '/api/v1/products/{product}',
        operationId: 'updateProduct',
        tags: ['Productos'],
        summary: 'Actualizar producto',
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'subcategory_id', type: 'integer', nullable: true),
                        new OA\Property(property: 'slug', type: 'string', nullable: true),
                        new OA\Property(property: 'description', type: 'string', nullable: true),
                        new OA\Property(property: 'unit', type: 'string'),
                        new OA\Property(property: 'base_price', type: 'number', format: 'float', nullable: true),
                        new OA\Property(property: 'sort_order', type: 'integer'),
                        new OA\Property(property: 'is_active', type: 'boolean'),
                        new OA\Property(property: 'image', type: 'string', format: 'binary', nullable: true),
                    ],
                ),
            ),
        ),
        responses: [new OA\Response(response: 200, description: 'Producto actualizado')],
    )]
    public function updateProduct(UpdateProductRequest $request, int $product, ProductIdentityService $identity, OperationalScopeService $scope): JsonResponse
    {
        $item = Product::findOrFail($product);
        $this->ensureProductAccess($request->user(), $item, $scope);
        $data = $request->validated();
        $image = $data['image'] ?? null;
        unset($data['image']);
        $item = $identity->update($item, $data, $image);

        return response()->json(['codigo' => 200, 'mensaje' => 'Producto actualizado.', 'datos' => new ProductResource($item)], 200);
    }

    #[OA\Delete(path: '/api/v1/products/{product}', operationId: 'deleteProduct', tags: ['Productos'], summary: 'Desactivar producto', responses: [new OA\Response(response: 200, description: 'Producto desactivado')])]
    public function deleteProduct(Request $request, int $product, OperationalScopeService $scope): JsonResponse
    {
        $item = Product::findOrFail($product);
        $this->ensureProductAccess($request->user(), $item, $scope);
        $item->update(['is_active' => false]);
        $item->delete();

        return response()->json(['codigo' => 200, 'mensaje' => 'Producto desactivado.', 'datos' => null]);
    }

    #[OA\Get(path: '/api/v1/campaigns/{campaign}/products', operationId: 'listCampaignProducts', tags: ['Productos'], summary: 'Listar productos de campaña', responses: [new OA\Response(response: 200, description: 'Productos de campaña obtenidos')])]
    public function campaignProducts(Request $request, int $campaign, OperationalScopeService $scope): JsonResponse
    {
        $item = Campaign::findOrFail($campaign);
        $this->ensureCampaignAccess($request->user(), $item, $scope);
        $products = $item->products()->where('products.is_active', true)->when($request->has('is_available'), fn ($query) => $query->where('campaign_product.is_available', $request->boolean('is_available')), fn ($query) => $query->where('campaign_product.is_available', true))->when($request->filled('search'), fn ($query) => $query->where(fn ($sub) => $sub->where('products.name', 'like', '%'.$request->string('search')->toString().'%')->orWhere('products.sku', 'like', '%'.$request->string('search')->toString().'%')))->when($request->filled('subcategory_id'), fn ($query) => $query->where('products.subcategory_id', $request->integer('subcategory_id')))->tap(function ($query) use ($request): void {
            $sortBy = $request->string('sort_by')->toString();
            $direction = strtolower($request->string('sort_dir')->toString());
            $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';
            if (in_array($sortBy, ['price', 'is_available', 'sort_order'], true)) {
                $query->orderBy('campaign_product.'.$sortBy, $direction);
            } elseif (in_array($sortBy, ['name', 'sku'], true)) {
                $query->orderBy('products.'.$sortBy, $direction);
            } else {
                $query->orderBy('campaign_product.sort_order', 'asc');
            }
            $query->orderBy('products.id', 'asc');
        })->with('subcategory.category')->get();

        return response()->json(['codigo' => 200, 'mensaje' => 'Productos de campaña obtenidos.', 'datos' => ProductResource::collection($products)]);
    }

    #[OA\Put(path: '/api/v1/campaigns/{campaign}/products', operationId: 'assignCampaignProducts', tags: ['Productos'], summary: 'Configurar productos de campaña', responses: [new OA\Response(response: 200, description: 'Productos de campaña actualizados')])]
    public function assignCampaignProducts(AssignCampaignProductsRequest $request, int $campaign, OperationalScopeService $scope): JsonResponse
    {
        $item = Campaign::findOrFail($campaign);
        $this->ensureCampaignAccess($request->user(), $item, $scope);
        $sync = [];
        foreach ($request->validated('products') as $product) {
            abort_unless(Product::query()->whereKey($product['product_id'])->where('branch_id', $item->branch_id)->exists(), 422, 'Todos los productos deben pertenecer a la sucursal de la campaña.');
            $sync[$product['product_id']] = ['price' => $product['price'] ?? null, 'is_available' => $product['is_available'] ?? true, 'sort_order' => $product['sort_order'] ?? 0, 'max_quantity' => $product['max_quantity'] ?? null];
        }
        $item->products()->sync($sync);

        return response()->json(['codigo' => 200, 'mensaje' => 'Productos de campaña actualizados.', 'datos' => ProductResource::collection($item->products()->with('subcategory.category')->orderBy('campaign_product.sort_order')->orderBy('products.id')->get())]);
    }

    private function ensureCampaignAccess(?User $actor, Campaign $campaign, OperationalScopeService $scope): void
    {
        abort_unless($actor instanceof User && $scope->canAccessCampaign($actor, $campaign), 403, 'No tienes acceso a la campaña.');
    }

    private function ensureBranchAccess(?User $actor, int $branchId, OperationalScopeService $scope): void
    {
        abort_unless($actor instanceof User && $scope->canAccessBranch($actor, $branchId), 403, 'No tienes acceso a la sucursal seleccionada.');
    }

    private function ensureProductAccess(?User $actor, Product $product, OperationalScopeService $scope): void
    {
        $this->ensureBranchAccess($actor, (int) $product->branch_id, $scope);
    }
}
