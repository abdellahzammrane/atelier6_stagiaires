<?php
header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    respond(405, ['error' => 'Methode non autorisee.']);
}

try {
    require __DIR__ . '/../config/db.php';
    $pdo = getDatabase();
    $stmt = $pdo->query('SELECT * FROM stagiaires ORDER BY date_inscription DESC');
    respond(200, $stmt->fetchAll());
} catch (Throwable $e) {
    error_log('Chargement des stagiaires failed: ' . $e->getMessage());

    if (strpos($e->getMessage(), 'Missing database environment variables:') === 0) {
        $message = $e->getMessage();
    } else {
        $message = 'Impossible de lire les stagiaires depuis Aiven. Verifiez les identifiants, le certificat CA et la table stagiaires.';
    }

    respond(500, ['error' => $message]);
}
