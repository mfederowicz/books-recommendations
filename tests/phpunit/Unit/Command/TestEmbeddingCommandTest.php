<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\TestEmbeddingCommand;
use App\DTO\OpenAIEmbeddingClientInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class TestEmbeddingCommandTest extends TestCase
{
    private OpenAIEmbeddingClientInterface $openAIEmbeddingClient;
    private TestEmbeddingCommand $command;

    protected function setUp(): void
    {
        $this->openAIEmbeddingClient = $this->createMock(OpenAIEmbeddingClientInterface::class);
        $this->command = new TestEmbeddingCommand($this->openAIEmbeddingClient);
    }

    public function testExecuteSingleEmbeddingSuccess(): void
    {
        $commandTester = new CommandTester($this->command);

        $text = 'Test embedding text';
        $embedding = array_fill(0, 1536, 0.1); // Mock 1536-dimension embedding

        $this->openAIEmbeddingClient
            ->expects($this->once())
            ->method('getEmbedding')
            ->with($text)
            ->willReturn($embedding);

        $exitCode = $commandTester->execute(['text' => $text]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Embedding generated successfully!', $output);
        $this->assertStringContainsString('Embedding dimensions: 1536', $output);
        $this->assertStringContainsString('First 5 values:', $output);
        $this->assertStringContainsString('Last 5 values:', $output);
    }

    public function testExecuteBatchEmbeddingSuccess(): void
    {
        $commandTester = new CommandTester($this->command);

        $text = 'Test batch text';
        $embeddings = [
            array_fill(0, 1536, 0.1),
            array_fill(0, 1536, 0.2),
            array_fill(0, 1536, 0.3),
        ];

        $this->openAIEmbeddingClient
            ->expects($this->once())
            ->method('getEmbeddingsBatch')
            ->with([
                $text,
                $text.' (variant 1)',
                $text.' (variant 2)',
            ])
            ->willReturn($embeddings);

        $exitCode = $commandTester->execute([
            'text' => $text,
            '--batch' => true,
        ]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Batch embedding generated successfully!', $output);
        $this->assertStringContainsString('Test batch text', $output);
        $this->assertStringContainsString('1536', $output); // Embedding length
    }

    public function testExecuteSingleEmbeddingFailure(): void
    {
        $commandTester = new CommandTester($this->command);

        $text = 'Test text';
        $errorMessage = 'API connection failed';

        $this->openAIEmbeddingClient
            ->expects($this->once())
            ->method('getEmbedding')
            ->with($text)
            ->willThrowException(new \Exception($errorMessage));

        $exitCode = $commandTester->execute(['text' => $text]);

        $this->assertEquals(1, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Embedding generation failed: '.$errorMessage, $output);
    }

    public function testExecuteBatchEmbeddingFailure(): void
    {
        $commandTester = new CommandTester($this->command);

        $text = 'Test batch text';
        $errorMessage = 'Batch API failed';

        $this->openAIEmbeddingClient
            ->expects($this->once())
            ->method('getEmbeddingsBatch')
            ->willThrowException(new \Exception($errorMessage));

        $exitCode = $commandTester->execute([
            'text' => $text,
            '--batch' => true,
        ]);

        $this->assertEquals(1, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Embedding generation failed: '.$errorMessage, $output);
    }

    public function testCommandHasCorrectNameAndDescription(): void
    {
        $this->assertEquals('app:test:embedding', $this->command->getName());
        $this->assertEquals('Test OpenAI embedding generation for given text', $this->command->getDescription());
    }

    public function testCommandHasOptionalTextArgument(): void
    {
        $commandTester = new CommandTester($this->command);

        // Text argument is now optional with default value 'test text'
        $embedding = array_fill(0, 1536, 0.1);

        $this->openAIEmbeddingClient
            ->expects($this->once())
            ->method('getEmbedding')
            ->with('test text') // Default value
            ->willReturn($embedding);

        $exitCode = $commandTester->execute([]);
        $this->assertEquals(0, $exitCode);
    }
}
