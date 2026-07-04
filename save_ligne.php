<?php
require_once "connexion.php";

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$nom = $input['nom_ligne'] ?? '';
$couleur = $input['couleur'] ?? '#e6194b';
$points = $input['points'] ?? [];

if (!is_array($points) || empty($points)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'points requis']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('INSERT INTO lignes (nom_ligne, couleur) VALUES (:nom, :couleur) RETURNING id_ligne');
    $stmt->execute([':nom' => $nom ?: null, ':couleur' => $couleur]);
    $id_ligne = $stmt->fetchColumn();

    $stmt = $pdo->prepare('INSERT INTO ligne_points (id_ligne, id_point, ordre) VALUES (:id_ligne, :id_point, :ordre)');
    foreach ($points as $ordre => $id_point) {
        $stmt->execute([
            ':id_ligne' => $id_ligne,
            ':id_point' => (int)$id_point,
            ':ordre' => $ordre + 1,
        ]);
    }

    // Récupérer les coordonnées des points pour OSRM
    $placeholders = implode(',', array_fill(0, count($points), '?'));
    $stmt = $pdo->prepare("SELECT latitude, longitude FROM points WHERE id_point IN ($placeholders) ORDER BY id_point");
    $stmt->execute(array_map('intval', $points));
    $coords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Réordonner selon l'ordre des points d'origine
    $ordered = [];
    foreach ($points as $id) {
        foreach ($coords as $c) {
            if ((int)$c['latitude'] === (int)$id) {
                // Simple fallback, on va reconstruire
            }
        }
    }
    // Meilleure approche : re-fetch dans l'ordre
    $orderedCoords = [];
    foreach ($points as $id) {
        $s = $pdo->prepare('SELECT latitude, longitude FROM points WHERE id_point = ?');
        $s->execute([(int)$id]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if ($r) $orderedCoords[] = $r['longitude'] . ',' . $r['latitude'];
    }

    $trajet_geo = null;
    if (count($orderedCoords) >= 2) {
        $waypoints = implode(';', $orderedCoords);
        $url = "https://router.project-osrm.org/route/v1/driving/{$waypoints}?geometries=geojson&overview=full&steps=false&alternatives=false";

        $ctx = stream_context_create([
            'http' => ['timeout' => 10, 'method' => 'GET'],
        ]);
        $response = @file_get_contents($url, false, $ctx);

        if ($response) {
            $data = json_decode($response, true);
            if ($data && isset($data['routes'][0]['geometry']['coordinates'])) {
                $trajet_geo = array_map(function($c) {
                    return [round($c[1], 6), round($c[0], 6)];
                }, $data['routes'][0]['geometry']['coordinates']);

                $stmt = $pdo->prepare('UPDATE lignes SET trajet_geo = :geo WHERE id_ligne = :id');
                $stmt->execute([
                    ':geo' => json_encode($trajet_geo),
                    ':id' => $id_ligne,
                ]);
            }
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'id_ligne' => (int)$id_ligne,
        'trajet_geo' => $trajet_geo,
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
