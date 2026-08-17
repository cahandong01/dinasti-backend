<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->registerRateLimiters();
        
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'entity' => \App\Modules\Entity\Models\Entity::class,
            'relationship' => \App\Modules\Relationship\Models\Relationship::class,
        ]);
    }

    /**
     * Semua rate limiter didaftarkan di sini (satu sumber kebenaran).
     * Angka batasnya diambil dari config/rate_limits.php — jangan
     * hardcode angka di sini.
     */
    protected function registerRateLimiters(): void
    {
        // Login & reset password — paling ketat, per IP
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(config('rate_limits.auth.per_minute'))
                ->by($request->ip());
        });

        // Graph traversal (Explore Network, Find Connection)
        RateLimiter::for('graph', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(config('rate_limits.graph.authenticated_per_minute'))
                    ->by($request->user()->id)
                : Limit::perMinute(config('rate_limits.graph.guest_per_minute'))
                    ->by($request->ip());
        });

        // Entity search (fuzzy pg_trgm)
        RateLimiter::for('dispute', function (Request $request) {
            return Limit::perMinute(config('rate_limits.dispute.per_minute'))
                ->by($request->ip());
        });

        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(config('rate_limits.search.per_minute'))
                ->by($request->user()?->id ?: $request->ip());
        });

        // Endpoint umum lain (CRUD, dst)
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(config('rate_limits.api.authenticated_per_minute'))
                    ->by($request->user()->id)
                : Limit::perMinute(config('rate_limits.api.guest_per_minute'))
                    ->by($request->ip());
        });
    }
}