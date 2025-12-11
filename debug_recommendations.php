<?php

require_once __DIR__.'/vendor/autoload.php';

// Bootstrap Symfony
$kernel = new \App\Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
$entityManager = $container->get('doctrine.orm.entity_manager');

echo "🔧 Debug Recommendation Results\n";
echo "===============================\n\n";

// Sprawdź liczbę wyników dla rekomendacji 1
$recommendationId = 1;
$resultsCount = $entityManager
    ->getRepository(\App\Entity\RecommendationResult::class)
    ->count(['recommendation' => $recommendationId]);

echo "Rekomendacja ID: $recommendationId\n";
echo "Liczba znalezionych książek: $resultsCount\n\n";

// Pokaż kilka pierwszych wyników
if ($resultsCount > 0) {
    $results = $entityManager
        ->getRepository(\App\Entity\RecommendationResult::class)
        ->findBy(['recommendation' => $recommendationId], ['rankOrder' => 'ASC'], 5);

    echo "Pierwsze 5 wyników:\n";
    echo "Rank | Similarity | Title\n";
    echo "-----|------------|------\n";

    foreach ($results as $result) {
        $ebook = $result->getEbook();
        printf(
            "%4d | %10.4f | %s\n",
            $result->getRankOrder(),
            $result->getSimilarityScore(),
            substr($ebook->getTitle(), 0, 50)
        );
    }
}

// Sprawdź informacje o rekomendacji
$recommendation = $entityManager
    ->getRepository(\App\Entity\Recommendation::class)
    ->find($recommendationId);

if ($recommendation) {
    echo "\nInformacje o rekomendacji:\n";
    echo "Opis: " . substr($recommendation->getShortDescription(), 0, 80) . "...\n";
    echo "Znalezionych książek: " . $recommendation->getFoundBooksCount() . "\n";
    echo "Ostatnie wyszukiwanie: " . ($recommendation->getLastSearchAt()?->format('Y-m-d H:i:s') ?? 'Nigdy') . "\n";
}

echo "\n✅ Debug zakończony\n";
