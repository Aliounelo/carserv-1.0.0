<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
function admin_config(): array {
  return require __DIR__ . '/config.php';
}
function is_logged_in(): bool {
  return isset($_SESSION['admin_user']);
}
function current_role(): string {
  return (string)($_SESSION['admin_role'] ?? 'admin');
}
function is_admin(): bool {
  return current_role() === 'admin';
}
function can_edit_leads(): bool {
  return in_array(current_role(), ['admin','manager'], true);
}
function can_edit_devis(): bool {
  return in_array(current_role(), ['admin','manager'], true);
}
function require_login(): void {
  if (!is_logged_in()) {
    header('Location: /dashboard/login.php');
    exit;
  }
}
function require_admin(): void {
  if (!is_admin()) {
    http_response_code(403);
    exit('Acces refuse');
  }
}
function current_user(): string {
  return (string)($_SESSION['admin_user'] ?? '');
}
function csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
  }
  return $_SESSION['csrf_token'];
}
function csrf_verify(string $token): bool {
  return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
function maybe_upgrade_password_hash(string $plain): void {
  $cfgPath = __DIR__ . '/config.php';
  $cfg = admin_config();
  if (!empty($cfg['admin_pass_hash'])) {
    return;
  }
  $hash = password_hash($plain, PASSWORD_DEFAULT);
  $cfg['admin_pass_hash'] = $hash;
  $cfg['admin_pass_plain'] = '';
  $export = "<?php\n\nreturn " . var_export($cfg, true) . ";\n";
  file_put_contents($cfgPath, $export);
}

