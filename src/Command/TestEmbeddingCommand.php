<?php

declare(strict_types=1);

namespace App\Command;

use App\DTO\OpenAIEmbeddingClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:embedding',
    description: 'Test OpenAI embedding generation for given text',
)]
final class TestEmbeddingCommand extends Command
{
    public function __construct(
        private OpenAIEmbeddingClientInterface $openAIEmbeddingClient
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('text', InputArgument::REQUIRED, 'Text to generate embedding for')
            ->addOption('batch', null, null, 'Test batch processing with multiple texts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $text = $input->getArgument('text');
        $batchMode = $input->getOption('batch');

        $io->title('Testing OpenAI Embedding Generation');

        try {
            if ($batchMode) {
                // Test batch processing
                $texts = [$text, $text . ' (variant 1)', $text . ' (variant 2)'];
                $io->info('Testing batch processing with 3 texts...');

                $embeddings = $this->openAIEmbeddingClient->getEmbeddingsBatch($texts);

                $io->success('Batch embedding generated successfully!');
                $io->table(
                    ['Text', 'Embedding Length'],
                    array_map(function($text, $embedding) {
                        return [$text, count($embedding)];
                    }, $texts, $embeddings)
                );

            } else {
                // Test single embedding
                $io->info('Testing single embedding generation...');
                $io->text("Text: \"$text\"");

                $embedding = $this->openAIEmbeddingClient->getEmbedding($text);

                $io->success('Embedding generated successfully!');
                $io->text("Embedding dimensions: " . count($embedding));
                $io->text("First 5 values: " . implode(', ', array_slice($embedding, 0, 5)));
                $io->text("Last 5 values: " . implode(', ', array_slice($embedding, -5)));
            }

        } catch (\Exception $e) {
            $io->error('Embedding generation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
