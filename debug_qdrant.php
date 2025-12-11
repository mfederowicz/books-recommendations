<?php

require_once __DIR__.'/vendor/autoload.php';

use App\Service\EbookEmbeddingService;

// Bootstrap Symfony
$kernel = new \App\Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
$ebookEmbeddingService = $container->get(EbookEmbeddingService::class);

echo "🔧 Debug Qdrant search\n";
echo "=====================\n\n";

// Test 1: Sprawdź połączenie z Qdrant
echo "1. Checking Qdrant connection...\n";
$stats = $ebookEmbeddingService->getQdrantCollectionStats();
if ($stats) {
    echo "✅ Qdrant connected. Points: " . ($stats['result']['points_count'] ?? 0) . "\n";
} else {
    echo "❌ Qdrant connection failed\n";
    exit(1);
}

// Test 2: Wygeneruj wektor testowy
echo "\n2. Generating test vector...\n";
$testVector = array_fill(0, 1536, 0.1); // Prosty wektor testowy
echo "✅ Test vector generated (length: " . count($testVector) . ")\n";

// Test 3: Wyszukaj podobne książki
echo "\n3. Searching for similar books...\n";
try {
    $results = $ebookEmbeddingService->findSimilarEbooks($testVector, 3);
    echo "✅ Search completed. Found " . count($results) . " results\n";

    if (!empty($results)) {
        foreach ($results as $index => $result) {
            $ebook = $result['ebook'];
            $score = $result['similarity_score'];
            echo "   " . ($index + 1) . ". {$ebook->getTitle()} (score: {$score})\n";
        }
    } else {
        echo "   No results found\n";
    }
} catch (Exception $e) {
    echo "❌ Search failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n🎉 Debug completed!\n";
