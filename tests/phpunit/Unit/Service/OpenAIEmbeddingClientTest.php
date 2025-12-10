<?php

declare(strict_types=1);

namespace App\Tests\phpunit\Unit\Service;

use App\Service\OpenAIEmbeddingClient;
use PHPUnit\Framework\TestCase;

class OpenAIEmbeddingClientTest extends TestCase
{
    private OpenAIEmbeddingClient $client;

    protected function setUp(): void
    {
        // Set required environment variables
        putenv('OPENAI_API_KEY=test_key');
        putenv('OPENAI_MODEL=text-embedding-3-small');

        $this->client = new OpenAIEmbeddingClient();
    }

    protected function tearDown(): void
    {
        // Clean up environment variables
        putenv('OPENAI_API_KEY');
        putenv('OPENAI_MODEL');
    }

    public function testConstructorThrowsExceptionWhenApiKeyNotSet(): void
    {
        // Clean up environment variables set in setUp
        putenv('OPENAI_API_KEY=');
        putenv('OPENAI_MODEL=');
        unset($_ENV['OPENAI_API_KEY']);
        unset($_ENV['OPENAI_MODEL']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Required environment variable 'OPENAI_API_KEY' is not set");

        new OpenAIEmbeddingClient();
    }

    public function testConstructorUsesDefaultModelWhenNotSet(): void
    {
        putenv('OPENAI_MODEL');

        $client = new OpenAIEmbeddingClient();

        $reflection = new \ReflectionClass($client);
        $modelProperty = $reflection->getProperty('model');
        $modelProperty->setAccessible(true);

        $this->assertEquals('text-embedding-3-small', $modelProperty->getValue($client));
    }

    public function testGetEmbeddingsBatchThrowsExceptionForEmptyTexts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('All texts must be non-empty strings');

        $this->client->getEmbeddingsBatch(['']);
    }

    public function testGetEmbeddingsBatchThrowsExceptionForInvalidTexts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('All texts must be non-empty strings');

        $this->client->getEmbeddingsBatch([123]);
    }

    public function testGetEmbeddingsBatchReturnsEmptyArrayForEmptyInput(): void
    {
        $result = $this->client->getEmbeddingsBatch([]);

        $this->assertEquals([], $result);
    }
}
