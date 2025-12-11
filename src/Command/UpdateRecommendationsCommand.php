<?php

declare(strict_types=1);

namespace App\Command;

use App\DTO\RecommendationServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:recommendations:update',
    description: 'Update recommendation results for books that need refreshing (cron-friendly)',
)]
final class UpdateRecommendationsCommand extends Command
{
    public function __construct(
        private RecommendationServiceInterface $recommendationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'max-recommendations',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Maximum number of recommendations to update (default: 100)',
                100
            )
            ->addOption(
                'batch-size',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Number of recommendations to process in one batch',
                10
            )
            ->addOption(
                'quiet',
                'q',
                InputOption::VALUE_NONE,
                'Suppress all output except errors (cron-friendly)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $maxRecommendations = (int) $input->getOption('max-recommendations');
        $batchSize = (int) $input->getOption('batch-size');
        $quiet = $input->getOption('quiet');

        // Use null output for quiet mode to suppress all output
        $io = $quiet ? new SymfonyStyle($input, new \Symfony\Component\Console\Output\NullOutput()) : new SymfonyStyle($input, $output);

        if (!$quiet) {
            $io->title('🔄 Updating Recommendation Results');
            $io->info("Processing up to $maxRecommendations recommendations in batches of $batchSize");
        }

        try {
            // Call the main search command with appropriate parameters
            // This will automatically find and update recommendations that need refreshing
            $command = $this->getApplication()->find('app:recommendations:search-books');

            $arguments = [
                '--max-recommendations' => $maxRecommendations,
                '--batch-size' => $batchSize,
            ];

            if ($quiet) {
                $arguments['--no-interaction'] = true;
            }

            // Execute the search command
            $exitCode = $command->run(new \Symfony\Component\Console\Input\ArrayInput($arguments), $output);

            if (!$quiet) {
                if ($exitCode === Command::SUCCESS) {
                    $io->success('Recommendation update completed successfully');
                } else {
                    $io->error('Recommendation update failed with exit code: ' . $exitCode);
                    return $exitCode;
                }
            }

            return $exitCode;

        } catch (\Exception $e) {
            if (!$quiet) {
                $io->error('Update failed: ' . $e->getMessage());

                if ($io->isVerbose()) {
                    $io->writeln('<comment>Stack trace:</comment>');
                    $io->writeln($e->getTraceAsString());
                }
            }

            return Command::FAILURE;
        }
    }
}
