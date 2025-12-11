<?php

declare(strict_types=1);

namespace App\Command;

use App\DTO\RecommendationServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:search:books',
    description: 'Search for books in Qdrant using text similarity',
)]
final class SearchBooksCommand extends Command
{
    public function __construct(
        private RecommendationServiceInterface $recommendationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('text', InputArgument::OPTIONAL, 'Text to search for similar books', 'fantasy adventure with dragons and magic')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Maximum number of results to return', 10)
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output results as JSON instead of table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $text = $input->getArgument('text');
        $limit = (int) $input->getOption('limit');
        $jsonOutput = $input->getOption('json');

        $io->title('🔍 Searching Books in Qdrant');
        $io->info("Query: \"$text\"");
        $io->info("Limit: $limit results");

        try {
            $results = $this->recommendationService->findSimilarEbooks($text, $limit);

            if (empty($results)) {
                $io->warning('No similar books found.');
                $io->note([
                    'Possible reasons:',
                    '- No books have been synced to Qdrant yet',
                    '- Qdrant service is not accessible',
                    '- The search text is not similar to any book descriptions',
                ]);

                // Check Qdrant stats
                $stats = $this->recommendationService->getQdrantStats();

                if ($stats) {
                    $io->info('Qdrant collection status:');
                    $io->table(
                        ['Property', 'Value'],
                        [
                            ['Points Count', $stats['result']['points_count'] ?? 0],
                            ['Status', $stats['result']['status'] ?? 'Unknown'],
                        ]
                    );
                } else {
                    $io->error('Cannot connect to Qdrant. Please check if the service is running.');
                }

                return Command::FAILURE;
            }

            if ($jsonOutput) {
                $this->outputJson($results, $text, $io);
            } else {
                $this->outputTable($results, $io);
            }

            $io->success('Found '.count($results).' similar books!');
        } catch (\Exception $e) {
            $io->error('Search failed: '.$e->getMessage());

            if ($io->isVerbose()) {
                $io->writeln('<comment>Stack trace:</comment>');
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function outputTable(array $results, SymfonyStyle $io): void
    {
        $tableData = [];

        foreach ($results as $index => $result) {
            $ebook = $result['ebook'];
            $score = $result['similarity_score'];

            $tableData[] = [
                $index + 1,
                $ebook->getTitle(),
                $ebook->getAuthor(),
                $ebook->getIsbn(),
                number_format($score, 4),
                $this->truncateText($ebook->getTags(), 30),
            ];
        }

        $io->table(
            ['#', 'Title', 'Author', 'ISBN', 'Similarity', 'Tags'],
            $tableData
        );

        // Show detailed info for top results
        $io->section('📚 Top Results Details');
        foreach (array_slice($results, 0, 3) as $index => $result) {
            $ebook = $result['ebook'];
            $score = $result['similarity_score'];

            $io->writeln(sprintf(
                '<info>%d. %s</info> by <comment>%s</comment> (Similarity: <options=bold>%.4f</>)',
                $index + 1,
                $ebook->getTitle(),
                $ebook->getAuthor(),
                $score
            ));

            if ($ebook->getTags()) {
                $io->writeln('   <comment>Tags:</comment> '.$ebook->getTags());
            }

            if ($ebook->getMainDescription()) {
                $description = $this->truncateText($ebook->getMainDescription(), 150);
                $io->writeln('   <comment>Description:</comment> '.$description);
            }

            $io->newLine();
        }
    }

    private function outputJson(array $results, string $query, SymfonyStyle $io): void
    {
        $books = array_map(function ($result) {
            $ebook = $result['ebook'];

            return [
                'id' => $ebook->getId(),
                'isbn' => $ebook->getIsbn(),
                'title' => $ebook->getTitle(),
                'author' => $ebook->getAuthor(),
                'description' => $ebook->getMainDescription(),
                'tags' => $ebook->getTags(),
                'similarity_score' => round($result['similarity_score'], 4),
                'comparison_link' => $ebook->getComparisonLink(),
            ];
        }, $results);

        $output = [
            'query' => $query,
            'total_results' => count($books),
            'books' => $books,
        ];

        $io->writeln(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

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
