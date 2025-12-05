<?php
header("Content-Type: application/json");

$host = 'datamaule.cl';
$port = '5432';
$dbname = 'ccerda_captacion_pruebas';
$user = 'ccerda';
$password = 'oQnUPUuiwqq6ZWpGgfDqxvxgHYU(';

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

try {
    $conn = new PDO($dsn, $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error al conectar a la base de datos: ' . $e->getMessage()]);
    exit;
}
?>
