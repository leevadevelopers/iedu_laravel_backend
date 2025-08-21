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
