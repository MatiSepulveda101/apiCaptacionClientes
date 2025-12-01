<?php
require_once 'conexion.php';
require_once 'verificar_token.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$tipoUsuario = $usuarioAutenticado->rol_id ?? $usuarioAutenticado->tipousuario_id ?? null;

if (!in_array($tipoUsuario, [1, 2, 3])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado.']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || empty($data['direccion_original'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No se proporcionó la dirección original.']);
        exit;
    }

    $direccion_original = $data['direccion_original'];
    $direccion = $data['direccion'] ?? null;
    if (!$direccion) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Debe proporcionar la nueva dirección.']);
        exit;
    }
    // Verificar si el cliente ya está asignado
    $stmtAsignado = $conn->prepare("SELECT asignado FROM cliente WHERE direccion = :direccion_original LIMIT 1");
    $stmtAsignado->bindValue(':direccion_original', $data['direccion_original']);
    $stmtAsignado->execute();
    $asignado = $stmtAsignado->fetchColumn();

    // Si es tipo 3 y el cliente está asignado, bloquear edición
    if ($tipoUsuario == 3 && $asignado) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'No puedes editar un cliente ya asignado.']);
        exit;
    }


    // Función para obtener id por nombre o null
    function getIdPorNombre($conn, $tabla, $nombre) {
        if (!$nombre) return null;
        $stmt = $conn->prepare("SELECT id FROM $tabla WHERE nombre = :nombre LIMIT 1");
        $stmt->bindValue(':nombre', $nombre);
        $stmt->execute();
        $id = $stmt->fetchColumn();
        return $id !== false ? (int)$id : null;
    }

    $rubro_id = null;
    if (isset($data['rubro_id']) && is_numeric($data['rubro_id'])) {
        // validar que exista ese rubro_id en la BD
        $stmtCheck = $conn->prepare("SELECT id FROM rubros WHERE id = :id LIMIT 1");
        $stmtCheck->bindValue(':id', $data['rubro_id'], PDO::PARAM_INT);
        $stmtCheck->execute();
        $existe = $stmtCheck->fetchColumn();
        if ($existe) {
            $rubro_id = $data['rubro_id'];
        }
    } else if (!empty($data['rubro'])) {
        $rubro_id = getIdPorNombre($conn, 'rubros', $data['rubro']);
    }
    $estado_interes_id = getIdPorNombre($conn, 'estado_interes', $data['estado_interes'] ?? null);
    $estado_negocio_id = getIdPorNombre($conn, 'estado_negocio', $data['estado_negocio'] ?? null);

    $sql = "UPDATE cliente SET
                direccion = :direccion,
                nombre_fantasia = :nombre_fantasia,
                razon_social = :razon_social,
                rut = :rut,
                telefono_contacto = :telefono_contacto,
                correo_empresa = :correo_empresa,
                rubro_id = :rubro_id,
                encargado_local = :encargado_local,
                datos_contacto = :datos_contacto,
                asignado = :asignado,
                latitud = :latitud,
                longitud = :longitud,
                estado_interes_id = :estado_interes_id,
                estado_negocio_id = :estado_negocio_id
            WHERE direccion = :direccion_original";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':direccion', $direccion);
    $stmt->bindValue(':nombre_fantasia', $data['nombre_fantasia'] ?? null);
    $stmt->bindValue(':razon_social', $data['razon_social'] ?? null);
    $stmt->bindValue(':rut', $data['rut'] ?? null);
    $stmt->bindValue(':telefono_contacto', $data['telefono_contacto'] ?? null);
    $stmt->bindValue(':correo_empresa', $data['correo_empresa'] ?? null);
    $stmt->bindValue(':rubro_id', $rubro_id, $rubro_id !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindValue(':encargado_local', $data['encargado_local'] ?? null);
    $stmt->bindValue(':datos_contacto', $data['datos_contacto'] ?? null);
    $stmt->bindValue(':asignado', isset($data['asignado']) ? filter_var($data['asignado'], FILTER_VALIDATE_BOOLEAN) : false, PDO::PARAM_BOOL);
    $stmt->bindValue(':latitud', $data['latitud'] ?? null, is_null($data['latitud'] ?? null) ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':longitud', $data['longitud'] ?? null, is_null($data['longitud'] ?? null) ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':estado_interes_id', $estado_interes_id, $estado_interes_id !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindValue(':estado_negocio_id', $estado_negocio_id, $estado_negocio_id !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindValue(':direccion_original', $direccion_original);
    $stmt->execute();

    // Actualizar relaciones si cambió la dirección
    if ($direccion_original !== $direccion) {
        $stmtUpdateInteracciones = $conn->prepare("UPDATE interacciones SET cliente_direccion = :nueva WHERE cliente_direccion = :anterior");
        $stmtUpdateInteracciones->bindValue(':nueva', $direccion);
        $stmtUpdateInteracciones->bindValue(':anterior', $direccion_original);
        $stmtUpdateInteracciones->execute();

        $stmtUpdateImagenes = $conn->prepare("UPDATE imagenes_cliente SET cliente_direccion = :nueva WHERE cliente_direccion = :anterior");
        $stmtUpdateImagenes->bindValue(':nueva', $direccion);
        $stmtUpdateImagenes->bindValue(':anterior', $direccion_original);
        $stmtUpdateImagenes->execute();
    }
    $imagenes = $data['imagenes'] ?? []; 
    // Manejar imágenes: borrar las que no están y agregar nuevas
    $stmtActuales = $conn->prepare("SELECT url FROM imagenes_cliente WHERE cliente_direccion = :direccion");
    $stmtActuales->bindValue(':direccion', $direccion);
    $stmtActuales->execute();
    $imagenesActuales = $stmtActuales->fetchAll(PDO::FETCH_COLUMN);

    foreach ($imagenesActuales as $imgActual) {
        if (!in_array($imgActual, $imagenes)) {
            $stmtDel = $conn->prepare("DELETE FROM imagenes_cliente WHERE cliente_direccion = :direccion AND url = :url");
            $stmtDel->bindValue(':direccion', $direccion);
            $stmtDel->bindValue(':url', $imgActual);
            $stmtDel->execute();
        }
    }

    foreach ($imagenes as $base64) {
        if (strpos($base64, 'data:image') === 0 && !in_array($base64, $imagenesActuales)) {
            $stmtImg = $conn->prepare("INSERT INTO imagenes_cliente (cliente_direccion, url) VALUES (:direccion, :url)");
            $stmtImg->bindValue(':direccion', $direccion);
            $stmtImg->bindValue(':url', $base64);
            $stmtImg->execute();
        }
    }

    // Actualizar descripción de la primera interacción si viene
    $descripcion_interaccion = $data['descripcion_interaccion'] ?? null;
    $usuario_id = $data['usuario_id'] ?? null;

    if ($descripcion_interaccion && $usuario_id) {
        $stmtBuscar = $conn->prepare("SELECT id FROM interacciones WHERE cliente_direccion = :direccion ORDER BY fecha_interaccion ASC LIMIT 1");
        $stmtBuscar->bindValue(':direccion', $direccion);
        $stmtBuscar->execute();
        $interaccion_id = $stmtBuscar->fetchColumn();

        if ($interaccion_id) {
            $stmtEditar = $conn->prepare("UPDATE interacciones SET descripcion = :descripcion WHERE id = :id");
            $stmtEditar->bindValue(':descripcion', $descripcion_interaccion);
            $stmtEditar->bindValue(':id', $interaccion_id, PDO::PARAM_INT);
            $stmtEditar->execute();
        }
    }

    echo json_encode(['success' => true, 'message' => 'Cliente actualizado correctamente.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en base de datos: ' . $e->getMessage()]);
}
