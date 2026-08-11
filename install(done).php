<?php
/**
 * Database Setup - Currently for min working demo
 * * Creates only the essential tables required for the 
 * min working demo (Timetable, Tasks, Deadlines, Tags, Settings).
 */

require 'db_connect.php';

try {
    // Users Table
    $sql_users = "CREATE TABLE IF NOT EXISTS Users (
        UserID INT(10) UNSIGNED PRIMARY KEY,
        user_name VARCHAR(100) NOT NULL
    )";
    $conn->exec($sql_users);
    echo "Table 'Users' Created succesffully .<br>";
    
    // User Settings Table 
    $sql_settings = "CREATE TABLE IF NOT EXISTS User_Settings (
        UserID INT(10) UNSIGNED PRIMARY KEY,
        postcode VARCHAR(20),
        preferred_transport VARCHAR(50) DEFAULT 'public_transport',
        email VARCHAR(255) DEFAULT NULL,
        start_hour INT DEFAULT 8,
        end_hour INT DEFAULT 18,
        display_name VARCHAR(50),
        FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE CASCADE
    )";
    $conn->exec($sql_settings);
    echo "Table 'User_Settings' created.<br>";

    // Events Table 
    $sql_events = "CREATE TABLE IF NOT EXISTS Events (
        EventID INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        UserID INT(6) UNSIGNED,
        module VARCHAR(100) NOT NULL,
        start_time DATETIME,
        duration VARCHAR(50), 
        type VARCHAR(50),       
        is_complete BOOLEAN DEFAULT 0, -- Used for 'Rescheduling missed tasks'
        staff VARCHAR(200),
        location VARCHAR(200),
        FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE CASCADE
    )";
    $conn->exec($sql_events);
    echo "Table 'Events' created.<br>";

    // ToDo Table
    $sql_todo = "CREATE TABLE IF NOT EXISTS ToDo (
        taskID INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        UserID INT(6) UNSIGNED,
        name VARCHAR(100) NOT NULL,
        description VARCHAR(255),
        deadline DATETIME,      
        FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE CASCADE
    )";
    $conn->exec($sql_todo);
    echo "Table 'ToDo' created .<br>";

    // Tags Table 
    $sql_tags = "CREATE TABLE IF NOT EXISTS Tags (
        TagID INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        UserID INT(6) UNSIGNED,
        tag_name VARCHAR(50) NOT NULL,
        color VARCHAR(7) DEFAULT '#808080',
        FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE CASCADE
    )";
    $conn->exec($sql_tags);
    echo "Table 'Tags' created.<br>";

    // ToDo_Tags Junction Table 
    $sql_todo_tags = "CREATE TABLE IF NOT EXISTS ToDo_Tags (
        taskID INT(6) UNSIGNED NOT NULL,
        TagID INT(6) UNSIGNED NOT NULL,
        PRIMARY KEY (taskID, TagID),
        FOREIGN KEY (taskID) REFERENCES ToDo(taskID) ON DELETE CASCADE,
        FOREIGN KEY (TagID) REFERENCES Tags(TagID) ON DELETE CASCADE
    )";
    $conn->exec($sql_todo_tags);
    echo "Table 'ToDo_Tags' created.<br>";

    // Timetable Links Table
    $sql_Timetable_Links="CREATE TABLE IF NOT EXISTS Timetable_Links (
        UserID INT(6) UNSIGNED PRIMARY KEY,
        ical_url VARCHAR(700) NOT NULL,
        last_synced DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE CASCADE
    )";
    $conn->exec($sql_Timetable_Links);
    echo "Table 'Timetable_Links' created.<br>";

    echo "<hr><strong>Database Ready</strong>";

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>