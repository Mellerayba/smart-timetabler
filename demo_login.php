<?php
session_start();
require 'db_connect.php'; // Ensure this matches your DB connection file name

// 1. CHOOSE A DEDICATED DEMO USER ID 
// Make sure this ID actually exists in your User_Settings table!
// It should contain your pre-loaded mock calendar events.
$demo_user_id = '9999'; 

try {
    // 2. Double check the demo user exists in the database
    $stmt = $conn->prepare("SELECT UserID FROM Users WHERE UserID = :uid LIMIT 1");
    $stmt->execute([':uid' => $demo_user_id]);
    $user = $stmt->fetch();

    if ($user) {
        // 3. Establish the session exactly how your app expects it
        $_SESSION['user_id'] = $user['UserID'];
        $_SESSION['is_demo'] = true; // Flag this session as a demo just in case
        
        // 4. Redirect straight to the dashboard
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Demo user account (ID: $demo_user_id) was not found in the database. Please create it first.";
    }
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Timetabler - Demo Access</title>
    <link rel="stylesheet" type="text/css" href="home.css" />
    <style>
        body { display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f5f7fb; font-family: sans-serif; }
        .demo-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: center; max-width: 400px; width: 90%; }
        .btn-demo { background: #007bff; color: white; border: none; padding: 12px 24px; font-size: 16px; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; transition: background 0.2s; }
        .btn-demo:hover { background: #0056b3; }
        .error-msg { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>

    <div class="demo-card">
        <h2>Smart Timetabler Portfolio Demo</h2>
        <p style="color: #666; margin-bottom: 30px;">Welcome! Click below to explore a pre-configured dashboard populated with sample university timetables and Canvas deadlines.</p>
        
        <?php if (isset($error)): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <button type="submit" class="btn-demo">Enter Live Demo Dashboard </button>
        </form>
    </div>

</body>
</html>