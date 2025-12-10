<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ResetUserPasswordCommand;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ResetUserPasswordCommandTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private ResetUserPasswordCommand $command;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->command = new ResetUserPasswordCommand($this->entityManager, $this->passwordHasher);
    }

    public function testExecuteResetsPasswordSuccessfully(): void
    {
        $commandTester = new CommandTester($this->command);

        $email = 'test@example.com';
        $newPassword = 'newpassword123';
        $hashedPassword = 'hashed_password';

        // Mock user
        $user = $this->createMock(User::class);

        // Mock repository
        $userRepository = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($userRepository);

        $userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $email])
            ->willReturn($user);

        // Mock password hasher
        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with($user, $newPassword)
            ->willReturn($hashedPassword);

        // Expect user methods to be called
        $user
            ->expects($this->once())
            ->method('setPasswordHash')
            ->with($hashedPassword);

        $user
            ->expects($this->once())
            ->method('setUpdatedAt')
            ->with($this->isInstanceOf(\DateTime::class));

        $user
            ->expects($this->once())
            ->method('setMustChangePassword')
            ->with(false);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $exitCode = $commandTester->execute([
            'email' => $email,
            'password' => $newPassword,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Password for user "test@example.com" has been successfully reset', $commandTester->getDisplay());
    }

    public function testExecuteFailsWithInvalidEmail(): void
    {
        $commandTester = new CommandTester($this->command);

        $exitCode = $commandTester->execute([
            'email' => 'invalid-email',
            'password' => 'validpassword123',
        ]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Invalid email format', $commandTester->getDisplay());
    }

    public function testExecuteFailsWithShortPassword(): void
    {
        $commandTester = new CommandTester($this->command);

        $exitCode = $commandTester->execute([
            'email' => 'test@example.com',
            'password' => 'short',
        ]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Password must be at least 8 characters long', $commandTester->getDisplay());
    }

    public function testExecuteFailsWhenUserNotFound(): void
    {
        $commandTester = new CommandTester($this->command);

        $email = 'nonexistent@example.com';

        // Mock repository
        $userRepository = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($userRepository);

        $userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $email])
            ->willReturn(null);

        $exitCode = $commandTester->execute([
            'email' => $email,
            'password' => 'validpassword123',
        ]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('User with email "nonexistent@example.com" not found', $commandTester->getDisplay());
    }

    public function testCommandHasCorrectNameAndDescription(): void
    {
        $this->assertEquals('security:reset-user-passwd', $this->command->getName());
        $this->assertEquals('Reset user password by email address', $this->command->getDescription());
    }

    public function testCommandHasRequiredArguments(): void
    {
        $commandTester = new CommandTester($this->command);

        // Try to execute without arguments - this should throw an exception
        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments');

        $commandTester->execute([]);
    }
}