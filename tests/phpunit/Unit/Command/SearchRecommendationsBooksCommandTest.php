<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\SearchRecommendationsBooksCommand;
use PHPUnit\Framework\TestCase;

class SearchRecommendationsBooksCommandTest extends TestCase
{
    public function testCommandClassExistsAndHasRequiredStructure(): void
    {
        $this->assertTrue(class_exists(SearchRecommendationsBooksCommand::class));
        $this->assertTrue(is_subclass_of(SearchRecommendationsBooksCommand::class, 'Symfony\Component\Console\Command\Command'));
    }

    public function testCommandHasRequiredMethods(): void
    {
        $this->assertTrue(method_exists(SearchRecommendationsBooksCommand::class, 'configure'));
        $this->assertTrue(method_exists(SearchRecommendationsBooksCommand::class, 'execute'));
    }

    public function testCommandNameAndDescription(): void
    {
        $reflection = new \ReflectionClass(SearchRecommendationsBooksCommand::class);
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
        $this->assertEquals('app:recommendations:search-books', $args['name']);
        $this->assertEquals('Search and store similar books for user recommendations', $args['description']);
    }

    public function testCommandConstructorParameters(): void
    {
        $reflection = new \ReflectionClass(SearchRecommendationsBooksCommand::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('recommendationService', $parameters[0]->getName());
        $this->assertEquals('entityManager', $parameters[1]->getName());
    }

    public function testCommandDoesNotHaveNoInteractionOption(): void
    {
        $command = new SearchRecommendationsBooksCommand(
            $this->createMock(\App\DTO\RecommendationServiceInterface::class),
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class)
        );

        $definition = $command->getDefinition();

        // This command should not have --no-interaction option as it relies on parent command
        $this->assertFalse($definition->hasOption('no-interaction'));
    }

    public function testCommandHasAllRequiredOptions(): void
    {
        $command = new SearchRecommendationsBooksCommand(
            $this->createMock(\App\DTO\RecommendationServiceInterface::class),
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class)
        );

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('recommendation-id'));
        $this->assertTrue($definition->hasOption('batch-size'));
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertTrue($definition->hasOption('force'));
        $this->assertTrue($definition->hasOption('max-recommendations'));
    }
}
