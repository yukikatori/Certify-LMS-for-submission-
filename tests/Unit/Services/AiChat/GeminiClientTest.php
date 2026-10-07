<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AiChat;

use App\Exceptions\AiChat\GeminiApiKeyNotConfiguredException;
use App\Services\AiChat\GeminiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

#[Group('external-api')]
class GeminiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai-chat.gemini.api_key' => 'gemini-test-key',
            'ai-chat.gemini.model' => 'gemini-test-model',
            'ai-chat.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'ai-chat.gemini.timeout' => 10,
            'ai-chat.gemini.retry_attempts' => 1,
        ]);
    }

    public function test_generate_returns_normal_response_without_real_http(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-test-model:generateContent' => Http::response([
                'modelVersion' => 'gemini-test-model-001',
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => '解説の回答です。',
                        ]],
                    ],
                ]],
                'usageMetadata' => [
                    'promptTokenCount' => 12,
                    'candidatesTokenCount' => 8,
                    'totalTokenCount' => 20,
                ],
            ]),
        ]);

        $response = app(GeminiClient::class)->generate($this->payload());

        $this->assertSame('解説の回答です。', $response->text);
        $this->assertSame('gemini-test-model-001', $response->model);
        $this->assertSame(12, $response->promptTokens);
        $this->assertSame(8, $response->completionTokens);
        $this->assertSame(20, $response->totalTokens);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test-model:generateContent'
                && $request->hasHeader('x-goog-api-key', 'gemini-test-key')
                && $request['contents'][0]['parts'][0]['text'] === '問題の考え方を教えてください。';
        });
    }

    public function test_generate_throws_when_api_key_is_missing(): void
    {
        config(['ai-chat.gemini.api_key' => null]);

        $this->expectException(GeminiApiKeyNotConfiguredException::class);

        app(GeminiClient::class)->generate($this->payload());
    }

    public function test_generate_throws_on_http_error(): void
    {
        config(['ai-chat.gemini.retry_attempts' => 0]);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-test-model:generateContent' => Http::response('Service Unavailable', 503),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gemini request failed: HTTP 503');

        app(GeminiClient::class)->generate($this->payload());
    }

    public function test_generate_throws_on_connection_error(): void
    {
        config(['ai-chat.gemini.retry_attempts' => 0]);

        Http::fake(function (): never {
            throw new ConnectionException('Connection refused');
        });

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Connection refused');

        app(GeminiClient::class)->generate($this->payload());
    }

    public function test_generate_allows_empty_response_as_empty_text(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-test-model:generateContent' => Http::response([]),
        ]);

        $response = app(GeminiClient::class)->generate($this->payload());

        $this->assertSame('', $response->text);
        $this->assertSame('gemini-test-model', $response->model);
        $this->assertNull($response->promptTokens);
        $this->assertNull($response->completionTokens);
        $this->assertNull($response->totalTokens);
    }

    public function test_generate_retries_temporary_error_and_returns_success(): void
    {
        Http::fakeSequence()
            ->push('Service Unavailable', 503)
            ->push([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => '再試行後の回答です。',
                        ]],
                    ],
                ]],
            ]);

        $response = app(GeminiClient::class)->generate($this->payload());

        $this->assertSame('再試行後の回答です。', $response->text);
        Http::assertSentCount(2);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'contents' => [[
                'role' => 'user',
                'parts' => [[
                    'text' => '問題の考え方を教えてください。',
                ]],
            ]],
        ];
    }
}
