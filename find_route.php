<?php
require_once "connexion.php";
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$depLat = $input['depart']['lat'] ?? null;
$depLng = $input['depart']['lng'] ?? null;
$arrLat = $input['arrivee']['lat'] ?? null;
$arrLng = $input['arrivee']['lng'] ?? null;

if ($depLat === null || $depLng === null || $arrLat === null || $arrLng === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Coordonnées départ/arrivée requises']);
    exit;
}

// Trouve l'arrêt de bus (id_type=1) le plus proche d'une coordonnée
function arretProche($pdo, $lat, $lng) {
    $sql = "SELECT id_point, nom_point, latitude, longitude,
            (6371000 * acos(least(1, cos(radians(:lat)) * cos(radians(latitude))
              * cos(radians(longitude) - radians(:lng)) + sin(radians(:lat)) * sin(radians(latitude))))) AS dist
            FROM points WHERE id_type = 1
            ORDER BY dist ASC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':lat' => $lat, ':lng' => $lng]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

try {
    $arretDep = arretProche($pdo, $depLat, $depLng);
    $arretArr = arretProche($pdo, $arrLat, $arrLng);

    if (!$arretDep || !$arretArr) {
        echo json_encode(['success' => false, 'error' => 'Aucun arrêt de bus trouvé']);
        exit;
    }

    // Cherche une ligne desservant les deux arrêts, avec leur ordre respectif
    $stmt = $pdo->prepare('
        SELECT l.id_ligne, l.nom_ligne, l.couleur, l.trajet_geo,
               lp1.ordre AS ordre_dep, lp2.ordre AS ordre_arr
        FROM lignes l
        JOIN ligne_points lp1 ON lp1.id_ligne = l.id_ligne AND lp1.id_point = :dep
        JOIN ligne_points lp2 ON lp2.id_ligne = l.id_ligne AND lp2.id_point = :arr
    ');
    $stmt->execute([':dep' => $arretDep['id_point'], ':arr' => $arretArr['id_point']]);
    $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($lignes)) {
        echo json_encode([
            'success' => true,
            'trouve' => false,
            'arret_depart' => $arretDep,
            'arret_arrivee' => $arretArr,
            'message' => "Aucune ligne directe ne relie ces deux arrêts.",
        ]);
        exit;
    }

    $ligne = $lignes[0];
    $ligne['sens_inverse'] = $ligne['ordre_dep'] > $ligne['ordre_arr'];
    $ligne['trajet_geo'] = $ligne['trajet_geo'] ? json_decode($ligne['trajet_geo'], true) : null;

    echo json_encode([
        'success' => true,
        'trouve' => true,
        'arret_depart' => $arretDep,
        'arret_arrivee' => $arretArr,
        'ligne' => $ligne,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
