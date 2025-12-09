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
                'batch-size',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Number of ebooks to process in one batch',
                10
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be processed without actually doing it'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $batchSize = (int) $input->getOption('batch-size');
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->info('Running in dry-run mode - no changes will be made');
        }

        $io->title('Processing Ebook Embeddings');

        // Znajdź książki bez embeddingów
        $ebooksWithoutEmbeddings = $this->findEbooksWithoutEmbeddings($batchSize);

        if (empty($ebooksWithoutEmbeddings)) {
            $io->success('All ebooks already have embeddings');

            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d ebooks without embeddings', count($ebooksWithoutEmbeddings)));

        $progressBar = $io->createProgressBar(count($ebooksWithoutEmbeddings));
        $progressBar->start();

        $processed = 0;
        $errors = 0;

        foreach ($ebooksWithoutEmbeddings as $ebook) {
            try {
                if (!$dryRun) {
                    $this->processEbookEmbedding($ebook);
                }
                ++$processed;
            } catch (\Exception $e) {
                ++$errors;
                $io->error(sprintf('Failed to process ebook "%s": %s', $ebook->getTitle(), $e->getMessage()));
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        if ($dryRun) {
            $io->success(sprintf('Dry run completed. Would process %d ebooks', $processed));
        } else {
            $io->success(sprintf('Successfully processed %d ebooks', $processed));
            if ($errors > 0) {
                $io->warning(sprintf('%d ebooks failed to process', $errors));
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Znajdź książki bez embeddingów.
     */
    private function findEbooksWithoutEmbeddings(int $limit): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        return $qb->select('e')
            ->from(Ebook::class, 'e')
            ->leftJoin(EbookEmbedding::class, 'ee', 'WITH', 'ee.ebook = e.id')
            ->where($qb->expr()->isNull('ee.id'))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Przetwórz embedding dla jednej książki.
     */
    private function processEbookEmbedding(Ebook $ebook): void
    {
        // Przygotuj tekst do embedding - połączenie tytułu i autora
        $textToEmbed = sprintf('%s by %s', $ebook->getTitle(), $ebook->getAuthor());

        // Pobierz embedding z OpenAI
        $embedding = $this->openAIEmbeddingClient->getEmbedding($textToEmbed);

        // Utwórz nowy embedding
        $ebookEmbedding = new EbookEmbedding();
        $ebookEmbedding->setEbook($ebook);
        $ebookEmbedding->setVector($embedding);
        $ebookEmbedding->setPayloadTitle($ebook->getTitle());
        $ebookEmbedding->setPayloadAuthor($ebook->getAuthor());
        $ebookEmbedding->setPayloadTags([]); // Na razie puste, można rozszerzyć później

        $this->entityManager->persist($ebookEmbedding);
        $this->entityManager->flush();
    }
}

