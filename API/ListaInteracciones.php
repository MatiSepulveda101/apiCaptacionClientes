<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// Manejo de preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Solo POST permitido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'error' => 'Solo se permite método POST']);
    exit;
}

require_once 'verificar_token.php';  
require_once 'conexion.php';

// Verificar token y rol
$usuarioAutenticado = verificarToken();

$tipoUsuarioId = $usuarioAutenticado->tipousuario_id ?? $usuarioAutenticado->TipoUsuario_id ?? null;

if (!in_array($tipoUsuarioId, [1, 2])) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

// Leer datos del body
$input = json_decode(file_get_contents("php://input"), true);
$direccion = $input['direccion'] ?? null;

try {
    $sql = "SELECT 
                i.id,
                i.tipo_interaccion AS tipo,
                i.fecha_interaccion AS fecha,
                i.descripcion AS observacion,
                c.nombre_fantasia AS cliente,
                u.nombre_completo AS creado_por
            FROM interacciones i
            JOIN cliente c ON c.direccion = i.cliente_direccion
            JOIN usuario u ON u.id = i.usuario_id";

    if ($direccion) {
        $sql .= " WHERE i.cliente_direccion = :direccion";
    }

    $stmt = $conn->prepare($sql);

    if ($direccion) {
        $stmt->bindParam(':direccion', $direccion);
    }

    $stmt->execute();
    $interacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'interacciones' => $interacciones]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en base de datos: ' . $e->getMessage()]);
}
