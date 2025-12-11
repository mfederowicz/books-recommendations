<?php

declare(strict_types=1);

namespace App\Command;

use App\DTO\OpenAIEmbeddingClientInterface;
use App\Entity\Ebook;
use App\Entity\EbookEmbedding;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:process:ebook-embeddings',
    description: 'Process embeddings for ebooks that don\'t have them yet',
)]
final class ProcessEbookEmbeddingsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OpenAIEmbeddingClientInterface $openAIEmbeddingClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'max-books',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Maximum number of ebooks to process in total',
                100
            )
            ->addOption(
                'batch-size',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Number of ebooks to process in one OpenAI API batch (max 10)',
                10
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be processed without actually doing it'
            );
    }

    /**
     * Generate a UUID v4.
     */
    private function generateUuid(): string
    {
        // Use Symfony's Uuid if available, otherwise fallback to random
        if (class_exists(\Symfony\Component\Uid\Uuid::class)) {
            return \Symfony\Component\Uid\Uuid::v4()->toRfc4122();
        }

        // Fallback implementation
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000,
            mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF)
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $maxBooks = (int) $input->getOption('max-books');
        $batchSize = (int) $input->getOption('batch-size');
        $dryRun = $input->getOption('dry-run');

        // Validate batch size
        if ($batchSize > 10) {
            $io->error('Batch size cannot exceed 10 (OpenAI API limit)');

            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->info('Running in dry-run mode - no changes will be made');
        }

        $io->title('Processing Ebook Embeddings (Batch Mode)');

        // Znajdź książki bez embeddingów
        $ebooksWithoutEmbeddings = $this->findEbooksWithoutEmbeddings($maxBooks);

        if (empty($ebooksWithoutEmbeddings)) {
            $io->success('All ebooks already have embeddings');

            return Command::SUCCESS;
        }

        $totalEbooks = count($ebooksWithoutEmbeddings);
        $io->info(sprintf('Found %d ebooks without embeddings', $totalEbooks));
        $io->info(sprintf('Will process in batches of %d ebooks each', $batchSize));

        // Podziel książki na batche
        $batches = array_chunk($ebooksWithoutEmbeddings, $batchSize);
        $totalBatches = count($batches);

        $io->info(sprintf('Created %d batches to process', $totalBatches));

        $progressBar = $io->createProgressBar($totalBatches);
        $progressBar->start();

        $totalProcessed = 0;
        $totalErrors = 0;

        foreach ($batches as $batchIndex => $batch) {
            try {
                if (!$dryRun) {
                    $batchProcessed = $this->processEbookBatch($batch);
                    $totalProcessed += $batchProcessed;
                } else {
                    $totalProcessed += count($batch);
                }

                $io->text(sprintf('Batch %d/%d: Processed %d ebooks', $batchIndex + 1, $totalBatches, count($batch)));
            } catch (\Exception $e) {
                $totalErrors += count($batch);
                $io->error(sprintf('Batch %d/%d failed: %s', $batchIndex + 1, $totalBatches, $e->getMessage()));
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        if ($dryRun) {
            $io->success(sprintf('Dry run completed. Would process %d ebooks in %d batches', $totalProcessed, $totalBatches));
        } else {
            $io->success(sprintf('Successfully processed %d ebooks in %d batches', $totalProcessed, $totalBatches));
            if ($totalErrors > 0) {
                $io->warning(sprintf('%d ebooks failed to process', $totalErrors));
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Znajdź książki bez embeddingów.
     */
    private function findEbooksWithoutEmbeddings(int $limit): array
    {
        return $this->entityManager->getRepository(Ebook::class)->findBy(
            ['hasEmbedding' => false],
            ['id' => 'ASC'],
            $limit
        );
    }

    /**
     * Przetwórz batch embeddingów dla grupy książek.
     *
     * @param Ebook[] $ebooks
     *
     * @return int Liczba pomyślnie przetworzonych książek
     */
    private function processEbookBatch(array $ebooks): int
    {
        if (empty($ebooks)) {
            return 0;
        }

        // Przygotuj payload dla batcha z UUID-ami
        $payloadData = [];
        $uuidMap = [];

        foreach ($ebooks as $ebook) {
            $uuid = $this->generateUuid();
            $payload = $this->preparePayload($ebook);

            $payloadData[$uuid] = $payload;
            $uuidMap[$uuid] = $ebook;
        }

        // Pobierz embeddingi z OpenAI w batchu
        $texts = array_values($payloadData);
        $embeddings = $this->openAIEmbeddingClient->getEmbeddingsBatch($texts);

        // Mapuj embeddingi z powrotem do UUID-ów
        $embeddingsByUuid = [];
        $uuids = array_keys($payloadData);
        foreach ($embeddings as $index => $embedding) {
            $uuid = $uuids[$index] ?? null;
            if ($uuid) {
                $embeddingsByUuid[$uuid] = $embedding;
            }
        }

        // Zapisz embeddingi do bazy
        $processed = 0;
        foreach ($embeddingsByUuid as $uuid => $embedding) {
            $ebook = $uuidMap[$uuid] ?? null;
            if (!$ebook) {
                continue;
            }

            // Przygotuj tagi
            $payloadTags = $this->parseTagsFromEbook($ebook);

            // Utwórz nowy embedding
            $ebookEmbedding = new EbookEmbedding();
            $ebookEmbedding->setEbookId($ebook->getIsbn());
            $ebookEmbedding->setVector($embedding);
            $ebookEmbedding->setPayloadTitle($ebook->getTitle());
            $ebookEmbedding->setPayloadAuthor($ebook->getAuthor());
            $ebookEmbedding->setPayloadTags($payloadTags);
            $ebookEmbedding->setPayloadDescription($ebook->getMainDescription());
            $ebookEmbedding->setPayloadUuid($uuid);

            // Ustaw flagę hasEmbedding na true
            $ebook->setHasEmbedding(true);

            $this->entityManager->persist($ebookEmbedding);
            ++$processed;
        }

        $this->entityManager->flush();

        return $processed;
    }

    /**
     * Przygotuj payload dla OpenAI w formacie: "{title}\n{author}\n{description}".
     */
    private function preparePayload(Ebook $ebook): string
    {
        $title = trim($ebook->getTitle());
        $author = trim($ebook->getAuthor());
        $description = trim($ebook->getMainDescription() ?? '');

        return sprintf("%s\n%s\n%s", $title, $author, $description);
    }

    /**
     * Parsuj tagi z ebook tags string do formatu array.
     */
    private function parseTagsFromEbook(Ebook $ebook): array
    {
        $tagsString = $ebook->getTags();

        if (null === $tagsString || empty(trim($tagsString))) {
            return [];
        }

        // Split tags by comma and clean them
        $tags = array_map('trim', explode(',', $tagsString));
        $tags = array_filter($tags, function ($tag) {
            return !empty($tag) && strlen($tag) <= 50; // Respect database constraint
        });

        return array_values($tags);
    }
}
