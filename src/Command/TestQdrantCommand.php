<?php

declare(strict_types=1);

namespace App\Command;

use App\DTO\QdrantClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:qdrant',
    description: 'Test Qdrant vector database operations',
)]
final class TestQdrantCommand extends Command
{
    public function __construct(
        private QdrantClientInterface $qdrantClient
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('collection', null, InputOption::VALUE_REQUIRED, 'Collection name to test', 'ebooks')
            ->addOption('cleanup', null, InputOption::VALUE_NONE, 'Clean up test collection after operations')
            ->addOption('create-test-data', null, InputOption::VALUE_NONE, 'Create test ebook-like data instead of random vectors');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $collectionName = $input->getOption('collection');
        $cleanup = $input->getOption('cleanup');
        $createTestData = $input->getOption('create-test-data');

        $io->title('Testing Qdrant Vector Database Operations (Ebooks Collection)');

        try {
            // Test collection creation
            $io->section('Creating Collection');
            $vectorSize = 1536; // Same as OpenAI text-embedding-3-small
            $created = $this->qdrantClient->createCollection($collectionName, $vectorSize);

            if ($created) {
                $io->success("Collection '{$collectionName}' created successfully");
            } else {
                $io->warning("Collection '{$collectionName}' might already exist");
            }

            // Test collection info
            $info = $this->qdrantClient->getCollectionInfo($collectionName);
            if ($info) {
                $io->info('Collection Info:');
                $io->table(
                    ['Property', 'Value'],
                    [
                        ['Name', $info['result']['name'] ?? 'N/A'],
                        ['Vectors Size', $info['result']['vectors']['size'] ?? 'N/A'],
                        ['Distance', $info['result']['vectors']['distance'] ?? 'N/A'],
                        ['Points Count', $info['result']['points_count'] ?? 0],
                    ]
                );
            }

            // Test upserting points
            $io->section('Upserting Test Ebook Points');

            if ($createTestData) {
                // Use realistic ebook-like test data
                $testPoints = $this->createRealisticTestEbookData($vectorSize);
            } else {
                // Generate some test vectors (random for demo)
                $testPoints = [];
                for ($i = 1; $i <= 5; $i++) {
                    $vector = [];
                    for ($j = 0; $j < $vectorSize; $j++) {
                        $vector[] = (float) (mt_rand(-1000, 1000) / 1000); // Random float between -1 and 1
                    }

                    $testPoints[] = [
                        'id' => "ebook_{$i}",
                        'vector' => $vector,
                        'payload' => [
                            'title' => "Sample Ebook {$i}",
                            'author' => "Author {$i}",
                            'tags' => ["fiction", "bestseller", "ebook_{$i}"],
                            'ebook_id' => $i,
                            'isbn' => "978-0-123456-78-{$i}",
                            'created_at' => date('c'),
                        ],
                    ];
                }
            }

            $batchUpserted = $this->qdrantClient->upsertPoints($collectionName, $testPoints);

            if ($batchUpserted) {
                $io->success('Batch upsert completed successfully');
                $io->table(
                    ['Point ID', 'Title', 'Author'],
                    array_map(function($point) {
                        return [
                            $point['id'],
                            $point['payload']['title'],
                            $point['payload']['author']
                        ];
                    }, $testPoints)
                );
            }

            // Test search functionality
            $io->section('Testing Search Functionality');

            // Use the first test vector as query
            $queryVector = $testPoints[0]['vector'];
            $searchResults = $this->qdrantClient->search($collectionName, $queryVector, 3);

            if (!empty($searchResults)) {
                $io->success('Search completed successfully');
                $io->table(
                    ['ID', 'Score', 'Title'],
                    array_map(function($result) {
                        return [
                            $result['id'],
                            number_format($result['score'], 4),
                            $result['payload']['title'] ?? 'N/A'
                        ];
                    }, $searchResults)
                );
            } else {
                $io->warning('No search results found');
            }

            // Clean up if requested
            if ($cleanup) {
                $io->section('Cleaning Up');
                $deleted = $this->qdrantClient->deleteCollection($collectionName);
                if ($deleted) {
                    $io->success("Collection '{$collectionName}' deleted successfully");
                } else {
                    $io->warning("Failed to delete collection '{$collectionName}'");
                }
            }

            $io->success('All Qdrant tests completed successfully!');

        } catch (\Exception $e) {
            $io->error('Qdrant test failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Create realistic test ebook data with proper structure.
     */
    private function createRealisticTestEbookData(int $vectorSize): array
    {
        $ebooks = [
            [
                'id' => 'ebook_1984',
                'title' => '1984',
                'author' => 'George Orwell',
                'tags' => ['dystopian', 'classic', 'political'],
                'ebook_id' => 1,
                'isbn' => '978-0-452-28423-4',
            ],
            [
                'id' => 'ebook_dune',
                'title' => 'Dune',
                'author' => 'Frank Herbert',
                'tags' => ['sci-fi', 'space opera', 'epic'],
                'ebook_id' => 2,
                'isbn' => '978-0-441-17271-9',
            ],
            [
                'id' => 'ebook_pride_prejudice',
                'title' => 'Pride and Prejudice',
                'author' => 'Jane Austen',
                'tags' => ['romance', 'classic', 'regency'],
                'ebook_id' => 3,
                'isbn' => '978-0-14-143951-8',
            ],
            [
                'id' => 'ebook_fahrenheit_451',
                'title' => 'Fahrenheit 451',
                'author' => 'Ray Bradbury',
                'tags' => ['dystopian', 'sci-fi', 'censorship'],
                'ebook_id' => 4,
                'isbn' => '978-1-4516-7331-9',
            ],
            [
                'id' => 'ebook_to_kill_mockingbird',
                'title' => 'To Kill a Mockingbird',
                'author' => 'Harper Lee',
                'tags' => ['classic', 'coming-of-age', 'racism'],
                'ebook_id' => 5,
                'isbn' => '978-0-06-112008-4',
            ],
        ];

        $testPoints = [];
        foreach ($ebooks as $ebook) {
            // Generate realistic-ish vector (in real scenario this would come from OpenAI)
            $vector = [];
            for ($j = 0; $j < $vectorSize; $j++) {
                $vector[] = (float) (mt_rand(-1000, 1000) / 1000);
            }

            $testPoints[] = [
                'id' => $ebook['id'],
                'vector' => $vector,
                'payload' => [
                    'title' => $ebook['title'],
                    'author' => $ebook['author'],
                    'tags' => $ebook['tags'],
                    'ebook_id' => $ebook['ebook_id'],
                    'isbn' => $ebook['isbn'],
                    'created_at' => date('c'),
                ],
            ];
        }

        return $testPoints;
    }
}
