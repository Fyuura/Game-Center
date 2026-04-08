<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gamecenter/pages/login.php");
    exit;
}