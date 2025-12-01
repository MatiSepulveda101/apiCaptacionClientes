<?php
require_once 'verificar_token.php';
require_once 'conexion.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$cliente_direccion = $data['cliente_direccion'] ?? null;
$usuario_id = $usuarioAutenticado->id ?? null; 
$tipo_interaccion = $data['tipo_interaccion'] ?? null;
$fecha_interaccion = $data['fecha_interaccion'] ?? null;
$descripcion = $data['descripcion'] ?? '';

if (!$cliente_direccion || !$usuario_id || !$tipo_interaccion || !$fecha_interaccion) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Faltan campos requeridos']);
    exit;
}

// Verificar que exista el cliente
$stmtCheck = $conn->prepare("SELECT 1 FROM cliente WHERE direccion = :direccion LIMIT 1");
$stmtCheck->execute([':direccion' => $cliente_direccion]);
if (!$stmtCheck->fetch()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cliente no encontrado']);
    exit;
}

try {
    $conn->beginTransaction();

    // Insertar interacción
    $stmt = $conn->prepare("INSERT INTO interacciones (cliente_direccion, usuario_id, tipo_interaccion, fecha_interaccion, descripcion)
                            VALUES (:cliente_direccion, :usuario_id, :tipo_interaccion, :fecha_interaccion, :descripcion)");
    $stmt->execute([
        ':cliente_direccion' => $cliente_direccion,
        ':usuario_id' => $usuario_id,
        ':tipo_interaccion' => $tipo_interaccion,
        ':fecha_interaccion' => $fecha_interaccion,
        ':descripcion' => $descripcion
    ]);

    $interaccionId = $conn->lastInsertId();

    // Verificar si hay datos de recordatorio
    if (!empty($data['recordatorio'])) {
        $r = $data['recordatorio'];

        $fecha = $r['fecha'] ?? null;
        $hora = $r['hora'] ?? null;
        $minutos_antes = $r['minutos_antes'] ?? null;
        $motivo = $r['motivo'] ?? '';

        if (!$fecha || !$hora || !$minutos_antes) {
            throw new Exception('Faltan campos requeridos para el recordatorio');
        }

        $stmtRec = $conn->prepare("INSERT INTO recordatorios (interaccion_id, fecha, hora, minutos_antes, motivo)
                                   VALUES (:interaccion_id, :fecha, :hora, :minutos_antes, :motivo)");
        $stmtRec->execute([
            ':interaccion_id' => $interaccionId,
            ':fecha' => $fecha,
            ':hora' => $hora,
            ':minutos_antes' => $minutos_antes,
            ':motivo' => $motivo
        ]);
    }

    $conn->commit();

    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Interacción y recordatorio guardados correctamente', 'interaccion_id' => $interaccionId]);

} catch (PDOException $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
