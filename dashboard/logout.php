<?php

require_once __DIR__ . '/_inc/auth.php';

session_destroy();
header('Location: /dashboard/login.php');
exit;
