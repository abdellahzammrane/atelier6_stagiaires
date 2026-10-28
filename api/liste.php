<?php
require __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    $pdo = getDatabase();
    $stmt = $pdo->query("SELECT * FROM stagiaires ORDER BY date_inscription DESC");
    $stagiaires = $stmt->fetchAll();
    
    echo json_encode($stagiaires);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}