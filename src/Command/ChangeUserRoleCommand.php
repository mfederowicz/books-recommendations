<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:change-role',
    description: 'Change a user role (user, admin, read_only)',
)]
final class ChangeUserRoleCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email to change role for')
            ->addArgument('role', InputArgument::REQUIRED, 'New role for the user', null, ['user', 'admin', 'read_only'])
            ->setHelp('This command allows you to change a user role by providing their email address and the desired role (user, admin, read_only).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $newRole = $input->getArgument('role');

        // Validate role
        $allowedRoles = ['user', 'admin', 'read_only'];
        if (!in_array($newRole, $allowedRoles, true)) {
            $io->error(sprintf('Invalid role "%s". Allowed roles are: %s', $newRole, implode(', ', $allowedRoles)));
            return Command::FAILURE;
        }

        // Find user by email
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error(sprintf('User with email "%s" not found.', $email));
            return Command::FAILURE;
        }

        // Check if user already has the requested role
        if ($user->getRole() === $newRole) {
            $io->warning(sprintf('User "%s" already has the role "%s".', $email, $newRole));
            return Command::SUCCESS;
        }

        // Change user role
        $oldRole = $user->getRole();
        $user->setRole($newRole);
        $user->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        $io->success(sprintf('User "%s" role has been changed from "%s" to "%s".', $email, $oldRole, $newRole));

        return Command::SUCCESS;
    }
}
