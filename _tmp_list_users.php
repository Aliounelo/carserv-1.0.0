<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/api/data/marge.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach ($pdo->query('SELECT id, username, role, active FROM users ORDER BY id') as $r) {
  echo $r['id'] . '|' . $r['username'] . '|' . $r['role'] . '|' . $r['active'] . PHP_EOL;
}
