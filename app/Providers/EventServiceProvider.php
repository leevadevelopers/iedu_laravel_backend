<?php

namespace App\Providers;

use App\Events\Library\BookLoaned;
use App\Events\Library\BookOverdue;
use App\Events\Library\ReservationReady;
use App\Events\Library\IncidentReported;
use App\Events\Financial\InvoiceIssued;
use App\Events\Financial\InvoicePaid;
use App\Events\Financial\FeeApplied;
use App\Events\TenantCreated;
use App\Listeners\ProvisionTenantDefaults;
use App\Notifications\Library\BookLoanedNotification;
use App\Notifications\Library\BookOverdueNotification;
use App\Notifications\Library\ReservationReadyNotification;
use App\Notifications\Library\IncidentReportedNotification;
use App\Notifications\Financial\InvoiceIssuedNotification;
use App\Notifications\Financial\InvoicePaidNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        TenantCreated::class => [
            ProvisionTenantDefaults::class,
        ],
    ];

    public function boot(): void
    {
        Event::listen(BookLoaned::class, function (BookLoaned $event) {
            $event->loan->borrower->notify(new BookLoanedNotification($event->loan));
        });

        Event::listen(BookOverdue::class, function (BookOverdue $event) {
            $event->loan->borrower->notify(new BookOverdueNotification($event->loan));
        });

        Event::listen(ReservationReady::class, function (ReservationReady $event) {
            $event->reservation->user->notify(new ReservationReadyNotification($event->reservation));
        });

        Event::listen(IncidentReported::class, function (IncidentReported $event) {
            $librarians = \App\Models\User::role('librarian')
                ->where('tenant_id', $event->incident->tenant_id)
                ->get();

            foreach ($librarians as $librarian) {
                $librarian->notify(new IncidentReportedNotification($event->incident));
            }
        });

        Event::listen(InvoiceIssued::class, function (InvoiceIssued $event) {
            $event->invoice->billable->notify(new InvoiceIssuedNotification($event->invoice));
        });

        Event::listen(InvoicePaid::class, function (InvoicePaid $event) {
            $event->invoice->billable->notify(new InvoicePaidNotification($event->invoice));
        });

        Event::listen(FeeApplied::class, function (FeeApplied $event) {
            $event->user->notify(new InvoiceIssuedNotification($event->invoice));
        });
    }
}
