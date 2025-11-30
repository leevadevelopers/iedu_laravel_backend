<?php

namespace App\Http\Controllers\API\V1\Documents;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Documents\GenerateDocumentRequest;
use App\Http\Resources\Documents\DocumentResource;
use App\Models\Documents\Document;
use App\Services\Documents\PDFGenerator;
use App\Services\SchoolContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends BaseController
{
    protected PDFGenerator $pdfGenerator;
    protected SchoolContextService $schoolContextService;

    public function __construct(PDFGenerator $pdfGenerator, SchoolContextService $schoolContextService)
    {
        $this->middleware('auth:api');
        $this->pdfGenerator = $pdfGenerator;
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Get available templates
     */
    public function templates(): JsonResponse
    {
        try {
            $templates = $this->pdfGenerator->getTemplates();

            return $this->successResponse(
                $templates,
                'Templates retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve templates: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Generate document
     */
    public function generate(GenerateDocumentRequest $request): JsonResponse
    {
        try {
            $schoolId = $this->getCurrentSchoolId();

            $document = Document::create([
                'template' => $request->template,
                'student_id' => $request->student_id,
                'purpose' => $request->purpose,
                'signed_by' => $request->signed_by ?? 'diretor',
                'notes' => $request->notes,
                'school_id' => $schoolId,
                'generated_by' => auth('api')->id(),
                'status' => 'draft',
            ]);

            // Generate PDF
            $pdfUrl = $this->pdfGenerator->generate($document);

            return $this->successResponse(
                new DocumentResource($document->fresh()->load(['student', 'generator'])),
                'Document generated successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to generate document: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Download document
     */
    public function download(Document $document): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (!$document->pdf_url) {
            abort(404, 'Document PDF not found');
        }

        $path = str_replace('/storage/', '', $document->pdf_url);

        return Storage::disk('public')->download($path, $document->document_id . '.pdf');
    }

    /**
     * List documents
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $schoolId = $this->getCurrentSchoolId();

            $query = Document::query();

            if ($schoolId) {
                $query->where('school_id', $schoolId);
            }

            if ($request->filled('template')) {
                $query->where('template', $request->template);
            }

            if ($request->filled('student_id')) {
                $query->where('student_id', $request->student_id);
            }

            $documents = $query->with(['student', 'generator'])
                ->latest()
                ->paginate($request->get('per_page', 15));

            return $this->paginatedResponse(
                DocumentResource::collection($documents),
                'Documents retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve documents: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get current school ID helper
     */
    protected function getCurrentSchoolId(): ?int
    {
        try {
            return $this->schoolContextService->getCurrentSchoolId();
        } catch (\Exception $e) {
            return null;
        }
    }
}

