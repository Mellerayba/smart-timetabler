<?php
session_start();
require 'db_connect.php';

// Kick them out if they aren't logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['taskID']) && isset($_POST['source_page'])) {
    $task_id = $_POST['taskID'];

    try {
        // Delete the links to any tags in the junction table first
        $tag_sql = "DELETE FROM ToDo_Tags WHERE taskID = :taskID";
        $tag_stmt = $conn->prepare($tag_sql);
        $tag_stmt->execute([':taskID' => $task_id]);

        // Permanently delete the task (ensuring it belongs to the user)
        $sql = "DELETE FROM ToDo WHERE taskID = :taskID AND UserID = :uid";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':taskID' => $task_id,
            ':uid' => $current_user_id
        ]);

    } catch (PDOException $e) {

        error_log("Error deleting task: " . $e->getMessage());
    }
    $source = $_POST['source_page'];
    header("Location: $source");
    exit();
}

// Instantly redirect back to the To-Do list
header("Location: view_todo.php");
exit();
?>