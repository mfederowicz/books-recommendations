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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'security:reset-user-passwd',
    description: 'Reset user password by email address',
)]
final class ResetUserPasswordCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email to reset password for')
            ->addArgument('password', InputArgument::REQUIRED, 'New password for the user')
            ->setHelp('This command allows you to reset a user password by providing their email address and the new password.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $newPassword = $input->getArgument('password');

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Invalid email format.');

            return Command::FAILURE;
        }

        // Validate password length (basic validation)
        if (strlen($newPassword) < 8) {
            $io->error('Password must be at least 8 characters long.');

            return Command::FAILURE;
        }

        // Find user by email
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error(sprintf('User with email "%s" not found.', $email));

            return Command::FAILURE;
        }

        // Hash the new password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);

        // Update user password
        $user->setPasswordHash($hashedPassword);
        $user->setUpdatedAt(new \DateTime());

        // Optionally set mustChangePassword to false if it was set
        $user->setMustChangePassword(false);

        $this->entityManager->flush();

        $io->success(sprintf('Password for user "%s" has been successfully reset.', $email));

        return Command::SUCCESS;
    }
}
