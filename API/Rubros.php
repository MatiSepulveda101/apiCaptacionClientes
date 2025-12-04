<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'verificar_token.php';
require_once 'conexion.php';

$usuario = verificarToken();

// aceptar ambos nombres según el token
$tipoUsuarioId = $usuario->tipousuario_id ?? $usuario->TipoUsuario_id ?? null;

// Solo GET es público; POST/PUT/DELETE/PATCH requieren roles 1 y 2
$soloLectura = ($_SERVER['REQUEST_METHOD'] === 'GET');
if (!$soloLectura && !in_array($tipoUsuarioId, [1, 2])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

function normalizarNombre($str) {
    return trim(mb_strtolower($str));
}

try {

    // ============================================================
    // GET - Obtener TODOS los rubros (activos + inactivos)
    // ============================================================
    if ($method === 'GET') {
        $stmt = $conn->query("SELECT id, nombre, vigente FROM rubros ORDER BY nombre ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // Leer JSON
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // ============================================================
    // POST - Crear rubro (SIN permitir nombres duplicados)
    // ============================================================
    if ($method === 'POST') {

        if (!isset($input['nombre'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Falta nombre']);
            exit;
        }

        $nombre = normalizarNombre($input['nombre']);

        if (strlen($nombre) < 2) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Nombre demasiado corto']);
            exit;
        }

        // Verificar duplicado (vigente o no vigente)
        $stmt = $conn->prepare("
            SELECT id FROM rubros 
            WHERE LOWER(TRIM(nombre)) = :nombre
        ");
        $stmt->execute([':nombre' => $nombre]);

        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Ya existe un rubro con ese nombre']);
            exit;
        }

        // Crear
        $stmt = $conn->prepare("
            INSERT INTO rubros (nombre, vigente)
            VALUES (:nombre, true)
        ");
        $stmt->execute([':nombre' => $nombre]);

        echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
        exit;
    }

    // ============================================================
    // PUT - Actualizar rubro (evitar duplicados)
    // ============================================================
    if ($method === 'PUT') {

        if (!isset($input['id']) || !isset($input['nombre'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Faltan campos (id, nombre)']);
            exit;
        }

        $id = (int)$input['id'];
        $nombre = normalizarNombre($input['nombre']);

        // Verificar duplicado excluyendo el propio ID
        $stmt = $conn->prepare("
            SELECT id FROM rubros
            WHERE LOWER(TRIM(nombre)) = :nombre
            AND id != :id
        ");
        $stmt->execute([':nombre' => $nombre, ':id' => $id]);

        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => 'Ya existe otro rubro con ese nombre'
            ]);
            exit;
        }

        // Actualizar
        $stmt = $conn->prepare("
            UPDATE rubros
            SET nombre = :nombre
            WHERE id = :id
        ");
        $stmt->execute([':nombre' => $nombre, ':id' => $id]);

        echo json_encode(['success' => true]);
        exit;
    }

    // ============================================================
    // PATCH - Habilitar rubro (vigente = true)
    // ============================================================
    if ($method === 'PATCH') {

        $id = $_GET['id'] ?? $input['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Falta id']);
            exit;
        }

        $id = (int)$id;

        // Obtener nombre del rubro a habilitar
        $stmt = $conn->prepare("SELECT nombre FROM rubros WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $rubro = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rubro) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Rubro no encontrado']);
            exit;
        }

        $nombre = normalizarNombre($rubro['nombre']);

        // Verificar si ya existe otro rubro activo con ese nombre
        $stmt = $conn->prepare("
            SELECT id FROM rubros
            WHERE LOWER(TRIM(nombre)) = :nombre
            AND vigente = true
            AND id != :id
        ");
        $stmt->execute([':nombre' => $nombre, ':id' => $id]);

        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => 'Ya existe un rubro activo con ese nombre'
            ]);
            exit;
        }

        // Habilitar
        $stmt = $conn->prepare("
            UPDATE rubros
            SET vigente = true
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);

        echo json_encode(['success' => true]);
        exit;
    }

    // ============================================================
    // DELETE - Eliminación lógica (vigente = false)
    // ============================================================
    if ($method === 'DELETE') {

        $id = $_GET['id'] ?? $input['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Falta id']);
            exit;
        }

        $id = (int)$id;

        $stmt = $conn->prepare("
            UPDATE rubros
            SET vigente = false
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);

        echo json_encode(['success' => true]);
        exit;
    }

    // ============================================================
    // Método no permitido
    // ============================================================
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error DB: ' . $e->getMessage()
    ]);
}
?>
