<?php
require_once __DIR__ . '/php/auth.php';
logout();
header('Location: login.php');
exit;
