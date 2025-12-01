<?php
header("Content-Type: application/json");
require_once 'conexion.php';
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Dotenv\Dotenv;

// Cargar variables de entorno (.env)
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $input = json_decode(file_get_contents("php://input"), true);

    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');
    $expoToken = trim($input['expoToken'] ?? '');

    if ($username === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Faltan credenciales']);
        exit;
    }

    try {
        $stmt = $conn->prepare("
            SELECT 
                u.id, 
                u.nombre_usuario, 
                u.nombre_completo, 
                u.correo,
                u.tipousuario_id,
                tu.nombre AS tipo_usuario,
                u.contrasena,
                u.estado
            FROM usuario u
            LEFT JOIN tipousuario tu ON u.tipousuario_id = tu.id
            WHERE LOWER(u.nombre_usuario) = LOWER(:username)
            LIMIT 1
        ");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && !empty($user['contrasena']) && password_verify($password, $user['contrasena'])) {

            // Verificar si el usuario está activo
            if ((int) $user['estado'] !== 1) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Usuario desactivado']);
                exit;
            }
            if (!empty($expoToken)) {
                $stmtUpdate = $conn->prepare("
            UPDATE usuario
            SET expoToken = :expoToken
            WHERE id = :user_id
        ");
                $stmtUpdate->execute([
                    'expoToken' => $expoToken,
                    'user_id' => $user['id']
                ]);
            }

            $secretKey = $_ENV['JWT_SECRET_KEY'] ?? null;
            if (!$secretKey) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'JWT_SECRET_KEY no definido en .env']);
                exit;
            }

            $payload = [
                'id' => $user['id'],
                'usuario' => $user['nombre_usuario'],
                'correo' => $user['correo'],
                'tipousuario_id' => $user['tipousuario_id'],
                'exp' => time() + (60 * 60 * 24 * 7), // 7 días
            ];

            $jwt = JWT::encode($payload, $secretKey, 'HS256');

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'token' => $jwt,
                'usuario' => [
                    'id' => $user['id'],
                    'nombre_usuario' => $user['nombre_usuario'],
                    'nombre' => $user['nombre_completo'],
                    'correo' => $user['correo'],
                    'rol' => $user['tipo_usuario'],
                    'rol_id' => $user['tipousuario_id']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Usuario o contraseña incorrectos.']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
