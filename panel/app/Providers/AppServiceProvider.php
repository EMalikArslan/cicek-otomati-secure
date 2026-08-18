<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureGates();
        $this->configureUrls();
    }

    private function configureModels(): void
    {
        // Uretimde kapali: yalnizca gelistirmede N+1 ve eksik alan hatalarini
        // erken yakalamak icin acilir.
        Model::shouldBeStrict(! app()->isProduction());

        // migrate:fresh / db:wipe gibi yikici komutlar uretimde calistirilamaz.
        DB::prohibitDestructiveCommands(app()->isProduction());
    }

    private function configureGates(): void
    {
        /**
         * Super admin her seyi gorur ve yapar (isterdeki "her datayi ve bilgiyi
         * super admin gorebilmeli"). `null` donmek diger politikalarin
         * calismasina izin verir; `true` donmek kontrolu kisa devre yapar.
         */
        Gate::before(function ($user, string $ability) {
            return $user->isSuperAdmin() ? true : null;
        });
    }

    private function configureUrls(): void
    {
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
