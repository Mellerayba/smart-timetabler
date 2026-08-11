<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Force UK Timezone
date_default_timezone_set('Europe/London');
$current_php_time = date('Y-m-d H:i:s'); 
$current_date_only = date('Y-m-d');

session_start();
require 'db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = (string) $_SESSION['user_id'];


$stmt = $conn->prepare("SELECT display_name, postcode, preferred_transport, last_synced FROM User_Settings WHERE UserID = :uid LIMIT 1");
$stmt->execute([':uid' => $current_user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$display_name = !empty($user['display_name']) ? $user['display_name'] : 'Student';
$home_postcode = !empty($user['postcode']) ? $user['postcode'] : 'M13 9PL';
$transport_mode = !empty($user['preferred_transport']) ? $user['preferred_transport'] : 'public_transport';

$last_synced_str = "Never synced";
if (!empty($user['last_synced'])) {
    $sync_time = strtotime($user['last_synced']);
    if (date('Y-m-d', $sync_time) == $current_date_only) {
        $last_synced_str = "Today at " . date('H:i', $sync_time);
    } else {
        $last_synced_str = date('d M \a\t H:i', $sync_time);
    }
}
// Transport Emoji Mapping
$transport_emojis = [
    'public_transport' => '🚌 Bus/Tram',
    'foot-walking' => '🚶‍♂️ Walk',
    'cycling-regular' => '🚲 Cycle',
    'driving-car' => '🚗 Drive'
];
$transport_label = $transport_emojis[$transport_mode] ?? 'Commute';

// fetch weather using open api
$weather_temp = "--";
$weather_emoji = "☁️";
$weather_desc = "Unknown";

try {
    $weather_url = "https://api.open-meteo.com/v1/forecast?latitude=53.4808&longitude=-2.2426&current_weather=true";
    $weather_json = @file_get_contents($weather_url);
    if ($weather_json) {
        $weather_data = json_decode($weather_json, true);
        if (isset($weather_data['current_weather'])) {
            $weather_temp = round($weather_data['current_weather']['temperature']);
            $code = $weather_data['current_weather']['weathercode'];
            
            if ($code == 0) { $weather_emoji = "☀️"; $weather_desc = "Clear"; }
            elseif ($code >= 1 && $code <= 3) { $weather_emoji = "⛅"; $weather_desc = "Cloudy"; }
            elseif ($code >= 45 && $code <= 48) { $weather_emoji = "🌫️"; $weather_desc = "Fog"; }
            elseif ($code >= 51 && $code <= 67) { $weather_emoji = "🌧️"; $weather_desc = "Rain"; }
            elseif ($code >= 71 && $code <= 77) { $weather_emoji = "❄️"; $weather_desc = "Snow"; }
            elseif ($code >= 80 && $code <= 82) { $weather_emoji = "🌦️"; $weather_desc = "Showers"; }
            elseif ($code >= 95) { $weather_emoji = "⛈️"; $weather_desc = "Thunderstorm"; }
        }
    }
} catch (Exception $e) {}

// Fetch next event 
$commute_text = "No upcoming events scheduled today.";
$leave_time_text = "";

try {
    $event_stmt = $conn->prepare("SELECT module as title, location, start_time FROM Events WHERE UserID = :uid AND start_time > :current_time ORDER BY start_time ASC LIMIT 1");
    $event_stmt->execute([':uid' => $current_user_id, ':current_time' => $current_php_time]);
    $next_event = $event_stmt->fetch(PDO::FETCH_ASSOC);

    if ($next_event && !empty($next_event['location'])) {
        $raw_location = $next_event['location'];
        $clean_location = $raw_location;
        
        if (strpos($raw_location, '_') !== false) {
            $building_name = explode('_', $raw_location)[0];
            $clean_location = $building_name . " Building"; 
        }

        $api_payload = json_encode([
            "home_postcode" => $home_postcode,
            "event_location" => $clean_location,
            "transport_mode" => $transport_mode
        ]);

        $ch = curl_init('https://timetable-api-ebyk.onrender.com/get_commute');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $api_payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($api_payload)]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $api_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $event_start_obj = new DateTime($next_event['start_time']);
        $event_time_str = $event_start_obj->format('H:i');
        // Include the date if the next event isn't today
        $event_display_date = ($event_start_obj->format('Y-m-d') === $current_date_only) ? "Today" : $event_start_obj->format('D, j M');
        $event_title = htmlspecialchars($next_event['title']);

        if ($http_code == 200) {
            $result = json_decode($api_response, true);
            if (isset($result['commute_minutes'])) {
                $commute_minutes = $result['commute_minutes'];
                $leave_time_obj = clone $event_start_obj; 
                $leave_time_obj->modify("-{$commute_minutes} minutes");
                
                $leave_time_text = "<span class='highlight-time'>Leave by " . $leave_time_obj->format('H:i') . " ({$event_display_date})</span>";
                $commute_text = "Next: <strong>{$event_title}</strong> at {$event_time_str}.<br><small>It takes {$commute_minutes} mins via {$transport_label}.</small>";
            }
        } else {
            $commute_text = "Next: <strong>{$event_title}</strong> at {$event_time_str} ({$event_display_date}).<br><small>📍 {$clean_location} (Routing unavailable)</small>";
        }
    } elseif ($next_event) {
        $event_start_obj = new DateTime($next_event['start_time']);
        $event_display_date = ($event_start_obj->format('Y-m-d') === $current_date_only) ? "Today" : $event_start_obj->format('D, j M');
        $commute_text = "Next: <strong>" . htmlspecialchars($next_event['title']) . "</strong> at " . $event_start_obj->format('H:i') . " ({$event_display_date}).<br><small>No location set.</small>";
    }
} catch (Exception $e) {
    $commute_text = "<span style='color:red;'>SQL Error: " . $e->getMessage() . "</span>";
}


$deadlines = [];
$deadline_error = "";
try {
    
    $canvas_stmt = $conn->prepare("SELECT title, due_date FROM Canvas_Assignments WHERE UserID = :uid AND status = 'pending' AND due_date >= :current_date ORDER BY due_date ASC LIMIT 4");
    $canvas_stmt->execute([':uid' => $current_user_id, ':current_date' => $current_date_only]);
    $deadlines = $canvas_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $deadline_error = $e->getMessage();
}

// Generate greeting based on time
$hour = date('H');
if ($hour < 12) { $greeting = "Good Morning"; }
elseif ($hour < 17) { $greeting = "Good Afternoon"; }
else { $greeting = "Good Evening"; }

?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard - Smart Timetabler</title>
    <link rel="stylesheet" type="text/css" href="home.css" />
    <style>
        .dash-header { text-align: center; margin-bottom: 25px; }
        .dash-greeting { color: #555; margin: 0; font-size: 1.2em; }
        .dash-time { font-size: 3em; font-weight: bold; color: #222; margin: 5px 0; letter-spacing: -1px; }
        .dash-date { color: #777; font-size: 1.1em; margin: 0; }
        .dash-grid { display: grid; gap: 15px; grid-template-columns: 1fr; }
        .dash-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #eee; }
        .dash-card h3 { margin-top: 0; font-size: 1.1em; color: #333; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px; margin-bottom: 15px; }
        .weather-info { display: flex; align-items: center; justify-content: space-between; font-size: 1.2em; }
        .weather-temp { font-size: 2em; font-weight: bold; color: #007bff; }
        .highlight-time { display: inline-block; background: #e3f2fd; color: #0d47a1; padding: 8px 15px; border-radius: 20px; font-weight: bold; font-size: 1.2em; margin-bottom: 10px; border: 1px solid #bbdefb; }
        .commute-details { color: #555; line-height: 1.5; font-size: 1.05em; }
        .deadline-list { list-style: none; padding: 0; margin: 0; }
        .deadline-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f5f5f5; }
        .deadline-item:last-child { border-bottom: none; }
        .deadline-title { font-weight: 500; color: #333; flex: 1; padding-right: 15px; }
        .deadline-date { color: #d32f2f; font-size: 0.9em; font-weight: 600; text-align: right; white-space: nowrap; }
        .empty-state { color: #888; font-style: italic; text-align: center; padding: 10px 0; }
    </style>
</head>
<body>
    
    <main id="AddPage" style="padding-bottom: 80px; max-width: 600px; margin: 0 auto; padding-top: 20px;">
        
        <div class="dash-header">
            <p class="dash-greeting"><?php echo $greeting; ?>, <?php echo htmlspecialchars($display_name); ?>!</p>
            <div class="dash-time" id="live-clock">--:--</div>
            <p class="dash-date"><?php echo date('l, j F Y'); ?></p>
        </div>

        <div class="dash-grid">
            
            <div class="dash-card" style="border-left: 5px solid #007bff;">
                <h3>🗺️ Smart Commute</h3>
                <?php if ($leave_time_text): ?>
                    <?php echo $leave_time_text; ?>
                <?php endif; ?>
                <div class="commute-details">
                    <?php echo $commute_text; ?>
                </div>
            </div>

            <div class="dash-card">
                <h3>🌤️ Current Weather</h3>
                <div class="weather-info">
                    <div>
                        <span class="weather-temp"><?php echo $weather_temp; ?>°C</span><br>
                        <span style="color: #666;">Manchester • <?php echo $weather_desc; ?></span>
                    </div>
                    <div style="font-size: 3em; line-height: 1;"><?php echo $weather_emoji; ?></div>
                </div>
            </div>

            <div class="dash-card">

                <h3 style="display: flex; justify-content: space-between; align-items: center;">
                    <span>📝 Canvas Assignments</span>
                    <span style="font-size: 0.7em; color: #888; font-weight: normal;">Synced: <?php echo $last_synced_str; ?></span>
                </h3>
                
                <?php if ($deadline_error): ?>
                    <div class="empty-state" style="color:red; font-size: 0.9em; text-align:left;">
                        <strong>SQL Error:</strong> <?php echo htmlspecialchars($deadline_error); ?>
                    </div>
                <?php elseif (count($deadlines) > 0): ?>
                    <ul class="deadline-list">
                        <?php foreach ($deadlines as $task): ?>
                            <?php 
                                $date_obj = new DateTime($task['due_date']);
                                $display_date = ($date_obj->format('Y-m-d') === $current_date_only) 
                                                ? "Today, " . $date_obj->format('H:i') 
                                                : $date_obj->format('D, j M');
                            ?>
                            <li class="deadline-item">
                                <span class="deadline-title"><?php echo htmlspecialchars($task['title']); ?></span>
                                <span class="deadline-date"><?php echo $display_date; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">No upcoming assignments! 🎉</div>
                <?php endif; ?>
                
            </div>

        </div>
    </main>

    <?php include 'toolbar.php'; ?>

    <script>
        function updateClock() {
            const now = new Date();
            let hours = now.getHours().toString().padStart(2, '0');
            let minutes = now.getMinutes().toString().padStart(2, '0');
            document.getElementById('live-clock').textContent = hours + ':' + minutes;
        }
        setInterval(updateClock, 1000);
        updateClock(); 
        // Trigger the background Auto-Sync
        document.addEventListener("DOMContentLoaded", function() {
            fetch('auto_sync.php')
                .then(response => response.json())
                .then(data => {
                    console.log("Auto-Sync:", data.message);

                    if (data.status === "success") {
                        console.log("New tasks found! Updating dashboard...");

                        location.reload(); //auto reloads the poage if resync
                    }
                })
                .catch(error => console.error("Sync failed", error));
        });
    </script>
</body>
</html>