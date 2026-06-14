<?php
declare(strict_types=1);

namespace Tests;

use app\middleware\ApiMiddleware;
use flight\Engine;
use PHPUnit\Framework\TestCase;

class ApiMiddlewareTest extends TestCase
{
    public function testReturnsErrorWhenApiKeyNotConfigured(): void
    {
        $middleware = new ApiMiddleware(new Engine(), ['api_key' => '']);

        $error = $middleware->getAuthorizationError('Bearer anything');

        $this->assertSame(
            ['error' => 'api_key needs to be set in the config file.'],
            $error
        );
    }

    public function testAcceptsValidBearerToken(): void
    {
        $apiKey = 'test-api-key-value';
        $middleware = new ApiMiddleware(new Engine(), ['api_key' => $apiKey]);

        $error = $middleware->getAuthorizationError('Bearer ' . $apiKey);

        $this->assertNull($error);
    }

    public function testRejectsInvalidBearerToken(): void
    {
        $middleware = new ApiMiddleware(new Engine(), ['api_key' => 'correct-key']);

        $error = $middleware->getAuthorizationError('Bearer wrong-key');

        $this->assertSame(
            ['error' => 'invalid api_key supplied in Authorization header. Make sure the request header is "Authorization: Bearer apikey"'],
            $error
        );
    }

    public function testRejectsMissingAuthorizationHeader(): void
    {
        $middleware = new ApiMiddleware(new Engine(), ['api_key' => 'correct-key']);

        $error = $middleware->getAuthorizationError(null);

        $this->assertSame(
            ['error' => 'invalid api_key supplied in Authorization header. Make sure the request header is "Authorization: Bearer apikey"'],
            $error
        );
    }
}