<?php

namespace App\Providers;

use App\Enums\GenderEnums;
use App\Enums\OccupationEnums;
use App\Enums\RoleEnums;
use App\Enums\StatesEnums;
use App\Enums\StatusEnums;
use App\Helpers\EnumHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

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
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('store_settings')) {
                $settings = \App\Models\StoreSetting::pluck('value', 'key')->toArray();
                config([
                    'services.google.client_id'     => $settings['google_client_id'] ?? env('GOOGLE_CLIENT_ID'),
                    'services.google.client_secret' => $settings['google_client_secret'] ?? env('GOOGLE_CLIENT_SECRET'),
                    'services.facebook.client_id'     => $settings['facebook_client_id'] ?? env('FACEBOOK_CLIENT_ID'),
                    'services.facebook.client_secret' => $settings['facebook_client_secret'] ?? env('FACEBOOK_CLIENT_SECRET'),
                ]);
            }
        } catch (\Exception $e) {
            // Ignore if DB not ready
        }

        Model::preventLazyLoading(! $this->app->isProduction());
        //
        Model::unguard();

        Paginator::useTailwind();
         // Set the timezone for Carbon (and all date/time functions)
        config(['app.timezone' => 'Africa/Lagos']);
        date_default_timezone_set(config('app.timezone'));
        Carbon::setLocale(config('app.locale'));


        Inertia::share([
            'enums' => fn () => EnumHelper::options([
                'roles' => RoleEnums::class,
                'genders' => GenderEnums::class,
                'states' => StatesEnums::class,
                'occupations' => OccupationEnums::class,
                'statuses'  => StatusEnums::class,
                // Add more enums here as needed
            ])
        ]);

    }
}
