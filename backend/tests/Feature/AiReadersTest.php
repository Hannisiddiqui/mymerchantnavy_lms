<?php

namespace Tests\Feature;

use App\Services\Imports\Ai\AiReaderManager;
use App\Services\Imports\Ai\GeminiReader;
use App\Services\Imports\Ai\OpenAiReader;
use App\Services\Imports\Ai\VertexReader;
use App\Services\Imports\AiPdfParser;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The AI services used to read question PDFs. No real calls are made: HTTP is faked, so these tests check
 * the requests we send (PDF attached, JSON schema, key) and how replies are read.
 */
class AiReadersTest extends TestCase
{
    private const SCHEMA = ['type' => 'object', 'additionalProperties' => false, 'properties' => ['questions' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'properties' => ['difficulty' => ['type' => ['string', 'null'], 'enum' => ['easy', null]]]]]]];

    private function keys(?string $gemini = null, ?string $openai = null, ?string $anthropic = null, string $provider = 'auto'): void
    {
        config(['services.gemini.key' => $gemini, 'services.openai.key' => $openai, 'services.anthropic.key' => $anthropic, 'services.ai.provider' => $provider]);
    }

    // ------------------------------------------------------------------ choosing the provider

    public function test_the_first_provider_with_a_key_is_used_cheapest_first(): void
    {
        $manager = new AiReaderManager;

        $this->keys();
        $this->assertFalse($manager->configured());
        $this->assertNull($manager->provider());

        $this->keys(anthropic: 'a');
        $this->assertSame('anthropic', $manager->provider());

        $this->keys(openai: 'o', anthropic: 'a');
        $this->assertSame('openai', $manager->provider());

        $this->keys(gemini: 'g', openai: 'o', anthropic: 'a');
        $this->assertSame('gemini', $manager->provider());
        $this->assertInstanceOf(GeminiReader::class, $manager->driver());
    }

    public function test_a_provider_can_be_forced_and_needs_its_own_key(): void
    {
        $manager = new AiReaderManager;

        $this->keys(gemini: 'g', openai: 'o', provider: 'openai');
        $this->assertInstanceOf(OpenAiReader::class, $manager->driver());

        $this->keys(gemini: 'g', provider: 'openai'); // forced to OpenAI but no OpenAI key
        $this->assertFalse($manager->configured());
    }

    // ------------------------------------------------------------------ Vertex AI

    public function test_vertex_is_chosen_after_gemini_and_before_openai(): void
    {
        $manager = new AiReaderManager;
        config(['services.vertex.key' => 'v']);

        $this->keys(openai: 'o');
        $this->assertSame('vertex', $manager->provider());
        $this->assertInstanceOf(VertexReader::class, $manager->driver());

        $this->keys(gemini: 'g');
        $this->assertSame('gemini', $manager->provider());

        $this->keys(gemini: 'g', provider: 'vertex');
        $this->assertSame('vertex', $manager->provider());
    }

    public function test_vertex_request_has_the_pdf_the_schema_and_the_key_and_reply_is_read(): void
    {
        $this->keys();
        config(['services.vertex.key' => 'vtx-key', 'services.vertex.model' => 'gemini-3.8-flash']);
        Http::fake(['aiplatform.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['thought' => true, 'text' => 'thinking'], ['text' => '{"questions":[]}']]], 'finishReason' => 'STOP']],
        ])]);

        $reply = app(VertexReader::class)->read(base64_encode('%PDF-fake'), 'Read it', self::SCHEMA);

        $this->assertSame(['text' => '{"questions":[]}', 'truncated' => false], $reply);
        Http::assertSent(function (Request $request) {
            $body = $request->data();
            $parts = $body['contents'][0]['parts'];
            $items = $body['generationConfig']['responseSchema']['properties']['questions']['items'];

            return str_starts_with($request->url(), 'https://aiplatform.googleapis.com/v1/publishers/google/models/gemini-3.8-flash:generateContent?')
                && str_contains($request->url(), 'key=vtx-key')
                && $parts[0] === ['inlineData' => ['mimeType' => 'application/pdf', 'data' => base64_encode('%PDF-fake')]]
                && $parts[1] === ['text' => 'Read it']
                && ! array_key_exists('temperature', $body['generationConfig'])
                && $body['generationConfig']['responseMimeType'] === 'application/json'
                && $body['generationConfig']['responseSchema']['type'] === 'OBJECT'
                && ! array_key_exists('additionalProperties', $items)
                && $items['properties']['difficulty'] === ['type' => 'STRING', 'enum' => ['easy'], 'nullable' => true];
        });
    }

    public function test_vertex_reports_truncation_and_failures(): void
    {
        $vertex = new VertexReader;

        $this->assertTrue($vertex->parse(['candidates' => [['content' => ['parts' => [['text' => '{"quest']]], 'finishReason' => 'MAX_TOKENS']]])['truncated']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SAFETY');
        $vertex->parse(['promptFeedback' => ['blockReason' => 'SAFETY']]);
    }

    // ------------------------------------------------------------------ Gemini

    public function test_gemini_request_has_the_pdf_the_schema_and_the_key_and_reply_is_read(): void
    {
        $this->keys(gemini: 'gem-key');
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'status' => 'completed',
            'steps' => [
                ['type' => 'thought', 'content' => [['type' => 'text', 'text' => 'ignored']]],
                ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => '{"questions":[]}']]],
            ],
        ])]);

        $reply = app(GeminiReader::class)->read(base64_encode('%PDF-fake'), 'Read it', self::SCHEMA);

        $this->assertSame(['text' => '{"questions":[]}', 'truncated' => false], $reply);
        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/interactions'
                && $request->header('x-goog-api-key') === ['gem-key']
                && $body['model'] === 'gemini-3.8-flash'
                && $body['input'][0] === ['type' => 'document', 'data' => base64_encode('%PDF-fake'), 'mime_type' => 'application/pdf']
                && $body['input'][1] === ['type' => 'text', 'text' => 'Read it']
                && $body['response_format']['mime_type'] === 'application/json'
                && ! array_key_exists('additionalProperties', $body['response_format']['schema'])                // unsupported keyword removed
                && ! array_key_exists('additionalProperties', $body['response_format']['schema']['properties']['questions']['items'])
                && $body['response_format']['schema']['properties']['questions']['items']['properties']['difficulty']['enum'] === ['easy']; // null only via type
        });
    }

    public function test_gemini_reports_truncation_and_failures(): void
    {
        $gemini = new GeminiReader;

        $this->assertTrue($gemini->parse(['status' => 'incomplete', 'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => '{"quest']]]]])['truncated']);
        $this->assertSame('{"x":1}', $gemini->parse(['candidates' => [['content' => ['parts' => [['text' => '{"x":1}']]], 'finishReason' => 'STOP']]])['text'], 'older reply shape is also understood');

        $this->expectExceptionMessage('quota');
        $gemini->parse(['status' => 'failed', 'error' => ['message' => 'quota exceeded']]);
    }

    // ------------------------------------------------------------------ OpenAI

    public function test_openai_request_attaches_the_pdf_with_strict_schema_and_low_reasoning(): void
    {
        $this->keys(openai: 'sk-test');
        Http::fake(['api.openai.com/*' => Http::response([
            'status' => 'completed',
            'output' => [
                ['type' => 'reasoning', 'summary' => []],
                ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '{"questions":[]}']]],
            ],
        ])]);

        $reply = app(OpenAiReader::class)->read(base64_encode('%PDF-fake'), 'Read it', self::SCHEMA);

        $this->assertSame(['text' => '{"questions":[]}', 'truncated' => false], $reply);
        Http::assertSent(function (Request $request) {
            $body = $request->data();
            $file = $body['input'][0]['content'][0];

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request->header('Authorization') === ['Bearer sk-test']
                && $file['type'] === 'input_file' && $file['file_data'] === 'data:application/pdf;base64,'.base64_encode('%PDF-fake')
                && $body['input'][0]['content'][1] === ['type' => 'input_text', 'text' => 'Read it']
                && $body['text']['format']['type'] === 'json_schema' && $body['text']['format']['strict'] === true
                && $body['reasoning'] === ['effort' => 'low']
                && $body['model'] === 'gpt-5-mini';
        });
    }

    public function test_openai_handles_truncation_and_refusals(): void
    {
        $openai = new OpenAiReader;

        $cut = $openai->parse(['status' => 'incomplete', 'incomplete_details' => ['reason' => 'max_output_tokens'], 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '{"qu']]]]]);
        $this->assertTrue($cut['truncated']);

        $this->expectExceptionMessage('declined');
        $openai->parse(['output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'no']]]]]);
    }

    public function test_openai_models_without_reasoning_do_not_get_the_reasoning_option(): void
    {
        $this->keys(openai: 'sk-test');
        config(['services.openai.model' => 'gpt-4.1-mini']);
        Http::fake(['api.openai.com/*' => Http::response(['output' => []])]);

        app(OpenAiReader::class)->read('x', 'p', self::SCHEMA);

        Http::assertSent(fn (Request $request) => ! array_key_exists('reasoning', $request->data()));
    }

    // ------------------------------------------------------------------ the instructions

    public function test_instructions_describe_formula_rules_without_php_getting_in_the_way(): void
    {
        $method = new \ReflectionMethod(AiPdfParser::class, 'prompt');
        $prompt = $method->invoke(app(AiPdfParser::class), 4, true);

        foreach (['\\frac{16}{3}', '\\sqrt[3]{x}', '\\vec{A}', '\\hat{i}', '$x^2$', '$$\\frac{a}{b}$$', '\\text{m/s}', '4 page(s)', 'do not output questions that start on that last page'] as $expected) {
            $this->assertStringContainsString($expected, $prompt);
        }
        $this->assertStringNotContainsString('{$', $prompt, 'no unreplaced PHP variables');
    }
}
