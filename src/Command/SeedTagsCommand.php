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

        $io->title('Wypełnianie tabeli tagów');

        $tags = [
            // Gatunki literackie
            ['name' => 'Fantastyka', 'ascii' => 'fantastyka'],
            ['name' => 'Science Fiction', 'ascii' => 'science-fiction'],
            ['name' => 'Romans', 'ascii' => 'romans'],
            ['name' => 'Kryminał', 'ascii' => 'kryminal'],
            ['name' => 'Thriller', 'ascii' => 'thriller'],
            ['name' => 'Horror', 'ascii' => 'horror'],
            ['name' => 'Powieść historyczna', 'ascii' => 'powiesc-historyczna'],
            ['name' => 'Biografia', 'ascii' => 'biografia'],
            ['name' => 'Pamiętnik', 'ascii' => 'pamietnik'],
            ['name' => 'Rozwój osobisty', 'ascii' => 'rozwoj-osobisty'],
            ['name' => 'Biznes', 'ascii' => 'biznes'],
            ['name' => 'Psychologia', 'ascii' => 'psychologia'],
            ['name' => 'Filozofia', 'ascii' => 'filozofia'],
            ['name' => 'Historia', 'ascii' => 'historia'],
            ['name' => 'Podróże', 'ascii' => 'podroze'],
            ['name' => 'Gotowanie', 'ascii' => 'gotowanie'],
            ['name' => 'Sztuka', 'ascii' => 'sztuka'],
            ['name' => 'Muzyka', 'ascii' => 'muzyka'],
            ['name' => 'Poezja', 'ascii' => 'poezja'],
            ['name' => 'Dramat', 'ascii' => 'dramat'],
            ['name' => 'Komedia', 'ascii' => 'komedia'],
            ['name' => 'Przygoda', 'ascii' => 'przygoda'],
            ['name' => 'Kryminalna', 'ascii' => 'kryminalna'],
            ['name' => 'Western', 'ascii' => 'western'],
            ['name' => 'Dla młodzieży', 'ascii' => 'dla-mlodziezy'],
            ['name' => 'Dla dzieci', 'ascii' => 'dla-dzieci'],
            ['name' => 'Literacka', 'ascii' => 'literacka'],
            ['name' => 'Klasyka', 'ascii' => 'klasyka'],
            ['name' => 'Współczesna', 'ascii' => 'wspolczesna'],
            ['name' => 'Dystopia', 'ascii' => 'dystopia'],
            ['name' => 'Realizm magiczny', 'ascii' => 'realizm-magiczny'],
            ['name' => 'Komiks', 'ascii' => 'komiks'],
            ['name' => 'Opowiadania', 'ascii' => 'opowiadania'],
            ['name' => 'Esej', 'ascii' => 'esej'],
            ['name' => 'Prawdziwa zbrodnia', 'ascii' => 'prawdziwa-zbrodnia'],
            ['name' => 'Zdrowie', 'ascii' => 'zdrowie'],
            ['name' => 'Fitness', 'ascii' => 'fitness'],
            ['name' => 'Duchowość', 'ascii' => 'duchowosc'],
            ['name' => 'Polityka', 'ascii' => 'polityka'],
            ['name' => 'Nauka', 'ascii' => 'nauka'],
            ['name' => 'Technologia', 'ascii' => 'technologia'],
            ['name' => 'Przyroda', 'ascii' => 'przyroda'],
            ['name' => 'Środowisko', 'ascii' => 'srodowisko'],
            ['name' => 'Sport', 'ascii' => 'sport'],
            ['name' => 'Humor', 'ascii' => 'humor'],
            ['name' => 'Poradnik', 'ascii' => 'poradnik'],
            ['name' => 'Edukacja', 'ascii' => 'edukacja'],
            ['name' => 'Język', 'ascii' => 'jezyk'],
            ['name' => 'Religia', 'ascii' => 'religia'],
            ['name' => 'Mitologia', 'ascii' => 'mitologia'],
        ];

        $tagRepository = $this->entityManager->getRepository(Tag::class);
        $created = 0;
        $skipped = 0;

        foreach ($tags as $tagData) {
            // Check if tag already exists
            $existingTag = $tagRepository->findOneBy(['name' => $tagData['name']]);
            if ($existingTag) {
                ++$skipped;
                continue;
            }

            $tag = new Tag();
            $tag->setName($tagData['name']);
            $tag->setAscii($tagData['ascii']);
            $tag->setActive(true);

            $this->entityManager->persist($tag);
            ++$created;
        }

        $this->entityManager->flush();

        if ($created > 0) {
            $io->success(sprintf('Pomyślnie utworzono %d nowych tagów.', $created));
        }

        if ($skipped > 0) {
            $io->warning(sprintf('Pominięto %d istniejących tagów.', $skipped));
        }

        if (0 === $created && 0 === $skipped) {
            $io->info('Żadne tagi nie zostały przetworzone.');
        }

        return Command::SUCCESS;
    }
}
