<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ImageHelper;
use PHPUnit\Framework\TestCase;

class ImageHelperTest extends TestCase
{
    public function testFormatIsbnForImagePathWithValidIsbn(): void
    {
        $isbn = '9788368590777';
        $expected = '978/83/6859/077/7';

        $result = ImageHelper::formatIsbnForImagePath($isbn);

        $this->assertEquals($expected, $result);
    }

    public function testFormatIsbnForImagePathWithHyphenatedIsbn(): void
    {
        $isbn = '978-83-6859-907-7';
        $expected = '978/83/6859/907/7';

        $result = ImageHelper::formatIsbnForImagePath($isbn);

        $this->assertEquals($expected, $result);
    }

    public function testFormatIsbnForImagePathWithInvalidLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ISBN must be 13 digits');

        ImageHelper::formatIsbnForImagePath('123');
    }

    public function testSlugifyBasicString(): void
    {
        $input = 'Test Book Title';
        $expected = 'test-book-title';

        $result = ImageHelper::slugify($input);

        $this->assertEquals($expected, $result);
    }

    public function testSlugifyWithSpecialCharacters(): void
    {
        $input = 'Dusza pokryta bliznami. Opowieści z meekhańskiego pogranicza';
        $expected = 'dusza-pokryta-bliznami-opowiesci-z-meekhanskiego-pogranicza';

        $result = ImageHelper::slugify($input);

        $this->assertEquals($expected, $result);
    }

    public function testSlugifyWithMultipleSpaces(): void
    {
        $input = 'Test   Book    Title';
        $expected = 'test-book-title';

        $result = ImageHelper::slugify($input);

        $this->assertEquals($expected, $result);
    }

    public function testSlugifyWithLeadingTrailingSpaces(): void
    {
        $input = '  Test Book Title  ';
        $expected = 'test-book-title';

        $result = ImageHelper::slugify($input);

        $this->assertEquals($expected, $result);
    }
}
