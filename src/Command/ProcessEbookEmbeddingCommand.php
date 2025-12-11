<?php

declare(strict_types=1);

namespace App\Command;

use App\DTO\OpenAIEmbeddingClientInterface;
use App\Entity\Ebook;
use App\Entity\EbookEmbedding;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:process:ebook-embedding',
    description: 'Process embedding for a specific ebook by ISBN',
)]
final class ProcessEbookEmbeddingCommand extends Command
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
            ->addArgument('isbn', InputArgument::REQUIRED, 'ISBN of the ebook to process')
            ->addOption('force', 'f', null, 'Force reprocessing even if embedding already exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isbn = $input->getArgument('isbn');
        $force = $input->getOption('force');

        $io->title('Processing Ebook Embedding');

        // Validate ISBN format (basic validation)
        if (empty($isbn) || !preg_match('/^[0-9]{10,13}$/', $isbn)) {
            $io->error('Invalid ISBN format. Expected 10-13 digits.');

            return Command::INVALID;
        }

        // Find ebook by ISBN
        $ebook = $this->entityManager->getRepository(Ebook::class)->findOneBy(['isbn' => $isbn]);

        if (null === $ebook) {
            $io->error(sprintf('Ebook with ISBN "%s" not found.', $isbn));

            return Command::FAILURE;
        }

        $io->info(sprintf('Found ebook: "%s" by %s', $ebook->getTitle(), $ebook->getAuthor()));

        // Check if embedding already exists
        $existingEmbedding = $this->entityManager->getRepository(EbookEmbedding::class)->findOneBy(['ebookId' => $isbn]);

        if (null !== $existingEmbedding && !$force) {
            $io->warning('Embedding already exists for this ebook. Use --force to overwrite.');

            return Command::SUCCESS;
        }

        if (null !== $existingEmbedding && $force) {
            $io->info('Removing existing embedding (force mode enabled)...');
            $this->entityManager->remove($existingEmbedding);
            // Set hasEmbedding to false when removing embedding
            $ebook->setHasEmbedding(false);
            $this->entityManager->flush();
        }

        try {
            // Prepare payload for OpenAI
            $payload = $this->preparePayload($ebook);
            $io->info('Prepared payload for OpenAI');
            $io->text('Payload length: '.strlen($payload).' characters');

            // Get embedding from OpenAI
            $io->info('Generating embedding from OpenAI...');
            $embedding = $this->openAIEmbeddingClient->getEmbedding($payload);

            $io->success('Embedding generated successfully!');
            $io->text('Embedding dimensions: '.count($embedding));

            // Prepare tags array for payload
            $payloadTags = $this->parseTagsFromEbook($ebook);

            // Create and save EbookEmbedding
            $ebookEmbedding = new EbookEmbedding();
            $ebookEmbedding->setEbookId($isbn);
            $ebookEmbedding->setVector($embedding);
            $ebookEmbedding->setPayloadTitle($ebook->getTitle());
            $ebookEmbedding->setPayloadAuthor($ebook->getAuthor());
            $ebookEmbedding->setPayloadTags($payloadTags);
            $ebookEmbedding->setPayloadDescription($ebook->getMainDescription());

            // Update ebook flag
            $ebook->setHasEmbedding(true);

            $this->entityManager->persist($ebookEmbedding);
            $this->entityManager->flush();

            $io->success('Embedding saved successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to process embedding: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Prepare payload for OpenAI in the format: "{title}\n{author}\n{description}".
     */
    private function preparePayload(Ebook $ebook): string
    {
        $title = trim($ebook->getTitle());
        $author = trim($ebook->getAuthor());
        $description = trim($ebook->getMainDescription() ?? '');

        return sprintf("%s\n%s\n%s", $title, $author, $description);
    }

    /**
     * Parse tags from ebook tags string into array format for payload.
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
