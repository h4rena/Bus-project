<?php
$host = 'localhost';
$port = '5432';
$dbname = 'webmapping_db';
$user = 'postgres';
$password = 'POSTGRES';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Connexion à la base échouée : ' . $e->getMessage()]);
    exit;
}
?>
