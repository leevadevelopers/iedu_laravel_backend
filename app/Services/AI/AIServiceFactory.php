<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Config;

class AIServiceFactory
{
    /**
     * Create the appropriate AI service based on configuration
     */
    public static function create(): AIServiceInterface
    {
        $provider = Config::get('ai.provider', 'null');

        return match($provider) {
            'openai' => app(\App\Services\AI\OpenAIService::class),
            'aws_bedrock' => app(\App\Services\AI\AWSBedrockService::class),
            'azure_openai' => app(\App\Services\AI\AzureOpenAIService::class),
            default => app(NullAIService::class)
        };
    }
} 