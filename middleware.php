<?php
// config/middleware.php

// 1. CORS Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Logging Middleware
function logRequest()
{
    $log = date('Y-m-d H:i:s') . " | Method: " . $_SERVER['REQUEST_METHOD'] . " | URI: " . $_SERVER['REQUEST_URI'] . "\n";
    @file_put_contents(__DIR__ . '/../api_requests.log', $log, FILE_APPEND);
}
logRequest();

// 3. Simple Rate Limiting Middleware (Max 60 requests per minute per IP)
session_start();
$ip = $_SERVER['REMOTE_ADDR'];
$time = time();
$_SESSION['rate_limit'][$ip][] = $time;
$_SESSION['rate_limit'][$ip] = array_filter($_SESSION['rate_limit'][$ip], fn($t) => $t > ($time - 60));

if (count($_SESSION['rate_limit'][$ip]) > 60) {
    http_response_code(429);
    echo json_encode(["status" => "error", "message" => "Rate limit exceeded. Try again in a minute."]);
    exit();
}
?>