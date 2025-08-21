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
