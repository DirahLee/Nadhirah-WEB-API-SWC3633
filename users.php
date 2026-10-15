<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/middleware.php";

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        if ($id) {
            $stmt = $db->prepare("SELECT user_id, name, email, role, created_at FROM Users WHERE user_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $db->prepare("SELECT user_id, name, email, role, created_at FROM Users");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(["status" => "success", "data" => $result]);
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['name']) || empty($data['email']) || empty($data['password']) || empty($data['role'])) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Incomplete data provided."]);
            break;
        }
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        try {
            $stmt = $db->prepare("INSERT INTO Users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$data['name'], $data['email'], $hash, $data['role']]);
            http_response_code(201);
            echo json_encode(["status" => "success", "message" => "User created successfully."]);
        } catch (PDOException $e) {
            http_response_code($e->getCode() == 23000 ? 409 : 500);
            echo json_encode(["status" => "error", "message" => $e->getCode() == 23000 ? "Email already exists." : "Unable to create user."]);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['user_id']) || empty($data['name']) || empty($data['role'])) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Missing user_id, name, or role."]);
            break;
        }
        $stmt = $db->prepare("UPDATE Users SET name = ?, role = ? WHERE user_id = ?");
        if ($stmt->execute([$data['name'], $data['role'], $data['user_id']])) {
            echo json_encode(["status" => "success", "message" => "User updated successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Unable to update user."]);
        }
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "User ID required."]);
            break;
        }
        $stmt = $db->prepare("DELETE FROM Users WHERE user_id = ?");
        if ($stmt->execute([$id])) {
            echo json_encode(["status" => "success", "message" => "User deleted successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Unable to delete user."]);
        }
        break;
}
?>