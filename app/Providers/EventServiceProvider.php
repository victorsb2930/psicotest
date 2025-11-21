<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        \Illuminate\Auth\Events\Login::class => [
            \App\Listeners\LogSuccessfulLogin::class,
        ],
        \Illuminate\Auth\Events\Logout::class => [
            \App\Listeners\LogSuccessfulLogout::class,
        ],
        \App\Events\AppointmentRescheduled::class => [
            \App\Listeners\SendAppointmentRescheduledNotifications::class,
        ],
        \App\Events\AppointmentSkipped::class => [
            \App\Listeners\SendAppointmentSkippedNotifications::class,
        ],
        \App\Events\AppointmentCompleted::class => [
            \App\Listeners\SendAppointmentCompletedNotifications::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }
}
