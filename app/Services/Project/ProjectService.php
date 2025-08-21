<?php

namespace App\Services\Project;

use App\Repositories\Project\Contracts\ProjectRepositoryInterface;
use App\Models\Project\Project;
use App\Events\Project\ProjectCreated;
use App\Events\Project\ProjectStatusChanged;
use App\Services\Forms\FormEngineService;
use App\Services\AI\Project\ProjectIntelligenceService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProjectService
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private FormEngineService $formEngineService,
        private ProjectIntelligenceService $intelligenceService
    ) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->projectRepository->paginate($filters);
    }

    public function create(array $data): Project
    {
        // Process form data through form engine
        $processedData = $this->formEngineService->processFormData('project_creation', $data);
        
        // Generate project code if not provided
        if (!isset($processedData['code'])) {
            $processedData['code'] = $this->generateProjectCode($processedData);
        }
        
        // Apply methodology-specific adaptations
        $processedData = $this->applyMethodologyAdaptations($processedData);
        
        $project = $this->projectRepository->create($processedData);
        
        // AI-enhanced project analysis (optional)
        $this->intelligenceService->analyzeNewProject($project);
        
        // Fire event for background processing
        event(new ProjectCreated($project));
        
        return $project;
    }

    public function findById(int $id): Project
    {
        $project = $this->projectRepository->find($id);
        
        if (!$project) {
            throw new ModelNotFoundException('Project not found');
        }
        
        return $project;
    }

    public function update(int $id, array $data): Project
    {
        $project = $this->findById($id);
        
        // Process form data through form engine
        $processedData = $this->formEngineService->processFormData('project_update', $data);
        
        $updatedProject = $this->projectRepository->update($project, $processedData);
        
        // Trigger AI re-analysis if significant changes
        if ($this->hasSignificantChanges($project, $processedData)) {
            $this->intelligenceService->reanalyzeProject($updatedProject);
        }
        
        return $updatedProject;
    }

    public function delete(int $id): bool
    {
        $project = $this->findById($id);
        
        return $this->projectRepository->delete($project);
    }

    public function approve(int $id): Project
    {
        $project = $this->findById($id);
        
        $project = $this->projectRepository->updateStatus($project, 'approved');
        
        event(new ProjectStatusChanged($project, 'pending_approval', 'approved'));
        
        return $project;
    }

    public function activate(int $id): Project
    {
        $project = $this->findById($id);
        
        // Validate project can be activated
        $this->validateProjectActivation($project);
        
        $project = $this->projectRepository->updateStatus($project, 'active');
        
        event(new ProjectStatusChanged($project, $project->getOriginal('status'), 'active'));
        
        return $project;
    }

    private function generateProjectCode(array $data): string
    {
        $prefix = strtoupper(substr($data['name'], 0, 3));
        $year = date('Y');
        $sequence = $this->projectRepository->getNextSequenceNumber();
        
        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }

    private function applyMethodologyAdaptations(array $data): array
    {
        $methodology = $data['methodology_type'] ?? 'universal';
        
        return match($methodology) {
            'usaid' => $this->applyUSAIDAdaptations($data),
            'world_bank' => $this->applyWorldBankAdaptations($data),
            'eu' => $this->applyEUAdaptations($data),
            default => $data
        };
    }

    private function applyUSAIDAdaptations(array $data): array
    {
        // Apply USAID-specific requirements
        $data['compliance_requirements'] = [
            'environmental_screening' => true,
            'gender_integration' => true,
            'marking_branding' => true
        ];
        
        return $data;
    }

    private function applyWorldBankAdaptations(array $data): array
    {
        // Apply World Bank-specific requirements
        $data['compliance_requirements'] = [
            'safeguards_screening' => true,
            'results_framework' => true,
            'procurement_plan' => true
        ];
        
        return $data;
    }

    private function applyEUAdaptations(array $data): array
    {
        // Apply EU-specific requirements
        $data['compliance_requirements'] = [
            'logical_framework' => true,
            'sustainability_plan' => true,
            'visibility_plan' => true
        ];
        
        return $data;
    }

    private function validateProjectActivation(Project $project): void
    {
        if ($project->status !== 'approved') {
            throw new \InvalidArgumentException('Project must be approved before activation');
        }
        
        // Add more validation logic as needed
    }

    private function hasSignificantChanges(Project $project, array $newData): bool
    {
        $significantFields = ['budget', 'end_date', 'methodology_type', 'category'];
        
        foreach ($significantFields as $field) {
            if (isset($newData[$field]) && $project->$field != $newData[$field]) {
                return true;
            }
        }
        
        return false;
    }
}
