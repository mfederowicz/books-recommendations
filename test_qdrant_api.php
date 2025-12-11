<?php

require_once __DIR__.'/vendor/autoload.php';

use App\Service\RecommendationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

// Bootstrap Symfony
$kernel = new \App\Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
$recommendationService = $container->get(RecommendationService::class);

echo "🔍 Test wyszukiwania książek w Qdrant przez API\n";
echo "==============================================\n\n";

// Test 1: Proste wyszukiwanie
$text = "fantasy adventure with dragons and magic";
echo "📖 Wyszukiwanie: \"$text\"\n\n";

try {
    $results = $recommendationService->findSimilarEbooks($text, 5);

    if (empty($results)) {
        echo "❌ Brak wyników wyszukiwania\n";
        echo "Możliwe przyczyny:\n";
        echo "- Brak zsynchronizowanych embeddingów w Qdrant\n";
        echo "- Problem z połączeniem do Qdrant\n";
        echo "- Błąd w wyszukiwaniu wektorowym\n\n";

        // Sprawdź statystyki
        $ebookEmbeddingService = $container->get(\App\Service\EbookEmbeddingService::class);
        $stats = $ebookEmbeddingService->getQdrantCollectionStats();

        echo "📊 Statystyki Qdrant:\n";
        if ($stats) {
            echo "- Kolekcja istnieje\n";
            echo "- Punkty: " . ($stats['result']['points_count'] ?? 'nieznana') . "\n";
        } else {
            echo "- Brak połączenia z Qdrant lub kolekcja nie istnieje\n";
        }

        exit(1);
    }

    echo "✅ Znaleziono " . count($results) . " podobnych książek:\n\n";

    foreach ($results as $index => $result) {
        $ebook = $result['ebook'];
        $score = $result['similarity_score'];

        echo sprintf("%d. 📚 %s\n", $index + 1, $ebook->getTitle());
        echo sprintf("   👤 Autor: %s\n", $ebook->getAuthor());
        echo sprintf("   📊 Podobność: %.4f\n", $score);
        echo sprintf("   🏷️ Tagi: %s\n", $ebook->getTags() ?: 'brak');
        echo sprintf("   📖 ISBN: %s\n", $ebook->getIsbn());
        echo "\n";
    }

} catch (\Exception $e) {
    echo "❌ Błąd podczas wyszukiwania: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n🎉 Test zakończony sukcesem!\n";
