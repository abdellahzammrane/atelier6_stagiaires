<?php
header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
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
    respond(500, ['error' => 'Inscription impossible. Verifiez la configuration Cloudinary et de la base de donnees.']);
}
