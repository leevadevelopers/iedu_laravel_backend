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
