<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

require_once 'verificar_token.php';
require_once 'conexion.php';

$input = json_decode(file_get_contents("php://input"), true);
$direccion = trim($input['direccion'] ?? '');

if (empty($direccion)) {
    echo json_encode(['success' => false, 'error' => 'Dirección no proporcionada']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM cliente WHERE direccion = :direccion");
    $stmt->bindParam(':direccion', $direccion);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'existe' => $row['total'] > 0
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error en base de datos']);
}
