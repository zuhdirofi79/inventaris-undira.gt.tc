<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BorrowingApprovalHistoryController;
use App\Http\Controllers\BorrowingApprovalPublicController;
use App\Http\Controllers\BorrowingController;
use App\Http\Controllers\BuildingController;
use App\Http\Controllers\BuildingManagementController;
use App\Http\Controllers\CampusController;
use App\Http\Controllers\CampusManagementController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DamagedInventoryController;
use App\Http\Controllers\DashboardCampusSummaryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeletionApprovalController;
use App\Http\Controllers\FloorController;
use App\Http\Controllers\FloorManagementController;
use App\Http\Controllers\InventoryAttachmentController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryExportController;
use App\Http\Controllers\InventoryImportController;
use App\Http\Controllers\InventoryMasterConfigurationController;
use App\Http\Controllers\InventoryPhotoController;
use App\Http\Controllers\InventoryQrController;
use App\Http\Controllers\InventorySelectorController;
use App\Http\Controllers\LocationExploreController;
use App\Http\Controllers\MaintenanceApprovalController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MaintenanceDashboardController;
use App\Http\Controllers\MaintenanceReportController;
use App\Http\Controllers\MaintenanceScheduleController;
use App\Http\Controllers\MutationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrgUnitController;
use App\Http\Controllers\ProcurementTypeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomManagementController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\SubCategoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupportingMasterController;
use App\Http\Controllers\TeknisiDashboardController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/scan/{inventoryNumber}', [InventoryQrController::class, 'scan'])
    ->middleware('throttle:60,1')
    ->name('inventory.scan');

Route::get('/borrowing/approval/{approvalNumber}', [BorrowingApprovalPublicController::class, 'show'])
    ->where('approvalNumber', 'APR-\d{8}-\d{6}')
    ->name('borrowing.approval.public');
Route::get('/borrowing/approval/{approvalNumber}/receipt', [BorrowingApprovalPublicController::class, 'receipt'])
    ->where('approvalNumber', 'APR-\d{8}-\d{6}')
    ->name('borrowing.approval.receipt');
Route::get('/borrowing/approval/{approvalNumber}/receipt/pdf', [BorrowingApprovalPublicController::class, 'receiptPdf'])
    ->where('approvalNumber', 'APR-\d{8}-\d{6}')
    ->name('borrowing.approval.receipt.pdf');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:5,1');

    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verify', [EmailVerificationController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('verification.verify');
    Route::post('/email/verify/resend', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:3,1')
        ->name('verification.resend');
});

// Password reset via OTP (must NOT use guest middleware: active session would
// RedirectIfAuthenticated → dashboard).
Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])
    ->middleware('throttle:5,1')
    ->name('password.email');
Route::get('/forgot-password/otp', [ForgotPasswordController::class, 'showOtpForm'])->name('password.otp');
Route::post('/forgot-password/otp', [ForgotPasswordController::class, 'verifyOtp'])
    ->middleware('throttle:10,1')
    ->name('password.otp.verify');
Route::post('/forgot-password/otp/resend', [ForgotPasswordController::class, 'resendOtp'])
    ->middleware('throttle:3,1')
    ->name('password.otp.resend');
Route::get('/reset-password', [ForgotPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [ForgotPasswordController::class, 'reset'])
    ->middleware('throttle:5,1')
    ->name('password.update');

Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/password/force-change', [ForcePasswordChangeController::class, 'edit'])->name('password.force.edit');
    Route::put('/password/force-change', [ForcePasswordChangeController::class, 'update'])
        ->middleware('throttle:10,1')
        ->name('password.force.update');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/tree', [DashboardController::class, 'tree'])->name('dashboard.tree');
    Route::get('/dashboard/tree/campuses/{campus}/buildings', [DashboardController::class, 'treeBuildings'])->name('dashboard.tree.buildings');
    Route::get('/dashboard/tree/buildings/{building}/floors', [DashboardController::class, 'treeFloors'])->name('dashboard.tree.floors');
    Route::get('/dashboard/tree/floors/{floor}/rooms', [DashboardController::class, 'treeRooms'])->name('dashboard.tree.rooms');
    Route::get('/dashboard/tree/rooms/{room}/categories', [DashboardController::class, 'treeCategories'])->name('dashboard.tree.categories');
    Route::get('/dashboard/tree/rooms/{room}/categories/{category}/items', [DashboardController::class, 'treeItems'])->name('dashboard.tree.items');
    Route::get('/dashboard/campus-summary/partial', [DashboardCampusSummaryController::class, 'partial'])
        ->name('dashboard.campus-summary.partial');
    Route::post('/dashboard/campus-summary/refresh', [DashboardCampusSummaryController::class, 'refresh'])
        ->middleware('throttle:20,1')
        ->name('dashboard.campus-summary.refresh');

    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('/scanner', [ScannerController::class, 'index'])->name('scanner.index');
        Route::post('/scanner/lookup', [ScannerController::class, 'lookup'])
            ->middleware('throttle:30,1')
            ->name('scanner.lookup');
    });

    Route::middleware('permission:location.view')->group(function () {
        Route::get('/campuses', [CampusController::class, 'index'])->name('campuses.index');
        Route::get('/campuses/{campus}', [CampusController::class, 'show'])->name('campuses.show');
        Route::get('/buildings/{building}', [BuildingController::class, 'show'])->name('buildings.show');
        Route::get('/floors/{floor}', [FloorController::class, 'show'])->name('floors.show');
        Route::get('/rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');

        Route::prefix('locations/explore')->name('locations.explore.')->group(function () {
            Route::get('/stats', [LocationExploreController::class, 'stats'])->name('stats');
            Route::get('/campuses', [LocationExploreController::class, 'campuses'])->name('campuses');
            Route::get('/campuses/{campus}/buildings', [LocationExploreController::class, 'buildings'])->name('buildings');
            Route::get('/buildings/{building}/floors', [LocationExploreController::class, 'floors'])->name('floors');
            Route::get('/floors/{floor}/rooms', [LocationExploreController::class, 'rooms'])->name('rooms');
            Route::get('/rooms/{room}/categories', [LocationExploreController::class, 'categories'])->name('categories');
            Route::get('/rooms/{room}/categories/{category}/sub-categories', [LocationExploreController::class, 'subCategories'])->name('sub-categories');
            Route::get('/rooms/{room}/categories/{category}/sub-categories/{subCategory}/items', [LocationExploreController::class, 'items'])
                ->whereNumber('subCategory')
                ->name('items');
            Route::get('/search', [LocationExploreController::class, 'search'])->name('search');
            Route::get('/filter-options', [LocationExploreController::class, 'filterOptions'])->name('filter-options');
        });
    });

    // Static inventory paths MUST be registered before /inventory/{inventory}
    Route::middleware('permission:inventory.create')->group(function () {
        Route::get('/inventory/create', [InventoryController::class, 'create'])->name('inventory.create');
        Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    });

    Route::middleware('permission:inventory.import')->prefix('inventory/import')->name('inventory.import.')->group(function () {
        Route::get('/', [InventoryImportController::class, 'index'])->name('index');
        Route::get('/template', [InventoryImportController::class, 'template'])->name('template');
        Route::post('/preview', [InventoryImportController::class, 'preview'])->name('preview');
        Route::post('/process', [InventoryImportController::class, 'process'])->name('process');
    });

    Route::middleware('permission:inventory.export')->prefix('inventory/export')->name('inventory.export.')->group(function () {
        Route::get('/excel', [InventoryExportController::class, 'excel'])->name('excel');
        Route::get('/csv', [InventoryExportController::class, 'csv'])->name('csv');
        Route::get('/pdf', [InventoryExportController::class, 'pdf'])->name('pdf');
    });

    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/lookup', [InventoryController::class, 'lookup'])->name('inventory.lookup');
        Route::get('/inventory/lookup-rooms', [InventoryController::class, 'lookupRooms'])->name('inventory.lookup-rooms');

        Route::prefix('inventory/selector')->name('inventory.selector.')->group(function () {
            Route::get('/campuses', [InventorySelectorController::class, 'campuses'])->name('campuses');
            Route::get('/buildings', [InventorySelectorController::class, 'buildings'])->name('buildings');
            Route::get('/floors', [InventorySelectorController::class, 'floors'])->name('floors');
            Route::get('/rooms', [InventorySelectorController::class, 'rooms'])->name('rooms');
            Route::get('/categories', [InventorySelectorController::class, 'categories'])->name('categories');
            Route::get('/sub-categories', [InventorySelectorController::class, 'subCategories'])->name('sub-categories');
            Route::get('/items', [InventorySelectorController::class, 'items'])->name('items');
            Route::get('/search', [InventorySelectorController::class, 'search'])->name('search');
            Route::get('/items/{item}/maintenance-context', [InventorySelectorController::class, 'maintenanceContext'])
                ->name('maintenance-context');
            Route::get('/items/{item}/borrow-siblings', [InventorySelectorController::class, 'borrowSiblings'])
                ->name('borrow-siblings');
            Route::get('/items/{item}', [InventorySelectorController::class, 'show'])->name('show');
            Route::post('/resolve-code', [InventorySelectorController::class, 'resolveCode'])
                ->middleware('throttle:60,1')
                ->name('resolve-code');
        });

        Route::get('/inventory/items', [InventoryController::class, 'items'])->name('inventory.items');
        Route::get('/inventory/trash', [InventoryController::class, 'trash'])->name('inventory.trash');
        Route::get('/inventory/group/{groupKey}', [InventoryController::class, 'groupUnits'])->name('inventory.group-units');
        Route::get('/inventory/by-category', [InventoryController::class, 'categories'])->name('inventory.categories');
        Route::get('/inventory/categories/{category}', [InventoryController::class, 'byCategory'])->name('inventory.category');
        Route::get('/inventory/campuses/{campus}', [InventoryController::class, 'byCampus'])->name('inventory.campus');
        Route::get('/inventory/campuses/{campus}/buildings/{building}/floors', [InventoryController::class, 'campusFloors'])->name('inventory.campus.floors');
        Route::get('/inventory/campuses/{campus}/floors/{floor}/rooms', [InventoryController::class, 'campusRooms'])->name('inventory.campus.rooms');
        Route::get('/inventory/campuses/{campus}/rooms/{room}/categories', [InventoryController::class, 'campusRoomCategories'])->name('inventory.campus.categories');
        Route::get('/inventory/campuses/{campus}/rooms/{room}/categories/{category}/sub-categories', [InventoryController::class, 'campusRoomSubCategories'])->name('inventory.campus.sub-categories');
        Route::get('/inventory/campuses/{campus}/rooms/{room}/categories/{category}/sub-categories/{subCategory}/items', [InventoryController::class, 'campusSubCategoryItems'])
            ->whereNumber('subCategory')
            ->name('inventory.campus.sub-category-items');
        Route::post('/inventory/labels', [InventoryQrController::class, 'labels'])->name('inventory.labels');
        Route::post('/inventory/labels/mass-30', [InventoryQrController::class, 'massLabels30'])->name('inventory.labels.mass30');
        Route::get('/inventory/{inventory}/qr', [InventoryQrController::class, 'inlineQr'])->name('inventory.qr');
        Route::get('/inventory/{inventory}/qr/download', [InventoryQrController::class, 'downloadQr'])->name('inventory.qr.download');
        Route::get('/inventory/{inventory}/label/print', [InventoryQrController::class, 'print'])->name('inventory.label.print');
        Route::get('/inventory/{inventory}', [InventoryController::class, 'show'])->name('inventory.show');
    });

    Route::middleware('permission:inventory.update')->group(function () {
        Route::post('/inventory/bulk-relocate', [InventoryController::class, 'bulkRelocate'])->name('inventory.bulk-relocate');
        Route::post('/inventory/{inventory}/codes/regenerate', [InventoryQrController::class, 'regenerate'])->name('inventory.codes.regenerate');
        Route::get('/inventory/{inventory}/edit', [InventoryController::class, 'edit'])->name('inventory.edit');
        Route::put('/inventory/{inventory}', [InventoryController::class, 'update'])->name('inventory.update');
        Route::post('/inventory/{inventory}/photos', [InventoryPhotoController::class, 'store'])->name('inventory.photos.store');
        Route::post('/inventory/{inventory}/photos/{photo}/primary', [InventoryPhotoController::class, 'setPrimary'])->name('inventory.photos.primary');
        Route::delete('/inventory/{inventory}/photos/{photo}', [InventoryPhotoController::class, 'destroy'])->name('inventory.photos.destroy');
        Route::post('/inventory/{inventory}/attachments', [InventoryAttachmentController::class, 'store'])->name('inventory.attachments.store');
        Route::delete('/inventory/{inventory}/attachments/{attachment}', [InventoryAttachmentController::class, 'destroy'])->name('inventory.attachments.destroy');
    });

    Route::middleware('permission:inventory.restore')->group(function () {
        Route::post('/inventory/{id}/restore', [InventoryController::class, 'restore'])->name('inventory.restore');
    });

    Route::middleware('permission:inventory.delete')->group(function () {
        Route::delete('/inventory/{inventory}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
    });

    Route::get('/categories', [CategoryController::class, 'index'])
        ->middleware('permission:category.manage|unit.manage|procurement.manage|supplier.manage|vendor.manage|location.manage|inventory.create')
        ->name('categories.index');

    Route::middleware('permission:category.manage|inventory.create')->group(function () {
        Route::post('/master-configurations', [InventoryMasterConfigurationController::class, 'store'])->name('master-configurations.store');
        Route::get('/master-configurations/preview', [InventoryMasterConfigurationController::class, 'preview'])->name('master-configurations.preview');
    });

    Route::middleware('permission:category.manage')->group(function () {
        Route::get('/categories/export', [CategoryController::class, 'export'])->name('categories.export');
        Route::get('/categories/import/template', [CategoryController::class, 'importTemplate'])->name('categories.import.template');
        Route::post('/categories/import', [CategoryController::class, 'import'])->name('categories.import');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
        Route::get('/categories/{category}/sub-categories', [SubCategoryController::class, 'index'])->name('sub-categories.index');
        Route::post('/sub-categories', [SubCategoryController::class, 'store'])->name('sub-categories.store');
        Route::put('/sub-categories/{subCategory}', [SubCategoryController::class, 'update'])->name('sub-categories.update');
        Route::delete('/sub-categories/{subCategory}', [SubCategoryController::class, 'destroy'])->name('sub-categories.destroy');

        Route::put('/master-configurations/{configuration}', [InventoryMasterConfigurationController::class, 'update'])->name('master-configurations.update');
        Route::delete('/master-configurations/{configuration}', [InventoryMasterConfigurationController::class, 'destroy'])->name('master-configurations.destroy');
        Route::post('/master-data/options', [InventoryMasterConfigurationController::class, 'storeOption'])->name('master-data.options.store');

        // Supporting masters (Lokasi & Lainnya hub) — reuse existing tables
        Route::post('/purchase-years', [SupportingMasterController::class, 'storePurchaseYear'])->name('purchase-years.store');
        Route::put('/purchase-years/{purchaseYear}', [SupportingMasterController::class, 'updatePurchaseYear'])->name('purchase-years.update');
        Route::delete('/purchase-years/{purchaseYear}', [SupportingMasterController::class, 'destroyPurchaseYear'])->name('purchase-years.destroy');
        Route::post('/room-functions', [SupportingMasterController::class, 'storeRoomFunction'])->name('room-functions.store');
        Route::put('/room-functions/{roomFunction}', [SupportingMasterController::class, 'updateRoomFunction'])->name('room-functions.update');
        Route::delete('/room-functions/{roomFunction}', [SupportingMasterController::class, 'destroyRoomFunction'])->name('room-functions.destroy');
        Route::post('/inventory-conditions', [SupportingMasterController::class, 'storeCondition'])->name('inventory-conditions.store');
        Route::put('/inventory-conditions/{inventoryCondition}', [SupportingMasterController::class, 'updateCondition'])->name('inventory-conditions.update');
        Route::delete('/inventory-conditions/{inventoryCondition}', [SupportingMasterController::class, 'destroyCondition'])->name('inventory-conditions.destroy');
    });

    Route::middleware('permission:category.manage|location.manage|inventory.view|inventory.create|inventory.update|report.view')->group(function () {
        Route::get('/master-data/campuses/{campus}/buildings', [InventoryMasterConfigurationController::class, 'buildingsByCampus'])->name('master-data.buildings.by-campus');
        Route::get('/master-data/buildings/{building}/floors', [InventoryMasterConfigurationController::class, 'floorsByBuilding'])->name('master-data.floors.by-building');
        Route::get('/master-data/floors/{floor}/rooms', [InventoryMasterConfigurationController::class, 'roomsByFloor'])->name('master-data.rooms.by-floor');
        Route::get('/master-data/categories/{category}/subclassifications', [InventoryMasterConfigurationController::class, 'subclassificationsByClassification'])->name('master-data.subclassifications.by-classification');
    });

    Route::middleware('permission:unit.manage')->group(function () {
        Route::post('/org-units', [OrgUnitController::class, 'store'])->name('org-units.store');
        Route::put('/org-units/{orgUnit}', [OrgUnitController::class, 'update'])->name('org-units.update');
        Route::delete('/org-units/{orgUnit}', [OrgUnitController::class, 'destroy'])->name('org-units.destroy');
    });

    Route::middleware('permission:procurement.manage')->group(function () {
        Route::post('/procurement-types', [ProcurementTypeController::class, 'store'])->name('procurement-types.store');
        Route::put('/procurement-types/{procurementType}', [ProcurementTypeController::class, 'update'])->name('procurement-types.update');
        Route::delete('/procurement-types/{procurementType}', [ProcurementTypeController::class, 'destroy'])->name('procurement-types.destroy');
    });

    Route::middleware('permission:supplier.manage')->group(function () {
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/suppliers/export', [SupplierController::class, 'export'])->name('suppliers.export');
        Route::get('/suppliers/import/template', [SupplierController::class, 'importTemplate'])->name('suppliers.import.template');
        Route::post('/suppliers/import', [SupplierController::class, 'import'])->name('suppliers.import');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });

    Route::middleware('permission:vendor.manage')->group(function () {
        Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
        Route::get('/vendors/export', [VendorController::class, 'export'])->name('vendors.export');
        Route::get('/vendors/import/template', [VendorController::class, 'importTemplate'])->name('vendors.import.template');
        Route::post('/vendors/import', [VendorController::class, 'import'])->name('vendors.import');
        Route::post('/vendors', [VendorController::class, 'store'])->name('vendors.store');
        Route::put('/vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');
        Route::delete('/vendors/{vendor}', [VendorController::class, 'destroy'])->name('vendors.destroy');
    });

    Route::middleware('permission:location.manage')->prefix('admin/locations')->name('admin.locations.')->group(function () {
        Route::get('/campuses', [CampusManagementController::class, 'index'])->name('campuses.index');
        Route::post('/campuses', [CampusManagementController::class, 'store'])->name('campuses.store');
        Route::put('/campuses/{campus}', [CampusManagementController::class, 'update'])->name('campuses.update');
        Route::delete('/campuses/{campus}', [CampusManagementController::class, 'destroy'])->name('campuses.destroy');
        Route::get('/campuses/{campus}/buildings', [BuildingManagementController::class, 'index'])->name('buildings.index');
        Route::post('/buildings', [BuildingManagementController::class, 'store'])->name('buildings.store');
        Route::put('/buildings/{building}', [BuildingManagementController::class, 'update'])->name('buildings.update');
        Route::delete('/buildings/{building}', [BuildingManagementController::class, 'destroy'])->name('buildings.destroy');
        Route::get('/buildings/{building}/floors', [FloorManagementController::class, 'index'])->name('floors.index');
        Route::post('/floors', [FloorManagementController::class, 'store'])->name('floors.store');
        Route::put('/floors/{floor}', [FloorManagementController::class, 'update'])->name('floors.update');
        Route::delete('/floors/{floor}', [FloorManagementController::class, 'destroy'])->name('floors.destroy');
        Route::get('/floors/{floor}/rooms', [RoomManagementController::class, 'index'])->name('rooms.index');
        Route::post('/rooms', [RoomManagementController::class, 'store'])->name('rooms.store');
        Route::put('/rooms/{room}', [RoomManagementController::class, 'update'])->name('rooms.update');
        Route::post('/rooms/{room}/duplicate', [RoomManagementController::class, 'duplicate'])->name('rooms.duplicate');
        Route::delete('/rooms/{room}', [RoomManagementController::class, 'destroy'])->name('rooms.destroy');
    });

    Route::middleware('permission:borrowing.create')->group(function () {
        Route::get('/borrowings/create', [BorrowingController::class, 'create'])->name('borrowings.create');
        Route::post('/borrowings', [BorrowingController::class, 'store'])->name('borrowings.store');
    });
    Route::middleware('permission:borrowing.view')->group(function () {
        Route::get('/borrowings', [BorrowingController::class, 'index'])->name('borrowings.index');
        Route::get('/borrowings/{borrowing}', [BorrowingController::class, 'show'])->name('borrowings.show');
        Route::get('/borrowings/{borrowing}/receipt', [BorrowingController::class, 'receipt'])->name('borrowings.receipt');
        Route::get('/borrowings/{borrowing}/receipt/pdf', [BorrowingController::class, 'receiptPdf'])->name('borrowings.receipt.pdf');
    });
    Route::middleware('permission:borrowing.update')->group(function () {
        Route::get('/borrowings/{borrowing}/edit', [BorrowingController::class, 'edit'])->name('borrowings.edit');
        Route::put('/borrowings/{borrowing}', [BorrowingController::class, 'update'])->name('borrowings.update');
        Route::post('/borrowings/{borrowing}/return-items', [BorrowingController::class, 'returnItems'])->name('borrowings.return-items');
    });
    Route::middleware('permission:borrowing.approve')->group(function () {
        Route::post('/borrowings/{borrowing}/approve', [BorrowingController::class, 'approve'])->name('borrowings.approve');
        Route::post('/borrowings/{borrowing}/reject', [BorrowingController::class, 'reject'])->name('borrowings.reject');
        Route::post('/borrowings/{borrowing}/save-approvals', [BorrowingController::class, 'saveApprovals'])->name('borrowings.save-approvals');
        Route::post('/borrowings/{borrowing}/items/{borrowingItem}/approve', [BorrowingController::class, 'approveItem'])->name('borrowings.items.approve');
        Route::post('/borrowings/{borrowing}/items/{borrowingItem}/reject', [BorrowingController::class, 'rejectItem'])->name('borrowings.items.reject');
        Route::post('/borrowings/{borrowing}/items/{borrowingItem}/handover', [BorrowingController::class, 'handoverItem'])->name('borrowings.items.handover');
        Route::get('/borrowing-approvals', [BorrowingApprovalHistoryController::class, 'index'])->name('borrowing-approvals.index');
        Route::get('/borrowing-approvals/export/pdf', [BorrowingApprovalHistoryController::class, 'exportPdf'])->name('borrowing-approvals.export.pdf');
        Route::get('/borrowing-approvals/export/excel', [BorrowingApprovalHistoryController::class, 'exportExcel'])->name('borrowing-approvals.export.excel');
    });
    Route::middleware('permission:borrowing.delete')->group(function () {
        Route::delete('/borrowings/{borrowing}', [BorrowingController::class, 'destroy'])->name('borrowings.destroy');
    });

    Route::middleware('permission:maintenance.create')->group(function () {
        Route::get('/maintenances/scan', [MaintenanceController::class, 'scan'])->name('maintenances.scan');
        Route::post('/maintenances/scan/lookup', [MaintenanceController::class, 'scanLookup'])
            ->middleware('throttle:60,1')
            ->name('maintenances.scan.lookup');
        Route::get('/maintenances/start-repair', [MaintenanceController::class, 'startRepairForm'])->name('maintenances.start-repair');
        Route::post('/maintenances/start-repair', [MaintenanceController::class, 'startRepair'])->name('maintenances.start-repair.store');
        Route::get('/maintenances/create', [MaintenanceController::class, 'create'])->name('maintenances.create');
        Route::post('/maintenances', [MaintenanceController::class, 'store'])->name('maintenances.store');
    });
    Route::middleware('permission:maintenance.view')->group(function () {
        Route::get('/maintenances', [MaintenanceController::class, 'index'])->name('maintenances.index');
        Route::get('/maintenances/{maintenance}', [MaintenanceController::class, 'show'])->name('maintenances.show');
        Route::get('/maintenance-dashboard', [MaintenanceDashboardController::class, 'index'])->name('maintenance-dashboard.index');
        Route::get('/teknisi-dashboard', [TeknisiDashboardController::class, 'index'])->name('teknisi-dashboard.index');
        Route::get('/maintenance-reports', [MaintenanceReportController::class, 'index'])->name('maintenance-reports.index');
        Route::post('/maintenance-reports/generate', [MaintenanceReportController::class, 'generate'])->name('maintenance-reports.generate');
    });
    Route::middleware('permission:maintenance.update')->group(function () {
        Route::get('/maintenances/{maintenance}/edit', [MaintenanceController::class, 'edit'])->name('maintenances.edit');
        Route::put('/maintenances/{maintenance}', [MaintenanceController::class, 'update'])->name('maintenances.update');
        Route::get('/maintenances/{maintenance}/complete', [MaintenanceController::class, 'completeRepairForm'])->name('maintenances.complete');
        Route::post('/maintenances/{maintenance}/complete', [MaintenanceController::class, 'completeRepair'])->name('maintenances.complete.store');
        Route::post('/maintenances/{maintenance}/photos', [MaintenanceController::class, 'storePhotos'])->name('maintenances.photos.store');
        Route::delete('/maintenances/{maintenance}/photos/{photo}', [MaintenanceController::class, 'destroyPhoto'])->name('maintenances.photos.destroy');
    });
    Route::middleware('permission:maintenance.approve')->group(function () {
        Route::get('/maintenance-approvals', [MaintenanceApprovalController::class, 'index'])->name('maintenance-approvals.index');
        Route::post('/maintenance-approvals/{maintenance}/approve', [MaintenanceApprovalController::class, 'approve'])->name('maintenance-approvals.approve');
        Route::post('/maintenance-approvals/{maintenance}/reject', [MaintenanceApprovalController::class, 'reject'])->name('maintenance-approvals.reject');
    });
    Route::middleware('permission:maintenance.delete')->group(function () {
        Route::delete('/maintenances/{maintenance}', [MaintenanceController::class, 'destroy'])->name('maintenances.destroy');
    });

    Route::middleware('permission:maintenance.create')->group(function () {
        Route::get('/maintenance-schedules/create', [MaintenanceScheduleController::class, 'create'])->name('maintenance-schedules.create');
        Route::post('/maintenance-schedules', [MaintenanceScheduleController::class, 'store'])->name('maintenance-schedules.store');
        Route::get('/maintenance-schedules/preview', [MaintenanceScheduleController::class, 'preview'])->name('maintenance-schedules.preview');
        Route::get('/maintenance-schedules/suggest-next', [MaintenanceScheduleController::class, 'suggestNext'])->name('maintenance-schedules.suggest-next');
    });
    Route::middleware('permission:maintenance.view')->group(function () {
        Route::get('/maintenance-schedules', [MaintenanceScheduleController::class, 'index'])->name('maintenance-schedules.index');
        Route::get('/maintenance-schedules/{maintenance_schedule}', [MaintenanceScheduleController::class, 'show'])->name('maintenance-schedules.show');
    });
    Route::middleware('permission:maintenance.update')->group(function () {
        Route::get('/maintenance-schedules/{maintenance_schedule}/edit', [MaintenanceScheduleController::class, 'edit'])->name('maintenance-schedules.edit');
        Route::put('/maintenance-schedules/{maintenance_schedule}', [MaintenanceScheduleController::class, 'update'])->name('maintenance-schedules.update');
        Route::post('/maintenance-schedules/{maintenance_schedule}/advance', [MaintenanceScheduleController::class, 'advance'])->name('maintenance-schedules.advance');
    });
    Route::middleware('permission:maintenance.delete')->group(function () {
        Route::delete('/maintenance-schedules/{maintenance_schedule}', [MaintenanceScheduleController::class, 'destroy'])->name('maintenance-schedules.destroy');
    });

    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('/inventory-damaged', [DamagedInventoryController::class, 'index'])->name('inventory.damaged.index');
    });
    Route::delete('/inventory-damaged/{inventory}', [DamagedInventoryController::class, 'destroy'])
        ->name('inventory.damaged.destroy');

    Route::middleware('permission:stock-opname.create')->group(function () {
        Route::get('/stock-opnames/create', [StockOpnameController::class, 'create'])->name('stock-opnames.create');
        Route::post('/stock-opnames', [StockOpnameController::class, 'store'])->name('stock-opnames.store');
    });
    Route::middleware('permission:stock-opname.view')->group(function () {
        Route::get('/stock-opnames', [StockOpnameController::class, 'index'])->name('stock-opnames.index');
        Route::get('/stock-opnames/{stockOpname}', [StockOpnameController::class, 'show'])->name('stock-opnames.show');
    });
    Route::middleware('permission:stock-opname.update')->group(function () {
        Route::post('/stock-opnames/{stockOpname}/start', [StockOpnameController::class, 'start'])->name('stock-opnames.start');
        Route::post('/stock-opnames/{stockOpname}/complete', [StockOpnameController::class, 'complete'])->name('stock-opnames.complete');
        Route::post('/stock-opnames/{stockOpname}/scan', [StockOpnameController::class, 'scan'])->name('stock-opnames.scan');
    });
    Route::middleware('permission:stock-opname.delete')->group(function () {
        Route::delete('/stock-opnames/{stockOpname}', [StockOpnameController::class, 'destroy'])->name('stock-opnames.destroy');
    });

    Route::middleware('permission:deletion-approval.view')->group(function () {
        Route::get('/deletion-approvals', [DeletionApprovalController::class, 'index'])->name('deletion-approvals.index');
    });
    Route::middleware('permission:deletion-approval.request')->group(function () {
        Route::post('/deletion-approvals', [DeletionApprovalController::class, 'store'])->name('deletion-approvals.store');
    });
    Route::middleware('permission:deletion-approval.approve')->group(function () {
        Route::post('/deletion-approvals/{deletionApproval}/approve', [DeletionApprovalController::class, 'approve'])->name('deletion-approvals.approve');
        Route::post('/deletion-approvals/{deletionApproval}/reject', [DeletionApprovalController::class, 'reject'])->name('deletion-approvals.reject');
    });

    Route::middleware('permission:mutation.create')->group(function () {
        Route::get('/mutations/create', [MutationController::class, 'create'])->name('mutations.create');
        Route::post('/mutations', [MutationController::class, 'store'])->name('mutations.store');
    });
    Route::middleware('permission:mutation.view')->group(function () {
        Route::get('/mutations', [MutationController::class, 'index'])->name('mutations.index');
        Route::get('/mutations/{mutation}', [MutationController::class, 'show'])->name('mutations.show');
    });

    Route::middleware('permission:report.view')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{type}', [ReportController::class, 'show'])->name('reports.show')
            ->where('type', 'lengkap|per-kampus|per-gedung|per-lantai|per-ruangan|per-kategori|per-subkategori|per-pic|dipinjam|maintenance|rusak|non-aktif|dimutasi|dihapus|rekapitulasi');
        Route::get('/reports/{type}/data', [ReportController::class, 'data'])->name('reports.data')
            ->where('type', 'lengkap|per-kampus|per-gedung|per-lantai|per-ruangan|per-kategori|per-subkategori|per-pic|dipinjam|maintenance|rusak|non-aktif|dimutasi|dihapus');
        Route::post('/reports/{type}/export', [ReportController::class, 'export'])->name('reports.export')
            ->where('type', 'lengkap|per-kampus|per-gedung|per-lantai|per-ruangan|per-kategori|per-subkategori|per-pic|dipinjam|maintenance|rusak|non-aktif|dimutasi|dihapus');
        Route::post('/reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
    });

    Route::middleware('permission:audit-log.view')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
    });

    Route::middleware('permission:trash.view')->group(function () {
        Route::get('/admin/trash', [TrashController::class, 'index'])->name('trash.index');
    });
    Route::middleware('permission:trash.restore')->group(function () {
        Route::post('/admin/trash/{type}/{id}/restore', [TrashController::class, 'restore'])->name('trash.restore');
    });
    Route::middleware('permission:trash.force-delete')->group(function () {
        Route::delete('/admin/trash/{type}/{id}', [TrashController::class, 'forceDelete'])->name('trash.force-delete');
    });

    Route::middleware('permission:user.manage')->group(function () {
        Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::post('/admin/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('admin.users.toggle-status');
        Route::post('/admin/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('admin.users.reset-password');
        Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    });

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('/admin/settings', [SettingController::class, 'index'])->name('admin.settings.index');
        Route::put('/admin/settings', [SettingController::class, 'update'])->name('admin.settings.update');
    });

    Route::middleware('permission:backup.manage')->group(function () {
        Route::post('/admin/settings/backup', [SettingController::class, 'backup'])->name('admin.settings.backup');
        Route::get('/admin/settings/backup/{file}', [SettingController::class, 'downloadBackup'])->name('admin.settings.backup.download');
        Route::post('/admin/settings/restore', [SettingController::class, 'restore'])->name('admin.settings.restore');
    });

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/unread', [NotificationController::class, 'unread'])->name('unread');
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
    });
});
