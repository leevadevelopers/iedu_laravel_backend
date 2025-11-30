<?php

namespace App\Services\AI;

use App\Models\AI\AIConversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ClaudeService
{
    protected string $apiKey;
    protected string $apiUrl;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.claude.api_key', '');
        $this->apiUrl = config('services.claude.api_url', 'https://api.anthropic.com/v1/messages');
        $this->model = config('services.claude.model', 'claude-3-5-sonnet-20241022');
    }

    /**
     * Ask a question to Claude
     */
    public function askQuestion(
        string $question,
        ?string $subject = null,
        ?array $context = null,
        ?string $imageBase64 = null
    ): array {
        try {
            $systemPrompt = $this->buildSystemPrompt($subject, $context);
            $messages = $this->buildMessages($question, $imageBase64);

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post($this->apiUrl, [
                'model' => $this->model,
                'max_tokens' => 4096,
                'system' => $systemPrompt,
                'messages' => $messages,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['content'][0]['text'] ?? '';
                $usage = $data['usage'] ?? [];

                return [
                    'answer' => $content,
                    'explanation' => $this->extractExplanation($content),
                    'examples' => $this->extractExamples($content),
                    'practice_problems' => $this->extractPracticeProblems($content),
                    'tokens_used' => $usage['input_tokens'] + $usage['output_tokens'] ?? 0,
                    'cost' => $this->calculateCost($usage),
                ];
            }

            throw new \Exception('Claude API request failed: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('Claude API error', [
                'error' => $e->getMessage(),
                'question' => substr($question, 0, 100),
            ]);
            throw $e;
        }
    }

    /**
     * Build system prompt
     */
    protected function buildSystemPrompt(?string $subject, ?array $context): string
    {
        $prompt = "You are a helpful and patient tutor for students. ";

        if ($subject) {
            $prompt .= "The student is asking about {$subject}. ";
        }

        if ($context) {
            if (isset($context['grade_level'])) {
                $prompt .= "The student is in grade {$context['grade_level']}. ";
            }
            if (isset($context['topic'])) {
                $prompt .= "The topic is {$context['topic']}. ";
            }
        }

        $prompt .= "Provide clear explanations, examples, and practice problems when appropriate. "
            . "Use simple language appropriate for the student's level. "
            . "Be encouraging and supportive.";

        return $prompt;
    }

    /**
     * Build messages array
     */
    protected function buildMessages(string $question, ?string $imageBase64): array
    {
        $content = [
            [
                'type' => 'text',
                'text' => $question,
            ],
        ];

        if ($imageBase64) {
            $content[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => 'image/jpeg',
                    'data' => $imageBase64,
                ],
            ];
        }

        return [
            [
                'role' => 'user',
                'content' => $content,
            ],
        ];
    }

    /**
     * Extract explanation from answer
     */
    protected function extractExplanation(string $content): ?string
    {
        // Try to extract explanation section
        if (preg_match('/Explanation[:\s]*(.+?)(?:\n\n|$)/is', $content, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Extract examples from answer
     */
    protected function extractExamples(string $content): array
    {
        $examples = [];
        if (preg_match('/Examples?[:\s]*(.+?)(?:\n\n|Practice|$)/is', $content, $matches)) {
            $exampleText = trim($matches[1]);
            $lines = explode("\n", $exampleText);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && (str_starts_with($line, '-') || str_starts_with($line, '•') || preg_match('/^\d+\./', $line))) {
                    $examples[] = preg_replace('/^[-•\d.\s]+/', '', $line);
                }
            }
        }
        return array_filter($examples);
    }

    /**
     * Extract practice problems from answer
     */
    protected function extractPracticeProblems(string $content): array
    {
        $problems = [];
        if (preg_match('/Practice Problems?[:\s]*(.+?)(?:\n\n|$)/is', $content, $matches)) {
            $problemText = trim($matches[1]);
            $lines = explode("\n", $problemText);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && (str_starts_with($line, '-') || str_starts_with($line, '•') || preg_match('/^\d+\./', $line))) {
                    $problems[] = preg_replace('/^[-•\d.\s]+/', '', $line);
                }
            }
        }
        return array_filter($problems);
    }

    /**
     * Calculate cost based on token usage
     */
    protected function calculateCost(array $usage): float
    {
        // Claude pricing (approximate, adjust based on actual pricing)
        $inputCostPerToken = 0.000003; // $3 per 1M tokens
        $outputCostPerToken = 0.000015; // $15 per 1M tokens

        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;

        return ($inputTokens * $inputCostPerToken) + ($outputTokens * $outputCostPerToken);
    }
}

