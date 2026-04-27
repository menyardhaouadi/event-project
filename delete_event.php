<?php 
include 'connect.php'; 
session_start();

$id = $_GET['id'];
$admin_id = $_SESSION['user_id'];

$conn->query("DELETE FROM events WHERE id=$id AND admin_id=$admin_id");

header("Location: admin.php");