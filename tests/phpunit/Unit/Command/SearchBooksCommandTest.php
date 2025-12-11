<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\SearchBooksCommand;
use PHPUnit\Framework\TestCase;

class SearchBooksCommandTest extends TestCase
{
    public function testCommandClassExistsAndHasRequiredStructure(): void
    {
        $this->assertTrue(class_exists(SearchBooksCommand::class));
        $this->assertTrue(is_subclass_of(SearchBooksCommand::class, 'Symfony\Component\Console\Command\Command'));
    }

    public function testCommandHasRequiredMethods(): void
    {
        $this->assertTrue(method_exists(SearchBooksCommand::class, 'configure'));
        $this->assertTrue(method_exists(SearchBooksCommand::class, 'execute'));
        $this->assertTrue(method_exists(SearchBooksCommand::class, 'outputTable'));
        $this->assertTrue(method_exists(SearchBooksCommand::class, 'outputJson'));
        $this->assertTrue(method_exists(SearchBooksCommand::class, 'truncateText'));
    }

    public function testCommandNameAndDescription(): void
    {
        $reflection = new \ReflectionClass(SearchBooksCommand::class);
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
        $this->assertEquals('app:search:books', $args['name']);
        $this->assertEquals('Search for books in Qdrant using text similarity', $args['description']);
    }

    public function testCommandConstructorParameters(): void
    {
        $reflection = new \ReflectionClass(SearchBooksCommand::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertEquals('recommendationService', $parameters[0]->getName());
    }
}
