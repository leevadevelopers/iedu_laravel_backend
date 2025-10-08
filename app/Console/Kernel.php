<?php

namespace App\Console;

use App\Jobs\Library\CheckOverdueLoansJob;
use App\Jobs\Library\ProcessOverdueFinesJob;
use App\Jobs\Library\ExpireReservationsJob;
use App\Jobs\Financial\CheckOverdueInvoicesJob;
use App\Jobs\Financial\GenerateMonthlyReportJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Check overdue loans daily at 8 AM
        $schedule->job(new CheckOverdueLoansJob)
            ->dailyAt('08:00')
            ->name('check-overdue-loans')
            ->onOneServer();

        // Process overdue fines daily at 9 AM
        $schedule->job(new ProcessOverdueFinesJob)
            ->dailyAt('09:00')
            ->name('process-overdue-fines')
            ->onOneServer();

        // Expire reservations every hour
        $schedule->job(new ExpireReservationsJob)
            ->hourly()
            ->name('expire-reservations')
            ->onOneServer();

        // Check overdue invoices daily at 7 AM
        $schedule->job(new CheckOverdueInvoicesJob)
            ->dailyAt('07:00')
            ->name('check-overdue-invoices')
            ->onOneServer();

        // Generate monthly reports on the first day of each month at midnight
        $schedule->call(function () {
            $tenants = \App\Models\Tenant::all();
            $lastMonth = now()->subMonth();

            foreach ($tenants as $tenant) {
                GenerateMonthlyReportJob::dispatch(
                    $tenant->id,
                    $lastMonth->month,
                    $lastMonth->year
                );
            }
        })->monthlyOn(1, '00:00')
          ->name('generate-monthly-reports')
          ->onOneServer();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
