<?php
require 'vendor/autoload.php';
require 'conexion.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$data = json_decode(file_get_contents('php://input'), true);
$correo = strtolower(trim($data['correo'] ?? ''));

if (!$correo) {
    echo json_encode(['success' => false, 'error' => 'Faltan datos']);
    exit;
}

try {
    // ✅ Ahora también traemos el estado
    $query = $conn->prepare("SELECT id, nombre_usuario, estado FROM usuario WHERE correo = :correo");
    $query->execute(['correo' => $correo]);
    $user = $query->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'El correo ingresado no es valido.']);
        exit;
    }

    // ✅ Verificar si está inactivo
    if (!$user['estado']) {
        echo json_encode(['success' => false, 'error' => 'El usuario está inactivo.']);
        exit;
    }

    // ✅ Si pasa, generar código
    $codigo = rand(100000, 999999);
    $expira = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $delete = $conn->prepare("DELETE FROM RecuperarContrasena WHERE usuario_id = :id");
    $delete->execute(['id' => $user['id']]);

    $insert = $conn->prepare("INSERT INTO RecuperarContrasena (usuario_id, codigo, expiracion) VALUES (:id, :codigo, :expiracion)");
    $insert->execute([
        'id' => $user['id'],
        'codigo' => $codigo,
        'expiracion' => $expira
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error en base de datos: ' . $e->getMessage()]);
    exit;
}

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'ssl://mail.datamaule.cl';
    $mail->SMTPAuth = true;
    $mail->Username = 'soporteapp@datamaule.cl';
    $mail->Password = 'Ollt&_eLF5NC5SjW';
    $mail->SMTPSecure = 'ssl';
    $mail->Port = 465;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom('soporteapp@datamaule.cl', 'SOPORTE DATAMAULE');   
    $mail->addAddress($correo, $user['nombre_usuario']);

    $mail->isHTML(true);
    $mail->Subject = "Contraseña de Captativa Clientes";
    $mail->Body = "<p>¿Te has olvidado de tu contraseña, <b>{$user['nombre_usuario']}</b>?</p>
               <p>Tu código de recuperación es: <b style='font-size: 24px;'>$codigo</b></p>
               <p>Este código expirará en 10 minutos.</p>";

    $mail->send();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'No se pudo enviar el correo.',
        'detalle' => $mail->ErrorInfo
    ]);
}
