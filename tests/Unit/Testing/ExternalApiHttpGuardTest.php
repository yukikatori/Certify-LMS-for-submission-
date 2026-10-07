<?php

declare(strict_types=1);

namespace Tests\Unit\Testing;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

#[Group('external-api')]
class ExternalApiHttpGuardTest extends TestCase
{
    public function test_unmocked_external_http_request_fails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('without a matching fake');

        Http::get('https://www.googleapis.com/unmocked');
    }

    public function test_mocked_external_http_request_is_allowed(): void
    {
        Http::fake([
            'https://www.googleapis.com/mocked' => Http::response(['ok' => true]),
        ]);

        $response = Http::get('https://www.googleapis.com/mocked');

        $this->assertTrue($response->json('ok'));
    }
}
