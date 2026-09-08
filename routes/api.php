<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Catalog\BranchController;
use App\Http\Controllers\Api\V1\Catalog\CampaignController;
use App\Http\Controllers\Api\V1\Catalog\DistrictController;
use App\Http\Controllers\Api\V1\Catalog\ProductCatalogController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\Orders\OrderController;
use App\Http\Controllers\Api\V1\Dispatch\CourierController;
use App\Http\Controllers\Api\V1\Dispatch\DeliveryRouteController;
use App\Http\Controllers\Api\V1\Imports\GoogleSheetsImportController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Forms\CampaignFormController;
use App\Http\Controllers\Api\V1\Forms\PublicCampaignFormController;
use App\Http\Controllers\Api\V1\Customers\CustomerInvitationController;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/public/forms/{publicKey}', [PublicCampaignFormController::class, 'show'])->middleware('throttle:public-form');
    Route::post('/public/forms/{publicKey}/submissions', [PublicCampaignFormController::class, 'submit'])->middleware('throttle:public-form');
    Route::get('/public/invitations/{token}', [PublicCampaignFormController::class, 'showInvitation'])->middleware('throttle:public-form');
    Route::post('/public/invitations/{token}/submissions', [PublicCampaignFormController::class, 'submitInvitation'])->middleware('throttle:public-form');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::get('/branches', [BranchController::class, 'index'])->middleware('permission:branches.view');
        Route::post('/branches', [BranchController::class, 'store'])->middleware('permission:branches.manage');
        Route::get('/branches/{branch}', [BranchController::class, 'show'])->middleware('permission:branches.view');
        Route::patch('/branches/{branch}', [BranchController::class, 'update'])->middleware('permission:branches.manage');
        Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->middleware('permission:branches.manage');

        Route::get('/districts', [DistrictController::class, 'index'])->middleware('permission:branches.view');
        Route::post('/districts', [DistrictController::class, 'store'])->middleware('permission:branches.manage');
        Route::get('/districts/{district}', [DistrictController::class, 'show'])->middleware('permission:branches.view');
        Route::patch('/districts/{district}', [DistrictController::class, 'update'])->middleware('permission:branches.manage');
        Route::delete('/districts/{district}', [DistrictController::class, 'destroy'])->middleware('permission:branches.manage');

        Route::get('/product-categories', [ProductCatalogController::class, 'categories'])->middleware('permission:products.view');
        Route::post('/product-categories', [ProductCatalogController::class, 'storeCategory'])->middleware('permission:products.manage');
        Route::patch('/product-categories/{category}', [ProductCatalogController::class, 'updateCategory'])->middleware('permission:products.manage');
        Route::delete('/product-categories/{category}', [ProductCatalogController::class, 'deleteCategory'])->middleware('permission:products.manage');
        Route::get('/product-subcategories', [ProductCatalogController::class, 'subcategories'])->middleware('permission:products.view');
        Route::post('/product-subcategories', [ProductCatalogController::class, 'storeSubcategory'])->middleware('permission:products.manage');
        Route::patch('/product-subcategories/{subcategory}', [ProductCatalogController::class, 'updateSubcategory'])->middleware('permission:products.manage');
        Route::delete('/product-subcategories/{subcategory}', [ProductCatalogController::class, 'deleteSubcategory'])->middleware('permission:products.manage');
        Route::get('/products', [ProductCatalogController::class, 'products'])->middleware('permission:products.view');
        Route::post('/products', [ProductCatalogController::class, 'storeProduct'])->middleware('permission:products.manage');
        Route::patch('/products/{product}', [ProductCatalogController::class, 'updateProduct'])->middleware('permission:products.manage');
        Route::delete('/products/{product}', [ProductCatalogController::class, 'deleteProduct'])->middleware('permission:products.manage');
        Route::get('/campaigns/{campaign}/products', [ProductCatalogController::class, 'campaignProducts'])->middleware('permission:campaigns.view');
        Route::put('/campaigns/{campaign}/products', [ProductCatalogController::class, 'assignCampaignProducts'])->middleware('permission:campaigns.manage');

        Route::get('/form-templates', [CampaignFormController::class, 'templates'])->middleware('permission:forms.view');
        Route::post('/customer-invitations', [CustomerInvitationController::class, 'store'])->middleware('permission:forms.manage');
        Route::get('/customers', [CustomerInvitationController::class, 'customers'])->middleware('permission:forms.view');
        Route::get('/customers/{customer}', [CustomerInvitationController::class, 'showCustomer'])->middleware('permission:forms.view');
        Route::get('/customer-invitations', [CustomerInvitationController::class, 'invitations'])->middleware('permission:forms.view');
        Route::post('/customer-invitations/{invitation}/revoke', [CustomerInvitationController::class, 'revoke'])->middleware('permission:forms.manage');
        Route::get('/campaign-forms', [CampaignFormController::class, 'index'])->middleware('permission:forms.view');
        Route::post('/campaign-forms', [CampaignFormController::class, 'store'])->middleware('permission:forms.manage');
        Route::get('/campaign-forms/{form}', [CampaignFormController::class, 'show'])->middleware('permission:forms.view');
        Route::patch('/campaign-forms/{form}', [CampaignFormController::class, 'update'])->middleware('permission:forms.manage');
        Route::post('/campaign-forms/{form}/publish', [CampaignFormController::class, 'publish'])->middleware('permission:forms.manage');
        Route::post('/campaign-forms/{form}/close', [CampaignFormController::class, 'close'])->middleware('permission:forms.manage');

        Route::get('/campaigns', [CampaignController::class, 'index'])->middleware('permission:campaigns.view');
        Route::post('/campaigns', [CampaignController::class, 'store'])->middleware('permission:campaigns.manage');
        Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->middleware('permission:campaigns.view');
        Route::patch('/campaigns/{campaign}', [CampaignController::class, 'update'])->middleware('permission:campaigns.manage');
        Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy'])->middleware('permission:campaigns.manage');

        Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view');
        Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.manage');
        Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:users.view');
        Route::patch('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.manage');
        Route::get('/roles', [UserController::class, 'roles'])->middleware('permission:users.view');
        Route::get('/permissions', [UserController::class, 'permissions'])->middleware('permission:users.view');

        Route::get('/orders', [OrderController::class, 'index'])->middleware('permission:orders.view');
        Route::post('/orders', [OrderController::class, 'store'])->middleware('permission:orders.manage');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('permission:orders.view');
        Route::patch('/orders/{order}', [OrderController::class, 'update'])->middleware('permission:orders.manage');
        Route::patch('/orders/{order}/status', [OrderController::class, 'changeStatus'])->middleware('permission:orders.manage');
        Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->middleware('permission:orders.manage');

        Route::get('/couriers', [CourierController::class, 'index'])->middleware('permission:routes.manage');
        Route::post('/couriers', [CourierController::class, 'store'])->middleware('permission:routes.manage');
        Route::patch('/couriers/{courier}', [CourierController::class, 'update'])->middleware('permission:routes.manage');

        Route::get('/routes', [DeliveryRouteController::class, 'index'])->middleware('permission:routes.manage');
        Route::post('/routes', [DeliveryRouteController::class, 'store'])->middleware('permission:routes.manage');
        Route::get('/routes/{route}', [DeliveryRouteController::class, 'show'])->middleware('permission:routes.manage');
        Route::patch('/routes/{route}', [DeliveryRouteController::class, 'update'])->middleware('permission:routes.manage');
        Route::patch('/routes/{route}/courier', [DeliveryRouteController::class, 'assignCourier'])->middleware('permission:routes.manage');
        Route::post('/routes/{route}/orders', [DeliveryRouteController::class, 'attachOrders'])->middleware('permission:routes.manage');
        Route::patch('/routes/{route}/status', [DeliveryRouteController::class, 'changeStatus'])->middleware('permission:routes.manage');

        Route::post('/imports/google-sheets', [GoogleSheetsImportController::class, 'store'])->middleware('permission:imports.create');
        Route::get('/imports/{import}', [GoogleSheetsImportController::class, 'show'])->middleware('permission:imports.create');
    });
});
