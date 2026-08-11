<?php
session_start();

// Kick them out if they aren't logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['ical_url'])) {
    
    $ical_url = $_POST['ical_url'];
    
    // Prepare the data to send to the Python API
    $api_url = 'https://timetable-api-ebyk.onrender.com/parse';
    $post_data = json_encode(array('url' => $ical_url));
    
    // Set up the HTTP request
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => $post_data,
            'timeout' => 60 // Gives time for the server to wake
        )
    );
    
    $context  = stream_context_create($options);
    
    // Send the url off to the python api 
    $api_response = @file_get_contents($api_url, false, $context);

    if ($api_response === FALSE) {
        $message = "Error : Could not reach the Python API";
    } else {
        // Decode the JSON from Python into a PHP Array
        $events = json_decode($api_response, true);
        
        if (isset($events['error'])) {
             $message = "Python API Error: " . htmlspecialchars($events['error']);
        } elseif (is_array($events)) {
            
            $events_imported = 0;
            $events_skipped = 0; // Tracks duplicates
            
            try {

                require 'db_connect.php'; 

                // Save or update the users iCal URL for dynamic updating in the future 
                $url_sql = "INSERT INTO Timetable_Links (UserID, ical_url) 
                            VALUES (:uid, :url) 
                            ON DUPLICATE KEY UPDATE ical_url = :url";
                $url_stmt = $conn->prepare($url_sql);
                $url_stmt->execute([
                    ':uid' => $current_user_id,
                    ':url' => $ical_url
                ]);
                
                // 1. Prepare to check if the event already exists
                $check_sql = "SELECT EventID FROM Events WHERE UserID = :uid AND module = :module AND start_time = :start_time";
                $check_stmt = $conn->prepare($check_sql);
                
                // 2. Prepare the Insertion
                $insert_sql = "INSERT INTO Events (UserID, module, start_time, duration, type, staff, location) 
                        VALUES (:uid, :module, :start_time, :duration, :type, :staff, :location)";
                $insert_stmt = $conn->prepare($insert_sql);

                foreach ($events as $event) {
                    $final_title = $event['unit_code'] . " - " . $event['description'];

                    // Run the check for this specific event
                    $check_stmt->execute([
                        ':uid' => $current_user_id,
                        ':module' => $final_title,
                        ':start_time' => $event['start']
                    ]);

                    // Only insert if 0 matches were found in the database
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
                    } else {
                        // Match found, skip it
                        $events_skipped++;
                    }
                }

                $message = "Success! <strong>$events_imported</strong> events were imported. <strong>$events_skipped</strong> events skipped";

            } catch (PDOException $e) {
                $message = "Database Error: " . $e->getMessage();
            }
        } else {
            $message = "Python API returned invalid data.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Import Timetable via Python API</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; }
        .box { border: 2px dashed #007bff; padding: 30px; margin-top: 20px; text-align: center; border-radius: 8px;}
        input[type="url"] { width: 90%; padding: 10px; margin: 15px 0; border: 1px solid #ccc; border-radius: 4px;}
        button { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; border-radius: 4px; font-size: 16px;}
        button:hover { background: #0056b3; }
        .msg { background: #e8f5e9; color: #2e7d32; padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid #c8e6c9;}
        .warning { font-size: 0.9em; color: #666; margin-top: 15px; }
    </style>
</head>
<body>

    <h1>Import Your Timetable</h1>
    <p>Logged in as ID: <strong><?php echo htmlspecialchars($current_user_id); ?></strong></p>

    <?php if ($message): ?>
        <div class="msg"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="box">
        <form method="POST" action="">
            <label for="ical_url"><b>Paste your iCal URL:</b></label><br>
            <input type="url" name="ical_url" id="ical_url" placeholder="enter a url..." required>
            <br>
            <button type="submit" onclick="this.innerHTML='Loading...'">Fetch via Python API</button>
        </form>
    </div>
    
    <br>
    <a href="index.php">Back to Dashboard</a>

</body>
</html>