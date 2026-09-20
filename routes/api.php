<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Catalog\BranchController;
use App\Http\Controllers\Api\V1\Catalog\CampaignController;
use App\Http\Controllers\Api\V1\Catalog\CampaignUserController;
use App\Http\Controllers\Api\V1\Catalog\DistrictController;
use App\Http\Controllers\Api\V1\Catalog\DistrictListController;
use App\Http\Controllers\Api\V1\Catalog\ProductCatalogController;
use App\Http\Controllers\Api\V1\Customers\CustomerInvitationController;
use App\Http\Controllers\Api\V1\Dispatch\CourierController;
use App\Http\Controllers\Api\V1\Dispatch\DeliveryRouteController;
use App\Http\Controllers\Api\V1\Dispatch\PublicDeliveryRouteController;
use App\Http\Controllers\Api\V1\Forms\CampaignFormController;
use App\Http\Controllers\Api\V1\Forms\FormSubmissionFileController;
use App\Http\Controllers\Api\V1\Forms\FormTemplateController;
use App\Http\Controllers\Api\V1\Forms\PublicCampaignFormController;
use App\Http\Controllers\Api\V1\Imports\GoogleSheetsImportController;
use App\Http\Controllers\Api\V1\Orders\OrderController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/public/forms/{publicKey}', [PublicCampaignFormController::class, 'show'])->middleware('throttle:public-form');
    Route::post('/public/forms/{publicKey}/submissions', [PublicCampaignFormController::class, 'submit'])->middleware('throttle:public-form');
    Route::get('/public/invitations/{token}', [PublicCampaignFormController::class, 'showInvitation'])->middleware('throttle:public-form');
    Route::post('/public/invitations/{token}/submissions', [PublicCampaignFormController::class, 'submitInvitation'])->middleware('throttle:public-form');
    Route::get('/public/routes/{token}', [PublicDeliveryRouteController::class, 'show'])->middleware('throttle:public-route');
    Route::post('/public/routes/{token}/orders/{order}/delivery-confirmation', [PublicDeliveryRouteController::class, 'confirmDelivery'])->middleware('throttle:public-route');
    Route::get('/public/routes/{token}/orders/{order}/delivery-evidence', [PublicDeliveryRouteController::class, 'evidence'])->middleware('throttle:public-route');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::get('/branches', [BranchController::class, 'index'])->middleware('permission:branches.view');
        Route::post('/branches', [BranchController::class, 'store'])->middleware('permission:branches.manage');
        Route::get('/branches/{branch}', [BranchController::class, 'show'])->middleware('permission:branches.view');
        Route::patch('/branches/{branch}', [BranchController::class, 'update'])->middleware('permission:branches.manage');
        Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->middleware('permission:branches.manage');

        Route::get('/districts', [DistrictController::class, 'index'])->middleware('permission:districts.view');
        Route::get('/districts/departments', [DistrictController::class, 'departments'])->middleware('permission:districts.view');
        Route::get('/districts/provinces', [DistrictController::class, 'provinces'])->middleware('permission:districts.view');
        Route::post('/districts', [DistrictController::class, 'store'])->middleware('permission:districts.manage');
        Route::get('/districts/{district}', [DistrictController::class, 'show'])->middleware('permission:districts.view');
        Route::patch('/districts/{district}', [DistrictController::class, 'update'])->middleware('permission:districts.manage');
        Route::delete('/districts/{district}', [DistrictController::class, 'destroy'])->middleware('permission:districts.manage');

        Route::get('/district-lists', [DistrictListController::class, 'index'])->middleware('permission:district_lists.view');
        Route::post('/district-lists', [DistrictListController::class, 'store'])->middleware('permission:district_lists.manage');
        Route::get('/district-lists/{districtList}', [DistrictListController::class, 'show'])->middleware('permission:district_lists.view');
        Route::patch('/district-lists/{districtList}', [DistrictListController::class, 'update'])->middleware('permission:district_lists.manage');
        Route::delete('/district-lists/{districtList}', [DistrictListController::class, 'destroy'])->middleware('permission:district_lists.manage');

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

        Route::get('/form-templates', [FormTemplateController::class, 'index'])->middleware('permission:forms.view');
        Route::post('/form-templates', [FormTemplateController::class, 'store'])->middleware('permission:forms.manage');
        Route::get('/form-templates/{template}', [FormTemplateController::class, 'show'])->middleware('permission:forms.view');
        Route::patch('/form-templates/{template}', [FormTemplateController::class, 'update'])->middleware('permission:forms.manage');
        Route::delete('/form-templates/{template}', [FormTemplateController::class, 'destroy'])->middleware('permission:forms.manage');
        Route::get('/form-templates/{template}/fields', [FormTemplateController::class, 'fields'])->middleware('permission:forms.view');
        Route::post('/form-templates/{template}/fields', [FormTemplateController::class, 'storeField'])->middleware('permission:forms.manage');
        Route::patch('/form-templates/{template}/fields/{field}', [FormTemplateController::class, 'updateField'])->middleware('permission:forms.manage');
        Route::delete('/form-templates/{template}/fields/{field}', [FormTemplateController::class, 'destroyField'])->middleware('permission:forms.manage');
        Route::post('/customer-invitations', [CustomerInvitationController::class, 'store'])->middleware('permission:forms.manage');
        Route::get('/customers', [CustomerInvitationController::class, 'customers'])->middleware('permission:forms.view');
        Route::get('/customers/{customer}', [CustomerInvitationController::class, 'showCustomer'])->middleware('permission:forms.view');
        Route::get('/customer-invitations', [CustomerInvitationController::class, 'invitations'])->middleware('permission:forms.view');
        Route::post('/customer-invitations/{invitation}/revoke', [CustomerInvitationController::class, 'revoke'])->middleware('permission:forms.manage');
        Route::get('/campaign-forms', [CampaignFormController::class, 'index'])->middleware('permission:forms.view');
        Route::post('/campaign-forms', [CampaignFormController::class, 'store'])->middleware('permission:forms.manage');
        Route::put('/campaigns/{campaign}/configuration', [CampaignFormController::class, 'configure'])->middleware(['permission:forms.manage', 'permission:campaigns.manage']);
        Route::get('/campaign-forms/{form}', [CampaignFormController::class, 'show'])->middleware('permission:forms.view');
        Route::get('/form-submission-files/{file}', [FormSubmissionFileController::class, 'show'])->middleware('permission:orders.view');
        Route::post('/orders/{order}/files/{file}', [FormSubmissionFileController::class, 'replace'])->middleware('permission:orders.manage');
        Route::delete('/form-submission-files/{file}', [FormSubmissionFileController::class, 'destroy'])->middleware('permission:orders.manage');
        Route::patch('/campaign-forms/{form}', [CampaignFormController::class, 'update'])->middleware('permission:forms.manage');
        Route::post('/campaign-forms/{form}/publish', [CampaignFormController::class, 'publish'])->middleware('permission:forms.manage');
        Route::post('/campaign-forms/{form}/close', [CampaignFormController::class, 'close'])->middleware('permission:forms.manage');

        Route::get('/campaigns/available', [CampaignController::class, 'available'])->middleware('permission:campaigns.view');
        Route::get('/internal/forms/{publicKey}', [PublicCampaignFormController::class, 'show'])->middleware('permission:forms.view');
        Route::post('/internal/forms/{publicKey}/submissions', [PublicCampaignFormController::class, 'submitInternal'])->middleware('permission:orders.manage');
        Route::get('/campaigns/{campaign}/users', [CampaignUserController::class, 'index'])->middleware('permission:campaigns.users.view');
        Route::get('/campaigns/{campaign}/available-dispatchers', [CampaignUserController::class, 'availableDispatchers'])->middleware('permission:campaigns.users.manage');
        Route::post('/campaigns/{campaign}/users', [CampaignUserController::class, 'store'])->middleware('permission:campaigns.users.manage');
        Route::delete('/campaigns/{campaign}/users/{user}', [CampaignUserController::class, 'destroy'])->middleware('permission:campaigns.users.manage');
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
        Route::get('/orders/{order}/delivery-evidence', [OrderController::class, 'deliveryEvidence'])->middleware('permission:orders.view');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('permission:orders.view');
        Route::patch('/orders/{order}', [OrderController::class, 'update'])->middleware('permission:orders.manage');
        Route::patch('/orders/{order}/status', [OrderController::class, 'changeStatus'])->middleware('permission:orders.manage');
        Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->middleware('permission:orders.manage');

        Route::get('/couriers', [CourierController::class, 'index'])->middleware('permission:routes.manage');
        Route::post('/couriers', [CourierController::class, 'store'])->middleware('permission:routes.manage');
        Route::patch('/couriers/{courier}', [CourierController::class, 'update'])->middleware('permission:routes.manage');

        Route::get('/routes', [DeliveryRouteController::class, 'index'])->middleware('permission:routes.manage');
        Route::get('/routes/eligible-orders', [DeliveryRouteController::class, 'eligibleOrders'])->middleware('permission:routes.manage');
        Route::get('/routes/map-orders', [DeliveryRouteController::class, 'mapOrders'])->middleware('permission:routes.manage');
        Route::post('/routes/generate', [DeliveryRouteController::class, 'generate'])->middleware('permission:routes.manage');
        Route::post('/routes', [DeliveryRouteController::class, 'store'])->middleware('permission:routes.manage');
        Route::put('/routes/{route}/stops', [DeliveryRouteController::class, 'updateStops'])->middleware('permission:routes.manage');
        Route::get('/routes/{route}', [DeliveryRouteController::class, 'show'])->middleware('permission:routes.manage');
        Route::patch('/routes/{route}', [DeliveryRouteController::class, 'update'])->middleware('permission:routes.manage');
        Route::patch('/routes/{route}/courier', [DeliveryRouteController::class, 'assignCourier'])->middleware('permission:routes.manage');
        Route::post('/routes/{route}/orders', [DeliveryRouteController::class, 'attachOrders'])->middleware('permission:routes.manage');
        Route::patch('/routes/{route}/status', [DeliveryRouteController::class, 'changeStatus'])->middleware('permission:routes.manage');
        Route::post('/routes/{route}/access-token', [DeliveryRouteController::class, 'createAccessToken'])->middleware('permission:routes.manage');
        Route::post('/routes/{route}/courier-invitations', [DeliveryRouteController::class, 'createCourierInvitation'])->middleware('permission:routes.manage');
        Route::delete('/routes/{route}/access-token', [DeliveryRouteController::class, 'revokeAccessToken'])->middleware('permission:routes.manage');

        Route::post('/imports/google-sheets', [GoogleSheetsImportController::class, 'store'])->middleware('permission:imports.create');
        Route::get('/imports/{import}', [GoogleSheetsImportController::class, 'show'])->middleware('permission:imports.create');
    });
});
