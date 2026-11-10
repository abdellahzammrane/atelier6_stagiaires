<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();
header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): void
{
    if (ob_get_level() > 0) {
        $unexpectedOutput = ob_get_contents();
        if ($unexpectedOutput !== false && trim($unexpectedOutput) !== '') {
            error_log('Unexpected output was discarded from the stagiaires API response.');
        }
        ob_clean();
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        $status = 500;
        $json = '{"error":"Could not encode API response."}';
    }

    http_response_code($status);
    echo $json;
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
