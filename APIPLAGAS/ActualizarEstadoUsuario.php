<?php
require_once 'verificar_token.php';
require_once 'conexion.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); 
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'], $data['estado'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos.']);
    exit;
}

$id = intval($data['id']);
$estado = filter_var($data['estado'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

if ($estado === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valor de estado inválido.']);
    exit;
}
if (intval($usuarioAutenticado->tipousuario_id ?? 0) !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}
try {
    $query = "UPDATE usuario SET estado = :estado WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindValue(':estado', $estado, PDO::PARAM_BOOL);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'No se pudo actualizar el estado.']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
