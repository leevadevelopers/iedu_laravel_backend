<?php

namespace App\Jobs\Transport;

use App\Models\V1\Transport\StudentTransportSubscription;
use App\Services\V1\Transport\StudentTransportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTransportSubscription implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 2;

    protected $subscriptionId;
    protected $action;
    protected $data;

    public function __construct(int $subscriptionId, string $action, array $data = [])
    {
        $this->subscriptionId = $subscriptionId;
        $this->action = $action;
        $this->data = $data;
    }

    public function handle(StudentTransportService $transportService)
    {
        try {
            $subscription = StudentTransportSubscription::findOrFail($this->subscriptionId);

            switch ($this->action) {
                case 'approve':
                    $this->approveSubscription($subscription);
                    break;

                case 'renew':
                    $this->renewSubscription($subscription, $transportService);
                    break;

                case 'expire':
                    $this->expireSubscription($subscription);
                    break;

                case 'cancel':
                    $this->cancelSubscription($subscription);
                    break;

                default:
                    throw new \InvalidArgumentException("Unknown subscription action: {$this->action}");
            }

        } catch (\Exception $e) {
            Log::error('Transport subscription processing failed', [
                'subscription_id' => $this->subscriptionId,
                'action' => $this->action,
                'error' => $e->getMessage()
            ]);

            $this->fail($e);
        }
    }

    private function approveSubscription(StudentTransportSubscription $subscription): void
    {
        $subscription->update(['status' => 'active']);

        // Update bus capacity
        $route = $subscription->transportRoute;
        $bus = $route->getCurrentBus();
        if ($bus) {
            $bus->increment('current_capacity');
        }

        // Send approval notification to parents
        if ($subscription->authorized_parents) {
            foreach ($subscription->authorized_parents as $parentId) {
                SendTransportNotification::dispatch([
                    'parent_id' => $parentId,
                    'student_id' => $subscription->student_id,
                    'type' => 'subscription_approved',
                    'channels' => ['email'],
                    'data' => [
                        'student_name' => $subscription->student->first_name . ' ' . $subscription->student->last_name,
                        'route_name' => $subscription->transportRoute->name,
                        'pickup_stop' => $subscription->pickupStop->name,
                        'start_date' => $subscription->start_date->format('Y-m-d')
                    ]
                ]);
            }
        }

        Log::info('Transport subscription approved', [
            'subscription_id' => $subscription->id,
            'student_id' => $subscription->student_id
        ]);
    }

    private function renewSubscription(StudentTransportSubscription $subscription, StudentTransportService $service): void
    {
        if (!$subscription->canAutoRenew()) {
            Log::warning('Attempted to renew non-renewable subscription', [
                'subscription_id' => $subscription->id
            ]);
            return;
        }

        // Calculate new end date based on subscription type
        $newEndDate = match($subscription->subscription_type) {
            'monthly' => $subscription->end_date->addMonth(),
            'term' => $subscription->end_date->addMonths(3),
            'weekly' => $subscription->end_date->addWeek(),
            default => $subscription->end_date->addMonth()
        };

        $subscription->update([
            'end_date' => $newEndDate,
            'status' => 'active'
        ]);

        Log::info('Transport subscription renewed', [
            'subscription_id' => $subscription->id,
            'new_end_date' => $newEndDate->format('Y-m-d')
        ]);
    }

    private function expireSubscription(StudentTransportSubscription $subscription): void
    {
        $subscription->update(['status' => 'expired']);

        // Update bus capacity
        $route = $subscription->transportRoute;
        $bus = $route->getCurrentBus();
        if ($bus) {
            $bus->decrement('current_capacity');
        }

        Log::info('Transport subscription expired', [
            'subscription_id' => $subscription->id,
            'student_id' => $subscription->student_id
        ]);
    }

    private function cancelSubscription(StudentTransportSubscription $subscription): void
    {
        $subscription->update(['status' => 'cancelled']);

        // Update bus capacity
        $route = $subscription->transportRoute;
        $bus = $route->getCurrentBus();
        if ($bus) {
            $bus->decrement('current_capacity');
        }

        Log::info('Transport subscription cancelled', [
            'subscription_id' => $subscription->id,
            'student_id' => $subscription->student_id
        ]);
    }

    public function failed(\Throwable $exception)
    {
        Log::error('ProcessTransportSubscription job failed permanently', [
            'subscription_id' => $this->subscriptionId,
            'action' => $this->action,
            'exception' => $exception->getMessage()
        ]);
    }
}
