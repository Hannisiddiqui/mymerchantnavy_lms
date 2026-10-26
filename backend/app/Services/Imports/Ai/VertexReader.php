<?php

namespace App\Services\Imports\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Gemini on Vertex AI (Google Cloud), with a Google Cloud API key.
 * The PDF is sent inline, so Gemini sees the page images as well as the text: good for formulas.
 * Key: VERTEX_API_KEY (Google Cloud console > Vertex AI > API keys). Model: VERTEX_MODEL.
 * Endpoint: https://aiplatform.googleapis.com/v1/publishers/google/models/{model}:generateContent?key=...
 */
class VertexReader implements AiReader
{
    private const ENDPOINT = 'https://aiplatform.googleapis.com/v1/publishers/google/models/%s:generateContent';

    public function label(): string
    {
        return 'Vertex AI ('.config('services.vertex.model').')';
    }

    public function read(string $pdfBase64, string $prompt, array $schema): array
    {
        $url = sprintf(self::ENDPOINT, config('services.vertex.model'));

        // expect=false: above ~1 MB curl would otherwise send "Expect: 100-continue", and Google answers 417.
        $response = Http::withOptions(['expect' => false])->timeout(600)
            // Google answers 429 "resource exhausted" when a project is over its per-minute allowance: wait, then try again.
            ->retry([5000, 15000, 45000, 90000], fn ($e) => $e instanceof ConnectionException || ($e instanceof RequestException && in_array($e->response->status(), [429, 500, 502, 503, 504], true)), throw: true)
            ->withQueryParameters(['key' => config('services.vertex.key')])
            ->post($url, [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [
                        ['inlineData' => ['mimeType' => 'application/pdf', 'data' => $pdfBase64]],
                        ['text' => $prompt],
                    ],
                ]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $this->schema($schema),
                    'maxOutputTokens' => 32768,
                    // Gemini 3 models are tuned for their default temperature: forcing 0 makes them loop and ramble.
                    'thinkingConfig' => ['thinkingLevel' => 'LOW'],
                ],
            ]);

        return $this->parse($response->json() ?? []);
    }

    /** Reads the reply: the text parts of the first candidate (thinking parts are skipped). */
    public function parse(array $json): array
    {
        if (isset($json['promptFeedback']['blockReason'])) {
            throw new RuntimeException('Vertex AI could not read this part: '.$json['promptFeedback']['blockReason']);
        }

        $candidate = $json['candidates'][0] ?? [];
        $text = '';
        foreach ($candidate['content']['parts'] ?? [] as $part) {
            if (empty($part['thought'])) {
                $text .= $part['text'] ?? '';
            }
        }
        if ($text === '' && ! empty($json['error']['message'])) {
            throw new RuntimeException('Vertex AI could not read this part: '.$json['error']['message']);
        }

        return ['text' => $text, 'truncated' => ($candidate['finishReason'] ?? null) === 'MAX_TOKENS'];
    }

    /**
     * Vertex's responseSchema is an OpenAPI subset: one upper-case type per field, "nullable" instead of a type list that
     * includes null, no additionalProperties, and no null inside enum.
     */
    public function schema(array $schema): array
    {
        unset($schema['additionalProperties']);

        if (isset($schema['type']) && is_array($schema['type'])) {
            $types = array_values(array_filter($schema['type'], fn ($t) => $t !== 'null'));
            if (count($types) < count($schema['type'])) {
                $schema['nullable'] = true;
            }
            $schema['type'] = $types[0] ?? 'string';
        }
        if (isset($schema['type']) && is_string($schema['type'])) {
            $schema['type'] = strtoupper($schema['type']);
        }
        if (isset($schema['enum'])) {
            $schema['enum'] = array_values(array_filter($schema['enum'], fn ($v) => $v !== null));
        }
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $schema['properties'] = array_map(fn ($child) => is_array($child) ? $this->schema($child) : $child, $schema['properties']);
        }
        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->schema($schema['items']);
        }

        return $schema;
    }
}
