<?php

declare(strict_types=1);

namespace App\DTO;

interface LoginThrottlingServiceInterface
{
    /**
     * Sprawdza czy użytkownik jest zablokowany z powodu zbyt wielu nieudanych prób logowania.
     *
     * @param string $email Email użytkownika
     *
     * @return bool True jeśli użytkownik jest zablokowany
     */
    public function isUserBlocked(string $email): bool;

    /**
     * Rejestruje nieudaną próbę logowania dla danego emaila.
     *
     * @param string      $email     Email użytkownika
     * @param string|null $ipAddress Adres IP użytkownika (opcjonalny)
     */
    public function recordFailedLoginAttempt(string $email, ?string $ipAddress = null): void;

    /**
     * Czyści historię nieudanych prób logowania dla danego emaila (po pomyślnym logowaniu).
     *
     * @param string $email Email użytkownika
     */
    public function clearFailedLoginAttempts(string $email): void;

    /**
     * Zwraca czas do którego użytkownik jest zablokowany.
     *
     * @param string $email Email użytkownika
     *
     * @return \DateTimeInterface|null Czas blokady lub null jeśli nie jest zablokowany
     */
    public function getBlockedUntil(string $email): ?\DateTimeInterface;

    /**
     * Zwraca liczbę nieudanych prób logowania dla danego emaila.
     *
     * @param string $email Email użytkownika
     *
     * @return int Liczba nieudanych prób
     */
    public function getFailedAttemptsCount(string $email): int;
}
