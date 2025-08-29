<?php

namespace App\Jobs\Transport;

use App\Models\User;
use App\Models\V1\SIS\Student\Student;
use App\Notifications\Transport\BusDelayNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendDelayNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;

    protected $delayData;

    public function __construct(array $delayData)
    {
        $this->delayData = $delayData;
    }

    public function handle()
    {
        try {
            $parent = User::findOrFail($this->delayData['parent_id']);
            $student = Student::findOrFail($this->delayData['student_id']);

            // Send notification
            $parent->notify(new BusDelayNotification($this->delayData));

            Log::info('Delay notification sent successfully', [
                'parent_id' => $parent->id,
                'student_id' => $student->id,
                'delay_minutes' => $this->delayData['delay_minutes']
            ]);

        } catch (\Exception $e) {
            Log::error('Delay notification failed', [
                'error' => $e->getMessage(),
                'data' => $this->delayData
            ]);

            $this->fail($e);
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('SendDelayNotification job failed permanently', [
            'exception' => $exception->getMessage(),
            'data' => $this->delayData
        ]);
    }
}
