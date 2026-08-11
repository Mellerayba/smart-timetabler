<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $task_id = $_POST['taskID'];

    try {
        // Check the current status of the task
        $check_sql = "SELECT is_complete FROM ToDo WHERE taskID = :taskID AND UserID = :uid";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([':taskID' => $task_id, ':uid' => $current_user_id]);
        $task = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($task) {
            // Decide what the new status should be
            if ($task['is_complete'] == 1) {
                // If it was done, mark it incomplete and clear the timestamp
                $update_sql = "UPDATE ToDo SET is_complete = 0, completed_at = NULL WHERE taskID = :taskID AND UserID = :uid";
            } else {
                // If it was incomplete, mark it done and record the time
                $update_sql = "UPDATE ToDo SET is_complete = 1, completed_at = NOW() WHERE taskID = :taskID AND UserID = :uid";
            }


            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->execute([
                ':taskID' => $task_id,
                ':uid' => $current_user_id
            ]);
        }

        header("Location: view_todo.php");
        exit();

    } catch (PDOException $e) {
        echo "Error updating task: " . $e->getMessage();
    }
} else {
    header("Location: view_todo.php");
    exit();
}
?>