<?php
require_once 'conexion.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Obtener input JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['nueva']) || empty(trim($input['nueva']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Falta la nueva contraseña.']);
    exit;
}

$correo = $input['correo'] ?? null;
if (!$correo) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Falta el correo.']);
    exit;
}

$nuevaPlano = trim($input['nueva']);

try {
    // Verificar si el usuario existe y obtener su contraseña actual
    $stmt = $conn->prepare("SELECT contrasena FROM usuario WHERE correo = :correo");
    $stmt->execute(['correo' => $correo]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Usuario no encontrado.']);
        exit;
    }

    // Verificar si la nueva contraseña es igual a la actual
    if (password_verify($nuevaPlano, $user['contrasena'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'La nueva contraseña no puede ser igual a la anterior.',
            'igual' => true
        ]);
        exit;
    }

    // Hashear y actualizar nueva contraseña
    $nuevaHash = password_hash($nuevaPlano, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE usuario SET contrasena = :nueva WHERE correo = :correo");
    $stmt->bindParam(':nueva', $nuevaHash);
    $stmt->bindParam(':correo', $correo);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'No se pudo actualizar la contraseña.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
