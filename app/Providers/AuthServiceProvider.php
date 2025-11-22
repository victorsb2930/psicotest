<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Appointment;
use App\Models\AppointmentReschedule;
use App\Policies\AppointmentPolicy;
use App\Policies\AppointmentReschedulePolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Appointment::class => AppointmentPolicy::class,
        AppointmentReschedule::class => AppointmentReschedulePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
        // Additional gates (if needed later)
        Gate::define('appointment.rate', [AppointmentPolicy::class, 'rate']);
        Gate::define('appointment.updateRating', [AppointmentPolicy::class, 'updateRating']);
    }
}
