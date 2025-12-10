<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\TextNormalizationService;
use PHPUnit\Framework\TestCase;

class TextNormalizationServiceTest extends TestCase
{
    private TextNormalizationService $service;

    protected function setUp(): void
    {
        $this->service = new TextNormalizationService();
    }

    public function testNormalizeTextConvertsToLowercase(): void
    {
        $result = $this->service->normalizeText('HELLO WORLD');

        $this->assertEquals('hello world', $result);
    }

    public function testNormalizeTextRemovesSpecialCharacters(): void
    {
        $result = $this->service->normalizeText('Hello! @#$% World?');

        $this->assertEquals('hello world', $result);
    }

    public function testNormalizeTextHandlesPolishCharacters(): void
    {
        $result = $this->service->normalizeText('Książka o Łódzkim Żołnierzu');

        $this->assertEquals('książka o łódzkim żołnierzu', $result);
    }

    public function testNormalizeTextReplacesMultipleSpacesWithSingle(): void
    {
        $result = $this->service->normalizeText('Hello   World    Test');

        $this->assertEquals('hello world test', $result);
    }

    public function testNormalizeTextTrimsWhitespace(): void
    {
        $result = $this->service->normalizeText('  Hello World  ');

        $this->assertEquals('hello world', $result);
    }

    public function testNormalizeTextHandlesEmptyString(): void
    {
        $result = $this->service->normalizeText('');

        $this->assertEquals('', $result);
    }

    public function testNormalizeTextHandlesOnlySpecialCharacters(): void
    {
        $result = $this->service->normalizeText('!@#$%^&*()');

        $this->assertEquals('', $result);
    }

    public function testNormalizeTextComplexExample(): void
    {
        $input = '  PRZYKŁADOWA   KSIĄŻKA!!! O   ŁÓDZKIM ŻOŁNIERZU?   ';
        $expected = 'przykładowa książka o łódzkim żołnierzu';

        $result = $this->service->normalizeText($input);

        $this->assertEquals($expected, $result);
    }

    public function testGenerateHash(): void
    {
        $text = 'test text';
        $expectedHash = hash('sha256', $text);

        $result = $this->service->generateHash($text);

        $this->assertEquals($expectedHash, $result);
        $this->assertEquals(64, strlen($result)); // SHA256 produces 64 character hash
    }

    public function testGenerateHashDifferentTextsProduceDifferentHashes(): void
    {
        $hash1 = $this->service->generateHash('text 1');
        $hash2 = $this->service->generateHash('text 2');

        $this->assertNotEquals($hash1, $hash2);
    }

    public function testGenerateHashSameTextProducesSameHash(): void
    {
        $text = 'same text';
        $hash1 = $this->service->generateHash($text);
        $hash2 = $this->service->generateHash($text);

        $this->assertEquals($hash1, $hash2);
    }

    public function testGenerateHashWithUnicode(): void
    {
        $text = 'książka';
        $result = $this->service->generateHash($text);

        $this->assertIsString($result);
        $this->assertEquals(64, strlen($result));
    }
}