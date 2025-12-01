<?php
header("Content-Type: application/json");
require_once 'middleware_log.php';
$host = 'localhost';
$port = '5433';
$dbname = 'captacionCliente';
$user = 'postgres';
$password = '159753';

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

try {
    $conn = new PDO($dsn, $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error al conectar a la base de datos: ' . $e->getMessage()]);
    exit;
}
?>
