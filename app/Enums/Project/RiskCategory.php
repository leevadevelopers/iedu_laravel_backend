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
