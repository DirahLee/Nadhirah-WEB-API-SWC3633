<?php
require_once __DIR__ . "/config/database.php";

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        if ($id) {
            $stmt = $db->prepare("SELECT b.*, u.name as customer_name, e.title as event_title FROM Bookings b JOIN Users u ON b.customer_id = u.user_id JOIN Events e ON b.event_id = e.event_id WHERE b.booking_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $db->prepare("SELECT b.*, u.name as customer_name, e.title as event_title FROM Bookings b JOIN Users u ON b.customer_id = u.user_id JOIN Events e ON b.event_id = e.event_id");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(["status" => "success", "data" => $result]);
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['customer_id']) || empty($data['event_id']) || empty($data['quantity'])) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Customer ID, event ID, and quantity required."]);
            break;
        }

        // Check Event availability & price
        $stmtEvent = $db->prepare("SELECT ticket_price, available_tickets FROM Events WHERE event_id = ?");
        $stmtEvent->execute([$data['event_id']]);
        $event = $stmtEvent->fetch(PDO::FETCH_ASSOC);

        if (!$event || $event['available_tickets'] < $data['quantity']) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Not enough available tickets."]);
            break;
        }

        $total_price = $event['ticket_price'] * $data['quantity'];

        // Transaction for database consistency
        $db->beginTransaction();
        try {
            $stmtBooking = $db->prepare("INSERT INTO Bookings (customer_id, event_id, quantity, total_price) VALUES (?, ?, ?, ?)");
            $stmtBooking->execute([$data['customer_id'], $data['event_id'], $data['quantity'], $total_price]);

            $stmtUpdateEvent = $db->prepare("UPDATE Events SET available_tickets = available_tickets - ? WHERE event_id = ?");
            $stmtUpdateEvent->execute([$data['quantity'], $data['event_id']]);

            $db->commit();
            http_response_code(201);
            echo json_encode(["status" => "success", "message" => "Booking successful.", "total_price" => $total_price]);
        } catch (Exception $e) {
            $db->rollBack();
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Booking transaction failed."]);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['booking_id']) || empty($data['status'])) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Booking ID and status required."]);
            break;
        }
        $stmt = $db->prepare("UPDATE Bookings SET status = ? WHERE booking_id = ?");
        if ($stmt->execute([$data['status'], $data['booking_id']])) {
            echo json_encode(["status" => "success", "message" => "Booking status updated."]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to update booking status."]);
        }
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Booking ID required."]);
            break;
        }
        $stmt = $db->prepare("DELETE FROM Bookings WHERE booking_id = ?");
        if ($stmt->execute([$id])) {
            echo json_encode(["status" => "success", "message" => "Booking cancelled/deleted."]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to delete booking."]);
        }
        break;
}
?>
