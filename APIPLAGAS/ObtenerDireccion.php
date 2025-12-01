<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

require_once 'verificar_token.php';
require_once 'conexion.php';

// Leer JSON desde el body
$input = json_decode(file_get_contents("php://input"), true);
$direccion = $input['direccion'] ?? '';

if (empty($direccion)) {
    echo json_encode(['success' => false, 'error' => 'No se proporcionó la dirección.']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT 
            c.direccion,
            c.nombre_fantasia,
            c.razon_social,
            c.rut,
            c.telefono_contacto,
            c.correo_empresa,
            r.nombre AS rubro_local,
            c.encargado_local,
            c.datos_contacto,
            c.asignado,
            c.latitud,
            c.longitud,
            ei.nombre AS estado_interes,
            en.nombre AS estado_negocio,
            u.nombre_completo AS creado_por,
            ua.nombre_completo AS nombre_asignado,
            primera.descripcion AS descripcion_creacion
        FROM cliente c
        LEFT JOIN rubros r ON c.rubro_id = r.id
        LEFT JOIN estado_interes ei ON c.estado_interes_id = ei.id
        LEFT JOIN estado_negocio en ON c.estado_negocio_id = en.id
        LEFT JOIN usuario u ON c.creado_por = u.id
        LEFT JOIN usuario ua ON c.id_asignado = ua.id
        LEFT JOIN (
            SELECT i1.*
            FROM interacciones i1
            INNER JOIN (
                SELECT cliente_direccion, MIN(id) AS min_id
                FROM interacciones
                GROUP BY cliente_direccion
            ) i2 ON i1.id = i2.min_id
        ) primera ON primera.cliente_direccion = c.direccion
        WHERE c.direccion = :direccion
        LIMIT 1
    ");
    $stmt->bindParam(':direccion', $direccion);
    $stmt->execute();
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cliente) {
        $cliente['asignado'] = filter_var($cliente['asignado'], FILTER_VALIDATE_BOOLEAN);

        $stmtImg = $conn->prepare("SELECT url FROM imagenes_cliente WHERE cliente_direccion = :direccion");
        $stmtImg->bindParam(':direccion', $direccion);
        $stmtImg->execute();
        $imagenes = $stmtImg->fetchAll(PDO::FETCH_COLUMN);

        $cliente['imagenes'] = $imagenes ?: [];

        echo json_encode(['success' => true, 'cliente' => $cliente]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Cliente no encontrado']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
