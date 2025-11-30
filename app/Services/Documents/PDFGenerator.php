<?php

namespace App\Services\Documents;

use App\Models\Documents\Document;
use App\Models\V1\SIS\Student\Student;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class PDFGenerator
{
    protected array $templates = [
        'enrollment_certificate' => 'documents.enrollment_certificate',
        'attendance_declaration' => 'documents.attendance_declaration',
        'conduct_certificate' => 'documents.conduct_certificate',
    ];

    /**
     * Generate PDF document
     */
    public function generate(Document $document): string
    {
        $template = $this->templates[$document->template] ?? null;

        if (!$template) {
            throw new \Exception("Template '{$document->template}' not found");
        }

        $data = $this->prepareData($document);

        try {
            $pdf = Pdf::loadView($template, $data);

            // Save PDF to storage
            $filename = $document->document_id . '.pdf';
            $path = 'documents/' . $filename;
            Storage::disk('public')->put($path, $pdf->output());

            $pdfUrl = Storage::url($path);
            $downloadUrl = url('/v1/documents/' . $document->id . '/download');

            $document->update([
                'pdf_url' => $pdfUrl,
                'download_url' => $downloadUrl,
                'status' => 'generated',
            ]);

            return $pdfUrl;
        } catch (\Exception $e) {
            throw new \Exception('Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * Prepare data for template
     */
    protected function prepareData(Document $document): array
    {
        $data = [
            'document' => $document,
            'student' => null,
            'school' => null,
            'date' => now()->format('d/m/Y'),
        ];

        if ($document->student_id) {
            $student = Student::with(['school', 'currentAcademicYear'])->find($document->student_id);
            $data['student'] = $student;
            $data['school'] = $student->school ?? null;
        }

        return $data;
    }

    /**
     * Get available templates
     */
    public function getTemplates(): array
    {
        return [
            ['id' => 'enrollment_certificate', 'name' => 'Certificado de Matrícula'],
            ['id' => 'attendance_declaration', 'name' => 'Declaração de Frequência'],
            ['id' => 'conduct_certificate', 'name' => 'Atestado de Comportamento'],
        ];
    }
}

