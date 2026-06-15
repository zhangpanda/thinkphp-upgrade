<?php

declare(strict_types=1);

namespace PHPLift\AI;

final class OpenAIProvider implements AIProviderInterface
{
    private const TIMEOUT_SECONDS = 30;
    private const CONNECT_TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly string $apiKey = '',
        private readonly string $model = 'gpt-4o',
        private readonly string $baseUrl = 'https://api.openai.com/v1',
    ) {}

    public function isAvailable(): bool
    {
        return $this->apiKey !== '';
    }

    public function suggest(MigrationContext $context): AISuggestion
    {
        if (!$this->isAvailable()) {
            return $this->fallback($context, 'AI unavailable. Manual migration required.');
        }

        $ch = curl_init($this->baseUrl . '/chat/completions');
        if ($ch === false) {
            return $this->fallback($context, 'Failed to initialize HTTP client.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_MAXFILESIZE => 1_048_576, // 1MB max response
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a PHP migration expert. Return only the migrated PHP code in a code block.'],
                    ['role' => 'user', 'content' => $this->buildPrompt($context)],
                ],
                'temperature' => 0.2,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $response === '') {
            return $this->fallback($context, "HTTP request failed: {$error}");
        }

        if ($httpCode !== 200) {
            return $this->fallback($context, "API returned HTTP {$httpCode}.");
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return $this->fallback($context, 'Invalid JSON response from API.');
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        if ($content === '') {
            return $this->fallback($context, 'Empty response from API.');
        }

        // Extract code from markdown block
        if (preg_match('/```php\s*\n(.*?)\n```/s', $content, $m)) {
            $code = $m[1];
        } else {
            $code = $content;
        }

        return new AISuggestion(
            suggestedCode: $code,
            explanation: 'AI-generated migration suggestion.',
            confidence: 0.8,
            needsReview: true,
        );
    }

    private function fallback(MigrationContext $context, string $reason): AISuggestion
    {
        return new AISuggestion(
            suggestedCode: $context->originalCode,
            explanation: $reason,
            confidence: 0.0,
            needsReview: true,
        );
    }

    private function buildPrompt(MigrationContext $context): string
    {
        return "Migrate the following PHP code from {$context->fromFramework} to {$context->toFramework}.\n\n"
            . "File: {$context->filePath}\n\n"
            . "```php\n{$context->originalCode}\n```\n\n"
            . "Return only the migrated code.";
    }
}
