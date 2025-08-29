<?php

namespace App\Notifications\Transport;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class TransportReportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $reportType;
    protected $filePath;
    protected $parameters;

    public function __construct(string $reportType, string $filePath, array $parameters = [])
    {
        $this->reportType = $reportType;
        $this->filePath = $filePath;
        $this->parameters = $parameters;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $reportTitle = ucwords(str_replace('_', ' ', $this->reportType));
        $fileSize = $this->getFileSize();
        $downloadUrl = $this->getDownloadUrl();

        return (new MailMessage)
            ->subject("📊 Your {$reportTitle} Report is Ready")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your requested {$reportTitle} report has been generated successfully and is ready for download.")
            ->line("**Report Details:**")
            ->line("- Report Type: {$reportTitle}")
            ->line("- Generated: " . now()->format('Y-m-d H:i:s'))
            ->line("- File Size: {$fileSize}")
            ->line("- Format: " . $this->getFileFormat())
            ->action('Download Report', $downloadUrl)
            ->line('The report will be available for download for the next 7 days.')
            ->line('If you have any questions about the report, please contact our support team.')
            ->salutation('iEDU Reporting Team');
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'report_ready',
            'title' => ucwords(str_replace('_', ' ', $this->reportType)) . ' Report Ready',
            'message' => "Your {$this->reportType} report has been generated and is ready for download.",
            'data' => [
                'report_type' => $this->reportType,
                'file_path' => $this->filePath,
                'file_size' => $this->getFileSize(),
                'file_format' => $this->getFileFormat(),
                'parameters' => $this->parameters,
                'expires_at' => now()->addDays(7)->toISOString()
            ],
            'actions' => [
                [
                    'title' => 'Download Report',
                    'url' => $this->getDownloadUrl(),
                    'type' => 'primary'
                ]
            ]
        ];
    }

    private function getFileSize(): string
    {
        $bytes = Storage::size($this->filePath);

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    private function getFileFormat(): string
    {
        return strtoupper(pathinfo($this->filePath, PATHINFO_EXTENSION));
    }

    private function getDownloadUrl(): string
    {
        return config('app.url') . '/transport/reports/download?file=' . urlencode($this->filePath);
    }
}
