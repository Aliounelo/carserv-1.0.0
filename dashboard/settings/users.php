<?php
require_once __DIR__ . '/../_inc/auth.php';
require_login();
require_admin();
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_inc/layout.php';

$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = (string)($_POST['csrf_token'] ?? '');
  if (!csrf_verify($token)) {
    http_response_code(403);
    exit('CSRF invalide');
  }

  if (isset($_POST['create_user'])) {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role = trim((string)($_POST['role'] ?? 'viewer'));
    if ($username === '' || $password === '') {
      $error = 'Utilisateur et mot de passe requis.';
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare('INSERT INTO users (username, pass_hash, role, active, created_at) VALUES (:u, :p, :r, 1, :c)');
      $stmt->execute([':u' => $username, ':p' => $hash, ':r' => $role, ':c' => date('Y-m-d H:i:s')]);
      audit_log('create_user', 'user', (int)$pdo->lastInsertId(), ['username' => $username, 'role' => $role], current_user(), current_role());
      $message = 'Utilisateur créé.';
    }
  }

  if (isset($_POST['toggle_user'])) {
    $userId = (int)($_POST['user_id'] ?? 0);
    $active = (int)($_POST['active'] ?? 0);
    if ($userId > 0) {
      $pdo->prepare('UPDATE users SET active = :a WHERE id = :id')->execute([':a' => $active, ':id' => $userId]);
      audit_log('toggle_user', 'user', $userId, ['active' => $active], current_user(), current_role());
      $message = 'Statut utilisateur mis à jour.';
    }
  }
}

$users = $pdo->query('SELECT id, username, role, active, created_at FROM users ORDER BY id DESC')->fetchAll();

render_header('Utilisateurs');
?>
<div class="admin-card">
  <div class="admin-section-title">
    <h5>Gestion des utilisateurs</h5>
  </div>
  <p class="admin-note">Rôles disponibles : admin, manager, viewer.</p>

  <?php if ($message): ?>
    <div class="p-3 mb-4 rounded-lg bg-emerald-50 text-emerald-700 text-sm border border-emerald-100"><?php echo htmlspecialchars($message); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="p-3 mb-4 rounded-lg bg-red-50 text-red-700 text-sm border border-red-100"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <form method="post" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6" data-auto-filter="0">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
    <input type="hidden" name="create_user" value="1">
    <div>
      <label class="text-sm font-medium text-slate-600">Utilisateur</label>
      <input type="text" name="username" class="ta-input w-full" required>
    </div>
    <div>
      <label class="text-sm font-medium text-slate-600">Mot de passe</label>
      <input type="password" name="password" class="ta-input w-full" required>
    </div>
    <div>
      <label class="text-sm font-medium text-slate-600">Rôle</label>
      <select name="role" class="ta-input w-full">
        <option value="admin">Admin</option>
        <option value="manager">Manager</option>
        <option value="viewer">Viewer</option>
      </select>
    </div>
    <div class="flex items-end">
      <button class="w-full rounded-xl bg-teal-600 text-white py-2.5 font-semibold">Créer</button>
    </div>
  </form>

  <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <div class="max-w-full overflow-x-auto">
      <table class="table admin-table min-w-[700px]">
        <thead class="border-b border-slate-100">
          <tr>
            <th class="px-5 py-3 text-start text-slate-500 text-sm">Utilisateur</th>
            <th class="px-5 py-3 text-start text-slate-500 text-sm">Rôle</th>
            <th class="px-5 py-3 text-start text-slate-500 text-sm">Statut</th>
            <th class="px-5 py-3 text-start text-slate-500 text-sm">Créé le</th>
            <th class="px-5 py-3 text-start text-slate-500 text-sm"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php if (!$users): ?>
            <tr><td colspan="5" class="px-5 py-8 text-center text-slate-400">Aucun utilisateur.</td></tr>
          <?php else: ?>
            <?php foreach ($users as $u): ?>
              <tr>
                <td class="px-5 py-4 font-medium text-slate-800"><?php echo htmlspecialchars($u['username']); ?></td>
                <td class="px-5 py-4 text-slate-600"><?php echo htmlspecialchars($u['role']); ?></td>
                <td class="px-5 py-4 text-slate-600"><?php echo ((int)$u['active'] === 1) ? 'Actif' : 'Désactivé'; ?></td>
                <td class="px-5 py-4 text-slate-500"><?php echo htmlspecialchars($u['created_at']); ?></td>
                <td class="px-5 py-4">
                  <form method="post" class="inline">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="toggle_user" value="1">
                    <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                    <input type="hidden" name="active" value="<?php echo ((int)$u['active'] === 1) ? 0 : 1; ?>">
                    <button class="text-slate-600" type="submit"><?php echo ((int)$u['active'] === 1) ? 'Désactiver' : 'Activer'; ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php render_footer(); ?>
