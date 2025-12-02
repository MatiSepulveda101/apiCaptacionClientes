<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
    exit(0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

require_once 'conexion.php';

function enviarExpo($expoToken, $mensaje, $titulo)
{
    $data = [
        "to" => $expoToken,
        "sound" => "default",
        "title" => $titulo,
        "body" => $mensaje
    ];

    $ch = curl_init("https://exp.host/--/api/v2/push/send");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Accept: application/json",
        "User-Agent: PHP-cURL"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if ($response === false) {
        return ['success' => false, 'error' => curl_error($ch)];
    }

    curl_close($ch);
    return json_decode($response, true);
}
try {
    $sql = "SELECT 
    r.id, 
    r.motivo, 
    i.usuario_id, 
    u.expotoken, 
    r.fecha, 
    r.hora,
    r.minutos_antes,
    c.nombre_fantasia,
    (r.fecha + r.hora - (r.minutos_antes || ' minutes')::interval) AS fecha_restar
FROM recordatorios r
JOIN interacciones i ON r.interaccion_id = i.id
JOIN usuario u ON u.id = i.usuario_id
JOIN cliente c ON c.direccion = i.cliente_direccion
WHERE 
    r.fecha = CURRENT_DATE
    AND r.enviado = false
    AND (r.fecha + r.hora - (r.minutos_antes || ' minutes')::interval) 
        <= (NOW() AT TIME ZONE 'America/Santiago');
";
    $stmt = $conn->query($sql);
    $recordatorios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($recordatorios as $r) {

        if (empty($r['expotoken'])) continue;

        $horaRecordatorio = substr($r['hora'], 0, 5);

        $mensaje = " Recordatorio para las " . $horaRecordatorio . ": " . $r['motivo'];

        $resultado = enviarExpo($r['expotoken'], $mensaje, $r['nombre_fantasia']);

        if (!isset($resultado['errors'])) {
            $update = $conn->prepare("UPDATE recordatorios SET enviado = true WHERE id = :id");
            $update->execute(['id' => $r['id']]);
        } else {
            error_log("Error enviando notificación a usuario {$r['usuario_id']}: " . json_encode($resultado['errors']));
        }
    }

    echo json_encode(['success' => true, 'count' => count($recordatorios)]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

