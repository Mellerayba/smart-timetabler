<?php
/* This file establishes a secure connection to the MySQL database using the PDO (PHP Data Objects) interface. 
It is included in every page that requires database access.
 */ 
$servername = "DUMMY_DB_HOST"; 
$username = "DUMMY_USERNAME";              
$password = "DUMMY_PASSWORD";        
$dbname = "DUMMY_DBNAME";    

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    //echo "Connected successfully";
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>