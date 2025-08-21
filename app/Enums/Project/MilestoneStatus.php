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
