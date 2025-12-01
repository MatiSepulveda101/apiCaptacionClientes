<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'verificar_token.php'; // Aquí haces la verificación JWT (lanza error y exit si falla)
require 'conexion.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !is_array($data)) {
        echo json_encode(['success' => false, 'error' => 'No se recibió información válida.']);
        exit;
    }

    // Asegurarse de que al menos uno esté presente
    if (!isset($data['nombreNegocio']) && !isset($data['colorPrincipal']) && !isset($data['logo'])) {
        echo json_encode(['success' => false, 'error' => 'Debes enviar al menos un campo.']);
        exit;
    }

    // Prepara partes dinámicamente
    $campos = [];
    $params = [];

    if (isset($data['nombreNegocio'])) {
        $campos[] = "nombre_negocio = :nombre";
        $params[':nombre'] = $data['nombreNegocio'];
    }

    if (isset($data['colorPrincipal'])) {
        $campos[] = "color_principal = :color";
        $params[':color'] = $data['colorPrincipal'];
    }

    if (isset($data['logo'])) {
        $campos[] = "logo = :logo";
        $params[':logo'] = $data['logo'];
    }

    // Siempre actualizar la fecha
    $campos[] = "actualizado_en = NOW()";

    // Asegúrate que el registro exista
    $stmt = $conn->prepare("SELECT id FROM configestilos WHERE id = 1");
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        // Actualiza solo los campos enviados
        $sql = "UPDATE configestilos SET " . implode(", ", $campos) . " WHERE id = 1";
    } else {
        // Inserta con campos disponibles
        $sqlCampos = ['id'];
        $sqlValores = [':id'];
        $params[':id'] = 1;

        if (isset($data['nombreNegocio'])) {
            $sqlCampos[] = 'nombre_negocio';
            $sqlValores[] = ':nombre';
        }

        if (isset($data['colorPrincipal'])) {
            $sqlCampos[] = 'color_principal';
            $sqlValores[] = ':color';
        }

        if (isset($data['logo'])) {
            $sqlCampos[] = 'logo';
            $sqlValores[] = ':logo';
        }

        $sql = "INSERT INTO configestilos (" . implode(', ', $sqlCampos) . ") VALUES (" . implode(', ', $sqlValores) . ")";
    }

    $stmtUpdate = $conn->prepare($sql);
    $stmtUpdate->execute($params);

    echo json_encode(['success' => true, 'message' => 'Configuración actualizada correctamente.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
