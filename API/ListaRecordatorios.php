<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Solo se permite método POST']);
    exit;
}

require_once 'verificar_token.php';
require_once 'conexion.php';

$usuarioAutenticado = verificarToken();

$tipoUsuarioId = $usuarioAutenticado->tipousuario_id ?? $usuarioAutenticado->TipoUsuario_id ?? null;

if (!in_array($tipoUsuarioId, [1, 2])) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

$id_usuario = $usuarioAutenticado->id ?? null;

if (!$id_usuario) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Falta id_usuario']);
    exit;
}

try {
    $sql = "SELECT 
    r.id, 
    r.motivo, 
    i.usuario_id,  
    r.fecha, 
    r.hora,
    c.nombre_fantasia
    FROM recordatorios r
JOIN interacciones i ON r.interaccion_id = i.id
JOIN usuario u ON u.id = i.usuario_id
JOIN cliente c ON c.direccion = i.cliente_direccion
WHERE i.usuario_id = :id_usuario
            ";

    $stmt = $conn->prepare($sql);

        $stmt->bindParam(':id_usuario', $id_usuario);


    $stmt->execute();
    $recordatorios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'recordatorios' => $recordatorios]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en base de datos: ' . $e->getMessage()]);
}
