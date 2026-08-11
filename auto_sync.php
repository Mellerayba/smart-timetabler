<?php
session_start();
require 'db_connect.php';
header('Content-Type: application/json');

// Ensure UK Timezone
date_default_timezone_set('Europe/London');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
    exit();
}

$current_user_id = (string) $_SESSION['user_id'];

try {
    //Check when we last synced
    $stmt = $conn->prepare("SELECT last_synced FROM User_Settings WHERE UserID = :uid LIMIT 1");
    $stmt->execute([':uid' => $current_user_id]);
    $user_settings = $stmt->fetch(PDO::FETCH_ASSOC);

    $last_synced = $user_settings['last_synced'] ?? null;

    // cooldown timer
    if ($last_synced) {
        $last_synced_time = strtotime($last_synced);
        $current_time = time();
        if (($current_time - $last_synced_time) < 3600) {
            echo json_encode(["status" => "cooldown", "message" => "Synced recently. Skipping."]);
            exit();
        }
    }

    // Get the Canvas URL
    $link_stmt = $conn->prepare("SELECT canvas_url FROM Timetable_Links WHERE UserID = :uid LIMIT 1");
    $link_stmt->execute([':uid' => $current_user_id]);
    $links = $link_stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($links['canvas_url'])) {
        echo json_encode(["status" => "skip", "message" => "No Canvas URL set"]);
        exit();
    }

    // send to the Python API
    $ch = curl_init('https://timetable-api-ebyk.onrender.com/parse_canvas');
    $payload = json_encode(['canvas_url' => $links['canvas_url']]);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $data = json_decode($response, true);
        if (isset($data['deadlines'])) {
            
            // Insert new assignments
            $stmt_check = $conn->prepare("SELECT AssignmentID FROM Canvas_Assignments WHERE UserID = ? AND title = ?");
            $stmt_insert = $conn->prepare("INSERT INTO Canvas_Assignments (UserID, title, due_date) VALUES (?, ?, ?)");

            foreach ($data['deadlines'] as $task) {
                $stmt_check->execute([$current_user_id, $task['title']]);
                if (!$stmt_check->fetch()) {
                    $stmt_insert->execute([$current_user_id, $task['title'], $task['due_date']]);
                }
            }

            // Update the last_synced timestamp
            $update_stmt = $conn->prepare("UPDATE User_Settings SET last_synced = :now WHERE UserID = :uid");
            $update_stmt->execute([':now' => date('Y-m-d H:i:s'), ':uid' => $current_user_id]);

            echo json_encode(["status" => "success", "message" => "Canvas synced successfully!"]);
            exit();
        }
    }

    echo json_encode(["status" => "error", "message" => "API returned an error"]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>