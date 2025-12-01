<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

require_once 'verificar_token.php';
require_once 'Conexion.php';

try {
    $stmt = $conn->prepare("SELECT * FROM configestilos WHERE id = 1");
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'estilo' => [
                'nombreNegocio' => $row['nombre_negocio'],
                'colorPrincipal' => $row['color_principal'],
                'logo' => $row['logo']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No hay configuración guardada.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
