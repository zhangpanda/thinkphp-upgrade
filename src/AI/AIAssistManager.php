<?php

declare(strict_types=1);

namespace PHPLift\AI;

use PhpParser\ParserFactory;

final class AIAssistManager
{
    private const MAX_RETRIES = 2;

    /** @var list<AIProviderInterface> */
    private array $providers = [];

    public function addProvider(AIProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    public function suggest(MigrationContext $context): AISuggestion
    {
        foreach ($this->providers as $provider) {
            if (!$provider->isAvailable()) {
                continue;
            }

            for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
                try {
                    $suggestion = $provider->suggest($context);
                    if ($this->validateSyntax($suggestion->suggestedCode)) {
                        return $suggestion;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return new AISuggestion(
            suggestedCode: $context->originalCode,
            explanation: 'No AI provider available. Manual migration required.',
            confidence: 0.0,
            needsReview: true,
        );
    }

    public function hasAvailableProvider(): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->isAvailable()) {
                return true;
            }
        }
        return false;
    }

    private function validateSyntax(string $code): bool
    {
        try {
            $parser = (new ParserFactory())->createForNewestSupportedVersion();
            $wrapped = str_starts_with(trim($code), '<?php') ? $code : "<?php\n" . $code;
            $parser->parse($wrapped);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
