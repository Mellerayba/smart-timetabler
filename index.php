<?php
// 1. Connect to database
require 'db_connect.php';

// 2. Setup variables
$status = "Disconnected";
$users_count = 0;
$events_count = 0;
$todo_count = 0;
$error_msg = "";

try {
    if ($conn) {
        $status = "Connected to Database";
        
        // 3. Simple queries to check if tables exist and count data
        
        $stmt = $conn->query("SELECT COUNT(*) FROM Users");
        $users_count = $stmt->fetchColumn();

        $stmt = $conn->query("SELECT COUNT(*) FROM Events");
        $events_count = $stmt->fetchColumn();

        $stmt = $conn->query("SELECT COUNT(*) FROM ToDo");
        $todo_count = $stmt->fetchColumn();
    }
} catch (PDOException $e) {
    $status = "Connection Error";
    $error_msg = $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Development</title>
</head>
<body>

    <h1>Smart Timetabler </h1>
    <hr>

    <h2>Database Status</h2>
    <p><strong>Status:</strong> <?php echo $status; ?></p>
    
    <?php if ($error_msg): ?>
        <p><strong>Error Details:</strong> <?php echo $error_msg; ?></p>
    <?php endif; ?>

    <hr>

    <h2>Data Counts</h2>
    <ul>
        <li><strong>Users:</strong> <?php echo $users_count; ?></li>
        <li><strong>Events:</strong> <?php echo $events_count; ?></li>
        <li><strong>To-Do Items:</strong> <?php echo $todo_count; ?></li>
    </ul>

    <hr>
    <li><a href="home.php">Demo Home page</a></li>
    <h2>Link to database setup</h2>
    <ul>
        <li><a href="install(done).php">Re-run Database Setup (Reset Tables)</a></li>
    </ul>

    
</body>
</html>