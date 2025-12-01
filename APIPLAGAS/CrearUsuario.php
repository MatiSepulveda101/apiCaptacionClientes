<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'verificar_token.php'; 
require_once 'conexion.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST");

// Mostrar errores para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    exit;
}

$usuarioAutenticado = verificarToken();

$rolId = $usuarioAutenticado->tipousuario_id ?? $usuarioAutenticado->rol_id ?? null;
if (!in_array($rolId, [1])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado.']);
    exit;
}
$data = json_decode(file_get_contents("php://input"), true);

// Validar campos
if (!isset($data['nombre_completo'], $data['correo'], $data['nombre_usuario'], $data['contrasena'], $data['tipo_usuario'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Faltan datos obligatorios.']);
    exit;
}

$nombre_completo = trim($data['nombre_completo']);
$correo = strtolower(trim($data['correo']));
$nombre_usuario = strtolower(trim($data['nombre_usuario']));
$contrasena = $data['contrasena'];
$tipousuario_id = intval($data['tipo_usuario']);

try {
    // Verificar si ya existe correo o nombre_usuario
    $stmtCheck = $conn->prepare("SELECT id FROM usuario WHERE correo = :correo OR nombre_usuario = :nombre_usuario");
    $stmtCheck->execute([
        ':correo' => $correo,
        ':nombre_usuario' => $nombre_usuario
    ]);

    if ($stmtCheck->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Correo o nombre de usuario ya existe.']);
        exit;
    }

    // Hashear la contraseña
    $hash = password_hash($contrasena, PASSWORD_BCRYPT);

    // Insertar nuevo usuario
    $stmt = $conn->prepare("
        INSERT INTO usuario (nombre_completo, correo, nombre_usuario, contrasena, tipousuario_id)
        VALUES (:nombre_completo, :correo, :nombre_usuario, :contrasena, :tipousuario_id)
    ");
    $stmt->execute([
        ':nombre_completo' => $nombre_completo,
        ':correo' => $correo,
        ':nombre_usuario' => $nombre_usuario,
        ':contrasena' => $hash,
        ':tipousuario_id' => $tipousuario_id
    ]);

    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Usuario creado correctamente.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Excepción: ' . $e->getMessage()]);
}
