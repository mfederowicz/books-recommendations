<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\ImageExtension;
use PHPUnit\Framework\TestCase;

class ImageExtensionTest extends TestCase
{
    private ImageExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new ImageExtension();
    }

    public function testGetFunctions(): void
    {
        $functions = $this->extension->getFunctions();

        $this->assertCount(2, $functions);
        $this->assertEquals('book_cover_url', $functions[0]->getName());
        $this->assertEquals('book_comparison_url', $functions[1]->getName());
    }

    public function testGenerateBookCoverUrlWithValidData(): void
    {
        $isbn = '9788368590777';
        $title = 'Dusza pokryta bliznami';
        $author = 'Robert M Wegner';

        $result = $this->extension->generateBookCoverUrl($isbn, $title, $author);

        $expected = '//static.swiatczytnikow.pl/img/covers/978/83/6859/077/7/big/dusza-pokryta-bliznami-robert-m-wegner.jpg';
        $this->assertEquals($expected, $result);
    }

    public function testGenerateBookCoverUrlWithEmptyIsbn(): void
    {
        $result = $this->extension->generateBookCoverUrl('', 'Title', 'Author');

        $this->assertEquals('', $result);
    }

    public function testGenerateBookCoverUrlWithNullIsbn(): void
    {
        $result = $this->extension->generateBookCoverUrl(null, 'Title', 'Author');

        $this->assertEquals('', $result);
    }

    public function testGenerateBookCoverUrlWithInvalidIsbn(): void
    {
        $result = $this->extension->generateBookCoverUrl('123', 'Title', 'Author');

        $this->assertEquals('', $result);
    }

    public function testGenerateBookComparisonUrlWithValidData(): void
    {
        $isbn = '9788368590777';
        $title = 'Dusza pokryta bliznami';
        $author = 'Robert M Wegner';

        $result = $this->extension->generateBookComparisonUrl($isbn, $title, $author);

        $expected = 'https://ebooki.swiatczytnikow.pl/ebook/9788368590777,dusza-pokryta-bliznami-robert-m-wegner.html?utm_source=polecenia&utm_medium=recommendation&utm_campaign=9788368590777';
        $this->assertEquals($expected, $result);
    }

    public function testGenerateBookComparisonUrlWithEmptyIsbn(): void
    {
        $result = $this->extension->generateBookComparisonUrl('', 'Title', 'Author');

        $this->assertEquals('', $result);
    }

    public function testGenerateBookComparisonUrlWithNullIsbn(): void
    {
        $result = $this->extension->generateBookComparisonUrl(null, 'Title', 'Author');

        $this->assertEquals('', $result);
    }

    public function testGenerateBookComparisonUrlWithInvalidIsbn(): void
    {
        $result = $this->extension->generateBookComparisonUrl('123', 'Title', 'Author');

        $this->assertEquals('', $result);
    }
}
