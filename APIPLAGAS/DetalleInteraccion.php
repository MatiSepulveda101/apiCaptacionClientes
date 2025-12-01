<?php
require_once 'verificar_token.php'; 
require_once 'conexion.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST");

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    exit;
}

// Verificar token y obtener usuario autenticado
$usuarioAutenticado = verificarToken();

$rolId = $usuarioAutenticado->rol_id ?? $usuarioAutenticado->tipousuario_id ?? null;

if (!in_array($rolId, [1, 2])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado.']);
    exit;
}

// Obtener datos JSON
$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'] ?? null;

if (!$id || !is_numeric($id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID no proporcionado o inválido.']);
    exit;
}

try {
    // Obtener datos de la interacción
    $sql = "SELECT 
                i.id,
                i.tipo_interaccion AS tipo,
                i.fecha_interaccion AS fecha,
                i.descripcion AS observacion,
                c.nombre_fantasia AS cliente,
                c.direccion AS cliente_direccion,
                u.nombre_completo AS creado_por_nombre
            FROM interacciones i
            JOIN cliente c ON c.direccion = i.cliente_direccion
            JOIN usuario u ON u.id = i.usuario_id
            WHERE i.id = :id
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $interaccion = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($interaccion) {
        // Obtener recordatorios asociados a esta interacción
        $sqlRecordatorios = "SELECT id, fecha, hora, motivo, minutos_antes
                             FROM recordatorios 
                             WHERE interaccion_id = :interaccion_id 
                             ORDER BY fecha, hora";

        $stmtRecordatorios = $conn->prepare($sqlRecordatorios);
        $stmtRecordatorios->bindValue(':interaccion_id', $id, PDO::PARAM_INT);
        $stmtRecordatorios->execute();

        $recordatorios = $stmtRecordatorios->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'interaccion' => $interaccion,
            'recordatorios' => $recordatorios
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Interacción no encontrada.']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en base de datos: ' . $e->getMessage()]);
}
