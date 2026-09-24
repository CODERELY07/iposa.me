<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        $this->defineCashierGates();
    }

    /**
     * Owners can always do these; cashiers only when the owner switched them on (Team screen).
     */
    private function defineCashierGates(): void
    {
        $gates = [
            'run-audit' => 'run_audit',
            'view-costs' => 'view_costs',
            'void-orders' => 'void_orders',
            'log-expenses' => 'log_expenses',
            'restock-stock' => 'restock_stock',
        ];

        foreach ($gates as $ability => $permission) {
            Gate::define($ability, fn (User $user): bool => $user->isAdmin()
                || ($user->isStaff() && (bool) $user->business?->cashierCan($permission)));
        }

        // Links are a plan feature: a cashier switch left on from a bigger plan does nothing.
        Gate::define('link-pieces', fn (User $user): bool => ($user->isAdmin() || ($user->isStaff() && (bool) $user->business?->cashierCan('link_pieces')))
            && (bool) $user->business?->hasFeature('recipes'));

        Gate::define('correct-audit', fn (User $user): bool => $user->isAdmin());
    }
}
