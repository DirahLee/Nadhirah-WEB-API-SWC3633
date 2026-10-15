<?php
require_once __DIR__ . "/config/database.php";

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        $currency = isset($_GET['currency']) ? strtoupper($_GET['currency']) : 'MYR';

        if ($id) {
            $stmt = $db->prepare("SELECT e.*, v.name as venue_name, u.name as organiser_name FROM Events e JOIN Venues v ON e.venue_id = v.venue_id JOIN Users u ON e.organiser_id = u.user_id WHERE e.event_id = ?");
            $stmt->execute([$id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($event && $currency !== 'MYR') {
                // External API Integration for Currency Conversion
                $api_url = "https://open.er-api.com/v6/latest/MYR";
                $response = @file_get_contents($api_url);
                if ($response) {
                    $rates = json_decode($response, true);
                    if (isset($rates['rates'][$currency])) {
                        $rate = $rates['rates'][$currency];
                        $event['ticket_price_converted'] = round($event['ticket_price'] * $rate, 2);
                        $event['currency'] = $currency;
                    }
                }
            }
            echo json_encode(["status" => "success", "data" => $event]);
        } else {
            $stmt = $db->prepare("SELECT e.*, v.name as venue_name, u.name as organiser_name FROM Events e JOIN Venues v ON e.venue_id = v.venue_id JOIN Users u ON e.organiser_id = u.user_id");
            $stmt->execute();
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["status" => "success", "data" => $events]);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['title']) || empty($data['event_date']) || empty($data['venue_id']) || empty($data['organiser_id']) || empty($data['ticket_price']) || empty($data['available_tickets'])) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Incomplete event details."]);
            break;
        }
        $stmt = $db->prepare("INSERT INTO Events (title, description, event_date, venue_id, organiser_id, ticket_price, available_tickets) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$data['title'], $data['description'] ?? '', $data['event_date'], $data['venue_id'], $data['organiser_id'], $data['ticket_price'], $data['available_tickets']])) {
            http_response_code(201);
            echo json_encode(["status" => "success", "message" => "Event created successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to create event."]);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['event_id']) || empty($data['title']) || empty($data['ticket_price']) || empty($data['available_tickets'])) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Event ID, title, price, and available tickets are required."]);
            break;
        }
        $stmt = $db->prepare("UPDATE Events SET title = ?, ticket_price = ?, available_tickets = ? WHERE event_id = ?");
        if ($stmt->execute([$data['title'], $data['ticket_price'], $data['available_tickets'], $data['event_id']])) {
            echo json_encode(["status" => "success", "message" => "Event updated successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to update event."]);
        }
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Event ID is required."]);
            break;
        }
        $stmt = $db->prepare("DELETE FROM Events WHERE event_id = ?");
        if ($stmt->execute([$id])) {
            echo json_encode(["status" => "success", "message" => "Event deleted successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to delete event."]);
        }
        break;
}
?>
