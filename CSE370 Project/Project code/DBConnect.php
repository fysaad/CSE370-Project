<?php
$servername="localhost";
$username="root";
$password="";
$dbname="krishidirect";

$conn = new mysqli($servername, $username, $password);
if($conn->connect_error){
	die("Connection failed: " . $conn->connection_error);
}
else {
	echo "Connection Successful";
}
?>