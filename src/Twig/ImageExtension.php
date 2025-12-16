<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\ImageHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ImageExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('book_cover_url', [$this, 'generateBookCoverUrl']),
            new TwigFunction('book_comparison_url', [$this, 'generateBookComparisonUrl']),
        ];
    }

    public function generateBookCoverUrl(?string $isbn, string $title, string $author): string
    {
        if (empty($isbn)) {
            return '';
        }

        try {
            $isbnPath = ImageHelper::formatIsbnForImagePath($isbn);
            $slugifiedTitle = ImageHelper::slugify($title.'--'.$author);

            return '//static.swiatczytnikow.pl/img/covers/'.$isbnPath.'/big/'.$slugifiedTitle.'.jpg';
        } catch (\InvalidArgumentException $e) {
            return '';
        }
    }

    public function generateBookComparisonUrl(?string $isbn, string $title, string $author): string
    {
        if (empty($isbn)) {
            return '';
        }

        // Basic ISBN validation - should be cleaned and 13 digits
        $cleanIsbn = preg_replace('/[^0-9]/', '', $isbn);
        if (13 !== strlen($cleanIsbn)) {
            return '';
        }

        try {
            $slugifiedTitle = ImageHelper::slugify($title.'--'.$author);

            return sprintf(
                'https://ebooki.swiatczytnikow.pl/ebook/%s,%s.html?utm_source=polecenia&utm_medium=recommendation&utm_campaign=%s',
                $isbn,
                $slugifiedTitle,
                $isbn
            );
        } catch (\InvalidArgumentException $e) {
            return '';
        }
    }
}
