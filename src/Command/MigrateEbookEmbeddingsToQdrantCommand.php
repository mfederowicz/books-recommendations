<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\EbookEmbeddingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate:ebook-embeddings-to-qdrant',
    description: 'Migrate ebook embeddings from database to Qdrant vector database for fast similarity search',
)]
final class MigrateEbookEmbeddingsToQdrantCommand extends Command
{
    public function __construct(
        private EbookEmbeddingService $ebookEmbeddingService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be migrated without actually doing it')
            ->addOption('stats-only', null, InputOption::VALUE_NONE, 'Only show collection statistics without migrating');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $statsOnly = $input->getOption('stats-only');

        $io->title('Migrating Ebook Embeddings to Qdrant');

        if ($dryRun) {
            $io->warning('DRY RUN MODE: No actual changes will be made');
        }

        if ($statsOnly) {
            $io->section('Qdrant Collection Statistics');
            $stats = $this->ebookEmbeddingService->getQdrantCollectionStats();

            if (null === $stats) {
                $io->warning('Qdrant collection does not exist or is not accessible');

                return Command::FAILURE;
            }

            $io->table(
                ['Property', 'Value'],
                [
                    ['Collection Name', $stats['result']['name'] ?? 'N/A'],
                    ['Vectors Size', $stats['result']['vectors']['size'] ?? 'N/A'],
                    ['Distance Metric', $stats['result']['vectors']['distance'] ?? 'N/A'],
                    ['Points Count', $stats['result']['points_count'] ?? 0],
                    ['Status', $stats['result']['status'] ?? 'N/A'],
                ]
            );

            return Command::SUCCESS;
        }

        try {
            $io->section('Starting Migration');

            if ($dryRun) {
                $io->info('This would migrate all ebook embeddings to Qdrant...');

                return Command::SUCCESS;
            }

            $result = $this->ebookEmbeddingService->syncAllEbookEmbeddingsToQdrant();

            $io->success('Migration completed!');
            $io->table(
                ['Metric', 'Count'],
                [
                    ['Total ebook embeddings', $result['total']],
                    ['Successfully synced', $result['synced']],
                    ['Errors', $result['errors']],
                ]
            );

            if ($result['errors'] > 0) {
                $io->warning("{$result['errors']} ebook embeddings failed to sync. Check logs for details.");
            }

            // Show final stats
            $finalStats = $this->ebookEmbeddingService->getQdrantCollectionStats();
            if ($finalStats) {
                $io->info('Final collection status:');
                $io->table(
                    ['Property', 'Value'],
                    [
                        ['Points Count', $finalStats['result']['points_count'] ?? 0],
                        ['Status', $finalStats['result']['status'] ?? 'N/A'],
                    ]
                );
            }
        } catch (\Exception $e) {
            $io->error('Migration failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
