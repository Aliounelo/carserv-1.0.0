<?php
require __DIR__ . '/api/db.php';
$pdo = db();
$rows = $pdo->query('SELECT id,type,name,email,phone,service,subject,created_at FROM leads ORDER BY id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
