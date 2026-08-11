<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';
$current_user_id = $_SESSION['user_id'];
$events = [];
$error_message = "";

try {
    // Fetch all events for this user, sorted by date and time
    $sql = "SELECT * FROM Events WHERE UserID = :uid ORDER BY start_time ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':uid' => $current_user_id]);
    
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Timetable</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background-color: #f4f7f6;
            color: #333;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
            padding-bottom: 80px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .btn-import {
            background: #007bff;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .btn-import:hover { background: #0056b3; }
        
        .date-header {
            background: #343a40;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        
        .event-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px 20px;
            margin-bottom: 15px;
            border-left: 5px solid #007bff;
            display: flex;
            flex-direction: column;
        }
        .event-card.lecture { border-left-color: #28a745; } /* Green for Lectures */
        .event-card.practical { border-left-color: #fd7e14; } /* Orange for Practicals */
        
        .event-time {
            color: #555;
            font-size: 0.9em;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .event-title {
            margin: 0 0 10px 0;
            font-size: 1.3em;
            color: #111;
        }
        .event-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 0.9em;
            color: #444;
        }
        .detail-item strong { color: #222; }
        
        .empty-state {
            text-align: center;
            padding: 50px;
            background: white;
            border-radius: 8px;
            color: #666;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>My Timetable</h1>
        <a href="import.php" class="btn-import">+ Import Classes</a>
    </div>

    <?php if ($error_message): ?>
        <p style="color: red;"><?php echo $error_message; ?></p>
    <?php endif; ?>

    <?php
    if (empty($events)) {
        echo "<div class='empty-state'>
                <h2>Your timetable is empty!</h2>
                <p>Click the import button above to sync your University iCal link.</p>
              </div>";
    } else {
        $current_date = "";

        foreach ($events as $event) {
            // Convert database datetime into readable formats
            $timestamp = strtotime($event['start_time']);
            $date_string = date('l, j F Y', $timestamp); // e.g., "Monday, 16 February 2026"
            $time_string = date('H:i', $timestamp);      // e.g., "15:00"
            
            // Calculate end time
            $end_timestamp = $timestamp + ((int)$event['duration'] * 60);
            $end_time_string = date('H:i', $end_timestamp);

            // Print a Date Header if we are on a new day
            if ($date_string !== $current_date) {
                echo "<div class='date-header'>" . $date_string . "</div>";
                $current_date = $date_string;
            }

            // Figure out a color class based on the event type (optional but looks great)
            $type_class = "";
            $type_lower = strtolower($event['type']);
            if (strpos($type_lower, 'lecture') !== false) $type_class = "lecture";
            if (strpos($type_lower, 'practical') !== false || strpos($type_lower, 'lab') !== false) $type_class = "practical";

            // Print the Class Card
            ?>
            <div class="event-card <?php echo $type_class; ?>">
                <div class="event-time">
                    🕒 <?php echo $time_string; ?> - <?php echo $end_time_string; ?> 
                    (<?php echo htmlspecialchars($event['duration']); ?> mins)
                </div>
                <h3 class="event-title"><?php echo htmlspecialchars($event['module']); ?></h3>
                
                <div class="event-details">
                    <div class="detail-item"><strong>Type:</strong> <?php echo htmlspecialchars($event['type']); ?></div>
                    <div class="detail-item"><strong>Room:</strong> <?php echo htmlspecialchars($event['location']); ?></div>
                    <div class="detail-item"><strong>Staff:</strong> <?php echo htmlspecialchars($event['staff']); ?></div>
                </div>
            </div>
            <?php
        }
    }
    ?>
<?php include 'toolbar.php'; ?>
</body>
</html>