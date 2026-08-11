<?php
// reschedule_tasks.php
// Uses Python API to find  gaps for overdue tasks

// Force UK Timezone so PHP and Python are  synced
date_default_timezone_set('Europe/London');
$current_timestamp = time();
$rounded_timestamp = ceil($current_timestamp / (15 * 60)) * (15 * 60);
$current_time_str = date('Y-m-d H:i:s', $rounded_timestamp);

if (!isset($conn) || !isset($current_user_id)) {
    die("reschedule_tasks.php requires \$conn and \$current_user_id to be set first.");
}

$rescheduled_count = 0;
$reschedule_error = "";

try {
    // Delete tasks completed > 24 hours ago
    $delete_threshold = date('Y-m-d H:i:s', strtotime('-1 day'));

    $cleanup_tags_sql = "DELETE ToDo_Tags FROM ToDo_Tags 
                         INNER JOIN ToDo ON ToDo_Tags.taskID = ToDo.taskID 
                         WHERE ToDo.UserID = :uid AND ToDo.is_complete = 1 AND ToDo.completed_at < :threshold";
    $cleanup_tags_stmt = $conn->prepare($cleanup_tags_sql);
    $cleanup_tags_stmt->execute([':uid' => $current_user_id, ':threshold' => $delete_threshold]);

    $cleanup_tasks_sql = "DELETE FROM ToDo 
                          WHERE UserID = :uid AND is_complete = 1 AND completed_at < :threshold";
    $cleanup_tasks_stmt = $conn->prepare($cleanup_tasks_sql);
    $cleanup_tasks_stmt->execute([':uid' => $current_user_id, ':threshold' => $delete_threshold]);

    // Get Users Work Hours
    $settings_stmt = $conn->prepare("SELECT start_hour, end_hour FROM User_Settings WHERE UserID = :uid LIMIT 1");
    $settings_stmt->execute([':uid' => $current_user_id]);
    $user_settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);
    
    $start_hour = $user_settings ? (int)$user_settings['start_hour'] : 9;
    $end_hour = $user_settings ? (int)$user_settings['end_hour'] : 17;

    //  Find overdue tasks excluding events
    $sql = "SELECT taskID, title, duration, deadline 
            FROM ToDo 
            WHERE UserID = :uid 
              AND is_complete = 0 
              AND type != 'Event' 
              AND deadline IS NOT NULL 
              AND deadline < :current_time"; 
              
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':uid' => $current_user_id,
        ':current_time' => $current_time_str
    ]);
    $overdue_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($overdue_tasks)) {
        
        // Gather all future busy slots
        $busy_slots = [];
        
        // Grab Events
        $events_stmt = $conn->prepare("SELECT start_time, duration FROM Events WHERE UserID = :uid AND start_time >= :current_time");
        $events_stmt->execute([':uid' => $current_user_id, ':current_time' => $current_time_str]);
        while ($row = $events_stmt->fetch(PDO::FETCH_ASSOC)) {
            $start_obj = new DateTime($row['start_time']);
            $end_obj = clone $start_obj;
            $end_obj->modify('+' . (int)$row['duration'] . ' minutes');
            $busy_slots[] = [
                "start" => $start_obj->format('Y-m-d H:i:s'),
                "end" => $end_obj->format('Y-m-d H:i:s')
            ];
        }

        // Grab upcoming Tasks
        $tasks_stmt = $conn->prepare("SELECT deadline, duration FROM ToDo WHERE UserID = :uid AND is_complete = 0 AND deadline >= :current_time");
        $tasks_stmt->execute([':uid' => $current_user_id, ':current_time' => $current_time_str]);
        while ($row = $tasks_stmt->fetch(PDO::FETCH_ASSOC)) {
            $end_obj = new DateTime($row['deadline']);
            $start_obj = clone $end_obj;
            $start_obj->modify('-' . max((int)$row['duration'], 30) . ' minutes'); 
            $busy_slots[] = [
                "start" => $start_obj->format('Y-m-d H:i:s'),
                "end" => $end_obj->format('Y-m-d H:i:s')
            ];
        }

        $update_stmt = $conn->prepare("UPDATE ToDo SET deadline = :new_deadline WHERE taskID = :taskID");

        // Send each overdue task to Python
        foreach ($overdue_tasks as $task) {
            $task_duration = max((int)$task['duration'], 30); 
            
            $payload = json_encode([
                "duration" => $task_duration,
                "start_hour" => $start_hour,
                "end_hour" => $end_hour,
                "current_time" => $current_time_str,
                "busy_slots" => $busy_slots
            ]);

            $ch = curl_init('https://timetable-api-ebyk.onrender.com/reschedule');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $api_response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code == 200 && $api_response) {
                $result = json_decode($api_response, true);
                
                if (isset($result['success']) && $result['success'] == True) {
                    $new_deadline_str = $result['new_deadline'];
                    
                    // Update the database
                    $update_stmt->execute([
                        ':new_deadline' => $new_deadline_str,
                        ':taskID' => $task['taskID']
                    ]);
                    
                    $rescheduled_count++;

                    // Add to busy slots so next task doesn't overlap
                    $new_end_obj = new DateTime($new_deadline_str);
                    $new_start_obj = clone $new_end_obj;
                    $new_start_obj->modify('-' . $task_duration . ' minutes');
                    
                    $busy_slots[] = [
                        "start" => $new_start_obj->format('Y-m-d H:i:s'),
                        "end" => $new_end_obj->format('Y-m-d H:i:s')
                    ];
                }
            }
        }
    }
} catch (Exception $e) {
    $reschedule_error = "Scheduling API Error: " . $e->getMessage();
} catch (PDOException $e) {
    $reschedule_error = "Database Error: " . $e->getMessage();
}
?>