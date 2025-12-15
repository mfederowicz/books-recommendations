<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\UpdateRecommendationsCommand;
use PHPUnit\Framework\TestCase;

class UpdateRecommendationsCommandTest extends TestCase
{
    public function testCommandClassExistsAndHasRequiredStructure(): void
    {
        $this->assertTrue(class_exists(UpdateRecommendationsCommand::class));
        $this->assertTrue(is_subclass_of(UpdateRecommendationsCommand::class, 'Symfony\Component\Console\Command\Command'));
    }

    public function testCommandHasRequiredMethods(): void
    {
        $this->assertTrue(method_exists(UpdateRecommendationsCommand::class, 'configure'));
        $this->assertTrue(method_exists(UpdateRecommendationsCommand::class, 'execute'));
    }

    public function testCommandNameAndDescription(): void
    {
        $reflection = new \ReflectionClass(UpdateRecommendationsCommand::class);
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
        $this->assertEquals('app:recommendations:update', $args['name']);
        $this->assertEquals('Update recommendation results for books that need refreshing (cron-friendly)', $args['description']);
    }

    public function testCommandConstructorParameters(): void
    {
        $reflection = new \ReflectionClass(UpdateRecommendationsCommand::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertEquals('recommendationService', $parameters[0]->getName());
    }

    public function testCommandHasQuietOption(): void
    {
        $command = new UpdateRecommendationsCommand(
            $this->createMock(\App\DTO\RecommendationServiceInterface::class)
        );

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('quiet'));
        $this->assertTrue($definition->hasOption('max-recommendations'));
        $this->assertTrue($definition->hasOption('batch-size'));
    }

    public function testCommandCanBeInstantiated(): void
    {
        $recommendationService = $this->createMock(\App\DTO\RecommendationServiceInterface::class);
        $command = new UpdateRecommendationsCommand($recommendationService);

        $this->assertInstanceOf(UpdateRecommendationsCommand::class, $command);
        $this->assertEquals('app:recommendations:update', $command->getName());
    }
}
