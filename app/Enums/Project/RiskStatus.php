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
