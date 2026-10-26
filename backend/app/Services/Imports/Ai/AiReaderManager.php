<?php

namespace App\Services\Imports\Ai;

use RuntimeException;

/**
 * Picks the AI service for reading PDFs.
 *   AI_PROVIDER=auto (default): the first provider that has a key: Gemini (AI Studio), Vertex AI, OpenAI, then Claude.
 *   AI_PROVIDER=gemini | vertex | openai | anthropic: force one.
 * Keys and model names live in backend/.env (see config/services.php).
 */
class AiReaderManager
{
    /** provider => driver class. Order = preference when AI_PROVIDER=auto (cheapest first). */
    private const DRIVERS = [
        'gemini' => GeminiReader::class,
        'vertex' => VertexReader::class,
        'openai' => OpenAiReader::class,
        'anthropic' => AnthropicReader::class,
    ];

    /** The provider that will be used, or null when no key is set. */
    public function provider(): ?string
    {
        $wanted = strtolower((string) config('services.ai.provider', 'auto'));

        if ($wanted !== 'auto' && isset(self::DRIVERS[$wanted])) {
            return config("services.{$wanted}.key") ? $wanted : null;
        }
        foreach (array_keys(self::DRIVERS) as $name) {
            if (config("services.{$name}.key")) {
                return $name;
            }
        }

        return null;
    }

    public function configured(): bool
    {
        return $this->provider() !== null;
    }

    /** @throws RuntimeException when no provider has a key */
    public function driver(): AiReader
    {
        $provider = $this->provider() ?? throw new RuntimeException(self::missingKeyMessage());

        return app(self::DRIVERS[$provider]);
    }

    public static function missingKeyMessage(): string
    {
        return 'The AI reader needs an API key. Add GEMINI_API_KEY (cheapest), VERTEX_API_KEY, OPENAI_API_KEY or ANTHROPIC_API_KEY to backend/.env.';
    }
}
