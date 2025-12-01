<?php
require_once 'verificar_token.php';
require_once 'conexion.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

// Leer JSON
$data = json_decode(file_get_contents("php://input"), true);

// Validar datos
if (!isset($data['direccion'], $data['estado_negocio_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

$direccion = trim($data['direccion']);
$estado = intval($data['estado_negocio_id']);

if ($estado <= 0 || empty($direccion)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Dirección o estado inválido.']);
    exit;
}

// ✅ Validar rol
$rolUsuario = $usuarioAutenticado->tipousuario_id ?? null;
if (!in_array($rolUsuario, [1, 2])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado.']);
    exit;
}

try {
    $sql = "UPDATE cliente SET estado_negocio_id = :estado WHERE direccion = :direccion";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':estado', $estado, PDO::PARAM_INT);
    $stmt->bindParam(':direccion', $direccion, PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Cliente no encontrado o sin cambios.']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en base de datos: ' . $e->getMessage()]);
}
