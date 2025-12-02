<?php
header("Content-Type: application/json");
require_once 'middleware_log.php';

$connectionString = "postgresql://captacioncliente_user:W8zeu4sTlpatQsWJtHxzl6qK3zHaxtl6@dpg-d4mvl73uibrs738ujdkg-a/captacioncliente";

$parts = parse_url($connectionString);

$host = $parts['host'];
$port = $parts['port'] ?? 5432;
$user = $parts['user'];
$pass = $parts['pass'];
$db   = ltrim($parts['path'], '/');

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$db", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al conectar a la base de datos: ' . $e->getMessage()
    ]);
    exit;
}
?>
