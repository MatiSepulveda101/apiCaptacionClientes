<?php
require 'conexion.php';
require 'verificar_token.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos JSON
$input = json_decode(file_get_contents('php://input'), true);

// Validación básica
if (!isset($input['id'], $input['nombre_completo'], $input['correo'], $input['nombre_usuario'], $input['tipousuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
    exit;
}

$id = (int)$input['id'];
$nombre_completo = trim($input['nombre_completo']);
$correo = trim($input['correo']);
$nombre_usuario = trim($input['nombre_usuario']);
$tipousuario_id = (int)$input['tipousuario_id'];
$contrasena = isset($input['contrasena']) ? trim($input['contrasena']) : null;

// Verificar existencia del usuario
$verificar = $conn->prepare("SELECT id FROM usuario WHERE id = ?");
$verificar->execute([$id]);

if ($verificar->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'El usuario no existe']);
    exit;
}

// Construir SQL dinámicamente
if ($contrasena !== null && $contrasena !== '') {
    // Si se envía nueva contraseña
    $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);
    $update = $conn->prepare("UPDATE usuario SET nombre_completo = ?, correo = ?, nombre_usuario = ?, contrasena = ?, tipousuario_id = ? WHERE id = ?");
    $exito = $update->execute([$nombre_completo, $correo, $nombre_usuario, $contrasena_hash, $tipousuario_id, $id]);
} else {
    // Sin cambiar contraseña
    $update = $conn->prepare("UPDATE usuario SET nombre_completo = ?, correo = ?, nombre_usuario = ?, tipousuario_id = ? WHERE id = ?");
    $exito = $update->execute([$nombre_completo, $correo, $nombre_usuario, $tipousuario_id, $id]);
}

if ($exito) {
    echo json_encode(['success' => true, 'message' => 'Usuario actualizado correctamente']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el usuario']);
}
?>