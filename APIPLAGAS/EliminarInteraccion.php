<?php
require_once 'verificar_token.php'; 
require_once 'conexion.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// Manejo de preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Verificar token y obtener usuario autenticado
$usuarioAutenticado = verificarToken();

$rolId = $usuarioAutenticado->tipousuario_id ?? $usuarioAutenticado->rol_id ?? null;
if (!in_array($rolId, [1, 2])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado.']);
    exit;
}

// Leer datos JSON
$input = json_decode(file_get_contents("php://input"), true);

// Validar acción y datos
if (!isset($input['accion']) || $input['accion'] !== 'eliminar') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    exit;
}

if (!isset($input['id']) || empty($input['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de interacción no proporcionado']);
    exit;
}

$id = (int)$input['id'];

try {
    if ($rolId === 2) {
        $stmt = $conn->prepare("DELETE FROM interacciones WHERE id = :id AND usuario_id = :usuario_id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':usuario_id', $usuarioAutenticado->id, PDO::PARAM_INT);
    } else {
        $stmt = $conn->prepare("DELETE FROM interacciones WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    }

    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Interacción eliminada correctamente']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'La interacción no existe o no tiene permiso para eliminarla']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'No se pudo eliminar la interacción']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
