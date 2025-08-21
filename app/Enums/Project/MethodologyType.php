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
