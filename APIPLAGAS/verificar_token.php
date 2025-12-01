<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once 'conexion.php';

use Dotenv\Dotenv;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

function getAuthorizationHeader() {
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim($_SERVER["HTTP_AUTHORIZATION"]);
    }
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                return trim($value);
            }
        }
    }
    return null;
}

function verificarToken() {
    global $conn;

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json");

    $secretKey = $_ENV['JWT_SECRET_KEY'] ?? null;

    if (!$secretKey) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Clave secreta no definida']);
        exit;
    }

    $authHeader = getAuthorizationHeader();

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token no proporcionado']);
        exit;
    }

    $jwt = $matches[1];

    try {
        $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));

        $id = $decoded->id ?? null;

        if (!$id) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Token inválido (sin ID)']);
            exit;
        }

        // Corrección aquí: se usa TipoUsuario_id en lugar de rol_id
        $stmt = $conn->prepare("SELECT id, usuario, correo, tipoUsuario_id FROM usuario WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $usuarioBD = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$usuarioBD) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
            exit;
        }

        return $usuarioBD;

    } catch (\Firebase\JWT\ExpiredException $e) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token expirado']);
        exit;
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token inválido', 'detalle' => $e->getMessage()]);
        exit;
    }
}
$usuarioAutenticado = verificarToken();