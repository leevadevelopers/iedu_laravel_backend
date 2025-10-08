<?php

namespace App\Providers;

use App\Models\Library\Book;
use App\Models\Library\Loan;
use App\Models\Library\Reservation;
use App\Models\Library\Incident;
use App\Models\Financial\Invoice;
use App\Models\Financial\Payment;
use App\Models\Financial\Fee;
use App\Policies\Library\BookPolicy;
use App\Policies\Library\LoanPolicy;
use App\Policies\Library\ReservationPolicy;
use App\Policies\Library\IncidentPolicy;
use App\Policies\Financial\InvoicePolicy;
use App\Policies\Financial\PaymentPolicy;
use App\Policies\Financial\FeePolicy;
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
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
