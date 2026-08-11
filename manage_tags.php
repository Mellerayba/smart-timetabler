<?php
// Turn on error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'db_connect.php';
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Kick them out if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$message = "";


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_tag'])) {
    $tag_id = $_POST['tag_id'];
    $new_color = $_POST['tag_color'];

    try {

        $update_sql = "UPDATE Tags SET color = :color WHERE TagID = :tag_id AND UserID = :uid";
        $stmt = $conn->prepare($update_sql);
        $stmt->execute([
            ':color' => $new_color,
            ':tag_id' => $tag_id,
            ':uid' => $current_user_id
        ]);
        
        $message = "<div style='background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; text-align: center; margin-bottom: 20px;'>
                    <strong>Color updated successfully!</strong>
                    </div>";
    } catch (PDOException $e) {
        $message = "<div style='background: #ffebee; color: #c62828; padding: 10px; border-radius: 5px; text-align: center; margin-bottom: 20px;'>
                    <strong>Error updating color:</strong> " . $e->getMessage() . "
                    </div>";
    }
}


try {
    $fetch_sql = "SELECT * FROM Tags WHERE UserID = :uid ORDER BY tag_name ASC";
    $stmt = $conn->prepare($fetch_sql);
    $stmt->execute([':uid' => $current_user_id]);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tags</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background-color: #f4f7f6;
            color: #333;
            padding: 20px;
            max-width: 600px;
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
        .btn-back {
            background: #6c757d;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .tag-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .tag-name-preview {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            color: white;
            font-weight: bold;
            font-size: 0.9em;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3); 
        }
        .tag-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        input[type="color"] {
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            padding: 0;
            background: none;
        }
        button[type="submit"] {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        button[type="submit"]:hover { background: #0056b3; }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
            background: white;
            border-radius: 8px;
            border: 2px dashed #ccc;
        }
    </style>
</head>
<body>

    <div class="header">
        <h2>🎨 Manage Tags</h2>
        <a href="view_todo.php" class="btn-back">Back</a> 
    </div>

    <?php if (!empty($message)) echo $message; ?>

    <?php if (empty($tags)): ?>
        <div class="empty-state">
            <h3>No tags found</h3>
            <p>Create a task and add some tags first.</p>
        </div>
    <?php else: ?>
        
        <?php foreach ($tags as $tag): ?>
            <div class="tag-card">
                
                <div>
                    <span class="tag-name-preview" style="background-color: <?php echo htmlspecialchars($tag['color']); ?>;">
                        🏷️ <?php echo htmlspecialchars($tag['tag_name']); ?>
                    </span>
                </div>

                <form method="POST" action="" class="tag-controls">
                    <input type="hidden" name="tag_id" value="<?php echo $tag['TagID']; ?>">
                    
                    <input type="color" name="tag_color" value="<?php echo htmlspecialchars($tag['color']); ?>" title="Choose a colour">
                    
                    <button type="submit" name="update_tag">Save</button>
                </form>

            </div>
        <?php endforeach; ?>

    <?php endif; ?>
    <?php include 'toolbar.php'; ?>
</body>
</html>