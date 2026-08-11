<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'db_connect.php';

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = (string) $_SESSION['user_id'];
$message = "";
$message_type = "";

$defaults = [
    'display_name' => '',
    'email' => '',
    'postcode' => 'M14 6YY',
    'preferred_transport' => 'public_transport',
    'start_hour' => 8,
    'end_hour' => 18,
    'timetable_url' => '',
    'canvas_url' => ''
];

try {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $settings = [
            'display_name' => trim($_POST['display_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'start_hour' => (int) ($_POST['start_hour'] ?? 8),
            'end_hour' => (int) ($_POST['end_hour'] ?? 18),
            'postcode' => trim($_POST['postcode'] ?? ''),
            'preferred_transport' => trim($_POST['preferred_transport'] ?? 'public_transport'),
            'timetable_url' => trim($_POST['timetable_url'] ?? ''),
            'canvas_url' => trim($_POST['canvas_url'] ?? '')
        ];

        if (!empty($settings['email']) && !filter_var($settings['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }
        if ($settings['start_hour'] < 0 || $settings['start_hour'] > 23) {
            throw new Exception("Start hour must be between 0 and 23.");
        }
        if ($settings['end_hour'] < 0 || $settings['end_hour'] > 23) {
            throw new Exception("End hour must be between 0 and 23.");
        }

        $sql_settings = "INSERT INTO User_Settings (
                            UserID, display_name, email,
                            start_hour, end_hour, postcode, preferred_transport
                        ) VALUES (
                            :UserID, :display_name, :email,
                            :start_hour, :end_hour, :postcode, :preferred_transport
                        )
                        ON DUPLICATE KEY UPDATE
                            display_name = VALUES(display_name),
                            email = VALUES(email),
                            start_hour = VALUES(start_hour),
                            end_hour = VALUES(end_hour),
                            postcode = VALUES(postcode),
                            preferred_transport = VALUES(preferred_transport)";

        $stmt_settings = $conn->prepare($sql_settings);
        $stmt_settings->execute([
            ':UserID' => $current_user_id,
            ':display_name' => $settings['display_name'],
            ':email' => $settings['email'],
            ':start_hour' => $settings['start_hour'],
            ':end_hour' => $settings['end_hour'],
            ':postcode' => $settings['postcode'],
            ':preferred_transport' => $settings['preferred_transport']
        ]);


        $sql_links = "INSERT INTO Timetable_Links (
                        UserID, ical_url, canvas_url
                      ) VALUES (
                        :UserID, :ical_url, :canvas_url
                      )
                      ON DUPLICATE KEY UPDATE
                        ical_url = VALUES(ical_url),
                        canvas_url = VALUES(canvas_url)";

        $stmt_links = $conn->prepare($sql_links);
        $stmt_links->execute([
            ':UserID' => $current_user_id,
            ':ical_url' => $settings['timetable_url'], 
            ':canvas_url' => $settings['canvas_url']
        ]);

        $sync_message = "";


        if (!empty($settings['canvas_url'])) {
            $ch = curl_init('https://timetable-api-ebyk.onrender.com/parse_canvas');
            $payload = json_encode(['canvas_url' => $settings['canvas_url']]);
            
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
                    $added_count = 0;
                    $stmt_check = $conn->prepare("SELECT AssignmentID FROM Canvas_Assignments WHERE UserID = ? AND title = ?");
                    $stmt_insert = $conn->prepare("INSERT INTO Canvas_Assignments (UserID, title, due_date) VALUES (?, ?, ?)");

                    foreach ($data['deadlines'] as $task) {
                        $stmt_check->execute([$current_user_id, $task['title']]);
                        if (!$stmt_check->fetch()) {
                            $stmt_insert->execute([$current_user_id, $task['title'], $task['due_date']]);
                            $added_count++;
                        }
                    }
                    $sync_message .= "<br>✅ Synced {$added_count} new Canvas assignments!";
                }
            } else {
                $sync_message .= "<br>❌ Canvas sync failed.";
            }
        }


        if (!empty($settings['timetable_url'])) {
            $ch2 = curl_init('https://timetable-api-ebyk.onrender.com/parse');
            $payload2 = json_encode(['url' => $settings['timetable_url']]);
            
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_POST, true);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, $payload2);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($payload2)]);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 60); 
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);

            $response2 = curl_exec($ch2);
            $http_code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);

            if ($http_code2 == 200 && $response2) {
                $events = json_decode($response2, true);
                
                if (is_array($events) && !isset($events['error'])) {
                    $events_imported = 0;
                    
                    $check_sql = "SELECT EventID FROM Events WHERE UserID = :uid AND module = :module AND start_time = :start_time";
                    $check_stmt = $conn->prepare($check_sql);
                    
                    $insert_sql = "INSERT INTO Events (UserID, module, start_time, duration, type, staff, location) 
                            VALUES (:uid, :module, :start_time, :duration, :type, :staff, :location)";
                    $insert_stmt = $conn->prepare($insert_sql);

                    foreach ($events as $event) {
                        $final_title = $event['unit_code'] . " - " . $event['description'];

                        $check_stmt->execute([
                            ':uid' => $current_user_id,
                            ':module' => $final_title,
                            ':start_time' => $event['start']
                        ]);

                        if ($check_stmt->rowCount() == 0) {
                            $insert_stmt->execute([
                                ':uid' => $current_user_id,
                                ':module' => $final_title,
                                ':start_time' => $event['start'],
                                ':duration' => (string)$event['duration'],
                                ':type' => $event['event_type'],
                                ':staff' => $event['staff'],
                                ':location' => $event['location']
                            ]);
                            $events_imported++;
                        }
                    }
                    $sync_message .= "<br>✅ Synced {$events_imported} new Timetable events!";
                } else {
                    $sync_message .= "<br>❌ Timetable sync returned an error.";
                }
            } else {
                $sync_message .= "<br>❌ Timetable sync failed to connect.";
            }
        }

        $message = "<strong>Settings saved successfully!</strong>" . $sync_message;
        $message_type = "success";
    }


    $stmt1 = $conn->prepare("SELECT * FROM User_Settings WHERE UserID = :uid LIMIT 1");
    $stmt1->execute([':uid' => $current_user_id]);
    $saved_settings = $stmt1->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt2 = $conn->prepare("SELECT ical_url AS timetable_url, canvas_url FROM Timetable_Links WHERE UserID = :uid LIMIT 1");
    $stmt2->execute([':uid' => $current_user_id]);
    $saved_links = $stmt2->fetch(PDO::FETCH_ASSOC) ?: [];

    $combined_saved_data = array_merge($saved_settings, $saved_links);
    $settings = array_merge($defaults, $combined_saved_data);

} catch (Exception $e) {
    $settings = $defaults;
    $message = $e->getMessage();
    $message_type = "error";
} catch (PDOException $e) {
    $settings = $defaults;
    $message = "Database error: " . $e->getMessage();
    $message_type = "error";
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Settings - Smart Timetabler</title>
    <link rel="stylesheet" type="text/css" href="home.css" />
</head>
<body>
    <h2 class="title">User Settings</h2>

    <main id="AddPage" style="padding-bottom: 80px;">
        <?php if (!empty($message)): ?>
            <div class="<?php echo $message_type === 'success' ? 'settings-message success-message' : 'settings-message error-message'; ?>" style="line-height: 1.5;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="background settings-card">
            <form method="POST" action="">
                
                <section class="settings-section">
                    <h3>Profile</h3>
                    <div class="input-group">
                        <label for="display_name">Display Name</label>
                        <input type="text" id="display_name" name="display_name" value="<?php echo htmlspecialchars($settings['display_name']); ?>" placeholder="How we should call you" />
                    </div>
                    <div class="input-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($settings['email']); ?>" placeholder="your.name@student.manchester.ac.uk" />
                    </div>
                </section>

                <section class="settings-section">
                    <h3>Timetable & Links</h3>
                    <div class="input-group">
                        <label for="timetable_url">University Timetable URL</label>
                        <input type="text" id="timetable_url" name="timetable_url" value="<?php echo htmlspecialchars($settings['timetable_url']); ?>" placeholder="Paste your university .ics link" />
                    </div>
                    <div class="input-group">
                        <label for="canvas_url">Canvas Deadlines URL</label>
                        <input type="text" id="canvas_url" name="canvas_url" value="<?php echo htmlspecialchars($settings['canvas_url']); ?>" placeholder="Paste your Canvas calendar feed link" />
                    </div>
                    <div class="row" style="margin-top: 15px;">
                        <div class="input-group narrow">
                            <label for="start_hour">Day Starts</label>
                            <input type="number" id="start_hour" name="start_hour" min="0" max="23" value="<?php echo htmlspecialchars($settings['start_hour']); ?>" />
                        </div>
                        <div class="input-group narrow">
                            <label for="end_hour">Day Ends</label>
                            <input type="number" id="end_hour" name="end_hour" min="0" max="23" value="<?php echo htmlspecialchars($settings['end_hour']); ?>" />
                        </div>
                    </div>
                </section>

                <section class="settings-section">
                    <h3>Commute & Location</h3>
                    <div class="input-group">
                        <label for="postcode">Home Postcode</label>
                        <input type="text" id="postcode" name="postcode" value="<?php echo htmlspecialchars($settings['postcode']); ?>" placeholder="e.g. M13 9PL" />
                    </div>
                    <div class="input-group wide">
                        <label for="preferred_transport">Preferred Transport</label>
                        <select id="preferred_transport" name="preferred_transport">
                            <option value="public_transport" <?php echo $settings['preferred_transport'] === 'public_transport' ? 'selected' : ''; ?>>🚌 Bus / Tram (Public Transport)</option>
                            <option value="foot-walking" <?php echo $settings['preferred_transport'] === 'foot-walking' ? 'selected' : ''; ?>>🚶‍♂️ Walking</option>
                            <option value="cycling-regular" <?php echo $settings['preferred_transport'] === 'cycling-regular' ? 'selected' : ''; ?>>🚲 Cycling</option>
                            <option value="driving-car" <?php echo $settings['preferred_transport'] === 'driving-car' ? 'selected' : ''; ?>>🚗 Driving</option>
                        </select>
                    </div>
                </section>

                <button type="submit">Save & Sync</button>
            </form>
        </div>
    </main>

    <?php include 'toolbar.php'; ?>
</body>
</html>