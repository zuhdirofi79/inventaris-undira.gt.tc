<?php

namespace App\Providers;

use App\Models\InventoryItem;
use App\Models\User;
use App\Observers\InventoryItemObserver;
use App\Policies\UserPolicy;
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
        Gate::policy(User::class, UserPolicy::class);
    }
}
