<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

require_once 'verificar_token.php';
require_once 'conexion.php';

// Obtener usuario autenticado
$usuarioAutenticado = verificarToken();

if (!isset($usuarioAutenticado->id)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token no válido o expirado.']);
    exit;
}

// Validar que sea tipo 1 (administrador)
if (($usuarioAutenticado->tipousuario_id ?? 0) !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso no autorizado.']);
    exit;
}

$idLogueado = intval($usuarioAutenticado->id);

try {
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.nombre_completo,
            u.nombre_usuario,
            u.correo,
            u.estado,
            t.nombre AS tipo_usuario
        FROM Usuario u
        JOIN TipoUsuario t ON u.tipoUsuario_id = t.id
        WHERE u.id != :idLogueado
    ");

    $stmt->bindParam(':idLogueado', $idLogueado, PDO::PARAM_INT);
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'usuarios' => $usuarios]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en la conexión: ' . $e->getMessage()]);
}
