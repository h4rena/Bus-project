<?php
require_once "connexion.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

try {
    $stmt = $pdo->query('
        SELECT p.id_point, p.nom_point, p.longitude, p.latitude, p.id_type, t.nom_type
        FROM points p
        LEFT JOIN type_point t ON t.id_type = p.id_type
        ORDER BY p.id_point
    ');
    $points = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'points' => $points,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la récupération : ' . $e->getMessage()]);
}
?>
