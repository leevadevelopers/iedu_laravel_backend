<?php

namespace App\Providers;

use App\Events\Library\BookLoaned;
use App\Events\Library\BookOverdue;
use App\Events\Library\ReservationReady;
use App\Events\Library\IncidentReported;
use App\Events\Financial\InvoiceIssued;
use App\Events\Financial\InvoicePaid;
use App\Events\Financial\FeeApplied;
use App\Notifications\Library\BookLoanedNotification;
use App\Notifications\Library\BookOverdueNotification;
use App\Notifications\Library\ReservationReadyNotification;
use App\Notifications\Library\IncidentReportedNotification;
use App\Notifications\Financial\InvoiceIssuedNotification;
use App\Notifications\Financial\InvoicePaidNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        BookLoaned::class => [
            function (BookLoaned $event) {
                $event->loan->borrower->notify(new BookLoanedNotification($event->loan));
            },
        ],
        BookOverdue::class => [
            function (BookOverdue $event) {
                $event->loan->borrower->notify(new BookOverdueNotification($event->loan));
            },
        ],
        ReservationReady::class => [
            function (ReservationReady $event) {
                $event->reservation->user->notify(new ReservationReadyNotification($event->reservation));
            },
        ],
        IncidentReported::class => [
            function (IncidentReported $event) {
                // Notify librarians
                $librarians = \App\Models\User::role('librarian')
                    ->where('tenant_id', $event->incident->tenant_id)
                    ->get();

                foreach ($librarians as $librarian) {
                    $librarian->notify(new IncidentReportedNotification($event->incident));
                }
            },
        ],
        InvoiceIssued::class => [
            function (InvoiceIssued $event) {
                $event->invoice->billable->notify(new InvoiceIssuedNotification($event->invoice));
            },
        ],
        InvoicePaid::class => [
            function (InvoicePaid $event) {
                $event->invoice->billable->notify(new InvoicePaidNotification($event->invoice));
            },
        ],
        FeeApplied::class => [
            function (FeeApplied $event) {
                $event->user->notify(new InvoiceIssuedNotification($event->invoice));
            },
        ],
    ];

    public function boot(): void
    {
        //
    }
}
