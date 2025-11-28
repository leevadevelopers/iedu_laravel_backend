<?php

namespace App\Providers;

use App\Models\V1\Financial\Fee;
use App\Models\V1\Financial\Invoice;
use App\Models\V1\Financial\Payment;
use App\Models\V1\Library\Book;
use App\Models\V1\Library\Incident;
use App\Models\V1\Library\Loan;
use App\Models\V1\Library\Reservation;
use App\Models\V1\SIS\School\School;
use App\Policies\Library\BookPolicy;
use App\Policies\Library\LoanPolicy;
use App\Policies\Library\ReservationPolicy;
use App\Policies\Library\IncidentPolicy;
use App\Policies\Financial\InvoicePolicy;
use App\Policies\Financial\PaymentPolicy;
use App\Policies\Financial\FeePolicy;
use App\Policies\V1\School\SchoolPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Book::class => BookPolicy::class,
        Loan::class => LoanPolicy::class,
        Reservation::class => ReservationPolicy::class,
        Incident::class => IncidentPolicy::class,
        Invoice::class => InvoicePolicy::class,
        Payment::class => PaymentPolicy::class,
        Fee::class => FeePolicy::class,
        School::class => SchoolPolicy::class,
    ];

    public function boot(): void
    {
        // Policies are automatically registered from $policies array in Laravel 11
    }
}
