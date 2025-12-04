<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Solo se permite método POST']);
    exit;
}

require_once 'verificar_token.php';
require_once 'conexion.php';

$usuarioAutenticado = verificarToken();

// validar permisos (ej. tipo 1 y 2 permitidos)
$tipoUsuarioId = $usuarioAutenticado->tipousuario_id ?? $usuarioAutenticado->TipoUsuario_id ?? null;
if (!in_array($tipoUsuarioId, [1, 2])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

// Leer JSON body
$input = json_decode(file_get_contents('php://input'), true);

$id_recordatorio = isset($input['id_recordatorio']) ? (int)$input['id_recordatorio'] : null;
$justificacion = isset($input['justificacion']) ? trim($input['justificacion']) : null;
$nuevo_motivo = isset($input['nuevo_motivo']) ? trim($input['nuevo_motivo']) : null;
$fecha = isset($input['fecha']) ? trim($input['fecha']) : null; // formato YYYY-MM-DD
$hora = isset($input['hora']) ? trim($input['hora']) : null;   // formato HH:MM
$minutos_antes = isset($input['minutos_antes']) ? (int)$input['minutos_antes'] : null;

if (!$id_recordatorio) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Falta id_recordatorio']);
    exit;
}
if (!$justificacion || strlen($justificacion) < 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Falta justificacion (mínimo 5 caracteres)']);
    exit;
}
if (!$fecha || !$hora || $minutos_antes === null || $nuevo_motivo === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Faltan datos para reagendar (fecha, hora, minutos_antes, nuevo_motivo)']);
    exit;
}

try {

    $conn->beginTransaction();

    $stmtSel = $conn->prepare("SELECT motivo, fecha, hora, minutos_antes FROM recordatorios WHERE id = :id FOR UPDATE");
    $stmtSel->execute([':id' => $id_recordatorio]);
    $current = $stmtSel->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        $conn->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Recordatorio no encontrado']);
        exit;
    }

    $motivo_anterior = $current['motivo'];
    // insertar en tabla reagendado
    $sqlInsert = "INSERT INTO reagendado (id_recordatorio, motivo_anterior, nuevo_motivo, justificacion, fecha_reagendado)
                  VALUES (:id_recordatorio, :motivo_anterior, :nuevo_motivo, :justificacion, NOW())";
    $stmtIns = $conn->prepare($sqlInsert);
    $stmtIns->execute([
        ':id_recordatorio' => $id_recordatorio,
        ':motivo_anterior' => $motivo_anterior,
        ':nuevo_motivo'    => $nuevo_motivo,
        ':justificacion'   => $justificacion
    ]);

    $sqlUpdate = "UPDATE recordatorios
                  SET enviado = :enviado,
                      vigente = :vigente,
                      fecha = :fecha,
                      hora = :hora,
                      minutos_antes = :minutos_antes,
                      motivo = :motivo
                  WHERE id = :id";
    $stmtUpd = $conn->prepare($sqlUpdate);
    $stmtUpd->execute([
        ':fecha' => $fecha,
        ':hora'  => $hora,
        ':minutos_antes' => $minutos_antes,
        ':motivo' => $nuevo_motivo,
        ':id' => $id_recordatorio,
        ':enviado' => 0,
        ':vigente' => 1,
    ]);

    $conn->commit();

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error en base de datos: ' . $e->getMessage()
    ]);
}
