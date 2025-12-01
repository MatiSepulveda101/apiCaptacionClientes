<?php
require_once 'verificar_token.php';
require_once 'conexion.php';

global $usuarioAutenticado;

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No se recibió ningún dato.']);
    exit;
}

if (empty($input['direccion'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'El campo dirección es obligatorio']);
    exit;
}

// Función para normalizar dirección (quita espacios extra y pasa a minúsculas)
function normalizarDireccion($direccion) {
    $direccion = trim($direccion);
    $direccion = preg_replace('/\s+/', ' ', $direccion); 
    $direccion = mb_convert_case($direccion, MB_CASE_TITLE, "UTF-8"); 
    return $direccion;
}


function calcularDistanciaMetros($lat1, $lon1, $lat2, $lon2) {
    $radioTierra = 6371000; // en metros
    $lat1Rad = deg2rad($lat1);
    $lat2Rad = deg2rad($lat2);
    $deltaLat = deg2rad($lat2 - $lat1);
    $deltaLon = deg2rad($lon2 - $lon1);

    $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
         cos($lat1Rad) * cos($lat2Rad) *
         sin($deltaLon / 2) * sin($deltaLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $radioTierra * $c;
}

$direccionNormalizada = normalizarDireccion($input['direccion']);

// Verificar duplicado antes de insertar
$stmtCheck = $conn->prepare("SELECT 1 FROM cliente WHERE TRIM(direccion) = :direccion LIMIT 1");
$stmtCheck->bindValue(':direccion', $direccionNormalizada);
$stmtCheck->execute();

if ($stmtCheck->fetchColumn()) {
    http_response_code(409); // Conflict
    echo json_encode(['success' => false, 'error' => 'Ya existe un cliente con esa dirección.']);
    exit;
}
if (!empty($input['latitud']) && !empty($input['longitud'])) {
    $lat = $input['latitud'];
    $lon = $input['longitud'];

    // Obtener todas las ubicaciones existentes
    $stmt = $conn->prepare("SELECT nombre_fantasia, direccion, latitud, longitud FROM cliente WHERE latitud IS NOT NULL AND longitud IS NOT NULL");
    $stmt->execute();
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($clientes as $clienteExistente) {
        $distancia = calcularDistanciaMetros($lat, $lon, $clienteExistente['latitud'], $clienteExistente['longitud']);

        if ($distancia <= 10 && empty($input['forzar_guardado'])) {
            http_response_code(202);
            echo json_encode([
                'success' => false,
                'proximidad' => true,
                'mensaje' => 'La ubicación ingresada está cerca del cliente "' . $clienteExistente['nombre_fantasia'] . '".',
                'cliente_cercano' => $clienteExistente['nombre_fantasia'],
                'direccion_cercana' => $clienteExistente['direccion']
            ]);
            exit;
        }
        }
    }
// Actualizar dirección con valor normalizado para guardar
$input['direccion'] = $direccionNormalizada;

if (!isset($input['creado_por']) || !$input['creado_por']) {
    $input['creado_por'] = $usuarioAutenticado->id ?? null;
}

function getIdPorNombre($conn, $tabla, $nombre) {
    if (!$nombre) return null;
    $stmt = $conn->prepare("SELECT id FROM $tabla WHERE nombre = :nombre LIMIT 1");
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmt->execute();
    $id = $stmt->fetchColumn();
    return $id !== false ? (int)$id : null;
}

if (isset($input['rubro'])) {
    if (is_numeric($input['rubro'])) {
        $rubroId = (int)$input['rubro'];
        $stmtCheck = $conn->prepare("SELECT id FROM rubros WHERE id = :id");
        $stmtCheck->bindValue(':id', $rubroId, PDO::PARAM_INT);
        $stmtCheck->execute();
        $exists = $stmtCheck->fetchColumn();
        $input['rubro_id'] = $exists ? $rubroId : null;
    } else {
        $input['rubro_id'] = getIdPorNombre($conn, 'rubros', $input['rubro']);
    }
}

if (isset($input['estado_interes'])) {
    $input['estado_interes_id'] = getIdPorNombre($conn, 'estado_interes', $input['estado_interes']);
}

if (!isset($input['estado_negocio_id'])) {
    if (isset($input['estado_negocio'])) {
        $input['estado_negocio_id'] = getIdPorNombre($conn, 'estado_negocio', $input['estado_negocio']);
        if (is_null($input['estado_negocio_id'])) {
            $input['estado_negocio_id'] = 2; 
        }
    } else {
        $input['estado_negocio_id'] = 2;
    }
}

$input['fecha_creacion'] = date('Y-m-d');

$campos = [
    'direccion', 'nombre_fantasia', 'razon_social', 'rut',
    'telefono_contacto', 'correo_empresa', 'rubro_id', 'encargado_local',
    'datos_contacto', 'latitud', 'longitud', 'creado_por',
    'estado_interes_id', 'estado_negocio_id', 'fecha_creacion'
];

$datos = array_filter(
    $input,
    fn($key) => in_array($key, $campos),
    ARRAY_FILTER_USE_KEY
);

try {
    $conn->beginTransaction();

    $columnas = implode(',', array_keys($datos));
    $placeholders = implode(',', array_map(fn($k) => ":$k", array_keys($datos)));
    $sql = "INSERT INTO cliente ($columnas) VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);

    foreach ($datos as $clave => $valor) {
        if (is_null($valor)) {
            $stmt->bindValue(":$clave", null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(":$clave", $valor);
        }
    }

    $stmt->execute();

    $direccionInsertada = $input['direccion'];

    $usuario_id = $usuarioAutenticado->id ?? null;
    $descripcion_interaccion = $input['descripcion_interaccion'] ?? 'Creación de cliente';

    if ($direccionInsertada && $usuario_id) {
        $stmt2 = $conn->prepare("
            INSERT INTO interacciones (
                cliente_direccion,
                usuario_id,
                tipo_interaccion,
                fecha_interaccion,
                descripcion
            ) VALUES (
                :direccion,
                :usuario_id,
                'Creación de cliente',
                CURRENT_DATE,
                :descripcion
            )
        ");
        $stmt2->bindValue(':direccion', $direccionInsertada);
        $stmt2->bindValue(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt2->bindValue(':descripcion', $descripcion_interaccion);
        $stmt2->execute();
    }

    // Insertar imágenes si vienen
    if (!empty($input['imagenes']) && is_array($input['imagenes']) && $direccionInsertada) {
        $stmtImg = $conn->prepare("
            INSERT INTO imagenes_cliente (cliente_direccion, url)
            VALUES (:direccion, :url)
        ");

        foreach ($input['imagenes'] as $img) {
            $stmtImg->bindValue(':direccion', $direccionInsertada);
            $stmtImg->bindValue(':url', $img);
            $stmtImg->execute();
        }
    }

    $conn->commit();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Cliente y registro de interacción guardados correctamente.'
    ]);
} catch (PDOException $e) {
    $conn->rollBack();

    if ($e->getCode() === '23505') { // Clave duplicada
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Ya existe un cliente con esa dirección.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error al guardar: ' . $e->getMessage()
        ]);
    }
}