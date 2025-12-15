<?php

require_once __DIR__.'/vendor/autoload.php';

use App\Service\EbookEmbeddingService;
use App\Service\RecommendationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

// Bootstrap Symfony
$kernel = new \App\Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
$recommendationService = $container->get(RecommendationService::class);
$ebookEmbeddingService = $container->get(EbookEmbeddingService::class);

echo "🔍 Test wyszukiwania książek w Qdrant\n";
echo "=====================================\n\n";

// Przykład 1: Wyszukiwanie na podstawie tekstu (używa RecommendationService)
$text = "A fantasy story about dragons, magic, and adventure in a magical world";
echo "📖 Wyszukiwanie na podstawie tekstu:\n";
echo "Tekst: \"$text\"\n\n";

try {
    $results = $recommendationService->findSimilarEbooks($text, 5);

    if (empty($results)) {
        echo "❌ Brak wyników wyszukiwania\n";
    } else {
        echo "✅ Znaleziono " . count($results) . " podobnych książek:\n\n";

        foreach ($results as $index => $result) {
            $ebook = $result['ebook'];
            $score = $result['similarity_score'];

            echo sprintf("%d. 📚 %s\n", $index + 1, $ebook->getTitle());
            echo sprintf("   👤 Autor: %s\n", $ebook->getAuthor());
            echo sprintf("   📊 Podobność: %.4f\n", $score);
            echo sprintf("   📋 Tagi: %s\n", $ebook->getTags() ?? 'brak');
            echo "\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Błąd podczas wyszukiwania: " . $e->getMessage() . "\n";
}

echo "\n";

// Przykład 2: Wyszukiwanie na podstawie wektora embedding (używa EbookEmbeddingService bezpośrednio)
echo "🔢 Wyszukiwanie na podstawie wektora embedding:\n";
echo "(Generowanie losowego wektora dla testu)\n\n";

try {
    // Generuj losowy wektor embedding (1536 wymiarów jak text-embedding-3-small)
    $randomVector = [];
    for ($i = 0; $i < 1536; $i++) {
        $randomVector[] = (mt_rand() / mt_getrandmax() - 0.5) * 2; // Wartości między -1 a 1
    }

    $results = $ebookEmbeddingService->findSimilarEbooks($randomVector, 3);

    if (empty($results)) {
        echo "❌ Brak wyników wyszukiwania\n";
    } else {
        echo "✅ Znaleziono " . count($results) . " książek dla losowego wektora:\n\n";

        foreach ($results as $index => $result) {
            $ebook = $result['ebook'];
            $score = $result['similarity_score'];

            echo sprintf("%d. 📚 %s\n", $index + 1, $ebook->getTitle());
            echo sprintf("   👤 Autor: %s\n", $ebook->getAuthor());
            echo sprintf("   📊 Podobność: %.4f\n", $score);
            echo "\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Błąd podczas wyszukiwania: " . $e->getMessage() . "\n";
}

echo "\n🎉 Test zakończony!\n";

