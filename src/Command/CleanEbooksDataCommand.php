<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Ebook;
use App\Entity\Tag;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clean:ebooks-data',
    description: 'Clean and prepare ebooks data before embedding processing',
)]
final class CleanEbooksDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be changed without actually making changes')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Batch size for processing ebooks per iteration', 10)
            ->addOption('max-iterations', null, InputOption::VALUE_REQUIRED, 'Maximum number of iterations to run', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $batchSize = (int) $input->getOption('batch-size');
        $maxIterations = $input->getOption('max-iterations') ? (int) $input->getOption('max-iterations') : null;

        $io->title('Czyszczenie danych książek');

        if ($dryRun) {
            $io->warning('DRY RUN MODE: No actual changes will be made');
        }

        $ebookRepository = $this->entityManager->getRepository(Ebook::class);
        $totalProcessed = 0;
        $totalFailed = 0;
        $totalTagsCreated = 0;
        $iteration = 0;

        while (true) {
            ++$iteration;

            // Check if we've reached max iterations
            if ($maxIterations && $iteration > $maxIterations) {
                $io->info(sprintf('Osiągnięto limit iteracji (%d). Zatrzymuję przetwarzanie.', $maxIterations));
                break;
            }

            // Find ebooks with null comparison_link for this batch
            $ebooks = $ebookRepository->findBy(['comparisonLink' => null], ['id' => 'ASC'], $batchSize);

            if (empty($ebooks)) {
                $io->success('Wszystkie książki zostały przetworzone!');
                break;
            }

            if (1 === $iteration) {
                $totalBooksToProcess = $ebookRepository->count(['comparisonLink' => null]);
                $io->info(sprintf('Znaleziono %d książek do wyczyszczenia', $totalBooksToProcess));
            }

            $io->section(sprintf('Iteracja %d: przetwarzanie %d książek', $iteration, count($ebooks)));

            $processedInBatch = 0;
            $failedInBatch = 0;
            $tagsCreatedInBatch = 0;

            foreach ($ebooks as $ebook) {
                try {
                    $io->text(sprintf('→ %s', $ebook->getIsbn()));

                    // 1. Clean main_description and format it
                    $cleanDescription = $this->cleanDescription($ebook->getMainDescription());
                    $formattedDescription = $this->formatDescription($ebook->getTitle(), $ebook->getAuthor(), $cleanDescription);

                    // 2. Set comparison link
                    $comparisonLink = sprintf('https://ebooki.swiatczytnikow.pl/ebook/%s', $ebook->getIsbn());

                    // 3. Extract and save tags
                    $newTagsCount = $this->extractAndSaveTags($ebook->getTags(), $dryRun);

                    if (!$dryRun) {
                        $ebook->setMainDescription($formattedDescription);
                        $ebook->setComparisonLink($comparisonLink);
                        $this->entityManager->persist($ebook);

                        $this->entityManager->flush();
                        $tagsCreatedInBatch += $newTagsCount;
                    } else {
                        $tagsCreatedInBatch += $newTagsCount; // In dry run, count what would be created
                    }

                    ++$processedInBatch;
                } catch (\Exception $e) {
                    ++$failedInBatch;
                    // Skip logging individual errors to avoid cluttering output
                    continue;
                }
            }

            // Clear entity manager after each batch
            if (!$dryRun) {
                $this->entityManager->clear();
            }

            // Update totals
            $totalProcessed += $processedInBatch;
            $totalFailed += $failedInBatch;
            $totalTagsCreated += $tagsCreatedInBatch;

            // Count remaining books
            $remainingBooks = $ebookRepository->count(['comparisonLink' => null]);

            $io->info(sprintf(
                'Iteracja %d zakończona: %d przetworzonych, %d błędów. Łącznie: %d/%d książek, %d tagów. Pozostało: %d',
                $iteration,
                $processedInBatch,
                $failedInBatch,
                $totalProcessed,
                $totalProcessed + $remainingBooks,
                $totalTagsCreated,
                $remainingBooks
            ));

            // Safety check: if all books in batch failed or very high error rate, stop processing
            $batchSize = count($ebooks); // Use actual batch size from this iteration
            if ($failedInBatch === $batchSize || (0 === $processedInBatch && $failedInBatch > 0)) {
                $io->error(sprintf(
                    'Wykryto zbyt wiele błędów w iteracji %d (%d błędów z %d książek). Przerywam przetwarzanie.',
                    $iteration,
                    $failedInBatch,
                    $batchSize
                ));
                break;
            }

            // Small delay between iterations to prevent overwhelming the system
            if (!$dryRun && $remainingBooks > 0) {
                sleep(1);
            }
        }

        $finalRemaining = $this->entityManager->getRepository(Ebook::class)->count(['comparisonLink' => null]);

        if (0 === $finalRemaining) {
            $io->success(sprintf(
                '✅ Wszystkie książki zostały przetworzone! Łącznie: %d książek, %d nowych tagów.',
                $totalProcessed,
                $totalTagsCreated
            ));
        } else {
            $io->warning(sprintf(
                '⚠️ Przetwarzanie zakończone. Przetworzono %d książek, utworzono %d tagów. Pozostało %d książek.',
                $totalProcessed,
                $totalTagsCreated,
                $finalRemaining
            ));
        }

        if ($totalFailed > 0) {
            $io->text(sprintf('Pominięto %d książek z powodu błędów.', $totalFailed));
        }

        return Command::SUCCESS;
    }

    private function cleanDescription(?string $description): string
    {
        if (null === $description) {
            return '';
        }

        // Remove HTML tags
        $cleanText = strip_tags($description);

        // Split into paragraphs
        $paragraphs = preg_split('/\n\s*\n/', $cleanText, -1, PREG_SPLIT_NO_EMPTY);

        // Take first 2 paragraphs or up to 900 characters
        $result = '';
        $charCount = 0;

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) {
                continue;
            }

            $paragraphLength = strlen($paragraph) + 1; // +1 for newline

            if ($charCount + $paragraphLength > 900) {
                // If adding this paragraph would exceed 900 chars, truncate current result
                $remainingChars = 900 - $charCount;
                if ($remainingChars > 0) {
                    $result .= substr($paragraph, 0, $remainingChars);
                }
                break;
            }

            $result .= $paragraph."\n\n";
            $charCount += $paragraphLength;

            if (substr_count($result, "\n\n") >= 2) {
                break; // Stop after 2 paragraphs
            }
        }

        return trim($result);
    }

    private function formatDescription(string $title, string $author, string $description): string
    {
        // Return only cleaned description without title and author
        return $description;
    }

    private function extractAndSaveTags(?string $tagsString, bool $dryRun): int
    {
        if (null === $tagsString) {
            return 0;
        }

        $tagRepository = $this->entityManager->getRepository(Tag::class);
        $created = 0;

        // Split tags by comma and clean them
        $tagNames = array_map('trim', explode(',', $tagsString));
        $tagNames = array_filter($tagNames); // Remove empty values

        $tagsToCreate = [];

        foreach ($tagNames as $tagName) {
            if (empty($tagName)) {
                continue;
            }

            // Skip tags that are too long for database field (VARCHAR(50))
            if (strlen($tagName) > 50) {
                continue;
            }

            // Check if tag already exists
            $existingTag = $tagRepository->findOneBy(['name' => $tagName]);
            if ($existingTag) {
                continue; // Skip existing tags
            }

            // Create ASCII version of tag name
            $asciiName = $this->slugify($tagName);

            // Check if ASCII version exists
            $existingAsciiTag = $tagRepository->findOneBy(['ascii' => $asciiName]);
            if ($existingAsciiTag) {
                continue; // Skip if ASCII version exists
            }

            $tagsToCreate[] = ['name' => $tagName, 'ascii' => $asciiName];
        }

        // Create tags one by one to handle duplicates gracefully
        if (!$dryRun && !empty($tagsToCreate)) {
            if (!$this->entityManager->isOpen()) {
                throw new \Exception('EntityManager jest zamknięty na początku tworzenia tagów');
            }

            foreach ($tagsToCreate as $tagData) {
                try {
                    // Double-check if tag was created by another process
                    $existingTag = $this->entityManager->getRepository(Tag::class)->findOneBy(['name' => $tagData['name']]);
                    if ($existingTag) {
                        continue; // Skip if already exists
                    }

                    $tag = new Tag();
                    $tag->setName($tagData['name']);
                    $tag->setAscii($tagData['ascii']);
                    $tag->setActive(true);

                    $this->entityManager->persist($tag);
                    $this->entityManager->flush();
                    ++$created;
                } catch (UniqueConstraintViolationException $e) {
                    // Tag already exists, continue
                    continue;
                } catch (\Exception $e) {
                    // For any other error (including EntityManager closed), continue with next tag
                    continue;
                }
            }
        } elseif ($dryRun) {
            $created = count($tagsToCreate);
        }

        return $created;
    }

    private function slugify(string $text): string
    {
        // Convert to lowercase
        $text = strtolower($text);

        // Replace non-alphanumeric characters with hyphens
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        // Remove leading/trailing hyphens
        $text = trim($text, '-');

        return $text;
    }
}
