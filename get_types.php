<?php
require_once "connexion.php";

header('Content-Type: application/json');

$stmt = $pdo->query('SELECT id_type, nom_type FROM type_point ORDER BY id_type');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
