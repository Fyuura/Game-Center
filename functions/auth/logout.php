<?php
session_start();

session_unset();
session_destroy();

header("Location: /gamecenter/pages/login.php");
exit;
