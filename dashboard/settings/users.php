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
      $message = 'Utilisateur cr&eacute;&eacute;.';
    }
  }

  if (isset($_POST['update_user'])) {
    $userId = (int)($_POST['user_id'] ?? 0);
    $role = trim((string)($_POST['role'] ?? 'viewer'));
    $active = (int)($_POST['active'] ?? 1);
    $password = (string)($_POST['password'] ?? '');
    if ($userId > 0) {
      if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET role = :r, active = :a, pass_hash = :p WHERE id = :id')
            ->execute([':r' => $role, ':a' => $active, ':p' => $hash, ':id' => $userId]);
      } else {
        $pdo->prepare('UPDATE users SET role = :r, active = :a WHERE id = :id')
            ->execute([':r' => $role, ':a' => $active, ':id' => $userId]);
      }
      audit_log('update_user', 'user', $userId, ['role' => $role, 'active' => $active, 'password_reset' => $password !== ''], current_user(), current_role());
      $message = 'Utilisateur mis &agrave; jour.';
    }
  }

  if (isset($_POST['delete_user'])) {
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId > 0) {
      $pdo->prepare('UPDATE users SET active = 0 WHERE id = :id')->execute([':id' => $userId]);
      audit_log('delete_user', 'user', $userId, ['soft' => true], current_user(), current_role());
      $message = 'Utilisateur d&eacute;sactiv&eacute;.';
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
  <p class="admin-note">R&ocirc;les disponibles : admin, manager, viewer.</p>

  <?php if ($message): ?>
    <div class="p-3 mb-4 rounded-lg bg-emerald-50 text-emerald-700 text-sm border border-emerald-100"><?php echo htmlspecialchars($message); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="p-3 mb-4 rounded-lg bg-red-50 text-red-700 text-sm border border-red-100"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <div class="p-4 rounded-xl border border-slate-200 bg-white mb-6">
    <h6 class="text-sm font-semibold text-slate-600 mb-3">Ajouter un utilisateur</h6>
    <form method="post" class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
        <label class="text-sm font-medium text-slate-600">R&ocirc;le</label>
        <select name="role" class="ta-input w-full">
          <option value="admin">Admin</option>
          <option value="manager">Manager</option>
          <option value="viewer">Viewer</option>
        </select>
      </div>
      <div class="flex items-end">
        <button class="w-full rounded-xl bg-teal-600 text-white py-2.5 font-semibold">Cr&eacute;er</button>
      </div>
    </form>
  </div>

  <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <div class="max-w-full overflow-x-auto">
      <table class="user-table min-w-[900px]">
        <thead>
          <tr>
            <th>Utilisateur</th>
            <th>Statut</th>
            <th>R&ocirc;le</th>
            <th>Acc&egrave;s web</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$users): ?>
            <tr><td colspan="5" class="px-5 py-8 text-center text-slate-400">Aucun utilisateur.</td></tr>
          <?php else: ?>
            <?php foreach ($users as $u): ?>
              <?php
                $username = (string)$u['username'];
                $initials = strtoupper(substr($username, 0, 1));
                $roleClass = 'role-viewer';
                if ($u['role'] === 'admin') $roleClass = 'role-admin';
                if ($u['role'] === 'manager') $roleClass = 'role-manager';
                $updateFormId = 'user-update-' . (int)$u['id'];
                $resetFormId = 'user-reset-' . (int)$u['id'];
                $activeChecked = ((int)$u['active'] === 1);
              ?>
              <tr class="user-row">
                <td>
                  <div class="user-meta">
                    <div class="ta-avatar"><?php echo htmlspecialchars($initials); ?></div>
                    <div>
                      <div class="user-name"><?php echo htmlspecialchars($u['username']); ?></div>
                      <div class="user-sub">Cr&eacute;&eacute; le <?php echo htmlspecialchars($u['created_at']); ?></div>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="status-dot <?php echo $activeChecked ? 'status-active' : 'status-inactive'; ?>"></span>
                  <span class="text-sm text-slate-600"><?php echo $activeChecked ? 'Actif' : 'D&eacute;sactiv&eacute;'; ?></span>
                </td>
                <td>
                  <form id="<?php echo $updateFormId; ?>" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="update_user" value="1">
                    <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                  </form>
                  <div class="role-pill <?php echo $roleClass; ?>">
                    <select name="role" form="<?php echo $updateFormId; ?>">
                      <option value="admin" <?php echo $u['role']==='admin'?'selected':''; ?>>Admin</option>
                      <option value="manager" <?php echo $u['role']==='manager'?'selected':''; ?>>Manager</option>
                      <option value="viewer" <?php echo $u['role']==='viewer'?'selected':''; ?>>Viewer</option>
                    </select>
                  </div>
                </td>
                <td>
                  <input type="hidden" name="active" value="0" form="<?php echo $updateFormId; ?>">
                  <label class="toggle">
                    <input type="checkbox" name="active" value="1" form="<?php echo $updateFormId; ?>" <?php echo $activeChecked ? 'checked' : ''; ?>>
                    <span class="toggle-track"></span>
                  </label>
                </td>
                <td>
                  <div class="user-actions">
                    <button type="submit" form="<?php echo $updateFormId; ?>" class="btn-ghost">Mettre &agrave; jour</button>
                    <form id="<?php echo $resetFormId; ?>" method="post" class="flex items-center gap-2">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                      <input type="hidden" name="update_user" value="1">
                      <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                      <input type="hidden" name="role" value="<?php echo htmlspecialchars($u['role']); ?>">
                      <input type="hidden" name="active" value="<?php echo (int)$u['active']; ?>">
                      <input type="password" name="password" class="ta-input" placeholder="Nouveau mot de passe">
                      <button class="btn-ghost" type="submit">Reset</button>
                    </form>
                    <form method="post">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                      <input type="hidden" name="delete_user" value="1">
                      <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                      <button class="btn-danger-link" type="submit">D&eacute;sactiver</button>
                    </form>
                  </div>
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
