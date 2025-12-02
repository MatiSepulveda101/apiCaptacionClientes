<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$path = ltrim($path, '/');

if (!file_exists($path)) {
    http_response_code(404);
    echo json_encode(["error" => "Endpoint no encontrado", "path" => $path]);
    exit();
}

require $path;
