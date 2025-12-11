<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\EbookEmbeddingService;
use Doctrine\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be migrated without actually doing it')
            ->addOption('stats-only', null, InputOption::VALUE_NONE, 'Only show collection statistics without migrating')
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Number of embeddings to process in one batch', 50)
            ->addOption('sync-mode', 'm', InputOption::VALUE_OPTIONAL, 'Sync mode: unsynced (default), all, force', 'unsynced')
            ->addOption('reset-status', null, InputOption::VALUE_NONE, 'Reset sync status for all embeddings before starting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $statsOnly = $input->getOption('stats-only');
        $batchSize = (int) $input->getOption('batch-size');
        $syncMode = $input->getOption('sync-mode');
        $resetStatus = $input->getOption('reset-status');

        $io->title('Migrating Ebook Embeddings to Qdrant');

        if ($dryRun) {
            $io->warning('DRY RUN MODE: No actual changes will be made');
        }

        // Validate sync mode
        $validModes = ['unsynced', 'all', 'force'];
        if (!in_array($syncMode, $validModes, true)) {
            $io->error("Invalid sync mode '{$syncMode}'. Valid modes: ".implode(', ', $validModes));

            return Command::INVALID;
        }

        if ($statsOnly) {
            return $this->showStats($io);
        }

        try {
            $io->section('Starting Migration');

            if ($dryRun) {
                $this->showDryRunInfo($io, $syncMode, $batchSize);

                return Command::SUCCESS;
            }

            // Handle reset status
            if ($resetStatus) {
                $io->info('Resetting sync status for all embeddings...');
                $this->resetSyncStatus();
                $io->success('Sync status reset completed');
            }

            $result = $this->performMigration($io, $syncMode, $batchSize);

            $this->showMigrationResults($io, $result, $syncMode);

            $io->success('Migration completed successfully!');
        } catch (\Exception $e) {
            $io->error('Migration failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function showStats(SymfonyStyle $io): int
    {
        $io->section('Qdrant Collection Statistics');
        $stats = $this->ebookEmbeddingService->getQdrantCollectionStats();

        if (null === $stats) {
            $io->warning('Qdrant collection does not exist or is not accessible');

            return Command::FAILURE;
        }

        // Show collection info with new vector structure
        $vectorsInfo = $stats['result']['config']['params']['vectors'] ?? [];
        if (isset($vectorsInfo['size'])) {
            // Old format (single vector)
            $distance = $vectorsInfo['distance'] ?? 'N/A';
            $vectorsDisplay = "Size: {$vectorsInfo['size']}, Distance: {$distance}";
        } else {
            // New format (named vectors)
            $vectorsDisplay = [];
            foreach ($vectorsInfo as $name => $config) {
                $vectorsDisplay[] = "{$name}: size {$config['size']}, distance {$config['distance']}";
            }
            $vectorsDisplay = implode('; ', $vectorsDisplay);
        }

        // Show sync status from database
        $totalEmbeddings = $this->getTotalEmbeddingsCount();
        $syncedEmbeddings = $this->getSyncedEmbeddingsCount();
        $unsyncedEmbeddings = $totalEmbeddings - $syncedEmbeddings;

        $io->table(
            ['Property', 'Value'],
            [
                ['Collection Name', 'ebooks'],
                ['Vectors Config', $vectorsDisplay],
                ['Points Count (Qdrant)', $stats['result']['points_count'] ?? 0],
                ['Status', $stats['result']['status'] ?? 'N/A'],
                ['Total Embeddings (DB)', $totalEmbeddings],
                ['Synced to Qdrant', $syncedEmbeddings],
                ['Pending Sync', $unsyncedEmbeddings],
            ]
        );

        return Command::SUCCESS;
    }

    private function showDryRunInfo(SymfonyStyle $io, string $syncMode, int $batchSize): void
    {
        $totalEmbeddings = $this->getTotalEmbeddingsCount();
        $syncedEmbeddings = $this->getSyncedEmbeddingsCount();

        $io->info("Sync mode: {$syncMode}");
        $io->info("Batch size: {$batchSize}");

        switch ($syncMode) {
            case 'unsynced':
                $toSync = $totalEmbeddings - $syncedEmbeddings;
                $io->info("Would sync {$toSync} unsynchronized embeddings");
                break;
            case 'all':
                $io->info("Would sync all {$totalEmbeddings} embeddings (including already synced)");
                break;
            case 'force':
                $io->info("Would force sync all {$totalEmbeddings} embeddings (reset status first)");
                break;
        }
    }

    private function resetSyncStatus(): void
    {
        // Reset all embeddings to unsynced status
        $embeddings = $this->entityManager
            ->getRepository(\App\Entity\EbookEmbedding::class)
            ->findAll();

        foreach ($embeddings as $embedding) {
            $embedding->setSyncedToQdrant(false);
        }

        $this->entityManager->flush();
    }

    private function performMigration(SymfonyStyle $io, string $syncMode, int $batchSize): array
    {
        $io->info("Migrating embeddings in batches of {$batchSize}...");

        switch ($syncMode) {
            case 'unsynced':
                return $this->ebookEmbeddingService->syncUnsyncedEbookEmbeddingsToQdrant();
            case 'all':
                return $this->ebookEmbeddingService->syncAllEbookEmbeddingsToQdrant();
            case 'force':
                // Force mode: reset status and sync all
                $this->resetSyncStatus();

                return $this->ebookEmbeddingService->syncAllEbookEmbeddingsToQdrant();
            default:
                throw new \InvalidArgumentException("Unknown sync mode: {$syncMode}");
        }
    }

    private function showMigrationResults(SymfonyStyle $io, array $result, string $syncMode): void
    {
        $io->success('Migration completed!');

        $description = match ($syncMode) {
            'unsynced' => 'Unsynced ebook embeddings',
            'all' => 'All ebook embeddings (including already synced)',
            'force' => 'All ebook embeddings (force sync)',
        };

        $io->table(
            ['Metric', 'Count'],
            [
                ["Total {$description}", $result['total'] ?? 0],
                ['Successfully synced', $result['synced'] ?? 0],
                ['Errors', $result['errors'] ?? 0],
            ]
        );

        if (($result['errors'] ?? 0) > 0) {
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
    }

    private function getTotalEmbeddingsCount(): int
    {
        return $this->entityManager
            ->getRepository(\App\Entity\EbookEmbedding::class)
            ->count([]);
    }

    private function getSyncedEmbeddingsCount(): int
    {
        return $this->entityManager
            ->getRepository(\App\Entity\EbookEmbedding::class)
            ->count(['syncedToQdrant' => true]);
    }
}
