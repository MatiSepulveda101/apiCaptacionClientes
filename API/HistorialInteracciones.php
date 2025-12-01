<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Solo se permite método POST']);
    exit;
}

require 'verificar_token.php';
require 'conexion.php';

$usuarioAutenticado = verificarToken();

$tipoUsuarioId = $usuarioAutenticado->tipousuario_id ?? null;
$usuarioId = $usuarioAutenticado->id ?? null;

// Solo permitir tipo 1 y 2
if (!in_array($tipoUsuarioId, [1, 2])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$page  = isset($input['page']) ? max(1, intval($input['page'])) : 1;
$limit = isset($input['limit']) ? max(1, intval($input['limit'])) : 20;
$offset = ($page - 1) * $limit;

$vista = $input['vista'] ?? 'recientes';

$conn->beginTransaction();

try {
    if ($vista === 'noAtendidos') {
        // Pendientes: clientes con 0 o 1 interacción (solo creación) o sin interacciones hace 7 días o más
        $sqlNoAtendidos = "
            SELECT 
                c.direccion,
                c.nombre_fantasia,
                ua.nombre_completo AS usuario_asignado,
                uc.nombre_completo AS usuario_creacion,
                COUNT(i.id) AS interacciones_count,
                MAX(i.fecha_interaccion) AS ultima_fecha_interaccion,
                en.nombre AS estado_negocio
            FROM cliente c
            JOIN estado_negocio en ON en.id = c.estado_negocio_id
            LEFT JOIN interacciones i ON i.cliente_direccion = c.direccion
            LEFT JOIN usuario ua ON ua.id = c.id_asignado
            LEFT JOIN usuario uc ON uc.id = c.creado_por
            WHERE LOWER(en.nombre) = 'en proceso'
        ";

        // Filtro usuario tipo 2: solo clientes asignados a este usuario o sin asignar
        if ($tipoUsuarioId === 2) {
            $sqlNoAtendidos .= " AND (c.id_asignado = :usuario_id OR c.id_asignado IS NULL) ";
        }

        $sqlNoAtendidos .= "
            GROUP BY c.direccion, c.nombre_fantasia, ua.nombre_completo, uc.nombre_completo, en.nombre
            HAVING 
                (
                    COUNT(i.id) = 0
                    OR (
                        COUNT(i.id) = 1 
                        AND SUM(CASE WHEN i.tipo_interaccion = 'Creación de cliente' THEN 1 ELSE 0 END) = 1
                    )
                    OR (
                        MAX(i.fecha_interaccion) <= CURRENT_DATE - INTERVAL '7 days'
                    )
                )
            ORDER BY ultima_fecha_interaccion DESC NULLS LAST
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $conn->prepare($sqlNoAtendidos);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        if ($tipoUsuarioId === 2) {
            $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Total para paginación
        $sqlTotal = "
            SELECT COUNT(*) FROM (
                SELECT c.direccion
                FROM cliente c
                JOIN estado_negocio en ON en.id = c.estado_negocio_id
                LEFT JOIN interacciones i ON i.cliente_direccion = c.direccion
                WHERE LOWER(en.nombre) = 'en proceso'
        ";
        if ($tipoUsuarioId === 2) {
            $sqlTotal .= " AND (c.id_asignado = :usuario_id OR c.id_asignado IS NULL) ";
        }
        $sqlTotal .= "
                GROUP BY c.direccion
                HAVING 
                    (
                        COUNT(i.id) = 0
                        OR (
                            COUNT(i.id) = 1 
                            AND SUM(CASE WHEN i.tipo_interaccion = 'Creación de cliente' THEN 1 ELSE 0 END) = 1
                        )
                        OR (
                            MAX(i.fecha_interaccion) <= CURRENT_DATE - INTERVAL '7 days'
                        )
                    )
            ) sub
        ";

        $stmtTotal = $conn->prepare($sqlTotal);
        if ($tipoUsuarioId === 2) {
            $stmtTotal->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        }
        $stmtTotal->execute();
        $total = (int) $stmtTotal->fetchColumn();

    } else {
        // Activos o Finalizados
        if ($vista === 'recientes') {
            $whereEstado = "LOWER(en.nombre) = 'en proceso'";
        } elseif ($vista === 'finalizados') {
            $whereEstado = "LOWER(en.nombre) IN ('cerrado', 'rechazado')";
        } else {
            $whereEstado = "LOWER(en.nombre) = 'en proceso'";
        }

        $filtroUsuario = '';
        if ($tipoUsuarioId === 2) {
            $filtroUsuario = " AND i.usuario_id = :usuario_id ";
        }

        // Total para paginación
        $sqlTotal = "
            SELECT COUNT(*)
            FROM interacciones i
            JOIN usuario u ON u.id = i.usuario_id
            JOIN cliente c ON c.direccion = i.cliente_direccion
            JOIN estado_negocio en ON en.id = c.estado_negocio_id
            WHERE $whereEstado
            $filtroUsuario
        ";
        $stmtTotal = $conn->prepare($sqlTotal);
        if ($tipoUsuarioId === 2) {
            $stmtTotal->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        }
        $stmtTotal->execute();
        $total = (int) $stmtTotal->fetchColumn();

        // Consulta principal
        $sql = "
            SELECT 
                i.id,
                i.tipo_interaccion,
                i.fecha_interaccion,
                i.descripcion,
                u.nombre_completo AS usuario_nombre,
                c.nombre_fantasia,
                en.nombre AS estado_negocio
            FROM interacciones i
            JOIN usuario u ON u.id = i.usuario_id
            JOIN cliente c ON c.direccion = i.cliente_direccion
            JOIN estado_negocio en ON en.id = c.estado_negocio_id
            WHERE $whereEstado
            $filtroUsuario
            ORDER BY i.fecha_interaccion DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        if ($tipoUsuarioId === 2) {
            $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "data" => $datos,
        "total" => $total
    ]);
} catch (PDOException $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Error en consulta: " . $e->getMessage()
    ]);
}
