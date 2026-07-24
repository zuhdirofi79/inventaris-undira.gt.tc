<?php

namespace App\Providers;

use App\Models\Borrowing;
use App\Models\Campus;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryMutation;
use App\Models\Maintenance;
use App\Models\MaintenanceSchedule;
use App\Models\StockOpname;
use App\Models\User;
use App\Observers\InventoryItemObserver;
use App\Policies\BorrowingPolicy;
use App\Policies\CampusPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\InventoryItemPolicy;
use App\Policies\InventoryMutationPolicy;
use App\Policies\MaintenancePolicy;
use App\Policies\MaintenanceSchedulePolicy;
use App\Policies\StockOpnamePolicy;
use App\Policies\UserPolicy;
use App\View\Components\PageHeader;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        InventoryItem::observe(InventoryItemObserver::class);

        Blade::component('page-header', PageHeader::class);
        Blade::component('ui.page-header', PageHeader::class);

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(InventoryItem::class, InventoryItemPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Campus::class, CampusPolicy::class);
        Gate::policy(Borrowing::class, BorrowingPolicy::class);
        Gate::policy(Maintenance::class, MaintenancePolicy::class);
        Gate::policy(MaintenanceSchedule::class, MaintenanceSchedulePolicy::class);
        Gate::policy(StockOpname::class, StockOpnamePolicy::class);
        Gate::policy(InventoryMutation::class, InventoryMutationPolicy::class);
    }
}
