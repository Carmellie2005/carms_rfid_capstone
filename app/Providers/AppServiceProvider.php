<?php

namespace App\Providers;

use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Services\WebPushNotifier;
use App\Support\NotificationFeed;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        IncidentReport::created(function (IncidentReport $incidentReport): void {
            app(WebPushNotifier::class)->sendSupervisorIncidentAlert($incidentReport);
        });

        IncidentReport::updated(function (IncidentReport $incidentReport): void {
            $previousStatus = $incidentReport->getOriginal('status');

            if (
                $incidentReport->wasChanged('status')
                && ! in_array($previousStatus, NotificationFeed::SUPERVISOR_INCIDENT_STATUSES, true)
            ) {
                app(WebPushNotifier::class)->sendSupervisorIncidentAlert($incidentReport);
            }
        });

        PatrolLog::created(function (PatrolLog $patrolLog): void {
            app(WebPushNotifier::class)->sendSupervisorPatrolAlert($patrolLog);
        });

        PatrolLog::updated(function (PatrolLog $patrolLog): void {
            $previousStatus = $patrolLog->getOriginal('status');

            if (
                $patrolLog->wasChanged('status')
                && ! in_array($previousStatus, NotificationFeed::SUPERVISOR_PATROL_STATUSES, true)
            ) {
                app(WebPushNotifier::class)->sendSupervisorPatrolAlert($patrolLog);
            }
        });
    }
}
