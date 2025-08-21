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
