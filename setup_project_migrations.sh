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
