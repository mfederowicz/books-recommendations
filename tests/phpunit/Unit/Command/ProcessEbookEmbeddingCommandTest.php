<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ProcessEbookEmbeddingCommand;
use PHPUnit\Framework\TestCase;

class ProcessEbookEmbeddingCommandTest extends TestCase
{
    public function testCommandClassExistsAndHasRequiredStructure(): void
    {
        $this->assertTrue(class_exists(ProcessEbookEmbeddingCommand::class));
        $this->assertTrue(is_subclass_of(ProcessEbookEmbeddingCommand::class, 'Symfony\Component\Console\Command\Command'));
    }

    public function testCommandHasRequiredMethods(): void
    {
        $this->assertTrue(method_exists(ProcessEbookEmbeddingCommand::class, 'configure'));
        $this->assertTrue(method_exists(ProcessEbookEmbeddingCommand::class, 'execute'));
        $this->assertTrue(method_exists(ProcessEbookEmbeddingCommand::class, 'preparePayload'));
        $this->assertTrue(method_exists(ProcessEbookEmbeddingCommand::class, 'parseTagsFromEbook'));
    }

    public function testCommandNameAndDescription(): void
    {
        $reflection = new \ReflectionClass(ProcessEbookEmbeddingCommand::class);
        $attributes = $reflection->getAttributes();

        $commandAttribute = null;
        foreach ($attributes as $attribute) {
            if ('Symfony\Component\Console\Attribute\AsCommand' === $attribute->getName()) {
                $commandAttribute = $attribute;
                break;
            }
        }

        $this->assertNotNull($commandAttribute, 'Command should have AsCommand attribute');

        $args = $commandAttribute->getArguments();
        $this->assertEquals('app:process:ebook-embedding', $args['name']);
        $this->assertEquals('Process embedding for a specific ebook by ISBN', $args['description']);
    }

    public function testCommandConstructorParameters(): void
    {
        $reflection = new \ReflectionClass(ProcessEbookEmbeddingCommand::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('entityManager', $parameters[0]->getName());
        $this->assertEquals('openAIEmbeddingClient', $parameters[1]->getName());
    }
}
