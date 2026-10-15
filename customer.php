<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/config/database.php";

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : null;
        $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : null;

        if ($customer_id) {
            $stmt = $db->prepare("SELECT b.booking_id, b.quantity, b.total_price, b.status, b.created_at, 
                                         e.event_id, e.title as event_title, e.event_date, v.name as venue_name 
                                  FROM Bookings b 
                                  JOIN Events e ON b.event_id = e.event_id 
                                  LEFT JOIN Venues v ON e.venue_id = v.venue_id 
                                  WHERE b.customer_id = ? 
                                  ORDER BY b.booking_id DESC");
            $stmt->execute([$customer_id]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["status" => "success", "data" => $result]);
        } elseif ($event_id) {
            $stmt = $db->prepare("SELECT e.*, v.name as venue_name, v.location 
                                  FROM Events e 
                                  LEFT JOIN Venues v ON e.venue_id = v.venue_id 
                                  WHERE e.event_id = ?");
            $stmt->execute([$event_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                echo json_encode(["status" => "success", "data" => $result]);
            } else {
                http_response_code(404);
                echo json_encode(["status" => "fail", "message" => "Event not found."]);
            }
        } else {
            $stmt = $db->prepare("SELECT e.*, v.name as venue_name, v.location 
                                  FROM Events e 
                                  LEFT JOIN Venues v ON e.venue_id = v.venue_id 
                                  ORDER BY e.event_date ASC");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["status" => "success", "data" => $result]);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['customer_id']) || empty($data['event_id']) || empty($data['quantity'])) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Customer ID, Event ID, and Quantity are required."]);
            break;
        }

        $customer_id = intval($data['customer_id']);
        $event_id = intval($data['event_id']);
        $quantity = intval($data['quantity']);

        if ($quantity <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Quantity must be greater than zero."]);
            break;
        }

        $stmtUser = $db->prepare("SELECT user_id FROM Users WHERE user_id = ? AND LOWER(role) = 'customer'");
        $stmtUser->execute([$customer_id]);
        if (!$stmtUser->fetch()) {
            http_response_code(404);
            echo json_encode(["status" => "fail", "message" => "Customer account not found."]);
            break;
        }

        $stmtEvent = $db->prepare("SELECT ticket_price, available_tickets FROM Events WHERE event_id = ?");
        $stmtEvent->execute([$event_id]);
        $event = $stmtEvent->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            http_response_code(404);
            echo json_encode(["status" => "fail", "message" => "Event not found."]);
            break;
        }

        if ($event['available_tickets'] < $quantity) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Not enough available tickets."]);
            break;
        }

        $total_price = $event['ticket_price'] * $quantity;

        $db->beginTransaction();
        try {
            $stmtBooking = $db->prepare("INSERT INTO Bookings (customer_id, event_id, quantity, total_price, status) VALUES (?, ?, ?, ?, 'confirmed')");
            $stmtBooking->execute([$customer_id, $event_id, $quantity, $total_price]);
            $booking_id = $db->lastInsertId();

            $stmtUpdateEvent = $db->prepare("UPDATE Events SET available_tickets = available_tickets - ? WHERE event_id = ?");
            $stmtUpdateEvent->execute([$quantity, $event_id]);

            $db->commit();
            http_response_code(201);
            echo json_encode([
                "status" => "success",
                "message" => "Booking successful.",
                "data" => [
                    "booking_id" => $booking_id,
                    "total_price" => $total_price,
                    "quantity" => $quantity
                ]
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Booking transaction failed."]);
        }
        break;

    case 'DELETE':
        // Cancel customer booking and restore tickets to the event pool
        $booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : null;
        
        if (!$booking_id) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Booking ID is required."]);
            break;
        }

        // Retrieve existing booking details
        $stmtGet = $db->prepare("SELECT event_id, quantity, status FROM Bookings WHERE booking_id = ?");
        $stmtGet->execute([$booking_id]);
        $booking = $stmtGet->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            http_response_code(404);
            echo json_encode(["status" => "fail", "message" => "Booking not found."]);
            break;
        }

        if ($booking['status'] === 'cancelled') {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Booking is already cancelled."]);
            break;
        }

        $db->beginTransaction();
        try {
            // Update booking status
            $stmtCancel = $db->prepare("UPDATE Bookings SET status = 'cancelled' WHERE booking_id = ?");
            $stmtCancel->execute([$booking_id]);

            // Restore ticket count to event
            $stmtRestore = $db->prepare("UPDATE Events SET available_tickets = available_tickets + ? WHERE event_id = ?");
            $stmtRestore->execute([$booking['quantity'], $booking['event_id']]);

            $db->commit();
            echo json_encode([
                "status" => "success",
                "message" => "Booking cancelled successfully and tickets restored."
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to cancel booking."]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["status" => "fail", "message" => "Method Not Allowed."]);
        break;
}
?>