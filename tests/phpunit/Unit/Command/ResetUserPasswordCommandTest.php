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

final class ResetUserPasswordCommandTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private EntityRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(EntityRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $this->entityManager
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $command = new ResetUserPasswordCommand($this->entityManager, $this->passwordHasher);
        $this->commandTester = new CommandTester($command);
    }

    public function testExecuteSuccessfullyResetsPassword(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPasswordHash('old_hash');
        $user->setMustChangePassword(true);

        $this->userRepository
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($user);

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'newpassword123')
            ->willReturn('hashed_new_password');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $exitCode = $this->commandTester->execute([
            'email' => 'test@example.com',
            'password' => 'newpassword123',
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Password for user "test@example.com" has been successfully reset.', $this->commandTester->getDisplay());

        // Verify password was updated
        $this->assertEquals('hashed_new_password', $user->getPasswordHash());
        // Verify mustChangePassword was set to false
        $this->assertFalse($user->isMustChangePassword());
        // Verify updatedAt was set
        $this->assertInstanceOf(\DateTime::class, $user->getUpdatedAt());
    }

    public function testExecuteFailsWithInvalidEmailFormat(): void
    {
        $exitCode = $this->commandTester->execute([
            'email' => 'invalid-email',
            'password' => 'newpassword123',
        ]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Invalid email format.', $this->commandTester->getDisplay());
    }

    public function testExecuteFailsWithShortPassword(): void
    {
        $exitCode = $this->commandTester->execute([
            'email' => 'test@example.com',
            'password' => 'short',
        ]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Password must be at least 8 characters long.', $this->commandTester->getDisplay());
    }

    public function testExecuteFailsWhenUserNotFound(): void
    {
        $this->userRepository
            ->method('findOneBy')
            ->with(['email' => 'nonexistent@example.com'])
            ->willReturn(null);

        $exitCode = $this->commandTester->execute([
            'email' => 'nonexistent@example.com',
            'password' => 'newpassword123',
        ]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('User with email "nonexistent@example.com" not found.', $this->commandTester->getDisplay());
    }

    public function testExecuteHandlesUserWithExistingMustChangePasswordFalse(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setMustChangePassword(false);

        $this->userRepository
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($user);

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'newpassword123')
            ->willReturn('hashed_new_password');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $exitCode = $this->commandTester->execute([
            'email' => 'test@example.com',
            'password' => 'newpassword123',
        ]);

        $this->assertEquals(0, $exitCode);
        // Verify mustChangePassword remains false
        $this->assertFalse($user->isMustChangePassword());
    }
}
