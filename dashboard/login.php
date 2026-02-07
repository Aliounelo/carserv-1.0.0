<?php

require_once __DIR__ . '/_inc/auth.php';
require_once __DIR__ . '/../api/db.php';

if (is_logged_in()) {
  header('Location: /dashboard/index.php');
  exit;
}

$cfg = admin_config();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $user = trim((string)($_POST['username'] ?? ''));
  $pass = (string)($_POST['password'] ?? '');

  $role = 'admin';
  $valid = false;

  // Try DB users first
  try {
    $pdo = db();
    $count = (int)$pdo->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
    if ($count === 0) {
      $seedUser = (string)($cfg['admin_user'] ?? '');
      $seedPlain = (string)($cfg['admin_pass_plain'] ?? '');
      $seedHash = (string)($cfg['admin_pass_hash'] ?? '');
      if ($seedUser !== '' && ($seedPlain !== '' || $seedHash !== '')) {
        $hash = $seedHash !== '' ? $seedHash : password_hash($seedPlain, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username, pass_hash, role, active, created_at) VALUES (:u, :p, :r, 1, :c)');
        $stmt->execute([':u' => $seedUser, ':p' => $hash, ':r' => 'admin', ':c' => date('Y-m-d H:i:s')]);
      }
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u AND active = 1 LIMIT 1');
    $stmt->execute([':u' => $user]);
    $row = $stmt->fetch();
    if ($row && password_verify($pass, (string)$row['pass_hash'])) {
      $valid = true;
      $role = (string)($row['role'] ?? 'admin');
    }
  } catch (Throwable $e) {
    // fallback to config
  }

  if (!$valid) {
    $validUser = $user === ($cfg['admin_user'] ?? '');
    $hash = (string)($cfg['admin_pass_hash'] ?? '');
    $plain = (string)($cfg['admin_pass_plain'] ?? '');
    $validPass = false;
    if ($hash !== '') {
      $validPass = password_verify($pass, $hash);
    } elseif ($plain !== '') {
      $validPass = hash_equals($plain, $pass);
    }
    $valid = $validUser && $validPass;
  }

  if ($valid) {
    $_SESSION['admin_user'] = $user;
    $_SESSION['admin_role'] = $role;
    if (!empty($plain) && ($cfg['admin_pass_hash'] ?? '') === '') {
      maybe_upgrade_password_hash($pass);
    }
    header('Location: /dashboard/index.php');
    exit;
  }

  $error = 'Identifiants invalides.';
}

?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Connexion | MARGE Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="/dashboard/assets/admin.css" rel="stylesheet">
</head>
<body class="font-outfit bg-slate-100">
  <div class="min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md login-card">
      <div class="flex items-center gap-4 mb-6">
        <img src="/img/logo-marge.png" alt="MARGE" class="login-logo">
        <div class="leading-tight">
          <div class="text-xs uppercase tracking-[0.2em] text-slate-400">MARGE</div>
          <div class="login-title">Connexion Admin</div>
        </div>
      </div>
      <?php if ($error): ?>
        <div class="mb-4 rounded-lg bg-red-50 text-red-700 px-3 py-2 text-sm"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <form method="post" class="space-y-4">
        <div>
          <label class="text-sm font-medium text-slate-600">Utilisateur</label>
          <input type="text" name="username" class="ta-input w-full" required>
        </div>
        <div>
          <label class="text-sm font-medium text-slate-600">Mot de passe</label>
          <input type="password" name="password" class="ta-input w-full" required>
        </div>
        <button class="w-full rounded-xl bg-teal-600 text-white py-2.5 font-semibold hover:bg-teal-700 transition">Se connecter</button>
      </form>
      <p class="text-xs text-slate-400 mt-4">Change le mot de passe dans <code>dashboard/_inc/config.php</code> apr&egrave;s la premi&egrave;re connexion.</p>
    </div>
  </div>
</body>
</html>
