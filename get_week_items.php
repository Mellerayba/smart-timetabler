<?php
session_start();
include 'db_connect.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not Logged In']);
    exit();
}

$current_uID = $_SESSION['user_id'];

if (!isset($_GET['start']) || !isset($_GET['end'])) {
    echo json_encode(['error' => 'Time Parameters Missing']);
    exit();
}

$start_date = $_GET['start'];
$end_date = $_GET['end'];

try {
    // SQL for the University calendar events
    $ev_sql = "SELECT * 
            FROM Events 
            WHERE UserID = :u_ID AND 
            start_time BETWEEN :s AND :e
            ORDER BY start_time ASC";

    $statement = $conn->prepare($ev_sql);
    $statement->execute(['u_ID' => $current_uID, 
                        's' => $start_date, 
                        'e' => $end_date]);

    $events = $statement->fetchAll(PDO::FETCH_ASSOC);

    // SQL for the ToDo events
    $td_sql = "SELECT ToDo.*, MAX(Tags.color) AS color
            FROM ToDo
            LEFT JOIN ToDo_Tags ON ToDo_Tags.taskID = ToDo.taskID
            LEFT JOIN Tags ON Tags.TagID = ToDo_Tags.TagID
            WHERE ToDo.UserID = :u_ID AND 
            ToDo.is_complete = 0 AND 
            deadline BETWEEN :s AND :e
            GROUP BY ToDo.taskID
            ORDER BY ToDo.deadline ASC";

    $statement = $conn->prepare($td_sql);
    $statement->execute(['u_ID' => $current_uID, 
                        's' => $start_date, 
                        'e' => $end_date]);


    $toDo = $statement->fetchall(PDO::FETCH_ASSOC);

    // SQL for the Canvas assignments
    $cv_sql = "SELECT *
            FROM Canvas_Assignments
            WHERE UserID = :u_ID AND 
            status = 'pending' AND 
            due_date BETWEEN :s AND :e
            ORDER BY due_date ASC";
    $statement = $conn->prepare($cv_sql);
    $statement->execute(['u_ID' => $current_uID, 
                        's' => $start_date, 
                        'e' => $end_date]);


    $assignments = $statement->fetchall(PDO::FETCH_ASSOC);

    echo json_encode(['events' => $events,
                        'toDo' => $toDo,
                        'assignments' => $assignments]);
}
catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit();
}

?>