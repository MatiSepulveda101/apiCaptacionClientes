<?php
ini_set('display_errors', 0);
error_reporting(0);

require_once 'verificar_token.php'; 
require_once 'conexion.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST");

$usuarioAutenticado = verificarToken();

// Validar rol (solo tipo 1 permitido)
$tipoUsuarioId = $usuarioAutenticado->tipousuario_id ?? $usuarioAutenticado->TipoUsuario_id ?? null;

if ($tipoUsuarioId !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    exit;
}

// Obtenemos el cuerpo JSON
$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'] ?? null;

if (!$id || !is_numeric($id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID no proporcionado o inválido.']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.nombre_usuario,
            u.nombre_completo,
            u.correo,
            u.estado,
            t.nombre AS nombre_tipo_usuario,
            t.id AS tipousuario_id
        FROM Usuario u
        JOIN TipoUsuario t ON u.tipoUsuario_id = t.id
        WHERE u.id = :id
    ");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        echo json_encode(['success' => true, 'usuario' => $usuario]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Usuario no encontrado.']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en base de datos: ' . $e->getMessage()]);
}
