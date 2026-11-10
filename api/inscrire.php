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
            error_log('Unexpected output was discarded from the registration API response.');
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'Methode non autorisee.']);
}

$nom = isset($_POST['nom']) && is_string($_POST['nom']) ? trim($_POST['nom']) : '';
$prenom = isset($_POST['prenom']) && is_string($_POST['prenom']) ? trim($_POST['prenom']) : '';
$ville = isset($_POST['ville']) && is_string($_POST['ville']) ? trim($_POST['ville']) : '';
$photo = $_FILES['photo'] ?? null;

if ($nom === '' || $prenom === '' || $ville === '' || !is_array($photo)) {
    respond(400, ['error' => 'Veuillez remplir tous les champs et choisir une photo.']);
}

if (($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($photo['tmp_name'])) {
    respond(400, ['error' => 'Le televersement de la photo a echoue. Choisissez une autre image.']);
}

try {
    require __DIR__ . '/../vendor/autoload.php';
    require __DIR__ . '/../config/db.php';

    $cloudinaryUrl = getenv('CLOUDINARY_URL');
    if ($cloudinaryUrl === false || $cloudinaryUrl === '') {
        throw new RuntimeException('CLOUDINARY_URL is not configured.');
    }

    \Cloudinary\Configuration\Configuration::instance($cloudinaryUrl);
    $upload = (new \Cloudinary\Api\Upload\UploadApi())->upload($photo['tmp_name'], [
        'folder' => 'stagiaires'
    ]);
    $photoUrl = $upload['secure_url'] ?? null;

    if (!$photoUrl) {
        throw new RuntimeException('Cloudinary did not return a secure image URL.');
    }

    $pdo = getDatabase();
    $stmt = $pdo->prepare('INSERT INTO stagiaires (nom, prenom, ville, photo) VALUES (?, ?, ?, ?)');
    $stmt->execute([$nom, $prenom, $ville, $photoUrl]);

    respond(200, ['message' => 'Inscription reussie.']);
} catch (Throwable $e) {
    error_log('Inscription stagiaire failed: ' . $e->getMessage());

    if (strpos($e->getMessage(), 'Missing database environment variables:') === 0) {
        $message = $e->getMessage() . ' Configure these values in Vercel.';
    } elseif (strpos($e->getMessage(), 'DATABASE_URL') === 0) {
        $message = $e->getMessage() . ' Check the Aiven connection URL in Vercel.';
    } elseif ($e->getMessage() === 'CLOUDINARY_URL is not configured.') {
        $message = 'CLOUDINARY_URL is missing. Add it to the Vercel environment variables.';
    } elseif ($e instanceof PDOException) {
        $message = 'Aiven rejected the database connection or insert. Check the Aiven credentials, CA certificate, and stagiaires table.';
    } else {
        $message = 'Cloudinary upload or database insert failed. Check the Vercel function logs.';
    }

    respond(500, ['error' => $message]);
}
