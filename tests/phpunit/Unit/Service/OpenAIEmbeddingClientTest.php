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
        // Set required environment variables for testing
        putenv('OPENAI_API_KEY=test_key');
        putenv('OPENAI_MODEL=text-embedding-3-small');

        // Create partial mock to override createEmbeddings method
        $this->client = $this->getMockBuilder(OpenAIEmbeddingClient::class)
            ->onlyMethods(['createEmbeddings'])
            ->getMock();
    }

    protected function tearDown(): void
    {
        // Clean up environment variables
        putenv('OPENAI_API_KEY');
        putenv('OPENAI_MODEL');
    }

    public function testConstructorThrowsExceptionWhenApiKeyNotSet(): void
    {
        putenv('OPENAI_API_KEY');
        putenv('OPENAI_MODEL');
        unset($_ENV['OPENAI_API_KEY']);
        unset($_ENV['OPENAI_MODEL']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Required environment variable 'OPENAI_API_KEY' is not set");

        new OpenAIEmbeddingClient();
    }

    public function testConstructorUsesDefaultModelWhenNotSet(): void
    {
        putenv('OPENAI_API_KEY=test_key');
        putenv('OPENAI_MODEL');

        $client = new OpenAIEmbeddingClient();

        $reflection = new \ReflectionClass($client);
        $modelProperty = $reflection->getProperty('model');
        $modelProperty->setAccessible(true);

        $this->assertEquals('text-embedding-3-small', $modelProperty->getValue($client));

        // Cleanup
        putenv('OPENAI_API_KEY');
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

    public function testGetEmbeddingsBatchThrowsExceptionForWhitespaceOnlyTexts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('All texts must be non-empty strings');

        $this->client->getEmbeddingsBatch(['   ', "\t", "\n"]);
    }

    public function testGetEmbeddingsBatchReturnsEmptyArrayForEmptyInput(): void
    {
        $result = $this->client->getEmbeddingsBatch([]);

        $this->assertEquals([], $result);
    }

    public function testGetEmbeddingsBatchSuccess(): void
    {
        $texts = ['Hello world', 'Test text'];
        $expectedEmbeddings = [[0.1, 0.2, 0.3], [0.4, 0.5, 0.6]];

        // Create mock embedding objects (simple stdClass instead of final classes)
        $embedding1 = (object) ['index' => 0, 'embedding' => $expectedEmbeddings[0]];
        $embedding2 = (object) ['index' => 1, 'embedding' => $expectedEmbeddings[1]];

        $response = (object) ['embeddings' => [$embedding2, $embedding1]]; // Out of order to test sorting

        // Mock the createEmbeddings method
        $this->client
            ->expects($this->once())
            ->method('createEmbeddings')
            ->with([
                'model' => 'text-embedding-3-small',
                'input' => $texts,
                'encoding_format' => 'float',
            ])
            ->willReturn($response);

        $result = $this->client->getEmbeddingsBatch($texts);

        $this->assertEquals($expectedEmbeddings, $result);
    }

    public function testGetEmbeddingsBatchHandlesSingleText(): void
    {
        $texts = ['Single text'];
        $expectedEmbeddings = [[0.1, 0.2, 0.3]];

        // Create mock embedding object
        $embedding = (object) ['index' => 0, 'embedding' => $expectedEmbeddings[0]];
        $response = (object) ['embeddings' => [$embedding]];

        // Mock the createEmbeddings method
        $this->client
            ->expects($this->once())
            ->method('createEmbeddings')
            ->willReturn($response);

        $result = $this->client->getEmbeddingsBatch($texts);

        $this->assertEquals($expectedEmbeddings, $result);
    }

    public function testGetEmbeddingsBatchFailure(): void
    {
        $texts = ['Test text'];

        // Mock the createEmbeddings method to throw an exception
        $this->client
            ->expects($this->once())
            ->method('createEmbeddings')
            ->willThrowException(new \Exception('API Error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to generate embeddings: API Error');

        $this->client->getEmbeddingsBatch($texts);
    }

    public function testGetEmbeddingSuccess(): void
    {
        $text = 'Test text';
        $expectedEmbedding = [0.1, 0.2, 0.3];

        // Create mock embedding object
        $embedding = (object) ['index' => 0, 'embedding' => $expectedEmbedding];
        $response = (object) ['embeddings' => [$embedding]];

        // Mock the createEmbeddings method
        $this->client
            ->expects($this->once())
            ->method('createEmbeddings')
            ->willReturn($response);

        $result = $this->client->getEmbedding($text);

        $this->assertEquals($expectedEmbedding, $result);
    }

    public function testGetEmbeddingFailure(): void
    {
        $text = 'Test text';

        // Mock the createEmbeddings method to throw an exception
        $this->client
            ->expects($this->once())
            ->method('createEmbeddings')
            ->willThrowException(new \Exception('API Error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to generate embeddings: API Error');

        $this->client->getEmbedding($text);
    }

    public function testGetEnvVarMethodComprehensive(): void
    {
        // Test getEnvVar method using reflection
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('getEnvVar');
        $method->setAccessible(true);

        // Test with environment variable set
        putenv('TEST_VAR=test_value');
        $result = $method->invoke($this->client, 'TEST_VAR');
        $this->assertEquals('test_value', $result);

        // Test with default value
        $result = $method->invoke($this->client, 'NON_EXISTENT_VAR', 'default');
        $this->assertEquals('default', $result);

        // Test with $_ENV
        $_ENV['TEST_ENV_VAR'] = 'env_value';
        putenv('TEST_ENV_VAR'); // Clear getenv
        $result = $method->invoke($this->client, 'TEST_ENV_VAR');
        $this->assertEquals('env_value', $result);

        // Test exception for required variable
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Required environment variable 'MISSING_VAR' is not set");
        $method->invoke($this->client, 'MISSING_VAR');

        // Cleanup
        putenv('TEST_VAR');
        unset($_ENV['TEST_ENV_VAR']);
    }

    public function testAllMethodsExist(): void
    {
        $expectedMethods = [
            'getEmbedding',
            'getEmbeddingsBatch',
        ];

        foreach ($expectedMethods as $methodName) {
            $this->assertTrue(method_exists($this->client, $methodName),
                "Method {$methodName} should exist in OpenAIEmbeddingClient");
        }
    }
}
