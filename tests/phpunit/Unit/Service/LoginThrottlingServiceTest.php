<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\UserFailedLogin;
use App\Service\LoginThrottlingService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class LoginThrottlingServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private LoginThrottlingService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new LoginThrottlingService($this->entityManager, 5, 15, 60);
    }

    public function testIsUserBlockedReturnsFalseWhenNoFailedLoginRecord(): void
    {
        $email = 'test@example.com';

        $repo = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(UserFailedLogin::class)
            ->willReturn($repo);

        $repo
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $email])
            ->willReturn(null);

        $result = $this->service->isUserBlocked($email);

        $this->assertFalse($result);
    }

    public function testIsUserBlockedReturnsBlockedStatus(): void
    {
        $email = 'test@example.com';

        $failedLogin = $this->createMock(UserFailedLogin::class);
        $failedLogin
            ->expects($this->once())
            ->method('isBlocked')
            ->willReturn(true);

        $repo = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->willReturn($repo);

        $repo
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $email])
            ->willReturn($failedLogin);

        $result = $this->service->isUserBlocked($email);

        $this->assertTrue($result);
    }

    public function testRecordFailedLoginAttemptCreatesNewRecord(): void
    {
        $email = 'test@example.com';
        $ipAddress = '192.168.1.1';

        $repo = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->willReturn($repo);

        $repo
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $email])
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($failedLogin) use ($email, $ipAddress) {
                return $failedLogin instanceof UserFailedLogin
                    && $failedLogin->getEmail() === $email
                    && $failedLogin->getIpAddress() === $ipAddress;
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->recordFailedLoginAttempt($email, $ipAddress);
    }

    public function testRecordFailedLoginAttemptIncrementsExistingRecord(): void
    {
        $email = 'test@example.com';

        $failedLogin = $this->createMock(UserFailedLogin::class);
        $failedLogin
            ->expects($this->once())
            ->method('incrementAttemptsCount');

        $failedLogin
            ->expects($this->once())
            ->method('setLastAttemptAt');

        $failedLogin
            ->expects($this->once())
            ->method('setIpAddress');

        $failedLogin
            ->expects($this->once())
            ->method('getAttemptsCount')
            ->willReturn(3); // Less than max attempts

        $repo = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->willReturn($repo);

        $repo
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($failedLogin);

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->recordFailedLoginAttempt($email);
    }

    public function testRecordFailedLoginAttemptBlocksUserAfterMaxAttempts(): void
    {
        $email = 'test@example.com';
        $maxAttempts = 5;

        $failedLogin = $this->createMock(UserFailedLogin::class);
        $failedLogin
            ->expects($this->once())
            ->method('incrementAttemptsCount');

        $failedLogin
            ->expects($this->once())
            ->method('setLastAttemptAt');

        $failedLogin
            ->expects($this->once())
            ->method('getAttemptsCount')
            ->willReturn($maxAttempts); // Exactly max attempts

        $failedLogin
            ->expects($this->once())
            ->method('setBlockedUntil');

        $repo = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->willReturn($repo);

        $repo
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($failedLogin);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->recordFailedLoginAttempt($email);
    }

    public function testClearFailedLoginAttemptsRemovesRecord(): void
    {
        $email = 'test@example.com';

        $failedLogin = $this->createMock(UserFailedLogin::class);

        $repo = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->willReturn($repo);

        $repo
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($failedLogin);

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($failedLogin);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->clearFailedLoginAttempts($email);
    }

    public function testClearFailedLoginAttemptsDoesNothingWhenNoRecord(): void
    {
        $email = 'test@example.com';

        $repo = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->willReturn($repo);

        $repo
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->never())
            ->method('remove');

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->service->clearFailedLoginAttempts($email);
    }

    public function testGetBlockedUntil(): void
    {
        $email = 'test@example.com';
        $blockedUntil = new \DateTime('+15 minutes');

        $failedLogin = $this->createMock(UserFailedLogin::class);
        $failedLogin
            ->expects($this->once())
            ->method('getBlockedUntil')
            ->willReturn($blockedUntil);

        $repo = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->willReturn($repo);

        $repo
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($failedLogin);

        $result = $this->service->getBlockedUntil($email);

        $this->assertSame($blockedUntil, $result);
    }

    public function testGetFailedAttemptsCount(): void
    {
        $email = 'test@example.com';
        $attemptsCount = 3;

        $failedLogin = $this->createMock(UserFailedLogin::class);
        $failedLogin
            ->expects($this->once())
            ->method('getAttemptsCount')
            ->willReturn($attemptsCount);

        $repo = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->willReturn($repo);

        $repo
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($failedLogin);

        $result = $this->service->getFailedAttemptsCount($email);

        $this->assertEquals($attemptsCount, $result);
    }

    public function testGetFailedAttemptsCountReturnsZeroWhenNoRecord(): void
    {
        $email = 'test@example.com';

        $repo = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->willReturn($repo);

        $repo
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $result = $this->service->getFailedAttemptsCount($email);

        $this->assertEquals(0, $result);
    }
}
