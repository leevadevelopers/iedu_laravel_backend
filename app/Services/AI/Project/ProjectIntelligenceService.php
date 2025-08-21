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
