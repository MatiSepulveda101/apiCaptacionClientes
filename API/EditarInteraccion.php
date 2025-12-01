<?php
require_once 'verificar_token.php'; 
require_once 'conexion.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// Manejo de preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Verificar token y obtener usuario autenticado
$usuarioAutenticado = verificarToken();

$rolId = $usuarioAutenticado->tipousuario_id ?? $usuarioAutenticado->rol_id ?? null;
if (!in_array($rolId, [1, 2])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado.']);
    exit;
}

// Leer datos JSON
$input = json_decode(file_get_contents("php://input"), true);

if (!$input || empty($input['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Falta el ID de la interacción.']);
    exit;
}

$id = (int)$input['id'];
$tipo = trim($input['tipo'] ?? '');
$fecha = $input['fecha'] ?? '';
$observacion = trim($input['observacion'] ?? '');

if (!$tipo || !$fecha || !$observacion) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Todos los campos son obligatorios.']);
    exit;
}

$date = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$date || $date->format('Y-m-d') !== $fecha) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Formato de fecha inválido. Use YYYY-MM-DD']);
    exit;
}
try {
    // Actualizar interacción
    $stmt = $conn->prepare("
        UPDATE interacciones
        SET tipo_interaccion = :tipo,
            fecha_interaccion = :fecha,
            descripcion = :observacion
        WHERE id = :id
    ");
    $stmt->bindValue(':tipo', $tipo);
    $stmt->bindValue(':fecha', $fecha);
    $stmt->bindValue(':observacion', $observacion);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    // Actualizar o insertar recordatorio
    $recordatorio = $input['recordatorio'] ?? null;

    if ($recordatorio) {
        $motivo = $recordatorio['titulo'] ?? ''; // puedes usar 'motivo' también si quieres
        $fechaHora = $recordatorio['fecha_hora'] ?? null; // formato: "YYYY-MM-DD HH:mm:ss"

        // Separar fecha y hora
        $fecha = null;
        $hora = null;
        if ($fechaHora) {
            $fecha = substr($fechaHora, 0, 10);  // "YYYY-MM-DD"
            $hora = substr($fechaHora, 11);      // "HH:mm:ss"
        }
        $minutos_antes = $recordatorio['minutos_antes'] ?? 10;

        $stmtCheck = $conn->prepare("SELECT id FROM recordatorios WHERE interaccion_id = :id");
        $stmtCheck->execute([':id' => $id]);
        $existe = $stmtCheck->fetchColumn();

        if ($existe) {
            $sqlUpdate = "UPDATE recordatorios 
                        SET motivo = :motivo, fecha = :fecha, hora = :hora, minutos_antes = :minutos_antes 
                        WHERE interaccion_id = :id";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':motivo' => $motivo,
                ':fecha' => $fecha,
                ':hora' => $hora,
                ':minutos_antes' => $minutos_antes,
                ':id' => $id,
            ]);
        } else {
            $sqlInsert = "INSERT INTO recordatorios (interaccion_id, motivo, fecha, hora, minutos_antes)
                        VALUES (:id, :motivo, :fecha, :hora, :minutos_antes)";
            $stmtInsert = $conn->prepare($sqlInsert);
            $stmtInsert->execute([
                ':id' => $id,
                ':motivo' => $motivo,
                ':fecha' => $fecha,
                ':hora' => $hora,
                ':minutos_antes' => $minutos_antes,
        ]);
    }
}

echo json_encode(['success' => true]);
exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al actualizar: ' . $e->getMessage()]);
}
