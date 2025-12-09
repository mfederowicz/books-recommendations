<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:tags',
    description: 'Seed the tags table with initial data',
)]
final class SeedTagsCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Seeding Tags Table');

        $tags = [
            // Genres
            ['name' => 'Fantasy', 'ascii' => 'fantasy'],
            ['name' => 'Science Fiction', 'ascii' => 'science-fiction'],
            ['name' => 'Romance', 'ascii' => 'romance'],
            ['name' => 'Mystery', 'ascii' => 'mystery'],
            ['name' => 'Thriller', 'ascii' => 'thriller'],
            ['name' => 'Horror', 'ascii' => 'horror'],
            ['name' => 'Historical Fiction', 'ascii' => 'historical-fiction'],
            ['name' => 'Biography', 'ascii' => 'biography'],
            ['name' => 'Memoir', 'ascii' => 'memoir'],
            ['name' => 'Self-Help', 'ascii' => 'self-help'],
            ['name' => 'Business', 'ascii' => 'business'],
            ['name' => 'Psychology', 'ascii' => 'psychology'],
            ['name' => 'Philosophy', 'ascii' => 'philosophy'],
            ['name' => 'History', 'ascii' => 'history'],
            ['name' => 'Travel', 'ascii' => 'travel'],
            ['name' => 'Cooking', 'ascii' => 'cooking'],
            ['name' => 'Art', 'ascii' => 'art'],
            ['name' => 'Music', 'ascii' => 'music'],
            ['name' => 'Poetry', 'ascii' => 'poetry'],
            ['name' => 'Drama', 'ascii' => 'drama'],
            ['name' => 'Comedy', 'ascii' => 'comedy'],
            ['name' => 'Adventure', 'ascii' => 'adventure'],
            ['name' => 'Crime', 'ascii' => 'crime'],
            ['name' => 'Western', 'ascii' => 'western'],
            ['name' => 'Young Adult', 'ascii' => 'young-adult'],
            ['name' => 'Children', 'ascii' => 'children'],
            ['name' => 'Literary Fiction', 'ascii' => 'literary-fiction'],
            ['name' => 'Classic', 'ascii' => 'classic'],
            ['name' => 'Contemporary', 'ascii' => 'contemporary'],
            ['name' => 'Dystopian', 'ascii' => 'dystopian'],
            ['name' => 'Magical Realism', 'ascii' => 'magical-realism'],
            ['name' => 'Graphic Novel', 'ascii' => 'graphic-novel'],
            ['name' => 'Short Stories', 'ascii' => 'short-stories'],
            ['name' => 'Essay', 'ascii' => 'essay'],
            ['name' => 'True Crime', 'ascii' => 'true-crime'],
            ['name' => 'Health', 'ascii' => 'health'],
            ['name' => 'Fitness', 'ascii' => 'fitness'],
            ['name' => 'Spirituality', 'ascii' => 'spirituality'],
            ['name' => 'Politics', 'ascii' => 'politics'],
            ['name' => 'Science', 'ascii' => 'science'],
            ['name' => 'Technology', 'ascii' => 'technology'],
            ['name' => 'Nature', 'ascii' => 'nature'],
            ['name' => 'Environment', 'ascii' => 'environment'],
            ['name' => 'Sports', 'ascii' => 'sports'],
            ['name' => 'Humor', 'ascii' => 'humor'],
            ['name' => 'Reference', 'ascii' => 'reference'],
            ['name' => 'Education', 'ascii' => 'education'],
            ['name' => 'Language', 'ascii' => 'language'],
            ['name' => 'Religion', 'ascii' => 'religion'],
            ['name' => 'Mythology', 'ascii' => 'mythology'],
        ];

        $tagRepository = $this->entityManager->getRepository(Tag::class);
        $created = 0;
        $skipped = 0;

        foreach ($tags as $tagData) {
            // Check if tag already exists
            $existingTag = $tagRepository->findOneBy(['name' => $tagData['name']]);
            if ($existingTag) {
                $skipped++;
                continue;
            }

            $tag = new Tag();
            $tag->setName($tagData['name']);
            $tag->setAscii($tagData['ascii']);
            $tag->setActive(true);

            $this->entityManager->persist($tag);
            $created++;
        }

        $this->entityManager->flush();

        if ($created > 0) {
            $io->success(sprintf('Successfully created %d new tags.', $created));
        }

        if ($skipped > 0) {
            $io->warning(sprintf('Skipped %d existing tags.', $skipped));
        }

        if ($created === 0 && $skipped === 0) {
            $io->info('No tags were processed.');
        }

        return Command::SUCCESS;
    }
}

