<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\TextNormalizationService;
use PHPUnit\Framework\TestCase;

final class TextNormalizationServiceTest extends TestCase
{
    private TextNormalizationService $textNormalizationService;

    protected function setUp(): void
    {
        $this->textNormalizationService = new TextNormalizationService();
    }

    public function testNormalizeTextConvertsToLowercase(): void
    {
        $input = 'HELLO WORLD';
        $expected = 'hello world';

        $result = $this->textNormalizationService->normalizeText($input);

        $this->assertEquals($expected, $result);
    }

    public function testNormalizeTextRemovesSpecialCharacters(): void
    {
        $input = 'Hello! @World# $Test%';
        $expected = 'hello world test';

        $result = $this->textNormalizationService->normalizeText($input);

        $this->assertEquals($expected, $result);
    }

    public function testNormalizeTextHandlesPolishCharacters(): void
    {
        $input = 'KSIĄŻKA ĆMA ŚWIAT';
        $expected = 'książka ćma świat';

        $result = $this->textNormalizationService->normalizeText($input);

        $this->assertEquals($expected, $result);
    }

    public function testNormalizeTextReplacesMultipleSpacesWithSingleSpace(): void
    {
        $input = 'Hello    World   Test';
        $expected = 'hello world test';

        $result = $this->textNormalizationService->normalizeText($input);

        $this->assertEquals($expected, $result);
    }

    public function testNormalizeTextTrimsLeadingAndTrailingSpaces(): void
    {
        $input = '  Hello World  ';
        $expected = 'hello world';

        $result = $this->textNormalizationService->normalizeText($input);

        $this->assertEquals($expected, $result);
    }

    public function testNormalizeTextHandlesComplexInput(): void
    {
        $input = '  To JEST! @PrzyKŁad#   $TEkstu%  Z różnymi ZNAKAMI!!!  ';
        $expected = 'to jest przykład tekstu z różnymi znakami';

        $result = $this->textNormalizationService->normalizeText($input);

        $this->assertEquals($expected, $result);
    }

    public function testNormalizeTextHandlesEmptyString(): void
    {
        $input = '';
        $expected = '';

        $result = $this->textNormalizationService->normalizeText($input);

        $this->assertEquals($expected, $result);
    }

    public function testNormalizeTextHandlesStringWithOnlySpecialCharacters(): void
    {
        $input = '!@#$%^&*()';
        $expected = '';

        $result = $this->textNormalizationService->normalizeText($input);

        $this->assertEquals($expected, $result);
    }

    public function testNormalizeTextPreservesNumbers(): void
    {
        $input = 'Book 123 Test 456';
        $expected = 'book 123 test 456';

        $result = $this->textNormalizationService->normalizeText($input);

        $this->assertEquals($expected, $result);
    }

    public function testGenerateHashReturnsSha256Hash(): void
    {
        $input = 'hello world';
        $hash = $this->textNormalizationService->generateHash($input);

        // SHA256 hash should be 64 characters long and contain only hex characters
        $this->assertEquals(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    public function testGenerateHashIsConsistent(): void
    {
        $input = 'test input';
        $hash1 = $this->textNormalizationService->generateHash($input);
        $hash2 = $this->textNormalizationService->generateHash($input);

        $this->assertEquals($hash1, $hash2);
    }

    public function testGenerateHashReturnsDifferentHashesForDifferentInputs(): void
    {
        $input1 = 'hello world';
        $input2 = 'hello world 2';

        $hash1 = $this->textNormalizationService->generateHash($input1);
        $hash2 = $this->textNormalizationService->generateHash($input2);

        $this->assertNotEquals($hash1, $hash2);
    }

    public function testGenerateHashWithEmptyString(): void
    {
        $input = '';
        $hash = $this->textNormalizationService->generateHash($input);

        // SHA256 of empty string
        $expectedHash = hash('sha256', '');
        $this->assertEquals($expectedHash, $hash);
    }
}

