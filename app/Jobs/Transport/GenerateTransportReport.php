<?php

namespace App\Jobs\Transport;

use App\Models\V1\SIS\School\School;
use App\Services\V1\Transport\TransportReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GenerateTransportReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes for large reports
    public $tries = 2;

    protected $schoolId;
    protected $reportType;
    protected $parameters;
    protected $requestedBy;

    public function __construct(int $schoolId, string $reportType, array $parameters, int $requestedBy)
    {
        $this->schoolId = $schoolId;
        $this->reportType = $reportType;
        $this->parameters = $parameters;
        $this->requestedBy = $requestedBy;
    }

    public function handle(TransportReportService $reportService)
    {
        try {
            Log::info('Starting transport report generation', [
                'school_id' => $this->schoolId,
                'report_type' => $this->reportType,
                'requested_by' => $this->requestedBy
            ]);

            $school = School::findOrFail($this->schoolId);

            // Generate the report based on type
            $reportData = $this->generateReportData($reportService, $school);

            // Convert to desired format (PDF, Excel, etc.)
            $filePath = $this->saveReportFile($reportData);

            // Send notification to requester
            $this->notifyReportReady($filePath);

            Log::info('Transport report generated successfully', [
                'school_id' => $this->schoolId,
                'report_type' => $this->reportType,
                'file_path' => $filePath
            ]);

        } catch (\Exception $e) {
            Log::error('Transport report generation failed', [
                'error' => $e->getMessage(),
                'school_id' => $this->schoolId,
                'report_type' => $this->reportType
            ]);

            $this->fail($e);
        }
    }

    private function generateReportData(TransportReportService $reportService, School $school): array
    {
        return match($this->reportType) {
            'attendance' => $reportService->generateAttendanceReport($school, $this->parameters),
            'performance' => $reportService->generatePerformanceReport($school, $this->parameters),
            'financial' => $reportService->generateFinancialReport($school, $this->parameters),
            'safety' => $reportService->generateSafetyReport($school, $this->parameters),
            'utilization' => $reportService->generateUtilizationReport($school, $this->parameters),
            'custom' => $reportService->generateCustomReport($school, $this->parameters),
            default => throw new \InvalidArgumentException("Unknown report type: {$this->reportType}")
        };
    }

    private function saveReportFile(array $reportData): string
    {
        $filename = $this->generateFilename();
        $format = $this->parameters['format'] ?? 'pdf';

        switch ($format) {
            case 'pdf':
                return $this->generatePdfReport($reportData, $filename);
            case 'excel':
                return $this->generateExcelReport($reportData, $filename);
            case 'csv':
                return $this->generateCsvReport($reportData, $filename);
            default:
                return $this->generateJsonReport($reportData, $filename);
        }
    }

    private function generateFilename(): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        return "transport_reports/{$this->reportType}_{$this->schoolId}_{$timestamp}";
    }

    private function generatePdfReport(array $data, string $filename): string
    {
        // Implementation would use a PDF library like TCPDF or DomPDF
        $pdfContent = $this->renderReportTemplate($data);
        $filePath = $filename . '.pdf';

        Storage::disk('local')->put($filePath, $pdfContent);
        return $filePath;
    }

    private function generateExcelReport(array $data, string $filename): string
    {
        // Implementation would use PhpSpreadsheet
        $filePath = $filename . '.xlsx';
        // Excel generation logic here
        return $filePath;
    }

    private function generateCsvReport(array $data, string $filename): string
    {
        $filePath = $filename . '.csv';
        $csvContent = $this->convertToCsv($data);

        Storage::disk('local')->put($filePath, $csvContent);
        return $filePath;
    }

    private function generateJsonReport(array $data, string $filename): string
    {
        $filePath = $filename . '.json';
        Storage::disk('local')->put($filePath, json_encode($data, JSON_PRETTY_PRINT));
        return $filePath;
    }

    private function renderReportTemplate(array $data): string
    {
        // This would render a blade template to HTML then convert to PDF
        return view('transport.reports.' . $this->reportType, compact('data'))->render();
    }

    private function convertToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'w');

        // Write headers
        if (isset($data[0]) && is_array($data[0])) {
            fputcsv($output, array_keys($data[0]));
        }

        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    private function notifyReportReady(string $filePath): void
    {
        // Send notification to the user who requested the report
        // This could be an email with download link, dashboard notification, etc.
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Report generation job failed permanently', [
            'exception' => $exception->getMessage(),
            'school_id' => $this->schoolId,
            'report_type' => $this->reportType
        ]);
    }
}
