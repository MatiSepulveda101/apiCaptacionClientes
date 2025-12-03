<?php
require 'conexion.php';
require 'verificar_token.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'Falta el id del recordatorio']);
    exit;
}

$id = (int) $input['id'];

$verificar = $conn->prepare("SELECT finalizado FROM Recordatorios WHERE id = ?");
$verificar->execute([$id]);
$record = $verificar->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    echo json_encode(['success' => false, 'message' => 'No existe el recordatorio']);
    exit;
}

$nuevoEstado = $record['finalizado'] ? 0 : 1;

$update = $conn->prepare("UPDATE Recordatorios SET finalizado = ? WHERE id = ?");
$exito = $update->execute([$nuevoEstado, $id]);

if ($exito) {
    echo json_encode(['success' => true, 'message' => 'Recordatorio actualizado correctamente', 'finalizado' => $nuevoEstado]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el Recordatorio']);
}
?>
