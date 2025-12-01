<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

require_once 'verificar_token.php';
require_once 'conexion.php';

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

$nombreUsuario = $input['nombre_usuario'] ?? null;
$id = isset($input['id']) ? intval($input['id']) : null;

if (!$nombreUsuario) {
    echo json_encode(['success' => false, 'error' => 'Nombre de usuario no proporcionado']);
    exit;
}

try {
    if ($id) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM usuario WHERE nombre_usuario = :nombre_usuario AND id != :id");
        $stmt->bindParam(':nombre_usuario', $nombreUsuario, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM usuario WHERE nombre_usuario = :nombre_usuario");
        $stmt->bindParam(':nombre_usuario', $nombreUsuario, PDO::PARAM_STR);
    }

    $stmt->execute();

    $existe = $stmt->fetchColumn() > 0;

    echo json_encode([
        'success' => true,
        'existe' => $existe
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('Error en la base de datos: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
}