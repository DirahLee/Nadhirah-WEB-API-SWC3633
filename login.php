<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/config/database.php";

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "fail", "message" => "Method Not Allowed."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['email']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(["status" => "fail", "message" => "Email and password are required."]);
    exit();
}

try {
    $stmt = $db->prepare("SELECT user_id, name, email, password, role FROM Users WHERE email = ?");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($data['password'], $user['password'])) {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Login successful.",
            "data" => [
                "user_id" => $user['user_id'],
                "name" => $user['name'],
                "email" => $user['email'],
                "role" => $user['role']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["status" => "fail", "message" => "Invalid email or password."]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error during login."]);
}
?>