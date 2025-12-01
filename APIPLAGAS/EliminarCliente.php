<?php
require 'vendor/autoload.php';
require_once 'verificar_token.php'; 

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

include 'conexion.php';

$data = json_decode(file_get_contents('php://input'), true);
$direccion = $data['direccion'] ?? '';

if (!$direccion) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Dirección no proporcionada']);
    exit;
}

try {
    $conn->beginTransaction();

    // Verificar existencia del cliente y su estado de asignación
    $verificar = $conn->prepare("SELECT id_asignado FROM cliente WHERE direccion = ?");
    $verificar->execute([$direccion]);
    $cliente = $verificar->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        $conn->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Cliente no encontrado']);
        exit;
    }

    // 🚫 Validar que NO esté asignado
    if (!is_null($cliente['id_asignado'])) {
        $conn->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No se puede eliminar un cliente asignado. Desasígnelo primero.']);
        exit;
    }

    // Eliminar interacciones asociadas al cliente
    $eliminarInteracciones = $conn->prepare("DELETE FROM interacciones WHERE cliente_direccion = ?");
    $eliminarInteracciones->execute([$direccion]);

    // Eliminar el cliente
    $eliminarCliente = $conn->prepare("DELETE FROM cliente WHERE direccion = ?");
    $eliminarCliente->execute([$direccion]);

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Cliente eliminado correctamente.']);
} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al eliminar: ' . $e->getMessage()]);
}
