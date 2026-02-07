<?php
declare(strict_types=1);

require_once __DIR__ . '/../_inc/auth.php';
require_login();
if (!can_edit_leads()) {
  http_response_code(403);
  exit('Acces refuse');
}
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_inc/layout.php';

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM leads WHERE id = :id');
$stmt->execute([':id' => $id]);
$lead = $stmt->fetch();
if (!$lead) {
  header('Location: /dashboard/leads/list.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = (string)($_POST['csrf_token'] ?? '');
  if (!csrf_verify($token)) {
    http_response_code(403);
    exit('CSRF invalide');
  }

  $status = trim((string)($_POST['status'] ?? 'Nouveau'));
  $priority = trim((string)($_POST['priority'] ?? 'Normal'));
  $notes = trim((string)($_POST['notes'] ?? ''));

  $stmt = $pdo->prepare('UPDATE leads SET status = :status, priority = :priority, notes = :notes, updated_at = :updated_at WHERE id = :id');
  $stmt->execute([
    ':status' => $status,
    ':priority' => $priority,
    ':notes' => $notes,
    ':updated_at' => date('Y-m-d H:i:s'),
    ':id' => $id
  ]);
  audit_log('update_lead', 'lead', $id, ['status' => $status, 'priority' => $priority], current_user(), current_role());

  header('Location: /dashboard/leads/view.php?id=' . $id);
  exit;
}

render_header('Modifier lead #' . $lead['id']);
?>
<div class="admin-card">
  <div class="admin-section-title">
    <h5>Mettre &agrave; jour le lead</h5>
  </div>
  <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

    <div>
      <label class="text-sm font-medium text-slate-600">Statut</label>
      <?php $statuses = ['Nouveau','En cours de traitement',"Contact\u{00E9}","Devis envoy\u{00E9}",'Converti',"Abandonn\u{00E9}"]; ?>
      <select name="status" class="ta-input w-full">
        <?php foreach ($statuses as $s): ?>
          <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $lead['status']===$s?'selected':''; ?>><?php echo htmlspecialchars($s); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="text-sm font-medium text-slate-600">Priorit&eacute;</label>
      <?php $priorities = ['Basse','Normal','Haute','Urgent']; ?>
      <select name="priority" class="ta-input w-full">
        <?php foreach ($priorities as $p): ?>
          <option value="<?php echo htmlspecialchars($p); ?>" <?php echo $lead['priority']===$p?'selected':''; ?>><?php echo htmlspecialchars($p); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="md:col-span-2">
      <label class="text-sm font-medium text-slate-600">Notes internes</label>
      <textarea name="notes" class="ta-input w-full" rows="5"><?php echo htmlspecialchars($lead['notes'] ?? ''); ?></textarea>
    </div>

    <div class="md:col-span-2 flex gap-2">
      <button class="px-4 py-2.5 rounded-xl bg-teal-600 text-white font-semibold" type="submit">Enregistrer</button>
      <a class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700" href="/dashboard/leads/view.php?id=<?php echo (int)$lead['id']; ?>">Annuler</a>
    </div>
  </form>
</div>
<?php render_footer(); ?>
