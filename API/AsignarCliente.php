<?php
require_once 'verificar_token.php'; 
require_once 'conexion.php';

$usuarioAutenticado = verificarToken(); // ← ya retorna el usuario

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$direccion = $data['direccion'] ?? null;
$id_asignado = $data['id_asignado'] ?? null;

if (!$direccion) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Dirección faltante']);
    exit;
}

// Lógica de autorización:
// - tipousuario_id 1 (admin): puede asignar a cualquier usuario
// - tipousuario_id 2 (coordinador): solo puede asignarse a sí mismo
if ($usuarioAutenticado->tipousuario_id == 1) {
    // OK, puede asignar a cualquier usuario
} elseif ($usuarioAutenticado->tipousuario_id == 2) {
    // Solo puede asignarse a sí mismo
    if ($id_asignado != $usuarioAutenticado->id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Solo puedes asignarte a ti mismo']);
        exit;
    }
} else {
    // Otros roles no permitidos
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

try {
    if (is_null($id_asignado)) {
        // Desasignar cliente
        $stmt = $conn->prepare("UPDATE cliente SET id_asignado = NULL, asignado = false WHERE direccion = :dir");
        $stmt->execute([':dir' => $direccion]);
    } else {
        // Asignar cliente
        $stmt = $conn->prepare("UPDATE cliente SET id_asignado = :id, asignado = true WHERE direccion = :dir");
        $stmt->execute([
            ':id' => $id_asignado,
            ':dir' => $direccion
        ]);
    }

    http_response_code(200);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
