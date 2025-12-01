<?php
function logRequest() {
    $date = date("Y-m-d H:i:s");
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    $url = $_SERVER['REQUEST_URI'] ?? '';
    $headers = json_encode(getallheaders());
    $body = file_get_contents("php://input");

    $log = "[$date] [$ip] [$method] $url | Headers: $headers | Body: $body" . PHP_EOL;

    file_put_contents(__DIR__ . "/api_log.txt", $log, FILE_APPEND);
}
logRequest();
