<?php
// Turn on error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'db_connect.php';

// force the database to show errors for debugging
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Kick them out if they aren't logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $event_title = isset($_POST['event_title']) ? trim($_POST['event_title']) : '';

    if (empty($event_title)) {
        $message = "<p style='color:red; text-align:center; font-weight:bold;'>Please enter an event title!</p>";
    } else {
        $event_type = isset($_POST['dropdown']) ? $_POST['dropdown'] : 'Task';  
        $duration = !empty($_POST['duration']) ? (int)$_POST['duration'] : 0;
        $description = isset($_POST['description']) ? $_POST['description'] : '';
        $raw_tags = isset($_POST['tags']) ? $_POST['tags'] : '';
        
        $date_time = !empty($_POST['date_time']) ? str_replace('T', ' ', $_POST['date_time']) . ':00' : NULL;

        try {
            // Check if this exact user already has this exact task at this exact time
            $check_sql = "SELECT taskID FROM ToDo WHERE UserID = :uid AND title = :title AND deadline = :deadline";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute([
                ':uid' => $current_user_id,
                ':title' => $event_title,
                ':deadline' => $date_time
            ]);

            if ($check_stmt->rowCount() > 0) {
                $message = "<p style='color:orange; text-align:center; font-weight:bold;'>You already added this task!</p>";
            } else {
                
                $conn->beginTransaction();

                $sql = "INSERT INTO ToDo (UserID, title, type, description, deadline, is_complete, duration) 
                        VALUES (:UserID, :title, :type, :description, :deadline, :is_complete, :duration)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':UserID' => $current_user_id,
                    ':title' => $event_title,
                    ':type' => $event_type,
                    ':description' => $description,
                    ':deadline' => $date_time,
                    ':is_complete' => 0,
                    ':duration' => $duration
                ]);
                
                // get the ID of the task just created
                $new_task_id = $conn->lastInsertId();

                if (!empty(trim($raw_tags))) {
                    
                    // Split by comma in case they typed 
                    $tags_array = explode(',', $raw_tags);
                    
                    // Prepare our tag SQL statements 
                    $check_tag_stmt = $conn->prepare("SELECT TagID FROM Tags WHERE UserID = :uid AND tag_name = :tag_name");
                    $insert_tag_stmt = $conn->prepare("INSERT INTO Tags (UserID, tag_name) VALUES (:uid, :tag_name)");
                    // Using INSERT IGNORE so if they type duplicate tags it wont crash
                    $link_tag_stmt = $conn->prepare("INSERT IGNORE INTO ToDo_Tags (taskID, TagID) VALUES (:taskID, :TagID)");

                    foreach ($tags_array as $tag) {
                        // remove extra spaces and forcing uppercase
                        $clean_tag = strtoupper(trim($tag));
                        
                        if (empty($clean_tag)) continue; // Skip empty commas

                        // If the user already has the tag
                        $check_tag_stmt->execute([
                            ':uid' => $current_user_id,
                            ':tag_name' => $clean_tag
                        ]);

                        if ($check_tag_stmt->rowCount() > 0) {
                            // Get the tag id if it already ecists 
                            $row = $check_tag_stmt->fetch(PDO::FETCH_ASSOC);
                            $tag_id = $row['TagID'];
                        } else {
                            // If not insertt a new tag
                            $insert_tag_stmt->execute([
                                ':uid' => $current_user_id,
                                ':tag_name' => $clean_tag
                            ]);
                            $tag_id = $conn->lastInsertId();
                        }

                        // Link the Task and the Tag together in the Junction Table
                        $link_tag_stmt->execute([
                            ':taskID' => $new_task_id,
                            ':TagID' => $tag_id
                        ]);
                    }
                }

                $conn->commit();
                
                $message = "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; text-align: center; margin: 15px 0; border: 1px solid #c3e6cb;'>
                            <strong>Event & Tags successfully added!</strong>
                            </div>";
            }

        } catch (PDOException $e) {
            // If there was an error roll back the database so we don't get partial data
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $message = "<div style='background: #ffebee; color: #c62828; padding: 15px; border: 2px solid #ef5350; text-align: center; margin: 15px 0;'>
                        <strong>Database Error:</strong><br>" . $e->getMessage() . "
                        </div>";
        }
    }
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Smart Timetabler</title>
    <link rel="stylesheet" type="text/css" href="home.css" />
  </head>

  <body>
    <h2 class="title">Add New Event</h2>

    <main id="AddPage">
      <div class="background">
        <form method="POST" action="">
          <div class="input-group">
            <p>Event Title</p>
            <input
              type="text"
              name="event_title"
              placeholder="e.g., Submit assignment"
            />
          </div>

          <div class="input-group">
            <label for="dropdown">Event Type</label>
            <select name="dropdown" id="dropdown">
              <option value="" hidden selected disabled>
                -- Select an option --
              </option>
              <option value="Event">Event</option>
              <option value="Task">Task</option>
              <option value="Other">Other</option>
            </select>
          </div>

          <div class="row">
            <div class="input-group wide">
              <p>Date & Start Time</p>
              <input type="datetime-local" name="date_time" />
            </div>
            <div class="input-group narrow">
              <p>Duration (mins)</p>
              <input placeholder="Input Time" name="duration" type="number" />
            </div>
          </div>

          <div class="input-group">
            <p>Description / Location</p>
            <textarea
              name="description"
              placeholder="Add details..."
              rows="4"
            ></textarea>
          </div>

          <div class="input-group">
            <p>Tags</p>
            <input
              type="text"
              name="tags"
              placeholder="e.g., maths, urgent, exam"
            />
          </div>

          <button type="submit">Create Event</button>
        </form>
      </div>
    </main>
    
    <?php include 'toolbar.php'; ?>


  </body>
</html>