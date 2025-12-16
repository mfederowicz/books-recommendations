<?php

require 'vendor/autoload.php';
use App\Service\ImageHelper;

// Test ISBN formatting
$isbn = '9788368590777';
$path = ImageHelper::formatIsbnForImagePath($isbn);
echo 'ISBN path: ' . $path . PHP_EOL;

// Test slugify
$title = 'Dusza pokryta bliznami. Opowieści z meekhańskiego pogranicza';
$author = 'Robert M Wegner';
$slug = ImageHelper::slugify($title . '--' . $author);
echo 'Slug: ' . $slug . PHP_EOL;

// Test full URL
$url = sprintf(
    '//static.swiatczytnikow.pl/img/covers/%s/big/%s.jpg',
    $path,
    $slug
);
echo 'Full URL: ' . $url . PHP_EOL;
