<?php
require 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require 'conexion.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit(0);
}

$data = json_decode(file_get_contents("php://input"), true);
$codigo = trim($data['codigo'] ?? '');

if (!$codigo) {
    echo json_encode(["success" => false, "error" => "Falta el código."]);
    exit;
}

try {
    // Buscar código válido
    $stmt = $conn->prepare("SELECT usuario_id FROM recuperarcontrasena WHERE codigo = :codigo AND expiracion > NOW()");
    $stmt->execute(['codigo' => $codigo]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(["success" => false, "error" => "Código inválido o expirado."]);
        exit;
    }

    $usuario_id = $row['usuario_id'];

    // Eliminar el código inmediatamente después de validar
    $delete = $conn->prepare("DELETE FROM recuperarcontrasena WHERE codigo = :codigo");
    $delete->execute(['codigo' => $codigo]);

    // Obtener el correo del usuario
    $stmt = $conn->prepare("SELECT correo FROM usuario WHERE id = :id");
    $stmt->execute(['id' => $usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        echo json_encode(["success" => false, "error" => "Usuario no encontrado."]);
        exit;
    }

    echo json_encode(["success" => true, "correo" => $usuario['correo']]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => "Error en la base de datos: " . $e->getMessage()]);
}
