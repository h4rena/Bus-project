<?php
require_once "connexion.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Données JSON invalides']);
    exit;
}

$latitude = $input['latitude'] ?? null;
$longitude = $input['longitude'] ?? null;
$nom_point = $input['nom_point'] ?? '';
$id_type = $input['id_type'] ?? null;

if ($latitude === null || $longitude === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'latitude et longitude requis']);
    exit;
}

if (!is_numeric($latitude) || !is_numeric($longitude)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'latitude et longitude doivent être numériques']);
    exit;
}

$latitude = round((float)$latitude, 6);
$longitude = round((float)$longitude, 6);
$nom_point = trim(substr($nom_point, 0, 100));
$id_type = $id_type ? (int)$id_type : null;
if ($id_type) {
    $stmt = $pdo->prepare('SELECT 1 FROM type_point WHERE id_type = :t');
    $stmt->execute([':t' => $id_type]);
    if (!$stmt->fetchColumn()) $id_type = null;
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO points (nom_point, longitude, latitude, id_type) VALUES (:nom, :lng, :lat, :type) RETURNING id_point'
    );
    $stmt->execute([
        ':nom' => $nom_point ?: null,
        ':lng' => $longitude,
        ':lat' => $latitude,
        ':type' => $id_type,
    ]);
    $id = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'id' => (int)$id,
        'point' => [
            'id_point' => (int)$id,
            'nom_point' => $nom_point,
            'longitude' => sprintf('%.6f', $longitude),
            'latitude' => sprintf('%.6f', $latitude),
            'id_type' => $id_type,
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'insertion : ' . $e->getMessage()]);
}
?>
