<?php
require __DIR__ . '/api/db.php';
$pdo = db();
echo $pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn();
