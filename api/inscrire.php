<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/db.php';

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $ville = $_POST['ville'] ?? '';
    $photo = $_FILES['photo'] ?? null;

    if ($nom && $prenom && $ville && $photo && $photo['tmp_name']) {
        try {
            Configuration::instance(getenv('CLOUDINARY_URL'));
            $upload = (new UploadApi())->upload($photo['tmp_name'], [
                'folder' => 'stagiaires'
            ]);
            $photoUrl = $upload['secure_url'];

            $pdo = getDatabase();
            $stmt = $pdo->prepare("INSERT INTO stagiaires (nom, prenom, ville, photo) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nom, $prenom, $ville, $photoUrl]);

            echo json_encode(['message' => 'Stagiaire inscrit avec succès']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Veuillez remplir tous les champs']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
}