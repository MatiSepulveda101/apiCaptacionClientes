<?php
require 'vendor/autoload.php';
require_once 'conexion.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit(0);
}

$data = json_decode(file_get_contents("php://input"), true);
$correo = trim(strtolower($data['correo'] ?? ''));
$nuevaContrasena = $data['nuevaContrasena'] ?? '';

if (!$correo || !$nuevaContrasena) {
    echo json_encode(["success" => false, "error" => "Faltan datos."]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id FROM usuario WHERE correo = :correo");
    $stmt->execute(['correo' => $correo]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        echo json_encode(["success" => false, "error" => "Usuario no encontrado."]);
        exit;
    }

    $hashedPassword = password_hash($nuevaContrasena, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE usuario SET contrasena = :contrasena WHERE id = :id");
    $stmt->execute([
        'contrasena' => $hashedPassword,
        'id' => $usuario['id']
    ]);

    echo json_encode(["success" => true, "mensaje" => "Contraseña actualizada correctamente."]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => "Error en la base de datos: " . $e->getMessage()]);
}
