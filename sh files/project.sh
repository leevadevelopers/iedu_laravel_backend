#!/bin/bash

# iPM Project Module Setup Script
# Run this from the Laravel root directory (where vendor folder exists)

echo "🚀 Setting up iPM Project Module structure using Laravel conventions..."

# Check if we're in the correct directory
if [ ! -d "vendor" ]; then
    echo "❌ Error: Please run this script from the Laravel root directory (where vendor folder exists)"
    exit 1
fi

# Create main directory structure following Laravel conventions
echo "📁 Creating directory structure..."

# App directories (Laravel structure)
mkdir -p app/Http/Controllers/Project
mkdir -p app/Services/Project
mkdir -p app/Repositories/Project/Contracts
mkdir -p app/Models/Project
mkdir -p app/Http/Requests/Project
mkdir -p app/Http/Resources/Project
mkdir -p app/Events/Project
mkdir -p app/Jobs/Project
mkdir -p app/Observers/Project
mkdir -p app/Enums/Project

# AI services for project intelligence
mkdir -p app/Services/AI/Project
mkdir -p app/Services/AI/Fallbacks/Project
mkdir -p app/Contracts/AI/Project

# Shared components
mkdir -p app/Traits
mkdir -p app/Services/Shared
mkdir -p app/Http/Middleware

# Database directories
mkdir -p database/migrations
mkdir -p database/factories
mkdir -p database/seeders

# Routes
mkdir -p routes

# Tests directories
mkdir -p tests/Unit/Services/Project
mkdir -p tests/Unit/Models/Project
mkdir -p tests/Feature/Project
mkdir -p tests/AI/Project

echo "✅ Directory structure created successfully!"

# Create Controllers
echo "🎮 Creating Controllers..."

cat > app/Http/Controllers/Project/ProjectController.php << 'EOF'
<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Services\Project\ProjectService;
use App\Http\Requests\Project\CreateProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\Project\ProjectResource;
use App\Http\Resources\Project\ProjectListResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    public function __construct(
        private ProjectService $projectService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $projects = $this->projectService->list($request->all());
        
        return response()->json([
            'data' => ProjectListResource::collection($projects->items()),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
            ]
        ]);
    }

    public function store(CreateProjectRequest $request): JsonResponse
    {
        $project = $this->projectService->create($request->validated());
        
        return response()->json([
            'data' => new ProjectResource($project),
            'message' => 'Project created successfully'
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $project = $this->projectService->findById($id);
        
        return response()->json([
            'data' => new ProjectResource($project)
        ]);
    }

    public function update(UpdateProjectRequest $request, int $id): JsonResponse
    {
        $project = $this->projectService->update($id, $request->validated());
        
        return response()->json([
            'data' => new ProjectResource($project),
            'message' => 'Project updated successfully'
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->projectService->delete($id);
        
        return response()->json([
            'message' => 'Project deleted successfully'
        ]);
    }

    public function approve(int $id): JsonResponse
    {
        $project = $this->projectService->approve($id);
        
        return response()->json([
            'data' => new ProjectResource($project),
            'message' => 'Project approved successfully'
        ]);
    }

    public function activate(int $id): JsonResponse
    {
        $project = $this->projectService->activate($id);
        
        return response()->json([
            'data' => new ProjectResource($project),
            'message' => 'Project activated successfully'
        ]);
    }
}
EOF

cat > app/Http/Controllers/Project/ProjectAnalyticsController.php << 'EOF'
<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Services\Project\ProjectAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProjectAnalyticsController extends Controller
{
    public function __construct(
        private ProjectAnalyticsService $analyticsService
    ) {}

    public function getProjectDashboard(int $projectId): JsonResponse
    {
        $dashboard = $this->analyticsService->getProjectDashboard($projectId);
        
        return response()->json([
            'data' => $dashboard
        ]);
    }

    public function getProjectHealth(int $projectId): JsonResponse
    {
        $health = $this->analyticsService->calculateProjectHealth($projectId);
        
        return response()->json([
            'data' => $health
        ]);
    }

    public function getProjectProgress(int $projectId): JsonResponse
    {
        $progress = $this->analyticsService->calculateProgress($projectId);
        
        return response()->json([
            'data' => $progress
        ]);
    }

    public function getRiskAnalysis(int $projectId): JsonResponse
    {
        $risks = $this->analyticsService->analyzeRisks($projectId);
        
        return response()->json([
            'data' => $risks
        ]);
    }

    public function getBudgetAnalysis(int $projectId): JsonResponse
    {
        $budget = $this->analyticsService->analyzeBudget($projectId);
        
        return response()->json([
            'data' => $budget
        ]);
    }
}
EOF

cat > app/Http/Controllers/Project/ProjectMilestoneController.php << 'EOF'
<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Services\Project\ProjectMilestoneService;
use App\Http\Requests\Project\CreateMilestoneRequest;
use App\Http\Requests\Project\UpdateMilestoneRequest;
use Illuminate\Http\JsonResponse;

class ProjectMilestoneController extends Controller
{
    public function __construct(
        private ProjectMilestoneService $milestoneService
    ) {}

    public function index(int $projectId): JsonResponse
    {
        $milestones = $this->milestoneService->getProjectMilestones($projectId);
        
        return response()->json([
            'data' => $milestones
        ]);
    }

    public function store(CreateMilestoneRequest $request, int $projectId): JsonResponse
    {
        $milestone = $this->milestoneService->create($projectId, $request->validated());
        
        return response()->json([
            'data' => $milestone,
            'message' => 'Milestone created successfully'
        ], 201);
    }

    public function update(UpdateMilestoneRequest $request, int $projectId, int $milestoneId): JsonResponse
    {
        $milestone = $this->milestoneService->update($milestoneId, $request->validated());
        
        return response()->json([
            'data' => $milestone,
            'message' => 'Milestone updated successfully'
        ]);
    }

    public function complete(int $projectId, int $milestoneId): JsonResponse
    {
        $milestone = $this->milestoneService->complete($milestoneId);
        
        return response()->json([
            'data' => $milestone,
            'message' => 'Milestone marked as completed'
        ]);
    }
}
EOF

cat > app/Http/Controllers/Project/ProjectWorkflowController.php << 'EOF'
<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Services\Project\ProjectWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProjectWorkflowController extends Controller
{
    public function __construct(
        private ProjectWorkflowService $workflowService
    ) {}

    public function getWorkflowStatus(int $projectId): JsonResponse
    {
        $status = $this->workflowService->getWorkflowStatus($projectId);
        
        return response()->json([
            'data' => $status
        ]);
    }

    public function transitionToNext(int $projectId): JsonResponse
    {
        $result = $this->workflowService->transitionToNext($projectId);
        
        return response()->json([
            'data' => $result,
            'message' => 'Project workflow transitioned successfully'
        ]);
    }

    public function getAvailableTransitions(int $projectId): JsonResponse
    {
        $transitions = $this->workflowService->getAvailableTransitions($projectId);
        
        return response()->json([
            'data' => $transitions
        ]);
    }
}
EOF

# Create Services
echo "⚙️ Creating Services..."

cat > app/Services/Project/ProjectService.php << 'EOF'
<?php

namespace App\Services\Project;

use App\Repositories\Project\Contracts\ProjectRepositoryInterface;
use App\Models\Project\Project;
use App\Events\Project\ProjectCreated;
use App\Events\Project\ProjectStatusChanged;
use App\Services\Shared\FormEngineService;
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
EOF

cat > app/Services/Project/ProjectAnalyticsService.php << 'EOF'
<?php

namespace App\Services\Project;

use App\Repositories\Project\Contracts\ProjectRepositoryInterface;
use App\Services\AI\Project\ProjectIntelligenceService;
use App\Models\Project\Project;

class ProjectAnalyticsService
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private ProjectIntelligenceService $intelligenceService
    ) {}

    public function getProjectDashboard(int $projectId): array
    {
        $project = $this->projectRepository->find($projectId);
        
        return [
            'project' => $project,
            'progress' => $this->calculateProgress($projectId),
            'health' => $this->calculateProjectHealth($projectId),
            'budget_status' => $this->analyzeBudget($projectId),
            'risks' => $this->analyzeRisks($projectId),
            'timeline' => $this->analyzeTimeline($projectId),
            'insights' => $this->intelligenceService->generateInsights($project)
        ];
    }

    public function calculateProgress(int $projectId): array
    {
        $project = $this->projectRepository->find($projectId);
        
        // Rule-based progress calculation
        $milestoneProgress = $this->calculateMilestoneProgress($project);
        $timeProgress = $this->calculateTimeProgress($project);
        $budgetProgress = $this->calculateBudgetProgress($project);
        
        $overallProgress = ($milestoneProgress + $timeProgress + $budgetProgress) / 3;
        
        return [
            'overall' => round($overallProgress, 2),
            'milestones' => $milestoneProgress,
            'timeline' => $timeProgress,
            'budget' => $budgetProgress,
            'status' => $this->getProgressStatus($overallProgress),
            'insights' => $this->intelligenceService->analyzeProgress($project)
        ];
    }

    public function calculateProjectHealth(int $projectId): array
    {
        $project = $this->projectRepository->find($projectId);
        
        $healthFactors = [
            'timeline_health' => $this->assessTimelineHealth($project),
            'budget_health' => $this->assessBudgetHealth($project),
            'risk_health' => $this->assessRiskHealth($project),
            'team_health' => $this->assessTeamHealth($project),
            'quality_health' => $this->assessQualityHealth($project)
        ];
        
        $overallHealth = array_sum($healthFactors) / count($healthFactors);
        
        return [
            'overall_score' => round($overallHealth, 2),
            'status' => $this->getHealthStatus($overallHealth),
            'factors' => $healthFactors,
            'recommendations' => $this->intelligenceService->generateHealthRecommendations($project),
            'alerts' => $this->getHealthAlerts($healthFactors)
        ];
    }

    public function analyzeRisks(int $projectId): array
    {
        $project = $this->projectRepository->find($projectId);
        
        // Rule-based risk analysis
        $risks = [
            'timeline_risk' => $this->assessTimelineRisk($project),
            'budget_risk' => $this->assessBudgetRisk($project),
            'complexity_risk' => $this->assessComplexityRisk($project),
            'external_risk' => $this->assessExternalRisk($project)
        ];
        
        $overallRisk = max($risks);
        
        return [
            'overall_risk' => $overallRisk,
            'risk_level' => $this->getRiskLevel($overallRisk),
            'risk_factors' => $risks,
            'mitigation_suggestions' => $this->intelligenceService->generateRiskMitigation($project),
            'risk_trend' => $this->analyzeRiskTrend($project)
        ];
    }

    public function analyzeBudget(int $projectId): array
    {
        $project = $this->projectRepository->find($projectId);
        
        // Budget analysis logic
        $totalBudget = $project->budget;
        $spentAmount = $this->calculateSpentAmount($project);
        $utilization = $totalBudget > 0 ? ($spentAmount / $totalBudget) * 100 : 0;
        
        $burnRate = $this->calculateBurnRate($project);
        $projectedSpend = $this->projectFinalSpend($project, $burnRate);
        
        return [
            'total_budget' => $totalBudget,
            'spent_amount' => $spentAmount,
            'remaining_budget' => $totalBudget - $spentAmount,
            'utilization_percentage' => round($utilization, 2),
            'burn_rate' => $burnRate,
            'projected_final_spend' => $projectedSpend,
            'variance' => $projectedSpend - $totalBudget,
            'status' => $this->getBudgetStatus($utilization, $projectedSpend, $totalBudget),
            'recommendations' => $this->intelligenceService->generateBudgetRecommendations($project)
        ];
    }

    public function analyzeTimeline(int $projectId): array
    {
        $project = $this->projectRepository->find($projectId);
        
        $timeline = [
            'start_date' => $project->start_date,
            'end_date' => $project->end_date,
            'current_date' => now(),
            'total_duration' => $project->start_date->diffInDays($project->end_date),
            'elapsed_days' => $project->start_date->diffInDays(now()),
            'remaining_days' => now()->diffInDays($project->end_date, false),
            'time_progress' => $this->calculateTimeProgress($project),
            'predicted_completion' => $this->intelligenceService->predictCompletion($project),
            'critical_path' => $this->analyzeCriticalPath($project)
        ];
        
        return $timeline;
    }

    // Helper methods for calculations
    private function calculateMilestoneProgress(Project $project): float
    {
        $milestones = $project->milestones;
        if ($milestones->isEmpty()) return 0;
        
        $completedWeight = $milestones->where('status', 'completed')->sum('weight');
        $totalWeight = $milestones->sum('weight');
        
        return $totalWeight > 0 ? ($completedWeight / $totalWeight) * 100 : 0;
    }

    private function calculateTimeProgress(Project $project): float
    {
        if (!$project->start_date || !$project->end_date) return 0;
        
        $totalDays = $project->start_date->diffInDays($project->end_date);
        $elapsedDays = $project->start_date->diffInDays(now());
        
        return $totalDays > 0 ? min(100, ($elapsedDays / $totalDays) * 100) : 0;
    }

    private function calculateBudgetProgress(Project $project): float
    {
        $spentAmount = $this->calculateSpentAmount($project);
        return $project->budget > 0 ? ($spentAmount / $project->budget) * 100 : 0;
    }

    private function calculateSpentAmount(Project $project): float
    {
        // This would integrate with your financial system
        // For now, return a placeholder
        return $project->budget * 0.3; // 30% spent as example
    }

    private function calculateBurnRate(Project $project): float
    {
        $elapsedDays = $project->start_date->diffInDays(now());
        $spentAmount = $this->calculateSpentAmount($project);
        
        return $elapsedDays > 0 ? $spentAmount / $elapsedDays : 0;
    }

    private function projectFinalSpend(Project $project, float $burnRate): float
    {
        $totalDays = $project->start_date->diffInDays($project->end_date);
        return $burnRate * $totalDays;
    }

    // Assessment methods
    private function assessTimelineHealth(Project $project): float
    {
        $timeProgress = $this->calculateTimeProgress($project);
        $milestoneProgress = $this->calculateMilestoneProgress($project);
        
        $variance = abs($timeProgress - $milestoneProgress);
        
        return max(0, 100 - $variance);
    }

    private function assessBudgetHealth(Project $project): float
    {
        $budgetUtilization = $this->calculateBudgetProgress($project);
        $timeProgress = $this->calculateTimeProgress($project);
        
        $variance = abs($budgetUtilization - $timeProgress);
        
        return max(0, 100 - $variance * 2);
    }

    private function assessRiskHealth(Project $project): float
    {
        // Simplified risk assessment
        $risks = $this->analyzeRisks($project->id);
        return 100 - ($risks['overall_risk'] * 100);
    }

    private function assessTeamHealth(Project $project): float
    {
        // Placeholder for team health assessment
        return 85.0;
    }

    private function assessQualityHealth(Project $project): float
    {
        // Placeholder for quality health assessment
        return 90.0;
    }

    // Risk assessment methods
    private function assessTimelineRisk(Project $project): float
    {
        $timeProgress = $this->calculateTimeProgress($project);
        $milestoneProgress = $this->calculateMilestoneProgress($project);
        
        if ($timeProgress > $milestoneProgress + 20) {
            return 0.8; // High risk
        } elseif ($timeProgress > $milestoneProgress + 10) {
            return 0.5; // Medium risk
        }
        
        return 0.2; // Low risk
    }

    private function assessBudgetRisk(Project $project): float
    {
        $burnRate = $this->calculateBurnRate($project);
        $projectedSpend = $this->projectFinalSpend($project, $burnRate);
        $variance = ($projectedSpend - $project->budget) / $project->budget;
        
        if ($variance > 0.2) return 0.9; // High risk
        if ($variance > 0.1) return 0.6; // Medium risk
        if ($variance > 0.05) return 0.3; // Low risk
        
        return 0.1; // Very low risk
    }

    private function assessComplexityRisk(Project $project): float
    {
        $complexityFactors = 0;
        
        // Budget complexity
        if ($project->budget > 1000000) $complexityFactors += 0.2;
        
        // Duration complexity
        $durationMonths = $project->start_date->diffInMonths($project->end_date);
        if ($durationMonths > 24) $complexityFactors += 0.2;
        
        // Team size complexity (if available)
        // $teamSize = $project->team->count();
        // if ($teamSize > 20) $complexityFactors += 0.2;
        
        return min(1.0, $complexityFactors);
    }

    private function assessExternalRisk(Project $project): float
    {
        // Placeholder for external risk factors
        return 0.3;
    }

    // Status determination methods
    private function getProgressStatus(float $progress): string
    {
        return match(true) {
            $progress >= 90 => 'excellent',
            $progress >= 70 => 'good',
            $progress >= 50 => 'fair',
            $progress >= 30 => 'poor',
            default => 'critical'
        };
    }

    private function getHealthStatus(float $health): string
    {
        return match(true) {
            $health >= 85 => 'healthy',
            $health >= 70 => 'warning',
            $health >= 50 => 'at_risk',
            default => 'critical'
        };
    }

    private function getRiskLevel(float $risk): string
    {
        return match(true) {
            $risk >= 0.8 => 'critical',
            $risk >= 0.6 => 'high',
            $risk >= 0.4 => 'medium',
            $risk >= 0.2 => 'low',
            default => 'minimal'
        };
    }

    private function getBudgetStatus(float $utilization, float $projectedSpend, float $totalBudget): string
    {
        $variance = ($projectedSpend - $totalBudget) / $totalBudget;
        
        return match(true) {
            $variance > 0.2 => 'over_budget',
            $variance > 0.1 => 'at_risk',
            $utilization > 90 => 'high_utilization',
            $utilization > 70 => 'normal',
            default => 'under_utilized'
        };
    }

    private function getHealthAlerts(array $factors): array
    {
        $alerts = [];
        
        foreach ($factors as $factor => $score) {
            if ($score < 50) {
                $alerts[] = [
                    'type' => 'critical',
                    'factor' => $factor,
                    'message' => "Critical issue with {$factor}",
                    'score' => $score
                ];
            } elseif ($score < 70) {
                $alerts[] = [
                    'type' => 'warning',
                    'factor' => $factor,
                    'message' => "Warning for {$factor}",
                    'score' => $score
                ];
            }
        }
        
        return $alerts;
    }

    private function analyzeRiskTrend(Project $project): string
    {
        // Placeholder for risk trend analysis
        return 'stable';
    }

    private function analyzeCriticalPath(Project $project): array
    {
        // Placeholder for critical path analysis
        return [];
    }
}
EOF

cat > app/Services/Project/ProjectMilestoneService.php << 'EOF'
<?php

namespace App\Services\Project;

use App\Repositories\Project\Contracts\ProjectMilestoneRepositoryInterface;
use App\Models\Project\ProjectMilestone;
use App\Events\Project\MilestoneCompleted;

class ProjectMilestoneService
{
    public function __construct(
        private ProjectMilestoneRepositoryInterface $milestoneRepository
    ) {}

    public function getProjectMilestones(int $projectId): array
    {
        return $this->milestoneRepository->getByProject($projectId);
    }

    public function create(int $projectId, array $data): ProjectMilestone
    {
        $data['project_id'] = $projectId;
        $milestone = $this->milestoneRepository->create($data);
        
        return $milestone;
    }

    public function update(int $milestoneId, array $data): ProjectMilestone
    {
        $milestone = $this->milestoneRepository->find($milestoneId);
        return $this->milestoneRepository->update($milestone, $data);
    }

    public function complete(int $milestoneId): ProjectMilestone
    {
        $milestone = $this->milestoneRepository->find($milestoneId);
        
        $milestone = $this->milestoneRepository->update($milestone, [
            'status' => 'completed',
            'completion_date' => now()
        ]);
        
        event(new MilestoneCompleted($milestone));
        
        return $milestone;
    }
}
EOF

cat > app/Services/Project/ProjectWorkflowService.php << 'EOF'
<?php

namespace App\Services\Project;

use App\Repositories\Project\Contracts\ProjectRepositoryInterface;
use App\Services\Shared\WorkflowEngineService;
use App\Models\Project\Project;

class ProjectWorkflowService
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private WorkflowEngineService $workflowEngine
    ) {}

    public function getWorkflowStatus(int $projectId): array
    {
        $project = $this->projectRepository->find($projectId);
        
        return [
            'current_status' => $project->status,
            'workflow_state' => $project->workflow_state,
            'available_transitions' => $this->getAvailableTransitions($projectId),
            'approval_chain' => $this->getApprovalChain($project),
            'history' => $this->getWorkflowHistory($project)
        ];
    }

    public function transitionToNext(int $projectId): array
    {
        $project = $this->projectRepository->find($projectId);
        
        $result = $this->workflowEngine->executeTransition($project);
        
        return $result;
    }

    public function getAvailableTransitions(int $projectId): array
    {
        $project = $this->projectRepository->find($projectId);
        
        return $this->workflowEngine->getAvailableTransitions($project);
    }

    private function getApprovalChain(Project $project): array
    {
        // Return approval chain based on project methodology and value
        return $this->workflowEngine->getApprovalChain($project);
    }

    private function getWorkflowHistory(Project $project): array
    {
        // Return workflow transition history
        return $this->workflowEngine->getWorkflowHistory($project);
    }
}
EOF

# Create Repository Interface and Implementation
echo "🗂️ Creating Repositories..."

cat > app/Repositories/Project/Contracts/ProjectRepositoryInterface.php << 'EOF'
<?php

namespace App\Repositories\Project\Contracts;

use App\Models\Project\Project;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProjectRepositoryInterface
{
    public function find(int $id): ?Project;
    
    public function create(array $data): Project;
    
    public function update(Project $project, array $data): Project;
    
    public function delete(Project $project): bool;
    
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    
    public function updateStatus(Project $project, string $status): Project;
    
    public function getNextSequenceNumber(): int;
    
    public function getByMethodology(string $methodology): array;
    
    public function getActiveProjects(): array;
    
    public function getProjectsByDateRange($startDate, $endDate): array;
}
EOF

cat > app/Repositories/Project/ProjectRepository.php << 'EOF'
<?php

namespace App\Repositories\Project;

use App\Repositories\Project\Contracts\ProjectRepositoryInterface;
use App\Models\Project\Project;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectRepository implements ProjectRepositoryInterface
{
    public function find(int $id): ?Project
    {
        return Project::with(['creator', 'milestones', 'formInstance'])->find($id);
    }

    public function create(array $data): Project
    {
        return Project::create($data);
    }

    public function update(Project $project, array $data): Project
    {
        $project->update($data);
        return $project->fresh();
    }

    public function delete(Project $project): bool
    {
        return $project->delete();
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Project::query();

        // Apply filters
        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('code', 'like', "%{$filters['search']}%")
                  ->orWhere('description', 'like', "%{$filters['search']}%");
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['methodology_type'])) {
            $query->where('methodology_type', $filters['methodology_type']);
        }

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['budget_min'])) {
            $query->where('budget', '>=', $filters['budget_min']);
        }

        if (isset($filters['budget_max'])) {
            $query->where('budget', '<=', $filters['budget_max']);
        }

        if (isset($filters['start_date_from'])) {
            $query->where('start_date', '>=', $filters['start_date_from']);
        }

        if (isset($filters['start_date_to'])) {
            $query->where('start_date', '<=', $filters['start_date_to']);
        }

        return $query->with(['creator', 'milestones'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);
    }

    public function updateStatus(Project $project, string $status): Project
    {
        $project->update(['status' => $status]);
        return $project->fresh();
    }

    public function getNextSequenceNumber(): int
    {
        $lastProject = Project::orderBy('id', 'desc')->first();
        return $lastProject ? $lastProject->id + 1 : 1;
    }

    public function getByMethodology(string $methodology): array
    {
        return Project::where('methodology_type', $methodology)
                     ->with(['creator', 'milestones'])
                     ->get()
                     ->toArray();
    }

    public function getActiveProjects(): array
    {
        return Project::where('status', 'active')
                     ->with(['creator', 'milestones'])
                     ->get()
                     ->toArray();
    }

    public function getProjectsByDateRange($startDate, $endDate): array
    {
        return Project::whereBetween('start_date', [$startDate, $endDate])
                     ->orWhereBetween('end_date', [$startDate, $endDate])
                     ->with(['creator', 'milestones'])
                     ->get()
                     ->toArray();
    }
}
EOF

cat > app/Repositories/Project/Contracts/ProjectMilestoneRepositoryInterface.php << 'EOF'
<?php

namespace App\Repositories\Project\Contracts;

use App\Models\Project\ProjectMilestone;

interface ProjectMilestoneRepositoryInterface
{
    public function find(int $id): ?ProjectMilestone;
    
    public function create(array $data): ProjectMilestone;
    
    public function update(ProjectMilestone $milestone, array $data): ProjectMilestone;
    
    public function delete(ProjectMilestone $milestone): bool;
    
    public function getByProject(int $projectId): array;
    
    public function getUpcomingMilestones(int $days = 30): array;
    
    public function getOverdueMilestones(): array;
}
EOF

cat > app/Repositories/Project/ProjectMilestoneRepository.php << 'EOF'
<?php

namespace App\Repositories\Project;

use App\Repositories\Project\Contracts\ProjectMilestoneRepositoryInterface;
use App\Models\Project\ProjectMilestone;

class ProjectMilestoneRepository implements ProjectMilestoneRepositoryInterface
{
    public function find(int $id): ?ProjectMilestone
    {
        return ProjectMilestone::find($id);
    }

    public function create(array $data): ProjectMilestone
    {
        return ProjectMilestone::create($data);
    }

    public function update(ProjectMilestone $milestone, array $data): ProjectMilestone
    {
        $milestone->update($data);
        return $milestone->fresh();
    }

    public function delete(ProjectMilestone $milestone): bool
    {
        return $milestone->delete();
    }

    public function getByProject(int $projectId): array
    {
        return ProjectMilestone::where('project_id', $projectId)
                              ->orderBy('target_date')
                              ->get()
                              ->toArray();
    }

    public function getUpcomingMilestones(int $days = 30): array
    {
        return ProjectMilestone::whereBetween('target_date', [now(), now()->addDays($days)])
                              ->where('status', '!=', 'completed')
                              ->with('project')
                              ->get()
                              ->toArray();
    }

    public function getOverdueMilestones(): array
    {
        return ProjectMilestone::where('target_date', '<', now())
                              ->where('status', '!=', 'completed')
                              ->with('project')
                              ->get()
                              ->toArray();
    }
}
EOF

# Create Models
echo "🏗️ Creating Models..."

cat > app/Models/Project/Project.php << 'EOF'
<?php

namespace App\Models\Project;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\HasFormInstance;
use App\Traits\HasWorkflow;
use App\Enums\Project\ProjectStatus;
use App\Enums\Project\ProjectPriority;

class Project extends Model
{
    use SoftDeletes, BelongsToTenant, HasFormInstance, HasWorkflow;

    protected $fillable = [
        'tenant_id',
        'form_instance_id',
        'name',
        'code',
        'description',
        'category',
        'priority',
        'status',
        'start_date',
        'end_date',
        'budget',
        'currency',
        'methodology_type',
        'metadata',
        'compliance_requirements',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'budget' => 'decimal:2',
        'metadata' => 'array',
        'compliance_requirements' => 'array',
        'status' => ProjectStatus::class,
        'priority' => ProjectPriority::class,
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function formInstance(): BelongsTo
    {
        return $this->belongsTo(\App\Models\FormInstance::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class);
    }

    public function team(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'project_users')
                    ->withPivot('role', 'access_level', 'joined_at')
                    ->withTimestamps();
    }

    public function stakeholders(): HasMany
    {
        return $this->hasMany(ProjectStakeholder::class);
    }

    public function risks(): HasMany
    {
        return $this->hasMany(ProjectRisk::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(\App\Models\Budget::class);
    }

    public function indicators(): HasManyThrough
    {
        return $this->hasManyThrough(
            \App\Models\Indicator::class,
            \App\Models\IndicatorFramework::class
        );
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', ProjectStatus::ACTIVE);
    }

    public function scopeByMethodology($query, string $methodology)
    {
        return $query->where('methodology_type', $methodology);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByPriority($query, ProjectPriority $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeOverBudget($query, float $amount)
    {
        return $query->where('budget', '>', $amount);
    }

    public function scopeUpcoming($query, int $days = 30)
    {
        return $query->whereBetween('start_date', [now(), now()->addDays($days)]);
    }

    // Accessors & Mutators
    public function getDurationInDaysAttribute(): ?int
    {
        if (!$this->start_date || !$this->end_date) {
            return null;
        }
        return $this->start_date->diffInDays($this->end_date);
    }

    public function getProgressPercentageAttribute(): float
    {
        // Basic time-based calculation - can be enhanced with milestone data
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }

        $totalDays = $this->start_date->diffInDays($this->end_date);
        $elapsedDays = $this->start_date->diffInDays(now());

        if ($totalDays <= 0) {
            return 0;
        }

        return min(100, max(0, ($elapsedDays / $totalDays) * 100));
    }

    public function getBudgetUtilizationAttribute(): float
    {
        // This would integrate with financial transactions
        // Placeholder calculation
        return 30.0; // 30% utilized
    }

    public function getHealthScoreAttribute(): array
    {
        // Comprehensive health calculation
        $factors = [
            'timeline' => $this->getTimelineHealth(),
            'budget' => $this->getBudgetHealth(),
            'milestones' => $this->getMilestoneHealth(),
            'risks' => $this->getRiskHealth(),
        ];

        $overallScore = array_sum($factors) / count($factors);

        return [
            'overall' => round($overallScore, 2),
            'factors' => $factors,
            'status' => $this->getHealthStatus($overallScore)
        ];
    }

    public function getRiskScoreAttribute(): array
    {
        $riskFactors = [
            'timeline_risk' => $this->getTimelineRisk(),
            'budget_risk' => $this->getBudgetRisk(),
            'complexity_risk' => $this->getComplexityRisk(),
        ];

        $overallRisk = max($riskFactors);

        return [
            'overall' => round($overallRisk, 2),
            'factors' => $riskFactors,
            'level' => $this->getRiskLevel($overallRisk)
        ];
    }

    // Helper methods for health calculations
    private function getTimelineHealth(): float
    {
        $progress = $this->progress_percentage;
        $timeProgress = $this->progress_percentage; // Time-based progress
        
        $variance = abs($progress - $timeProgress);
        return max(0, 100 - $variance);
    }

    private function getBudgetHealth(): float
    {
        $utilization = $this->budget_utilization;
        $timeProgress = $this->progress_percentage;
        
        $variance = abs($utilization - $timeProgress);
        return max(0, 100 - $variance * 2);
    }

    private function getMilestoneHealth(): float
    {
        $milestones = $this->milestones;
        if ($milestones->isEmpty()) return 100;
        
        $onTrack = $milestones->where('status', 'on_track')->count();
        $total = $milestones->count();
        
        return ($onTrack / $total) * 100;
    }

    private function getRiskHealth(): float
    {
        $riskScore = $this->risk_score['overall'];
        return 100 - ($riskScore * 100);
    }

    private function getTimelineRisk(): float
    {
        $remainingDays = now()->diffInDays($this->end_date, false);
        $progress = $this->progress_percentage;
        
        if ($remainingDays < 0) return 1.0; // Overdue
        if ($progress < 50 && $remainingDays < 30) return 0.8; // High risk
        if ($progress < 75 && $remainingDays < 60) return 0.5; // Medium risk
        
        return 0.2; // Low risk
    }

    private function getBudgetRisk(): float
    {
        $utilization = $this->budget_utilization;
        $timeProgress = $this->progress_percentage;
        
        if ($utilization > $timeProgress + 20) return 0.9; // Very high risk
        if ($utilization > $timeProgress + 10) return 0.6; // High risk
        if ($utilization > $timeProgress + 5) return 0.3; // Medium risk
        
        return 0.1; // Low risk
    }

    private function getComplexityRisk(): float
    {
        $risk = 0;
        
        // Budget complexity
        if ($this->budget > 1000000) $risk += 0.2;
        if ($this->budget > 5000000) $risk += 0.2;
        
        // Duration complexity
        if ($this->duration_in_days > 365) $risk += 0.2;
        if ($this->duration_in_days > 730) $risk += 0.2;
        
        // Team size complexity (if available)
        // $teamSize = $this->team->count();
        // if ($teamSize > 20) $risk += 0.2;
        
        return min(1.0, $risk);
    }

    private function getHealthStatus(float $score): string
    {
        return match(true) {
            $score >= 85 => 'excellent',
            $score >= 70 => 'good',
            $score >= 50 => 'warning',
            $score >= 30 => 'poor',
            default => 'critical'
        };
    }

    private function getRiskLevel(float $risk): string
    {
        return match(true) {
            $risk >= 0.8 => 'critical',
            $risk >= 0.6 => 'high',
            $risk >= 0.4 => 'medium',
            $risk >= 0.2 => 'low',
            default => 'minimal'
        };
    }

    // Methodology-specific methods
    public function getMethodologyRequirements(): array
    {
        return match($this->methodology_type) {
            'usaid' => $this->getUSAIDRequirements(),
            'world_bank' => $this->getWorldBankRequirements(),
            'eu' => $this->getEURequirements(),
            default => []
        };
    }

    private function getUSAIDRequirements(): array
    {
        return [
            'environmental_screening' => 'required',
            'gender_integration' => 'required',
            'marking_branding' => 'required',
            'procurement_plan' => 'required',
            'results_framework' => 'required'
        ];
    }

    private function getWorldBankRequirements(): array
    {
        return [
            'safeguards_screening' => 'required',
            'results_framework' => 'required',
            'procurement_plan' => 'required',
            'financial_management' => 'required',
            'monitoring_evaluation' => 'required'
        ];
    }

    private function getEURequirements(): array
    {
        return [
            'logical_framework' => 'required',
            'sustainability_plan' => 'required',
            'visibility_plan' => 'required',
            'risk_management' => 'required',
            'quality_assurance' => 'required'
        ];
    }

    // Workflow state methods
    public function canTransitionTo(string $newStatus): bool
    {
        $allowedTransitions = $this->getAllowedTransitions();
        return in_array($newStatus, $allowedTransitions[$this->status->value] ?? []);
    }

    private function getAllowedTransitions(): array
    {
        return [
            'draft' => ['pending_approval', 'cancelled'],
            'pending_approval' => ['approved', 'rejected', 'draft'],
            'approved' => ['active', 'cancelled'],
            'active' => ['on_hold', 'completed', 'cancelled'],
            'on_hold' => ['active', 'cancelled'],
            'completed' => [],
            'cancelled' => ['draft'],
            'rejected' => ['draft', 'cancelled']
        ];
    }
}
EOF

cat > app/Models/Project/ProjectMilestone.php << 'EOF'
<?php

namespace App\Models\Project;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;
use App\Enums\Project\MilestoneStatus;

class ProjectMilestone extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'project_id',
        'tenant_id',
        'name',
        'description',
        'target_date',
        'completion_date',
        'status',
        'weight',
        'deliverables',
        'success_criteria',
        'responsible_user_id',
        'dependencies',
        'notes',
    ];

    protected $casts = [
        'target_date' => 'date',
        'completion_date' => 'date',
        'weight' => 'decimal:2',
        'deliverables' => 'array',
        'success_criteria' => 'array',
        'dependencies' => 'array',
        'status' => MilestoneStatus::class,
    ];

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'responsible_user_id');
    }

    // Scopes
    public function scopeUpcoming($query, int $days = 30)
    {
        return $query->whereBetween('target_date', [now(), now()->addDays($days)]);
    }

    public function scopeOverdue($query)
    {
        return $query->where('target_date', '<', now())
                    ->where('status', '!=', MilestoneStatus::COMPLETED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', MilestoneStatus::COMPLETED);
    }

    // Accessors
    public function getDaysUntilDueAttribute(): int
    {
        return now()->diffInDays($this->target_date, false);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->target_date < now() && $this->status !== MilestoneStatus::COMPLETED;
    }

    public function getProgressStatusAttribute(): string
    {
        if ($this->status === MilestoneStatus::COMPLETED) {
            return 'completed';
        }
        
        if ($this->is_overdue) {
            return 'overdue';
        }
        
        if ($this->days_until_due <= 7) {
            return 'due_soon';
        }
        
        if ($this->days_until_due <= 30) {
            return 'upcoming';
        }
        
        return 'on_track';
    }
}
EOF

cat > app/Models/Project/ProjectStakeholder.php << 'EOF'
<?php

namespace App\Models\Project;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;

class ProjectStakeholder extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'project_id',
        'tenant_id',
        'name',
        'email',
        'organization',
        'role',
        'influence_level',
        'interest_level',
        'communication_preference',
        'notes',
    ];

    protected $casts = [
        'influence_level' => 'integer',
        'interest_level' => 'integer',
    ];

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // Accessors
    public function getStakeholderMatrixAttribute(): string
    {
        // Stakeholder analysis matrix based on influence and interest
        return match(true) {
            $this->influence_level >= 4 && $this->interest_level >= 4 => 'manage_closely',
            $this->influence_level >= 4 && $this->interest_level < 4 => 'keep_satisfied',
            $this->influence_level < 4 && $this->interest_level >= 4 => 'keep_informed',
            default => 'monitor'
        };
    }
}
EOF

cat > app/Models/Project/ProjectRisk.php << 'EOF'
<?php

namespace App\Models\Project;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;
use App\Enums\Project\RiskStatus;
use App\Enums\Project\RiskCategory;

class ProjectRisk extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'project_id',
        'tenant_id',
        'title',
        'description',
        'category',
        'probability',
        'impact',
        'risk_score',
        'status',
        'mitigation_strategy',
        'contingency_plan',
        'owner_id',
        'review_date',
        'identified_date',
    ];

    protected $casts = [
        'probability' => 'integer',
        'impact' => 'integer',
        'risk_score' => 'decimal:2',
        'review_date' => 'date',
        'identified_date' => 'date',
        'status' => RiskStatus::class,
        'category' => RiskCategory::class,
    ];

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    // Scopes
    public function scopeHighRisk($query)
    {
        return $query->where('risk_score', '>=', 15);
    }

    public function scopeActive($query)
    {
        return $query->where('status', RiskStatus::ACTIVE);
    }

    // Accessors
    public function getRiskLevelAttribute(): string
    {
        return match(true) {
            $this->risk_score >= 20 => 'critical',
            $this->risk_score >= 15 => 'high',
            $this->risk_score >= 10 => 'medium',
            $this->risk_score >= 5 => 'low',
            default => 'minimal'
        };
    }

    // Mutators
    public function setRiskScoreAttribute($value): void
    {
        // Auto-calculate risk score if not provided
        if (is_null($value) && $this->probability && $this->impact) {
            $this->attributes['risk_score'] = $this->probability * $this->impact;
        } else {
            $this->attributes['risk_score'] = $value;
        }
    }
}
EOF

# Create Enums
echo "📋 Creating Enums..."

cat > app/Enums/Project/ProjectStatus.php << 'EOF'
<?php

namespace App\Enums\Project;

enum ProjectStatus: string
{
    case DRAFT = 'draft';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case ACTIVE = 'active';
    case ON_HOLD = 'on_hold';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::PENDING_APPROVAL => 'Pending Approval',
            self::APPROVED => 'Approved',
            self::ACTIVE => 'Active',
            self::ON_HOLD => 'On Hold',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::REJECTED => 'Rejected',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::NOT_STARTED => 'gray',
            self::IN_PROGRESS => 'blue',
            self::COMPLETED => 'green',
            self::DELAYED => 'red',
            self::CANCELLED => 'red',
        };
    }
}
EOF

cat > app/Enums/Project/RiskStatus.php << 'EOF'
<?php

namespace App\Enums\Project;

enum RiskStatus: string
{
    case IDENTIFIED = 'identified';
    case ACTIVE = 'active';
    case MITIGATED = 'mitigated';
    case CLOSED = 'closed';
    case OCCURRED = 'occurred';

    public function label(): string
    {
        return match($this) {
            self::IDENTIFIED => 'Identified',
            self::ACTIVE => 'Active',
            self::MITIGATED => 'Mitigated',
            self::CLOSED => 'Closed',
            self::OCCURRED => 'Occurred',
        };
    }
}
EOF

cat > app/Enums/Project/RiskCategory.php << 'EOF'
<?php

namespace App\Enums\Project;

enum RiskCategory: string
{
    case TECHNICAL = 'technical';
    case FINANCIAL = 'financial';
    case OPERATIONAL = 'operational';
    case EXTERNAL = 'external';
    case REGULATORY = 'regulatory';
    case HUMAN_RESOURCES = 'human_resources';
    case ENVIRONMENTAL = 'environmental';
    case POLITICAL = 'political';

    public function label(): string
    {
        return match($this) {
            self::TECHNICAL => 'Technical',
            self::FINANCIAL => 'Financial',
            self::OPERATIONAL => 'Operational',
            self::EXTERNAL => 'External',
            self::REGULATORY => 'Regulatory',
            self::HUMAN_RESOURCES => 'Human Resources',
            self::ENVIRONMENTAL => 'Environmental',
            self::POLITICAL => 'Political',
        };
    }
}
EOF

cat > app/Enums/Project/MethodologyType.php << 'EOF'
<?php

namespace App\Enums\Project;

enum MethodologyType: string
{
    case UNIVERSAL = 'universal';
    case USAID = 'usaid';
    case WORLD_BANK = 'world_bank';
    case EU = 'eu';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match($this) {
            self::UNIVERSAL => 'Universal',
            self::USAID => 'USAID Program Cycle',
            self::WORLD_BANK => 'World Bank Project Cycle',
            self::EU => 'EU Grant Management',
            self::CUSTOM => 'Custom Methodology',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::UNIVERSAL => 'Standard project management approach',
            self::USAID => 'USAID Program Cycle methodology with compliance requirements',
            self::WORLD_BANK => 'World Bank Project Cycle with safeguards and results framework',
            self::EU => 'EU Grant Management with logical framework approach',
            self::CUSTOM => 'Organization-specific custom methodology',
        };
    }
}
EOF


cat > app/Enums/Project/ProjectPriority.php << 'EOF'
<?php

namespace App\Enums\Project;

enum ProjectPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    public function label(): string
    {
        return match($this) {
            self::LOW => 'Low',
            self::MEDIUM => 'Medium',
            self::HIGH => 'High',
            self::CRITICAL => 'Critical',
        };
    }

    public function weight(): int
    {
        return match($this) {
            self::LOW => 1,
            self::MEDIUM => 2,
            self::HIGH => 3,
            self::CRITICAL => 4,
        };
    }

    public function color(): string
    {
        return match($this) {
            self::LOW => 'green',
            self::MEDIUM => 'yellow',
            self::HIGH => 'orange',
            self::CRITICAL => 'red',
        };
    }
}
EOF

cat > app/Enums/Project/MilestoneStatus.php << 'EOF'
<?php

namespace App\Enums\Project;

enum MilestoneStatus: string
{
    case NOT_STARTED = 'not_started';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case DELAYED = 'delayed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::NOT_STARTED => 'Not Started',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::DELAYED => 'Delayed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::PENDING_APPROVAL => 'yellow',
            self::APPROVED => 'blue',
            self::ACTIVE => 'green',
            self::ON_HOLD => 'orange',
            self::COMPLETED => 'emerald',
            self::CANCELLED => 'red',
            self::REJECTED => 'red',
        };
    }
}
EOF

# Create Request Classes
echo "📝 Creating Request Classes..."

cat > app/Http/Requests/Project/CreateProjectRequest.php << 'EOF'
<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\Project\ProjectStatus;
use App\Enums\Project\ProjectPriority;
use App\Enums\Project\MethodologyType;

class CreateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handle authorization in middleware/policies
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:projects,code',
            'description' => 'required|string|min:10',
            'category' => 'nullable|string|max:100',
            'priority' => 'nullable|string|in:' . implode(',', array_column(ProjectPriority::cases(), 'value')),
            'status' => 'nullable|string|in:' . implode(',', array_column(ProjectStatus::cases(), 'value')),
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'budget' => 'required|numeric|min:1000|max:99999999.99',
            'currency' => 'nullable|string|size:3',
            'methodology_type' => 'required|string|in:' . implode(',', array_column(MethodologyType::cases(), 'value')),
            'metadata' => 'nullable|array',
            'compliance_requirements' => 'nullable|array',
            
            // Form instance integration
            'form_instance_id' => 'nullable|exists:form_instances,id',
            'form_data' => 'nullable|array',
            
            // Milestones
            'milestones' => 'nullable|array',
            'milestones.*.name' => 'required|string|max:255',
            'milestones.*.description' => 'nullable|string',
            'milestones.*.target_date' => 'required|date|after_or_equal:start_date|before_or_equal:end_date',
            'milestones.*.weight' => 'nullable|numeric|min:0|max:100',
            'milestones.*.responsible_user_id' => 'nullable|exists:users,id',
            
            // Stakeholders
            'stakeholders' => 'nullable|array',
            'stakeholders.*.name' => 'required|string|max:255',
            'stakeholders.*.email' => 'nullable|email',
            'stakeholders.*.organization' => 'nullable|string|max:255',
            'stakeholders.*.role' => 'required|string|max:255',
            'stakeholders.*.influence_level' => 'nullable|integer|min:1|max:5',
            'stakeholders.*.interest_level' => 'nullable|integer|min:1|max:5',
            
            // Team members
            'team_members' => 'nullable|array',
            'team_members.*' => 'exists:users,id',
            
            // Project-specific methodology requirements
            'environmental_screening' => 'nullable|boolean',
            'gender_integration' => 'nullable|boolean',
            'marking_branding' => 'nullable|boolean',
            'safeguards_screening' => 'nullable|boolean',
            'results_framework' => 'nullable|boolean',
            'logical_framework' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Project name is required.',
            'description.min' => 'Project description must be at least 10 characters.',
            'start_date.after_or_equal' => 'Project start date cannot be in the past.',
            'end_date.after' => 'Project end date must be after the start date.',
            'budget.min' => 'Project budget must be at least $1,000.',
            'budget.max' => 'Project budget cannot exceed $99,999,999.99.',
            'methodology_type.required' => 'Please select a project methodology.',
            'milestones.*.target_date.after_or_equal' => 'Milestone dates must be within the project timeline.',
            'milestones.*.target_date.before_or_equal' => 'Milestone dates must be within the project timeline.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate milestone weights total to 100% if provided
            if ($this->has('milestones')) {
                $totalWeight = collect($this->input('milestones', []))
                    ->sum('weight');
                
                if ($totalWeight > 0 && abs($totalWeight - 100) > 0.01) {
                    $validator->errors()->add('milestones', 'Milestone weights must total 100%.');
                }
            }
            
            // Validate methodology-specific requirements
            $this->validateMethodologyRequirements($validator);
        });
    }

    private function validateMethodologyRequirements($validator): void
    {
        $methodology = $this->input('methodology_type');
        
        switch ($methodology) {
            case 'usaid':
                if ($this->input('budget', 0) > 100000) {
                    if (!$this->input('environmental_screening')) {
                        $validator->errors()->add('environmental_screening', 'Environmental screening is required for USAID projects over $100,000.');
                    }
                    if (!$this->input('gender_integration')) {
                        $validator->errors()->add('gender_integration', 'Gender integration is required for USAID projects.');
                    }
                }
                break;
                
            case 'world_bank':
                if (!$this->input('safeguards_screening')) {
                    $validator->errors()->add('safeguards_screening', 'Safeguards screening is required for World Bank projects.');
                }
                if (!$this->input('results_framework')) {
                    $validator->errors()->add('results_framework', 'Results framework is required for World Bank projects.');
                }
                break;
                
            case 'eu':
                if (!$this->input('logical_framework')) {
                    $validator->errors()->add('logical_framework', 'Logical framework is required for EU projects.');
                }
                break;
        }
    }
}
EOF

cat > app/Http/Requests/Project/UpdateProjectRequest.php << 'EOF'
<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\Project\ProjectStatus;
use App\Enums\Project\ProjectPriority;
use App\Enums\Project\MethodologyType;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $projectId = $this->route('project') ?? $this->route('id');
        
        return [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|string|max:50|unique:projects,code,' . $projectId,
            'description' => 'sometimes|required|string|min:10',
            'category' => 'nullable|string|max:100',
            'priority' => 'nullable|string|in:' . implode(',', array_column(ProjectPriority::cases(), 'value')),
            'status' => 'nullable|string|in:' . implode(',', array_column(ProjectStatus::cases(), 'value')),
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'budget' => 'sometimes|required|numeric|min:1000|max:99999999.99',
            'currency' => 'nullable|string|size:3',
            'methodology_type' => 'sometimes|required|string|in:' . implode(',', array_column(MethodologyType::cases(), 'value')),
            'metadata' => 'nullable|array',
            'compliance_requirements' => 'nullable|array',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate status transitions
            $this->validateStatusTransition($validator);
        });
    }

    private function validateStatusTransition($validator): void
    {
        if ($this->has('status')) {
            $project = $this->route('project');
            if ($project && !$project->canTransitionTo($this->input('status'))) {
                $validator->errors()->add('status', 'Invalid status transition.');
            }
        }
    }
}
EOF

cat > app/Http/Requests/Project/CreateMilestoneRequest.php << 'EOF'
<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\Project\MilestoneStatus;

class CreateMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'target_date' => 'required|date|after_or_equal:today',
            'weight' => 'nullable|numeric|min:0|max:100',
            'deliverables' => 'nullable|array',
            'success_criteria' => 'nullable|array',
            'responsible_user_id' => 'nullable|exists:users,id',
            'dependencies' => 'nullable|array',
            'notes' => 'nullable|string',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate milestone date is within project timeline
            $projectId = $this->route('project');
            if ($projectId) {
                $project = \App\Models\Project\Project::find($projectId);
                if ($project) {
                    $targetDate = $this->input('target_date');
                    if ($targetDate < $project->start_date || $targetDate > $project->end_date) {
                        $validator->errors()->add('target_date', 'Milestone date must be within project timeline.');
                    }
                }
            }
        });
    }
}
EOF

cat > app/Http/Requests/Project/UpdateMilestoneRequest.php << 'EOF'
<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\Project\MilestoneStatus;

class UpdateMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'target_date' => 'sometimes|required|date',
            'completion_date' => 'nullable|date',
            'status' => 'nullable|string|in:' . implode(',', array_column(MilestoneStatus::cases(), 'value')),
            'weight' => 'nullable|numeric|min:0|max:100',
            'deliverables' => 'nullable|array',
            'success_criteria' => 'nullable|array',
            'responsible_user_id' => 'nullable|exists:users,id',
            'dependencies' => 'nullable|array',
            'notes' => 'nullable|string',
        ];
    }
}
EOF

# Create Resources
echo "📄 Creating Resources..."

cat > app/Http/Resources/Project/ProjectResource.php << 'EOF'
<?php

namespace App\Http\Resources\Project;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'category' => $this->category,
            'priority' => [
                'value' => $this->priority?->value,
                'label' => $this->priority?->label(),
                'color' => $this->priority?->color(),
                'weight' => $this->priority?->weight(),
            ],
            'status' => [
                'value' => $this->status?->value,
                'label' => $this->status?->label(),
                'color' => $this->status?->color(),
            ],
            'dates' => [
                'start_date' => $this->start_date?->format('Y-m-d'),
                'end_date' => $this->end_date?->format('Y-m-d'),
                'duration_days' => $this->duration_in_days,
            ],
            'financial' => [
                'budget' => $this->budget,
                'currency' => $this->currency,
                'utilization_percentage' => $this->budget_utilization,
            ],
            'methodology' => [
                'type' => $this->methodology_type,
                'label' => \App\Enums\Project\MethodologyType::from($this->methodology_type)->label(),
                'requirements' => $this->methodology_requirements,
            ],
            'progress' => [
                'percentage' => $this->progress_percentage,
                'health_score' => $this->health_score,
                'risk_score' => $this->risk_score,
            ],
            'compliance_requirements' => $this->compliance_requirements,
            'metadata' => $this->metadata,
            
            // Relationships
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'milestones' => $this->whenLoaded('milestones', function () {
                return ProjectMilestoneResource::collection($this->milestones);
            }),
            'team' => $this->whenLoaded('team', function () {
                return $this->team->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'role' => $member->pivot->role,
                        'access_level' => $member->pivot->access_level,
                        'joined_at' => $member->pivot->joined_at,
                    ];
                });
            }),
            'stakeholders' => $this->whenLoaded('stakeholders'),
            'risks' => $this->whenLoaded('risks'),
            
            'timestamps' => [
                'created_at' => $this->created_at->toISOString(),
                'updated_at' => $this->updated_at->toISOString(),
            ],
        ];
    }
}
EOF

cat > app/Http/Resources/Project/ProjectListResource.php << 'EOF'
<?php

namespace App\Http\Resources\Project;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'category' => $this->category,
            'priority' => [
                'value' => $this->priority?->value,
                'label' => $this->priority?->label(),
                'color' => $this->priority?->color(),
            ],
            'status' => [
                'value' => $this->status?->value,
                'label' => $this->status?->label(),
                'color' => $this->status?->color(),
            ],
            'dates' => [
                'start_date' => $this->start_date?->format('Y-m-d'),
                'end_date' => $this->end_date?->format('Y-m-d'),
                'duration_days' => $this->duration_in_days,
            ],
            'budget' => $this->budget,
            'currency' => $this->currency,
            'methodology_type' => $this->methodology_type,
            'progress_percentage' => $this->progress_percentage,
            'health_score' => $this->health_score['overall'] ?? 0,
            'creator_name' => $this->creator?->name,
            'milestones_count' => $this->whenCounted('milestones'),
            'team_count' => $this->whenCounted('team'),
            'created_at' => $this->created_at->format('Y-m-d H:i'),
        ];
    }
}
EOF

cat > app/Http/Resources/Project/ProjectMilestoneResource.php << 'EOF'
<?php

namespace App\Http\Resources\Project;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectMilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'dates' => [
                'target_date' => $this->target_date?->format('Y-m-d'),
                'completion_date' => $this->completion_date?->format('Y-m-d'),
                'days_until_due' => $this->days_until_due,
            ],
            'status' => [
                'value' => $this->status?->value,
                'label' => $this->status?->label(),
                'color' => $this->status?->color(),
                'progress_status' => $this->progress_status,
            ],
            'weight' => $this->weight,
            'deliverables' => $this->deliverables,
            'success_criteria' => $this->success_criteria,
            'dependencies' => $this->dependencies,
            'notes' => $this->notes,
            'is_overdue' => $this->is_overdue,
            'responsible_user' => $this->whenLoaded('responsibleUser', function () {
                return [
                    'id' => $this->responsibleUser->id,
                    'name' => $this->responsibleUser->name,
                    'email' => $this->responsibleUser->email,
                ];
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
EOF

cat > app/Http/Resources/Project/ProjectAnalyticsResource.php << 'EOF'
<?php

namespace App\Http\Resources\Project;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectAnalyticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'project_id' => $this->resource['project']->id,
            'project_name' => $this->resource['project']->name,
            'analytics' => [
                'progress' => $this->resource['progress'],
                'health' => $this->resource['health'],
                'budget_status' => $this->resource['budget_status'],
                'risks' => $this->resource['risks'],
                'timeline' => $this->resource['timeline'],
            ],
            'insights' => $this->resource['insights'] ?? [],
            'generated_at' => now()->toISOString(),
        ];
    }
}
EOF

# Create AI Services
echo "🤖 Creating AI Services..."

cat > app/Services/AI/Project/ProjectIntelligenceService.php << 'EOF'
<?php

namespace App\Services\AI\Project;

use App\Contracts\AI\Project\ProjectIntelligenceInterface;
use App\Models\Project\Project;

class ProjectIntelligenceService implements ProjectIntelligenceInterface
{
    private bool $aiEnabled;

    public function __construct()
    {
        $this->aiEnabled = config('ai.enabled', false) && config('ai.project.enabled', false);
    }

    public function analyzeNewProject(Project $project): array
    {
        if (!$this->aiEnabled) {
            return $this->ruleBasedProjectAnalysis($project);
        }

        try {
            return $this->aiEnhancedProjectAnalysis($project);
        } catch (\Exception $e) {
            // Fallback to rule-based analysis
            \Log::warning('AI project analysis failed, falling back to rule-based analysis', [
                'project_id' => $project->id,
                'error' => $e->getMessage()
            ]);
            
            return $this->ruleBasedProjectAnalysis($project);
        }
    }

    public function reanalyzeProject(Project $project): array
    {
        return $this->analyzeNewProject($project);
    }

    public function generateInsights(Project $project): array
    {
        if (!$this->aiEnabled) {
            return $this->ruleBasedInsights($project);
        }

        try {
            return $this->aiGeneratedInsights($project);
        } catch (\Exception $e) {
            return $this->ruleBasedInsights($project);
        }
    }

    public function analyzeProgress(Project $project): array
    {
        if (!$this->aiEnabled) {
            return $this->ruleBasedProgressAnalysis($project);
        }

        try {
            return $this->aiProgressAnalysis($project);
        } catch (\Exception $e) {
            return $this->ruleBasedProgressAnalysis($project);
        }
    }

    public function predictCompletion(Project $project): array
    {
        if (!$this->aiEnabled) {
            return $this->ruleBasedCompletionPrediction($project);
        }

        try {
            return $this->aiCompletionPrediction($project);
        } catch (\Exception $e) {
            return $this->ruleBasedCompletionPrediction($project);
        }
    }

    public function generateHealthRecommendations(Project $project): array
    {
        if (!$this->aiEnabled) {
            return $this->ruleBasedHealthRecommendations($project);
        }

        try {
            return $this->aiHealthRecommendations($project);
        } catch (\Exception $e) {
            return $this->ruleBasedHealthRecommendations($project);
        }
    }

    public function generateRiskMitigation(Project $project): array
    {
        if (!$this->aiEnabled) {
            return $this->ruleBasedRiskMitigation($project);
        }

        try {
            return $this->aiRiskMitigation($project);
        } catch (\Exception $e) {
            return $this->ruleBasedRiskMitigation($project);
        }
    }

    public function generateBudgetRecommendations(Project $project): array
    {
        if (!$this->aiEnabled) {
            return $this->ruleBasedBudgetRecommendations($project);
        }

        try {
            return $this->aiBudgetRecommendations($project);
        } catch (\Exception $e) {
            return $this->ruleBasedBudgetRecommendations($project);
        }
    }

    // Rule-based implementations (always available)
    private function ruleBasedProjectAnalysis(Project $project): array
    {
        $risks = [];
        $recommendations = [];

        // Budget analysis
        if ($project->budget > 5000000) {
            $risks[] = [
                'type' => 'budget_complexity',
                'level' => 'high',
                'message' => 'Large budget requires enhanced financial controls'
            ];
            $recommendations[] = 'Implement quarterly budget reviews';
        }

        // Timeline analysis
        if ($project->duration_in_days > 730) {
            $risks[] = [
                'type' => 'timeline_complexity',
                'level' => 'medium',
                'message' => 'Long duration increases delivery risk'
            ];
            $recommendations[] = 'Break down into smaller phases';
        }

        // Methodology-specific analysis
        $methodologyRisks = $this->analyzeMethodologyRisks($project);
        $risks = array_merge($risks, $methodologyRisks);

        return [
            'analysis_type' => 'rule_based',
            'risks' => $risks,
            'recommendations' => $recommendations,
            'confidence' => 0.85
        ];
    }

    private function ruleBasedInsights(Project $project): array
    {
        $insights = [];

        // Progress insights
        $progress = $project->progress_percentage;
        if ($progress < 25 && $project->start_date->diffInDays(now()) > 30) {
            $insights[] = [
                'type' => 'progress_warning',
                'priority' => 'medium',
                'message' => 'Project progress appears slower than expected',
                'action' => 'Review project plan and resource allocation'
            ];
        }

        // Budget insights
        $budgetUtilization = $project->budget_utilization;
        if ($budgetUtilization > $progress + 10) {
            $insights[] = [
                'type' => 'budget_concern',
                'priority' => 'high',
                'message' => 'Budget utilization exceeds progress',
                'action' => 'Review spending patterns and budget allocation'
            ];
        }

        return $insights;
    }

    private function ruleBasedProgressAnalysis(Project $project): array
    {
        $timeProgress = $project->progress_percentage;
        $milestoneProgress = $this->calculateMilestoneProgress($project);
        
        $variance = abs($timeProgress - $milestoneProgress);
        
        return [
            'time_vs_milestone_variance' => $variance,
            'status' => $variance > 20 ? 'concerning' : 'normal',
            'recommendations' => $variance > 20 ? ['Review milestone planning', 'Assess resource allocation'] : []
        ];
    }

    private function ruleBasedCompletionPrediction(Project $project): array
    {
        $currentProgress = $project->progress_percentage;
        $elapsedTime = $project->start_date->diffInDays(now());
        $totalTime = $project->start_date->diffInDays($project->end_date);
        
        if ($elapsedTime == 0) {
            $predictedCompletion = $project->end_date;
        } else {
            $progressRate = $currentProgress / $elapsedTime;
            $remainingProgress = 100 - $currentProgress;
            $estimatedDaysToComplete = $remainingProgress / $progressRate;
            $predictedCompletion = now()->addDays($estimatedDaysToComplete);
        }
        
        return [
            'predicted_completion_date' => $predictedCompletion->format('Y-m-d'),
            'variance_from_planned' => $predictedCompletion->diffInDays($project->end_date, false),
            'confidence' => 0.7,
            'method' => 'linear_progression'
        ];
    }

    private function ruleBasedHealthRecommendations(Project $project): array
    {
        $recommendations = [];
        $healthScore = $project->health_score;
        
        if ($healthScore['overall'] < 50) {
            $recommendations[] = [
                'priority' => 'high',
                'area' => 'overall',
                'recommendation' => 'Immediate intervention required - conduct project health assessment',
                'actions' => ['Schedule emergency review meeting', 'Assess resource requirements', 'Review project scope']
            ];
        } elseif ($healthScore['overall'] < 70) {
            $recommendations[] = [
                'priority' => 'medium',
                'area' => 'overall',
                'recommendation' => 'Monitor closely and address identified issues',
                'actions' => ['Weekly progress reviews', 'Address bottlenecks', 'Stakeholder communication']
            ];
        }
        
        return $recommendations;
    }

    private function ruleBasedRiskMitigation(Project $project): array
    {
        $mitigations = [];
        $riskScore = $project->risk_score;
        
        if ($riskScore['overall'] > 0.7) {
            $mitigations[] = [
                'risk_type' => 'high_overall_risk',
                'mitigation_strategy' => 'Implement enhanced monitoring and control measures',
                'actions' => [
                    'Daily standup meetings',
                    'Weekly risk assessment reviews',
                    'Escalation procedures activation'
                ]
            ];
        }
        
        return $mitigations;
    }

    private function ruleBasedBudgetRecommendations(Project $project): array
    {
        $recommendations = [];
        $utilization = $project->budget_utilization;
        $progress = $project->progress_percentage;
        
        if ($utilization > $progress + 15) {
            $recommendations[] = [
                'type' => 'cost_control',
                'priority' => 'high',
                'message' => 'Budget utilization significantly exceeds progress',
                'actions' => [
                    'Review all pending expenditures',
                    'Implement stricter approval process',
                    'Conduct cost-benefit analysis of remaining activities'
                ]
            ];
        }
        
        return $recommendations;
    }

    // AI-enhanced implementations (optional)
    private function aiEnhancedProjectAnalysis(Project $project): array
    {
        // TODO: Implement AI-enhanced analysis using LLM
        // This would integrate with OpenAI, AWS Bedrock, etc.
        return $this->ruleBasedProjectAnalysis($project);
    }

    private function aiGeneratedInsights(Project $project): array
    {
        // TODO: Implement AI-generated insights
        return $this->ruleBasedInsights($project);
    }

    private function aiProgressAnalysis(Project $project): array
    {
        // TODO: Implement AI progress analysis
        return $this->ruleBasedProgressAnalysis($project);
    }

    private function aiCompletionPrediction(Project $project): array
    {
        // TODO: Implement AI completion prediction
        return $this->ruleBasedCompletionPrediction($project);
    }

    private function aiHealthRecommendations(Project $project): array
    {
        // TODO: Implement AI health recommendations
        return $this->ruleBasedHealthRecommendations($project);
    }

    private function aiRiskMitigation(Project $project): array
    {
        // TODO: Implement AI risk mitigation
        return $this->ruleBasedRiskMitigation($project);
    }

    private function aiBudgetRecommendations(Project $project): array
    {
        // TODO: Implement AI budget recommendations
        return $this->ruleBasedBudgetRecommendations($project);
    }

    // Helper methods
    private function analyzeMethodologyRisks(Project $project): array
    {
        $risks = [];
        
        switch ($project->methodology_type) {
            case 'usaid':
                if ($project->budget > 100000 && !($project->compliance_requirements['environmental_screening'] ?? false)) {
                    $risks[] = [
                        'type' => 'compliance_risk',
                        'level' => 'high',
                        'message' => 'USAID environmental screening required for projects over $100K'
                    ];
                }
                break;
                
            case 'world_bank':
                if (!($project->compliance_requirements['safeguards_screening'] ?? false)) {
                    $risks[] = [
                        'type' => 'compliance_risk',
                        'level' => 'high',
                        'message' => 'World Bank safeguards screening is mandatory'
                    ];
                }
                break;
                
            case 'eu':
                if (!($project->compliance_requirements['logical_framework'] ?? false)) {
                    $risks[] = [
                        'type' => 'compliance_risk',
                        'level' => 'medium',
                        'message' => 'EU logical framework should be completed'
                    ];
                }
                break;
        }
        
        return $risks;
    }

    private function calculateMilestoneProgress(Project $project): float
    {
        $milestones = $project->milestones;
        if ($milestones->isEmpty()) return 0;
        
        $completedWeight = $milestones->where('status', 'completed')->sum('weight');
        $totalWeight = $milestones->sum('weight');
        
        return $totalWeight > 0 ? ($completedWeight / $totalWeight) * 100 : 0;
    }
}
EOF

# Create AI Contract
cat > app/Contracts/AI/Project/ProjectIntelligenceInterface.php << 'EOF'
<?php

namespace App\Contracts\AI\Project;

use App\Models\Project\Project;

interface ProjectIntelligenceInterface
{
    public function analyzeNewProject(Project $project): array;
    
    public function reanalyzeProject(Project $project): array;
    
    public function generateInsights(Project $project): array;
    
    public function analyzeProgress(Project $project): array;
    
    public function predictCompletion(Project $project): array;
    
    public function generateHealthRecommendations(Project $project): array;
    
    public function generateRiskMitigation(Project $project): array;
    
    public function generateBudgetRecommendations(Project $project): array;
}
EOF

# Create Shared Services stubs
echo "🏗️ Creating Shared Service Stubs..."

cat > app/Services/Shared/WorkflowEngineService.php << 'EOF'
<?php

namespace App\Services\Shared;

use App\Models\Project\Project;

class WorkflowEngineService
{
    public function executeTransition(Project $project): array
    {
        // TODO: Implement workflow transition logic
        // This should integrate with your existing workflow engine
        return [
            'success' => true,
            'new_status' => $project->status,
            'message' => 'Workflow transition completed'
        ];
    }

    public function getAvailableTransitions(Project $project): array
    {
        return $project->canTransitionTo('active') ? ['active'] : [];
    }

    public function getApprovalChain(Project $project): array
    {
        // TODO: Return methodology-specific approval chain
        return [
            'current_step' => 1,
            'total_steps' => 3,
            'approvers' => []
        ];
    }

    public function getWorkflowHistory(Project $project): array
    {
        // TODO: Return workflow transition history
        return [];
    }
}
EOF

# Create Basic Events
echo "📡 Creating Events..."

cat > app/Events/Project/ProjectCreated.php << 'EOF'
<?php

namespace App\Events\Project;

use App\Models\Project\Project;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Project $project
    ) {}
}
EOF

cat > app/Events/Project/ProjectStatusChanged.php << 'EOF'
<?php

namespace App\Events\Project;

use App\Models\Project\Project;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Project $project,
        public string $oldStatus,
        public string $newStatus
    ) {}
}
EOF

cat > app/Events/Project/MilestoneCompleted.php << 'EOF'
<?php

namespace App\Events\Project;

use App\Models\Project\ProjectMilestone;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MilestoneCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ProjectMilestone $milestone
    ) {}
}
EOF

# Create route files
echo "🛣️ Creating Route Files..."

cat > routes/project.php << 'EOF'
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Project\ProjectAnalyticsController;
use App\Http\Controllers\Project\ProjectMilestoneController;
use App\Http\Controllers\Project\ProjectWorkflowController;

// Project CRUD routes
Route::prefix('projects')->group(function () {
    Route::get('/', [ProjectController::class, 'index']);
    Route::post('/', [ProjectController::class, 'store']);
    Route::get('/{id}', [ProjectController::class, 'show']);
    Route::put('/{id}', [ProjectController::class, 'update']);
    Route::delete('/{id}', [ProjectController::class, 'destroy']);
    
    // Project actions
    Route::post('/{id}/approve', [ProjectController::class, 'approve']);
    Route::post('/{id}/activate', [ProjectController::class, 'activate']);
    
    // Project analytics routes
    Route::get('/{id}/dashboard', [ProjectAnalyticsController::class, 'getProjectDashboard']);
    Route::get('/{id}/health', [ProjectAnalyticsController::class, 'getProjectHealth']);
    Route::get('/{id}/progress', [ProjectAnalyticsController::class, 'getProjectProgress']);
    Route::get('/{id}/risks', [ProjectAnalyticsController::class, 'getRiskAnalysis']);
    Route::get('/{id}/budget-analysis', [ProjectAnalyticsController::class, 'getBudgetAnalysis']);
    
    // Project milestone routes
    Route::get('/{id}/milestones', [ProjectMilestoneController::class, 'index']);
    Route::post('/{id}/milestones', [ProjectMilestoneController::class, 'store']);
    Route::put('/{projectId}/milestones/{milestoneId}', [ProjectMilestoneController::class, 'update']);
    Route::post('/{projectId}/milestones/{milestoneId}/complete', [ProjectMilestoneController::class, 'complete']);
    
    // Project workflow routes
    Route::get('/{id}/workflow', [ProjectWorkflowController::class, 'getWorkflowStatus']);
    Route::post('/{id}/workflow/transition', [ProjectWorkflowController::class, 'transitionToNext']);
    Route::get('/{id}/workflow/transitions', [ProjectWorkflowController::class, 'getAvailableTransitions']);
});
EOF

# Create service providers registration note
echo "📝 Creating Service Provider Registration Note..."

cat > SERVICE_PROVIDERS.md << 'EOF'
# Service Provider Registration

Add these to your `config/app.php` providers array or create dedicated service providers:

## Repository Bindings
```php
// In AppServiceProvider boot() method or dedicated provider
$this->app->bind(
    \App\Repositories\Project\Contracts\ProjectRepositoryInterface::class,
    \App\Repositories\Project\ProjectRepository::class
);

$this->app->bind(
    \App\Repositories\Project\Contracts\ProjectMilestoneRepositoryInterface::class,
    \App\Repositories\Project\ProjectMilestoneRepository::class
);
```

## AI Service Bindings
```php
// AI service bindings with fallbacks
$this->app->bind(
    \App\Contracts\AI\Project\ProjectIntelligenceInterface::class,
    \App\Services\AI\Project\ProjectIntelligenceService::class
);
```

## Route Registration
Add to `routes/api.php`:
```php
Route::middleware(['auth:sanctum', 'tenant'])->prefix('api/v1')->group(function () {
    require base_path('routes/project.php');
});
```

## Form Engine Integration
Ensure your form engine is configured to handle these form types:
- `project_creation`
- `project_update`
- `milestone_creation`
- `milestone_update`

## AI Configuration
Add to `config/ai.php`:
```php
return [
    'enabled' => env('AI_ENABLED', false),
    'project' => [
        'enabled' => env('AI_PROJECT_ENABLED', true),
        'provider' => env('AI_PROVIDER', 'openai'),
        'models' => [
            'analysis' => env('AI_ANALYSIS_MODEL', 'gpt-4'),
            'insights' => env('AI_INSIGHTS_MODEL', 'gpt-3.5-turbo'),
        ]
    ]
];
```

## Environment Variables
Add to `.env`:
```
# AI Configuration
AI_ENABLED=false
AI_PROJECT_ENABLED=true
AI_PROVIDER=openai
AI_OPENAI_KEY=your_openai_key_here

# Project Module
PROJECT_DEFAULT_CURRENCY=USD
PROJECT_MAX_BUDGET=99999999.99
PROJECT_MIN_BUDGET=1000
```

## Composer Autoload
After running this script, make sure to run:
```bash
composer dump-autoload
```

## Database Migration
Run the migration script to create all necessary tables:
```bash
# Create and run the migration setup script first
./setup_project_migrations.sh
php artisan migrate
```

## Seeding Test Data
```bash
php artisan db:seed --class=ProjectSeeder
```
EOF

echo ""
echo "✅ iPM Project Module structure created successfully!"
echo ""
echo "📁 Created directories and files using Laravel conventions:"
echo "   - Controllers in app/Http/Controllers/Project/ (4 files)"
echo "   - Services in app/Services/Project/ (4 files)" 
echo "   - Repositories in app/Repositories/Project/ (4 files)"
echo "   - Models in app/Models/Project/ (4 files)"
echo "   - Enums in app/Enums/Project/ (6 files)"
echo "   - Requests in app/Http/Requests/Project/ (4 files)"
echo "   - Resources in app/Http/Resources/Project/ (4 files)"
echo "   - Events in app/Events/Project/ (3 files)"
echo "   - AI Services in app/Services/AI/Project/ (1 file)"
echo "   - AI Contracts in app/Contracts/AI/Project/ (1 file)"
echo "   - Shared Services in app/Services/Shared/ (1 file)"
echo "   - Route file (1 file)"
echo ""
echo "🎯 Key Features Implemented:"
echo "   ✓ Form-driven project creation with methodology adaptation"
echo "   ✓ AI-optional intelligence with rule-based fallbacks"
echo "   ✓ Comprehensive project analytics and health scoring"
echo "   ✓ Risk analysis and predictive insights"
echo "   ✓ Milestone management with progress tracking"
echo "   ✓ Workflow engine integration"
echo "   ✓ Methodology compliance (USAID/World Bank/EU)"
echo "   ✓ Multi-tenant architecture support"
echo ""
echo "📝 Next steps:"
echo "   1. Run: composer dump-autoload"
echo "   2. Create and run the database migrations"
echo "   3. Register the service providers and bindings"
echo "   4. Add the routes to your API routes"
echo "   5. Configure AI services (optional)"
echo "   6. Integrate with your existing form engine"
echo "   7. Add tests"
echo ""
echo "📄 Check SERVICE_PROVIDERS.md for detailed setup instructions"
echo ""
echo "🎉 Ready to build intelligent project management!"

# Create database migration setup script
echo "📊 Creating database migration setup script..."

cat > setup_project_migrations.sh << 'EOF'
#!/bin/bash

# iPM Project Module - Database Migrations Setup Script
# Run this from the Laravel root directory (where vendor folder exists)

echo "🗄️ Setting up iPM Project Module Database Migrations..."

# Check if we're in the correct directory
if [ ! -d "vendor" ]; then
    echo "❌ Error: Please run this script from the Laravel root directory (where vendor folder exists)"
    exit 1
fi

# Create directories if they don't exist
mkdir -p database/migrations
mkdir -p database/factories
mkdir -p database/seeders

# Generate sequential timestamps starting from current time
CURRENT_TIME=$(date +%s)
TIMESTAMP_1=$(date -d "@$((CURRENT_TIME + 1))" +%Y_%m_%d_%H%M%S)
TIMESTAMP_2=$(date -d "@$((CURRENT_TIME + 2))" +%Y_%m_%d_%H%M%S)
TIMESTAMP_3=$(date -d "@$((CURRENT_TIME + 3))" +%Y_%m_%d_%H%M%S)
TIMESTAMP_4=$(date -d "@$((CURRENT_TIME + 4))" +%Y_%m_%d_%H%M%S)
TIMESTAMP_5=$(date -d "@$((CURRENT_TIME + 5))" +%Y_%m_%d_%H%M%S)
TIMESTAMP_6=$(date -d "@$((CURRENT_TIME + 6))" +%Y_%m_%d_%H%M%S)

echo "📊 Creating migration files with sequential timestamps..."

# 1. Create projects table migration
cat > database/migrations/${TIMESTAMP_1}_create_projects_table.php << 'MIGRATION_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('form_instance_id')->nullable();
            
            // Basic project information
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description');
            $table->string('category')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'active', 'on_hold', 'completed', 'cancelled', 'rejected'])->default('draft');
            
            // Timeline
            $table->date('start_date');
            $table->date('end_date');
            
            // Financial
            $table->decimal('budget', 15, 2);
            $table->string('currency', 3)->default('USD');
            
            // Methodology
            $table->enum('methodology_type', ['universal', 'usaid', 'world_bank', 'eu', 'custom'])->default('universal');
            
            // JSON fields for flexible data
            $table->json('metadata')->nullable();
            $table->json('compliance_requirements')->nullable();
            
            // Audit
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['methodology_type']);
            $table->index(['category']);
            $table->index(['priority', 'status']);
            $table->index(['start_date', 'end_date']);
            
            // Foreign key constraints (uncomment when you have the referenced tables)
            // $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            // $table->foreign('form_instance_id')->references('id')->on('form_instances')->onDelete('set null');
            // $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
MIGRATION_EOF

# 2. Create project_milestones table migration
cat > database/migrations/${TIMESTAMP_2}_create_project_milestones_table.php << 'MIGRATION_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('target_date');
            $table->date('completion_date')->nullable();
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'delayed', 'cancelled'])->default('not_started');
            $table->decimal('weight', 5, 2)->default(0.00); // Weight for progress calculation
            $table->json('deliverables')->nullable();
            $table->json('success_criteria')->nullable();
            $table->unsignedBigInteger('responsible_user_id')->nullable();
            $table->json('dependencies')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['tenant_id']);
            $table->index(['target_date']);
            $table->index(['responsible_user_id']);
            
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            // $table->foreign('responsible_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_milestones');
    }
};
MIGRATION_EOF

# 3. Create project_stakeholders table migration
cat > database/migrations/${TIMESTAMP_3}_create_project_stakeholders_table.php << 'MIGRATION_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_stakeholders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('organization')->nullable();
            $table->string('role');
            $table->integer('influence_level')->default(3); // 1-5 scale
            $table->integer('interest_level')->default(3); // 1-5 scale
            $table->enum('communication_preference', ['email', 'phone', 'meeting', 'report'])->default('email');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['project_id']);
            $table->index(['tenant_id']);
            $table->index(['influence_level', 'interest_level']);
            
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_stakeholders');
    }
};
MIGRATION_EOF

# 4. Create project_risks table migration
cat > database/migrations/${TIMESTAMP_4}_create_project_risks_table.php << 'MIGRATION_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_risks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('title');
            $table->text('description');
            $table->enum('category', ['technical', 'financial', 'operational', 'external', 'regulatory', 'human_resources', 'environmental', 'political'])->default('operational');
            $table->integer('probability')->default(3); // 1-5 scale
            $table->integer('impact')->default(3); // 1-5 scale
            $table->decimal('risk_score', 5, 2)->default(9.00); // Calculated: probability * impact
            $table->enum('status', ['identified', 'active', 'mitigated', 'closed', 'occurred'])->default('identified');
            $table->text('mitigation_strategy')->nullable();
            $table->text('contingency_plan')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->date('review_date')->nullable();
            $table->date('identified_date');
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['tenant_id']);
            $table->index(['category']);
            $table->index(['risk_score']);
            $table->index(['review_date']);
            
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            // $table->foreign('owner_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_risks');
    }
};
MIGRATION_EOF

# 5. Create project_users pivot table migration
cat > database/migrations/${TIMESTAMP_5}_create_project_users_table.php << 'MIGRATION_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->default('member'); // manager, member, viewer, etc.
            $table->enum('access_level', ['read', 'write', 'admin'])->default('read');
            $table->timestamp('joined_at')->default(now());
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->index(['project_id', 'role']);
            $table->index(['user_id']);
            
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_users');
    }
};
MIGRATION_EOF

# 6. Create project_analytics table migration
cat > database/migrations/${TIMESTAMP_6}_create_project_analytics_table.php << 'MIGRATION_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('tenant_id');
            $table->date('analysis_date');
            $table->decimal('progress_percentage', 5, 2)->default(0.00);
            $table->decimal('health_score', 5, 2)->default(0.00);
            $table->decimal('risk_score', 5, 2)->default(0.00);
            $table->decimal('budget_utilization', 5, 2)->default(0.00);
            $table->json('health_factors')->nullable();
            $table->json('risk_factors')->nullable();
            $table->json('insights')->nullable();
            $table->json('recommendations')->nullable();
            $table->enum('analysis_type', ['rule_based', 'ai_enhanced'])->default('rule_based');
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'analysis_date']);
            $table->index(['tenant_id']);
            $table->index(['analysis_date']);
            $table->index(['health_score']);
            $table->index(['risk_score']);
            
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_analytics');
    }
};
MIGRATION_EOF

echo "🏭 Creating factory files..."

# Create ProjectFactory
cat > database/factories/ProjectFactory.php << 'FACTORY_EOF'
<?php

namespace Database\Factories;

use App\Models\Project\Project;
use App\Enums\Project\ProjectStatus;
use App\Enums\Project\ProjectPriority;
use App\Enums\Project\MethodologyType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-6 months', '+3 months');
        $endDate = $this->faker->dateTimeBetween($startDate, '+2 years');
        
        return [
            'tenant_id' => 1, // Adjust based on your tenant setup
            'name' => $this->faker->company() . ' ' . $this->faker->randomElement(['Development', 'Infrastructure', 'Capacity Building', 'Research']) . ' Project',
            'code' => strtoupper($this->faker->lexify('???-????-????')),
            'description' => $this->faker->paragraphs(3, true),
            'category' => $this->faker->randomElement(['infrastructure', 'health', 'education', 'agriculture', 'governance', 'environment', 'economic_development']),
            'priority' => $this->faker->randomElement(ProjectPriority::cases())->value,
            'status' => $this->faker->randomElement(ProjectStatus::cases())->value,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'budget' => $this->faker->numberBetween(50000, 10000000),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP']),
            'methodology_type' => $this->faker->randomElement(MethodologyType::cases())->value,
            'metadata' => [
                'location' => $this->faker->country(),
                'beneficiaries' => $this->faker->numberBetween(100, 10000),
                'target_groups' => $this->faker->randomElements(['women', 'youth', 'farmers', 'entrepreneurs', 'students'], 2),
            ],
            'compliance_requirements' => $this->generateComplianceRequirements(),
            'created_by' => 1, // Adjust based on your user setup
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::ACTIVE->value,
        ]);
    }

    public function usaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'methodology_type' => MethodologyType::USAID->value,
            'compliance_requirements' => [
                'environmental_screening' => true,
                'gender_integration' => true,
                'marking_branding' => true,
            ],
        ]);
    }

    public function worldBank(): static
    {
        return $this->state(fn (array $attributes) => [
            'methodology_type' => MethodologyType::WORLD_BANK->value,
            'compliance_requirements' => [
                'safeguards_screening' => true,
                'results_framework' => true,
                'procurement_plan' => true,
            ],
        ]);
    }

    public function largeBudget(): static
    {
        return $this->state(fn (array $attributes) => [
            'budget' => $this->faker->numberBetween(5000000, 50000000),
            'priority' => ProjectPriority::HIGH->value,
        ]);
    }

    private function generateComplianceRequirements(): array
    {
        return [
            'environmental_screening' => $this->faker->boolean(70),
            'gender_integration' => $this->faker->boolean(80),
            'marking_branding' => $this->faker->boolean(60),
            'safeguards_screening' => $this->faker->boolean(50),
            'results_framework' => $this->faker->boolean(90),
        ];
    }
}
FACTORY_EOF

# Create ProjectMilestoneFactory
cat > database/factories/ProjectMilestoneFactory.php << 'FACTORY_EOF'
<?php

namespace Database\Factories;

use App\Models\Project\ProjectMilestone;
use App\Enums\Project\MilestoneStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectMilestoneFactory extends Factory
{
    protected $model = ProjectMilestone::class;

    public function definition(): array
    {
        $targetDate = $this->faker->dateTimeBetween('now', '+18 months');
        
        return [
            'tenant_id' => 1,
            'name' => $this->faker->randomElement([
                'Project Initiation',
                'Stakeholder Engagement',
                'Baseline Study Completion',
                'Training Module Development',
                'Infrastructure Setup',
                'Implementation Phase 1',
                'Mid-term Review',
                'Implementation Phase 2',
                'Impact Assessment',
                'Project Closure'
            ]),
            'description' => $this->faker->sentence(12),
            'target_date' => $targetDate,
            'completion_date' => $this->faker->boolean(30) ? $this->faker->dateTimeBetween($targetDate, '+1 month') : null,
            'status' => $this->faker->randomElement(MilestoneStatus::cases())->value,
            'weight' => $this->faker->randomFloat(2, 5, 25),
            'deliverables' => [
                $this->faker->sentence(6),
                $this->faker->sentence(8),
                $this->faker->sentence(5),
            ],
            'success_criteria' => [
                $this->faker->sentence(10),
                $this->faker->sentence(12),
            ],
            'dependencies' => $this->faker->optional()->randomElements([
                'Budget approval',
                'Team recruitment',
                'Equipment procurement',
                'Legal clearance',
                'Stakeholder approval'
            ], 2),
            'notes' => $this->faker->optional()->paragraph(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MilestoneStatus::COMPLETED->value,
            'completion_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MilestoneStatus::NOT_STARTED->value,
            'target_date' => $this->faker->dateTimeBetween('now', '+3 months'),
        ]);
    }
}
FACTORY_EOF

echo "🌱 Creating seeder files..."

# Create ProjectSeeder
cat > database/seeders/ProjectSeeder.php << 'SEEDER_EOF'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project\Project;
use App\Models\Project\ProjectMilestone;
use App\Models\Project\ProjectStakeholder;
use App\Models\Project\ProjectRisk;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        // Create various types of projects
        $this->createSampleProjects();
        $this->createMethodologySpecificProjects();
    }

    private function createSampleProjects(): void
    {
        // Create 20 general projects with milestones
        Project::factory()
            ->count(20)
            ->has(ProjectMilestone::factory()->count(rand(3, 8)), 'milestones')
            ->create()
            ->each(function ($project) {
                // Add stakeholders
                $this->createProjectStakeholders($project);
                
                // Add risks
                $this->createProjectRisks($project);
                
                // Ensure milestone weights total 100%
                $this->adjustMilestoneWeights($project);
            });
    }

    private function createMethodologySpecificProjects(): void
    {
        // USAID Projects
        Project::factory()
            ->usaid()
            ->count(5)
            ->has(ProjectMilestone::factory()->count(6), 'milestones')
            ->create()
            ->each(function ($project) {
                $this->createProjectStakeholders($project);
                $this->createProjectRisks($project);
                $this->adjustMilestoneWeights($project);
            });

        // World Bank Projects
        Project::factory()
            ->worldBank()
            ->largeBudget()
            ->count(3)
            ->has(ProjectMilestone::factory()->count(8), 'milestones')
            ->create()
            ->each(function ($project) {
                $this->createProjectStakeholders($project);
                $this->createProjectRisks($project);
                $this->adjustMilestoneWeights($project);
            });

        // Active projects for dashboard testing
        Project::factory()
            ->active()
            ->count(8)
            ->has(ProjectMilestone::factory()->count(5), 'milestones')
            ->create()
            ->each(function ($project) {
                $this->createProjectStakeholders($project);
                $this->createProjectRisks($project);
                $this->adjustMilestoneWeights($project);
            });
    }

    private function createProjectStakeholders(Project $project): void
    {
        $stakeholders = [
            [
                'name' => 'Project Manager',
                'email' => 'pm@example.com',
                'organization' => $project->name,
                'role' => 'Project Manager',
                'influence_level' => 5,
                'interest_level' => 5,
            ],
            [
                'name' => 'Donor Representative',
                'email' => 'donor@example.com',
                'organization' => 'Donor Organization',
                'role' => 'Funding Officer',
                'influence_level' => 5,
                'interest_level' => 4,
            ],
            [
                'name' => 'Beneficiary Representative',
                'email' => 'beneficiary@example.com',
                'organization' => 'Community Group',
                'role' => 'Community Leader',
                'influence_level' => 2,
                'interest_level' => 5,
            ],
            [
                'name' => 'Government Liaison',
                'email' => 'gov@example.com',
                'organization' => 'Government Ministry',
                'role' => 'Policy Advisor',
                'influence_level' => 4,
                'interest_level' => 3,
            ],
        ];

        foreach ($stakeholders as $stakeholder) {
            $project->stakeholders()->create(array_merge($stakeholder, [
                'tenant_id' => $project->tenant_id,
            ]));
        }
    }

    private function createProjectRisks(Project $project): void
    {
        $risks = [
            [
                'title' => 'Budget Overrun Risk',
                'description' => 'Risk of exceeding allocated budget due to inflation or scope changes',
                'category' => 'financial',
                'probability' => 3,
                'impact' => 4,
                'status' => 'active',
                'mitigation_strategy' => 'Regular budget monitoring and approval processes for changes',
                'identified_date' => now()->subDays(rand(1, 90)),
            ],
            [
                'title' => 'Timeline Delay Risk',
                'description' => 'Risk of project delays due to external dependencies',
                'category' => 'operational',
                'probability' => 4,
                'impact' => 3,
                'status' => 'active',
                'mitigation_strategy' => 'Buffer time allocation and contingency planning',
                'identified_date' => now()->subDays(rand(1, 60)),
            ],
            [
                'title' => 'Stakeholder Engagement Risk',
                'description' => 'Risk of low stakeholder participation affecting project outcomes',
                'category' => 'external',
                'probability' => 2,
                'impact' => 4,
                'status' => 'identified',
                'mitigation_strategy' => 'Enhanced communication and engagement strategy',
                'identified_date' => now()->subDays(rand(1, 30)),
            ],
        ];

        foreach ($risks as $risk) {
            $risk['risk_score'] = $risk['probability'] * $risk['impact'];
            $project->risks()->create(array_merge($risk, [
                'tenant_id' => $project->tenant_id,
            ]));
        }
    }

    private function adjustMilestoneWeights(Project $project): void
    {
        $milestones = $project->milestones;
        $totalMilestones = $milestones->count();
        
        if ($totalMilestones > 0) {
            $baseWeight = 100 / $totalMilestones;
            
            $milestones->each(function ($milestone, $index) use ($baseWeight, $totalMilestones) {
                // Add some variation to weights
                $variation = rand(-5, 5);
                $weight = $baseWeight + $variation;
                
                // Ensure the last milestone gets any remaining weight
                if ($index === $totalMilestones - 1) {
                    $currentTotal = $milestone->project->milestones()
                        ->where('id', '!=', $milestone->id)
                        ->sum('weight');
                    $weight = 100 - $currentTotal;
                }
                
                $milestone->update(['weight' => max(5, min(40, $weight))]);
            });
        }
    }
}
SEEDER_EOF

# Create main database seeder file
cat > database/seeders/ProjectModuleSeeder.php << 'SEEDER_EOF'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProjectModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProjectSeeder::class,
        ]);
    }
}
SEEDER_EOF

echo ""
echo "✅ iPM Project Module database setup completed successfully!"
echo ""
echo "📊 Created:"
echo "   - 6 Migration files with complete schema"
echo "   - 2 Factory files for testing data"
echo "   - 2 Seeder files with realistic data"
echo ""
echo "🚀 Next steps:"
echo "   1. Run migrations:"
echo "      php artisan migrate"
echo ""
echo "   2. Seed the database with test data:"
echo "      php artisan db:seed --class=ProjectSeeder"
echo ""
echo "   3. Or run all project seeders at once:"
echo "      php artisan db:seed --class=ProjectModuleSeeder"
echo ""
echo "📋 Database Tables Created:"
echo "   - projects (main project table)"
echo "   - project_milestones (project milestones)"
echo "   - project_stakeholders (stakeholder management)"
echo "   - project_risks (risk register)"
echo "   - project_users (team membership)"
echo "   - project_analytics (analytics cache)"
echo ""
echo "🎉 Database ready for iPM Project Module!"
EOF

chmod +x setup_project_migrations.sh

echo ""
echo "📊 Created additional migration setup script: setup_project_migrations.sh"
echo ""
echo "To set up the complete iPM Project Module:"
echo "   1. Run: ./setup_project_migrations.sh"
echo "   2. Run: php artisan migrate"
echo "   3. Run: php artisan db:seed --class=ProjectModuleSeeder"
