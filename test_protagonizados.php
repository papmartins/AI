<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$chatbot = new App\Services\NLPChatbotService(new App\Services\MovieRecommender());

$testQueries = [
    'Filmes protagonizados por Bruce Willis',
    'Películas protagonizadas por Bruce Willis'
];

echo "Testing protagonizados queries:\n";
foreach ($testQueries as $query) {
    echo "\nQuery: '" . $query . "'\n";
    
    // Check intent classification
    $intentClassifier = new App\Services\IntentClassifierService();
    $intentData = $intentClassifier->classifyMultipleIntents($query);
    echo "Intent: " . implode(', ', $intentData['intents']) . "\n";
    
    // Get full response
    $result = $chatbot->processQuestion($query);
    echo "Result: " . substr($result, 0, 80) . "...\n";
}
