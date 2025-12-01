<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit(0);
}

// Cargar .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$secretKey = $_ENV['JWT_SECRET_KEY'] ?? null;

if (!$secretKey) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Clave JWT no definida en .env']);
    exit;
}

// Capturar Authorization (usando getallheaders)
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = null;

if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $token = $matches[1];
}

if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token no proporcionado']);
    exit;
}

try {
    $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token inválido: ' . $e->getMessage()]);
    exit;
}

$response = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['imagen'])) {
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileTmpPath = $_FILES['imagen']['tmp_name'];
    $fileName = uniqid() . '_' . basename($_FILES['imagen']['name']);
    $fileDest = $uploadDir . $fileName;

    if (move_uploaded_file($fileTmpPath, $fileDest)) {
        $response = ['success' => true, 'url' => $fileDest];
    } else {
        $response = ['success' => false, 'error' => 'Error al mover el archivo.'];
    }
} else {
    $response = ['success' => false, 'error' => 'No se recibió archivo.'];
}

echo json_encode($response);