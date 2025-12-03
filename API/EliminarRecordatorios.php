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
    echo json_encode(['success' => false, 'error' => 'Solo se permite método POST']);
    exit;
}

require_once 'verificar_token.php';
require_once 'conexion.php';

$usuarioAutenticado = verificarToken();

$tipoUsuarioId = $usuarioAutenticado->tipousuario_id ?? $usuarioAutenticado->TipoUsuario_id ?? null;

if (!in_array($tipoUsuarioId, [1, 2])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

// Leer body
$input = json_decode(file_get_contents("php://input"), true);

$id_recordatorio = $input['id_recordatorio'] ?? null;
$justificacion = $input['justificacion'] ?? null;

if (!$id_recordatorio) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Falta id_recordatorio']);
    exit;
}

if (!$justificacion) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Falta justificacion']);
    exit;
}

try {
    $sql = "UPDATE recordatorios
            SET vigente = false,
                justificacioneliminacion = :justificacion
            WHERE id = :id";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $id_recordatorio);
    $stmt->bindParam(':justificacion', $justificacion);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error en base de datos: ' . $e->getMessage()
    ]);
}
