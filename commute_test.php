<?php
session_start();

// Turn on error reporting for testing
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    die("<div style='text-align:center; padding: 50px; font-family: sans-serif;'><h2>🛑 Please log in first!</h2><a href='login.php'>Go to Login</a></div>");
}

require 'db_connect.php';
$current_user_id = $_SESSION['user_id'];

$commute_data = null;
$error_message = "";

// The 4 modes our Python API understands
$available_modes = [
    'public_transport' => '🚌 Bus',
    'foot-walking' => '🚶‍♂️ Walk',
    'cycling-regular' => '🚲 Cycle',
    'driving-car' => '🚗 Drive'
];

try {
    // 1. Get the User's Default Settings
    $settings_stmt = $conn->prepare("SELECT postcode, preferred_transport FROM User_Settings WHERE UserID = :uid");
    $settings_stmt->execute([':uid' => $current_user_id]);
    $user_settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);

    // Safety fallback: If they haven't saved settings yet, use defaults for testing
    $home_postcode = ($user_settings && !empty($user_settings['postcode'])) ? $user_settings['postcode'] : 'M13 9PL'; 
    $db_default_mode = ($user_settings && !empty($user_settings['preferred_transport'])) ? $user_settings['preferred_transport'] : 'public_transport';

    // 2. Check if they clicked a button to override the mode, otherwise use their default
    $active_mode = isset($_GET['mode']) ? $_GET['mode'] : $db_default_mode;

    // Security check: make sure they didn't mess with the URL parameters
    if (!array_key_exists($active_mode, $available_modes)) {
        $active_mode = 'public_transport'; 
    }

    // 3. Find the NEXT upcoming event (must be in the future)
    $event_sql = "SELECT module as title, location, start_time FROM Events 
                  WHERE UserID = :uid AND start_time > NOW() 
                  ORDER BY start_time ASC LIMIT 1";
                  
    $event_stmt = $conn->prepare($event_sql);
    $event_stmt->execute([':uid' => $current_user_id]);
    $next_event = $event_stmt->fetch(PDO::FETCH_ASSOC);

    // 4. Ping the Python API if we have an event with a location
    if ($next_event && !empty($next_event['location'])) {
        $raw_location = $next_event['location'];
        $clean_location = $raw_location;
        
        if (strpos($raw_location, '_') !== false) {
            $building_name = explode('_', $raw_location)[0]; // Grabs the first part
            $clean_location = $building_name . " Building, University of "; 
        }
        $api_payload = json_encode([
            "home_postcode" => $home_postcode,
            "event_location" => $clean_location,
            "transport_mode" => $active_mode
        ]);
        // Initialize cURL to talk to your Render backend
        $ch = curl_init('https://timetable-api-ebyk.onrender.com/get_commute');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $api_payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($api_payload)
        ]);
        
        // --- FIXES FOR RENDER & INFINITYFREE ---
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Give Render 60 full seconds to wake up!
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Tell InfinityFree to chill out about SSL certs

        $api_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err = curl_error($ch); // Grab the exact error message if it fails
        curl_close($ch);

        if ($http_code == 200) {
            $result = json_decode($api_response, true);
            if (isset($result['commute_minutes'])) {
                
                // Do the Math: Event Time - Commute Time = Leave Time!
                $commute_minutes = $result['commute_minutes'];
                $event_start_obj = new DateTime($next_event['start_time']);
                
                $leave_time_obj = clone $event_start_obj; 
                $leave_time_obj->modify("-{$commute_minutes} minutes");

                // Save data to print in HTML below
                $commute_data = [
                    'minutes' => $commute_minutes,
                    'leave_time' => $leave_time_obj->format('H:i'),
                    'start_time' => $event_start_obj->format('H:i'),
                    'date_str' => $event_start_obj->format('l, j M Y'),
                    'title' => $next_event['title'],
                    'location' => $next_event['location'],
                    'postcode_used' => $home_postcode
                ];
            } else {
                // If Python replied, but sent an error message (like a bad postcode)
                $error_message = "Python API Error: " . (isset($result['error']) ? $result['error'] : "Unknown API error.");
            }
        } else {
            // Print the exact reason cURL failed!
            $error_message = "Connection Failed. HTTP Code: {$http_code} | Error: {$curl_err}";
        }
    } elseif ($next_event) {
        $error_message = "Your next event doesn't have a location set, so we can't calculate a route!";
    } else {
        $error_message = "You have no upcoming events scheduled!";
    }

} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Commute Tracker</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f4f7f6;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            max-width: 450px;
            width: 100%;
            margin-top: 40px;
        }
        h2 { margin-top: 0; color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .info-row {
            margin: 10px 0;
            color: #555;
            font-size: 1.05em;
        }
        .highlight-box {
            background: #e3f2fd;
            border: 2px solid #2196f3;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            margin: 20px 0;
        }
        .highlight-box h3 {
            margin: 0 0 5px 0;
            color: #0d47a1;
            font-size: 1.8em;
        }
        .highlight-box p {
            margin: 0;
            color: #1565c0;
            font-weight: bold;
            font-size: 1.1em;
        }
        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        .mode-btn {
            flex: 1;
            text-align: center;
            padding: 10px 5px;
            background: #f8f9fa;
            color: #333;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            border: 1px solid #ddd;
            transition: 0.2s;
            min-width: 80px;
        }
        .mode-btn:hover { background: #e9ecef; }
        
        /* Highlight the currently active button */
        .mode-btn.active {
            background: #28a745;
            color: white;
            border-color: #218838;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }
        .error { 
            color: #d32f2f; 
            background: #ffebee; 
            padding: 15px; 
            border-radius: 8px; 
            font-weight: bold; 
            border: 1px solid #ef5350;
            word-wrap: break-word; /* Makes sure long errors don't break the layout */
        }
        .nav-back {
            display: inline-block;
            margin-bottom: 15px;
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="card">
    <a href="home.php" class="nav-back">← Back to Dashboard</a>
    <h2>Next Event Commute</h2>

    <?php if ($error_message): ?>
        <div class="error">⚠️ <?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if ($commute_data): ?>
        <div class="info-row"><strong>📅 Event:</strong> <?php echo htmlspecialchars($commute_data['title']); ?></div>
        <div class="info-row"><strong>⏰ Starts at:</strong> <?php echo $commute_data['start_time']; ?> (<?php echo $commute_data['date_str']; ?>)</div>
        <div class="info-row"><strong>📍 Location:</strong> <?php echo htmlspecialchars($commute_data['location']); ?></div>
        <div class="info-row" style="font-size: 0.9em; color: #888;"><em>Calculating route from: <?php echo htmlspecialchars($commute_data['postcode_used']); ?></em></div>

        <div class="highlight-box">
            <h3>Leave by <?php echo $commute_data['leave_time']; ?></h3>
            <p><?php echo $available_modes[$active_mode]; ?> takes <?php echo $commute_data['minutes']; ?> mins</p>
        </div>
    <?php endif; ?>

    <p style="margin-bottom: 5px; margin-top: 25px; color: #666; font-size: 0.9em; font-weight: bold;">Check a different transport mode:</p>
    <div class="button-group">
        <?php foreach ($available_modes as $mode_key => $mode_label): ?>
            <a href="?mode=<?php echo $mode_key; ?>" 
               class="mode-btn <?php echo ($active_mode == $mode_key) ? 'active' : ''; ?>">
                <?php echo $mode_label; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>