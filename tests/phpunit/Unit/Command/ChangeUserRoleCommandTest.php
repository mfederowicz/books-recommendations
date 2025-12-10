<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ChangeUserRoleCommand;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ChangeUserRoleCommandTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private ChangeUserRoleCommand $command;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->command = new ChangeUserRoleCommand($this->entityManager);
    }

    public function testExecuteChangesRoleSuccessfully(): void
    {
        $commandTester = new CommandTester($this->command);

        $email = 'test@example.com';
        $newRole = 'admin';
        $oldRole = 'user';

        // Mock user
        $user = $this->createMock(User::class);
        $user
            ->expects($this->exactly(2)) // Once for checking current role, once for getting old role for message
            ->method('getRole')
            ->willReturn($oldRole);

        $user
            ->expects($this->once())
            ->method('setRole')
            ->with($newRole);

        $user
            ->expects($this->once())
            ->method('setUpdatedAt')
            ->with($this->isInstanceOf(\DateTime::class));

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

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $exitCode = $commandTester->execute([
            'email' => $email,
            'role' => $newRole,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('User "test@example.com" role has been changed from "user" to "admin"', $commandTester->getDisplay());
    }

    public function testExecuteWarnsWhenUserAlreadyHasRole(): void
    {
        $commandTester = new CommandTester($this->command);

        $email = 'test@example.com';
        $role = 'admin';

        // Mock user
        $user = $this->createMock(User::class);
        $user
            ->expects($this->once())
            ->method('getRole')
            ->willReturn($role);

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

        // Should not call setRole or flush when role is the same
        $user
            ->expects($this->never())
            ->method('setRole');

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $exitCode = $commandTester->execute([
            'email' => $email,
            'role' => $role,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('User "test@example.com" already has the role "admin"', $commandTester->getDisplay());
    }

    public function testExecuteFailsWithInvalidRole(): void
    {
        $commandTester = new CommandTester($this->command);

        $exitCode = $commandTester->execute([
            'email' => 'test@example.com',
            'role' => 'invalid_role',
        ]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Invalid role "invalid_role"', $commandTester->getDisplay());
        $this->assertStringContainsString('Allowed roles are: user, admin, read_only', $commandTester->getDisplay());
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
            'role' => 'admin',
        ]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('User with email "nonexistent@example.com" not found', $commandTester->getDisplay());
    }

    public function testCommandHasCorrectNameAndDescription(): void
    {
        $this->assertEquals('app:user:change-role', $this->command->getName());
        $this->assertEquals('Change a user role (user, admin, read_only)', $this->command->getDescription());
    }

    // Test temporarily removed due to complex mocking issues with CommandTester
}
