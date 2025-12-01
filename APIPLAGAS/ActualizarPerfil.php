<?php
require_once 'verificar_token.php';
require_once 'conexion.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (
    !isset($data['id'], $data['nombre_completo'], $data['correo'], $data['nombre_usuario'])
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Faltan datos obligatorios']);
    exit;
}

$id = intval($data['id']);
$nombre_completo = trim($data['nombre_completo']);
$correo = trim($data['correo']);
$nombre_usuario = trim($data['nombre_usuario']);
$contrasena = $data['contrasena'] ?? '';


// Validar usuario duplicado
try {
    $stmt = $conn->prepare("SELECT id FROM usuario WHERE nombre_usuario = :nombre_usuario AND id != :id");
    $stmt->execute([':nombre_usuario' => $nombre_usuario, ':id' => $id]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'El nombre de usuario ya está en uso.']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al verificar el nombre de usuario.']);
    exit;
}

try {
    if (!empty($contrasena)) {
        $hash = password_hash($contrasena, PASSWORD_BCRYPT);
        $query = "
            UPDATE usuario 
            SET nombre_completo = :nombre_completo,
                correo = :correo,
                nombre_usuario = :nombre_usuario,
                contrasena = :contrasena
            WHERE id = :id
        ";
        $params = [
            ':nombre_completo' => $nombre_completo,
            ':correo' => $correo,
            ':nombre_usuario' => $nombre_usuario,
            ':contrasena' => $hash,
            ':id' => $id
        ];
    } else {
        $query = "
            UPDATE usuario 
            SET nombre_completo = :nombre_completo,
                correo = :correo,
                nombre_usuario = :nombre_usuario
            WHERE id = :id
        ";
        $params = [
            ':nombre_completo' => $nombre_completo,
            ':correo' => $correo,
            ':nombre_usuario' => $nombre_usuario,
            ':id' => $id
        ];
    }

    $stmt = $conn->prepare($query);
    $stmt->execute($params);

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Usuario actualizado correctamente']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al actualizar los datos']);
}
