<?php
require_once "connexion.php";

header('Content-Type: application/json');

$stmt = $pdo->query('
    SELECT l.id_ligne, l.nom_ligne, l.couleur, l.trajet_geo,
           lp.id_point, lp.ordre,
           p.latitude, p.longitude, p.nom_point
    FROM lignes l
    JOIN ligne_points lp ON lp.id_ligne = l.id_ligne
    JOIN points p ON p.id_point = lp.id_point
    ORDER BY l.id_ligne, lp.ordre
');

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$lignes = [];

foreach ($rows as $row) {
    $id = $row['id_ligne'];
    if (!isset($lignes[$id])) {
        $lignes[$id] = [
            'id_ligne' => (int)$id,
            'nom_ligne' => $row['nom_ligne'],
            'couleur' => $row['couleur'] ?? '#e6194b',
            'trajet_geo' => $row['trajet_geo'] ? json_decode($row['trajet_geo'], true) : null,
            'points' => [],
        ];
    }
    $lignes[$id]['points'][] = [
        'id_point' => (int)$row['id_point'],
        'ordre' => (int)$row['ordre'],
        'latitude' => $row['latitude'],
        'longitude' => $row['longitude'],
        'nom_point' => $row['nom_point'],
    ];
}

echo json_encode(array_values($lignes));
?>
