<?php
$current_file = basename($_SERVER['PHP_SELF']);

function isActive($pageName, $current_file) {
    return ($pageName === $current_file) ? 'active' : '';
}
?>

<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<footer class="toolbar">
    <a href="dashboard.php" class="nav-item <?php echo isActive('dashboard.php', $current_file); ?>">
        <span class="material-icons">home</span>
        <span class="nav-text">Home</span>
    </a>
    
    <a href="view_todo.php" class="nav-item <?php echo isActive('view_todo.php', $current_file); ?>">
        <span class="material-icons">list_alt</span>
        <span class="nav-text">Todo</span>
    </a>

    <div class="add-btn-container">
        <a href="add_task.php" class="add-btn <?php echo ($current_file == 'add_task.php') ? 'is-adding' : ''; ?>">
            <span>+</span>
        </a>
    </div>

    <a href="week_view.php" class="nav-item <?php echo isActive('week_view.php', $current_file); ?>">
        <span class="material-icons">calendar_today</span>
        <span class="nav-text">Calendar</span>
    </a>

    <a href="settings.php" class="nav-item <?php echo isActive('settings.php', $current_file); ?>">
        <span class="material-icons">settings</span>
        <span class="nav-text">Settings</span>
    </a>
</footer>

<style>
.toolbar {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 70px;
    background: #ffffff;
    display: flex;
    justify-content: space-around;
    align-items: center;
    border-top: 1px solid #eee;
    z-index: 1000;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
}

.nav-item {
    flex: 1;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #999; 
    transition: color 0.3s ease;
}

.nav-item.active {
    color: #007bff; 
    font-weight: bold;
}

.nav-item .material-icons {
    font-size: 26px;
}

.nav-item .nav-text {
    font-size: 11px;
    margin-top: 2px;
    font-weight: 500;
}

.add-btn-container {
    flex: 1;
    display: flex;
    justify-content: center;
    position: relative;
    top: -5px; 
}

.add-btn {
    width: 55px;
    height: 55px;
    background-color: #007bff;
    color: white;
    text-decoration: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: 300;
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.4); 
    transition: transform 0.2s ease, background-color 0.3s, box-shadow 0.2s;
}

.add-btn:hover {
    transform: scale(1.15);
    background-color: #0069d9;
    box-shadow: 0 6px 16px rgba(0, 123, 255, 0.5);
}

.add-btn:active {
    transform: scale(0.95);
}
</style>