<?php
require_once __DIR__ . "/config/database.php";

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        if ($id) {
            $stmt = $db->prepare("SELECT * FROM Venues WHERE venue_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $db->prepare("SELECT * FROM Venues");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(["status" => "success", "data" => $result]);
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['name']) || empty($data['location']) || empty($data['capacity'])) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Name, location, and capacity are required."]);
            break;
        }
        $stmt = $db->prepare("INSERT INTO Venues (name, location, capacity) VALUES (?, ?, ?)");
        if ($stmt->execute([$data['name'], $data['location'], $data['capacity']])) {
            http_response_code(201);
            echo json_encode(["status" => "success", "message" => "Venue created successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to create venue."]);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['venue_id']) || empty($data['name']) || empty($data['capacity'])) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Venue ID, name, and capacity required."]);
            break;
        }
        $stmt = $db->prepare("UPDATE Venues SET name = ?, capacity = ? WHERE venue_id = ?");
        if ($stmt->execute([$data['name'], $data['capacity'], $data['venue_id']])) {
            echo json_encode(["status" => "success", "message" => "Venue updated successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to update venue."]);
        }
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Venue ID required."]);
            break;
        }
        $stmt = $db->prepare("DELETE FROM Venues WHERE venue_id = ?");
        if ($stmt->execute([$id])) {
            echo json_encode(["status" => "success", "message" => "Venue deleted successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to delete venue."]);
        }
        break;
}
?>
