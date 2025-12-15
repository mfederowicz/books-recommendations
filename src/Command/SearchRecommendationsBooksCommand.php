<?php

declare(strict_types=1);

namespace App\Command;

use App\DTO\RecommendationServiceInterface;
use App\Entity\Recommendation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:recommendations:search-books',
    description: 'Search and store similar books for user recommendations',
)]
final class SearchRecommendationsBooksCommand extends Command
{
    public function __construct(
        private RecommendationServiceInterface $recommendationService,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'recommendation-id',
                'r',
                InputOption::VALUE_OPTIONAL,
                'Process only specific recommendation by ID'
            )
            ->addOption(
                'batch-size',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Number of recommendations to process in one batch',
                50
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Run without making actual changes to database'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force re-search even if recommendation already has results'
            )
            ->addOption(
                'max-recommendations',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Maximum number of recommendations to process (useful for testing)',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $recommendationId = $input->getOption('recommendation-id') ? (int) $input->getOption('recommendation-id') : null;
        $batchSize = (int) $input->getOption('batch-size');
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');
        $maxRecommendations = $input->getOption('max-recommendations') ? (int) $input->getOption('max-recommendations') : null;
        $noInteraction = !$input->isInteractive();

        $io->title('🔍 Searching Books for Recommendations');

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No changes will be made to database');
        }

        try {
            // Get recommendations to process
            $recommendations = $this->getRecommendationsToProcess($recommendationId, $force, $maxRecommendations);

            if (empty($recommendations)) {
                $io->success('No recommendations need processing.');

                return Command::SUCCESS;
            }

            $totalCount = count($recommendations);
            $io->info("Found $totalCount recommendation(s) to process");

            // Show summary table
            $this->displayRecommendationsSummary($recommendations, $io);

            if ($noInteraction || $io->confirm('Do you want to proceed with processing these recommendations?', false)) {
                // Proceed with processing
            } else {
                $io->info('Operation cancelled by user.');

                return Command::SUCCESS;
            }

            // Process recommendations
            return $this->processRecommendations($recommendations, $batchSize, $dryRun, $io);
        } catch (\Exception $e) {
            $io->error('Command failed: '.$e->getMessage());

            if ($io->isVerbose()) {
                $io->writeln('<comment>Stack trace:</comment>');
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Get recommendations that need processing.
     */
    private function getRecommendationsToProcess(?int $recommendationId, bool $force, ?int $maxRecommendations): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(Recommendation::class, 'r')
            ->orderBy('r.createdAt', 'ASC');

        if ($recommendationId) {
            $qb->where('r.id = :id')
               ->setParameter('id', $recommendationId);
        } elseif (!$force) {
            // Only process recommendations that don't have results yet or haven't been searched recently
            // Strategy: update recommendations that are older than 7 days or have no results
            $qb->leftJoin('r.recommendationResults', 'rr')
               ->groupBy('r.id')
               ->having('COUNT(rr.id) = 0 OR r.lastSearchAt IS NULL OR r.lastSearchAt < :oneWeekAgo')
               ->setParameter('oneWeekAgo', new \DateTime('-7 days'));
        }

        if ($maxRecommendations) {
            $qb->setMaxResults($maxRecommendations);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Display summary table of recommendations to process.
     */
    private function displayRecommendationsSummary(array $recommendations, SymfonyStyle $io): void
    {
        $tableData = [];

        foreach ($recommendations as $recommendation) {
            $tableData[] = [
                $recommendation->getId(),
                $this->truncateText($recommendation->getShortDescription(), 50),
                $recommendation->getFoundBooksCount(),
                $recommendation->getLastSearchAt()?->format('Y-m-d H:i:s') ?? 'Never',
                $recommendation->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        $io->table(
            ['ID', 'Description', 'Found Books', 'Last Search', 'Created'],
            $tableData
        );
    }

    /**
     * Process recommendations in batches.
     */
    private function processRecommendations(array $recommendations, int $batchSize, bool $dryRun, SymfonyStyle $io): int
    {
        $totalCount = count($recommendations);
        $processedCount = 0;
        $successCount = 0;
        $errorCount = 0;

        $progressBar = $io->createProgressBar($totalCount);
        $progressBar->setFormat('verbose');

        foreach (array_chunk($recommendations, $batchSize) as $batch) {
            foreach ($batch as $recommendation) {
                try {
                    if (!$dryRun) {
                        $this->recommendationService->searchAndStoreSimilarEbooks($recommendation);
                    }

                    $progressBar->setMessage(sprintf(
                        'Processed recommendation #%d (%s)',
                        $recommendation->getId(),
                        $this->truncateText($recommendation->getShortDescription(), 30)
                    ));

                    ++$successCount;
                } catch (\Exception $e) {
                    ++$errorCount;
                    $progressBar->setMessage(sprintf(
                        'ERROR on recommendation #%d: %s',
                        $recommendation->getId(),
                        $e->getMessage()
                    ));

                    if ($io->isVerbose()) {
                        $io->error("Recommendation #{$recommendation->getId()}: ".$e->getMessage());
                    }
                }

                ++$processedCount;
                $progressBar->advance();
            }

            // Clear entity manager to free memory
            $this->entityManager->clear();
        }

        $progressBar->finish();
        $io->newLine(2);

        // Show final statistics
        $io->section('📊 Processing Summary');

        $statsTable = [
            ['Total recommendations', $totalCount],
            ['Successfully processed', $successCount],
            ['Errors', $errorCount],
            ['Dry run mode', $dryRun ? 'Yes' : 'No'],
        ];

        $io->table(['Metric', 'Value'], $statsTable);

        if ($errorCount > 0) {
            $io->warning("$errorCount recommendation(s) failed to process. Check logs for details.");

            return Command::FAILURE;
        }

        $io->success("Successfully processed $successCount recommendation(s)!");

        return Command::SUCCESS;
    }

    /**
     * Truncate text to specified length.
     */
    private function truncateText(?string $text, int $maxLength): string
    {
        if (!$text) {
            return '';
        }

        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return substr($text, 0, $maxLength - 3).'...';
    }
}
