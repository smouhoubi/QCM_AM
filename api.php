<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion des requêtes OPTIONS pour CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$testsDir = './tests/';
$resultsDir = './results/';

// Créer le répertoire results s'il n'existe pas
if (!is_dir($resultsDir)) {
    mkdir($resultsDir, 0755, true);
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list_tests':
        listTests();
        break;
        
    case 'get_test':
        getTest();
        break;
        
    case 'save_result':
        saveResult();
        break;
        
    case 'list_results':
        listResults();
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Action non spécifiée']);
        break;
}

/**
 * Lister tous les tests disponibles
 */
function listTests() {
    global $testsDir;
    
    // Vérifier si le fichier index.json existe
    $indexFile = $testsDir . 'index.json';
    if (file_exists($indexFile)) {
        $indexContent = file_get_contents($indexFile);
        echo $indexContent;
        return;
    }
    
    // Sinon, scanner automatiquement les fichiers JSON
    $tests = [];
    $files = glob($testsDir . '*.json');
    
    foreach ($files as $file) {
        if (basename($file) === 'index.json') continue;
        
        $content = file_get_contents($file);
        $testData = json_decode($content, true);
        
        if ($testData && isset($testData['title'])) {
            $tests[] = [
                'id' => pathinfo($file, PATHINFO_FILENAME),
                'title' => $testData['title'],
                'description' => $testData['description'] ?? '',
                'totalQuestions' => $testData['totalQuestions'] ?? count($testData['questions'] ?? []),
                'filename' => basename($file),
                'difficulty' => $testData['metadata']['difficulty'] ?? 'Moyen',
                'category' => $testData['metadata']['category'] ?? 'Général'
            ];
        }
    }
    
    echo json_encode(['tests' => $tests]);
}

/**
 * Récupérer un test spécifique
 */
function getTest() {
    global $testsDir;
    
    $filename = $_GET['filename'] ?? '';
    if (empty($filename)) {
        http_response_code(400);
        echo json_encode(['error' => 'Nom de fichier manquant']);
        return;
    }
    
    $filePath = $testsDir . basename($filename);
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Test non trouvé']);
        return;
    }
    
    $content = file_get_contents($filePath);
    echo $content;
}

/**
 * Sauvegarder un résultat de test
 */
function saveResult() {
    global $resultsDir;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Méthode non autorisée']);
        return;
    }
    
    $input = file_get_contents('php://input');
    $resultData = json_decode($input, true);
    
    if (!$resultData) {
        http_response_code(400);
        echo json_encode(['error' => 'Données invalides']);
        return;
    }
    
    // Générer un nom de fichier unique
    $candidateName = preg_replace('/[^a-zA-Z0-9]/', '-', $resultData['candidateName'] ?? 'unknown');
    $testID = preg_replace('/[^a-zA-Z0-9]/', '-', $resultData['testId'] ?? 'test');
    $timestamp = date('Y-m-d-H-i-s');
    $filename = "result-{$candidateName}-{$testID}-{$timestamp}.json";
    
    $filePath = $resultsDir . $filename;
    
    // Ajouter des métadonnées
    $resultData['savedAt'] = date('c');
    $resultData['filename'] = $filename;
    $resultData['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    if (file_put_contents($filePath, json_encode($resultData, JSON_PRETTY_PRINT))) {
        echo json_encode([
            'success' => true,
            'filename' => $filename,
            'message' => 'Résultat sauvegardé avec succès'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la sauvegarde']);
    }
}

/**
 * Lister tous les résultats sauvegardés
 */
function listResults() {
    global $resultsDir;
    
    $results = [];
    $files = glob($resultsDir . 'result-*.json');
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $resultData = json_decode($content, true);
        
        if ($resultData) {
            $results[] = [
                'filename' => basename($file),
                'candidateName' => $resultData['candidateName'] ?? 'Inconnu',
                'testTitle' => $resultData['testTitle'] ?? 'Test inconnu',
                'score' => $resultData['score'] ?? '0',
                'date' => $resultData['date'] ?? $resultData['savedAt'] ?? 'Date inconnue',
                'correctAnswers' => $resultData['correctAnswers'] ?? 0,
                'totalQuestions' => $resultData['totalQuestions'] ?? 0
            ];
        }
    }
    
    // Trier par date (plus récent en premier)
    usort($results, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    echo json_encode(['results' => $results]);
}
?>