<?php
require_once __DIR__ . '/../_inc/auth.php';
require_login();
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

$devisRows = $pdo->prepare('SELECT id, reference, status, amount, currency, created_at FROM devis WHERE lead_id = :id ORDER BY id DESC');
$devisRows->execute([':id' => $id]);
$devis = $devisRows->fetchAll();

$wa = '';
if (!empty($lead['phone'])) {
  $phoneDigits = preg_replace('/\D+/', '', $lead['phone']);
  $msg = urlencode('Bonjour, merci pour votre demande. Nous revenons vers vous rapidement.');
  $wa = 'https://wa.me/' . $phoneDigits . '?text=' . $msg;
}

render_header('Fiche lead #' . $lead['id']);
?>
<div class="admin-card">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <h5><?php echo htmlspecialchars($lead['name']); ?></h5>
      <div class="admin-note"><?php echo htmlspecialchars($lead['type']); ?> &middot; <?php echo htmlspecialchars($lead['created_at']); ?></div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-light" href="/dashboard/leads/list.php">Retour aux leads</a>
      <a class="btn btn-outline-primary" href="/dashboard/leads/edit.php?id=<?php echo (int)$lead['id']; ?>">Modifier statut</a>
      <a class="btn btn-primary" href="/dashboard/devis/create.php?lead_id=<?php echo (int)$lead['id']; ?>">Cr&eacute;er un devis</a>
      <?php if (is_admin()): ?>
        <form method="post" action="/dashboard/leads/archive.php">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <input type="hidden" name="id" value="<?php echo (int)$lead['id']; ?>">
          <button class="btn btn-outline-danger" type="submit">Archiver</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="admin-card">
  <div class="admin-section-title">
    <h5>Coordonn&eacute;es</h5>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="admin-card">
      <p class="admin-note">Email</p>
      <p class="font-medium"><?php echo htmlspecialchars($lead['email']); ?></p>
    </div>
    <div class="admin-card">
      <p class="admin-note">T&eacute;l&eacute;phone</p>
      <p class="font-medium"><?php echo htmlspecialchars($lead['phone'] ?? ''); ?></p>
    </div>
  </div>
  <div class="mt-4 flex flex-wrap gap-2">
    <a class="btn btn-sm btn-primary" href="mailto:<?php echo htmlspecialchars($lead['email']); ?>">R&eacute;pondre par email</a>
    <?php if (!empty($lead['phone'])): ?>
      <a class="btn btn-sm btn-outline-primary" href="tel:<?php echo htmlspecialchars($lead['phone']); ?>">Appeler</a>
    <?php endif; ?>
    <?php if ($wa): ?>
      <a class="btn btn-sm btn-outline-success" href="<?php echo $wa; ?>" target="_blank" rel="noopener">WhatsApp</a>
    <?php endif; ?>
  </div>
</div>

<div class="admin-card">
  <div class="admin-section-title">
    <h5>D&eacute;tails de la demande</h5>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <p><strong>Service :</strong> <?php echo htmlspecialchars($lead['service'] ?? ''); ?></p>
      <p><strong>Date souhait&eacute;e :</strong> <?php echo htmlspecialchars($lead['requested_date'] ?? ''); ?></p>
      <p><strong>Objet :</strong> <?php echo htmlspecialchars($lead['subject'] ?? ''); ?></p>
    </div>
    <div>
      <p class="admin-note">Message</p>
      <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-sm">
        <?php echo nl2br(htmlspecialchars($lead['message'] ?? '')); ?>
      </div>
    </div>
  </div>
  <div class="mt-4">
    <p class="admin-note">D&eacute;tails</p>
    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-sm">
      <?php echo nl2br(htmlspecialchars($lead['details'] ?? '')); ?>
    </div>
  </div>
</div>

<div class="admin-card">
  <div class="admin-section-title">
    <h5>Suivi</h5>
  </div>
  <p><strong>Statut :</strong> <?php echo htmlspecialchars($lead['status']); ?></p>
  <p><strong>Priorit&eacute; :</strong> <?php echo htmlspecialchars($lead['priority']); ?></p>
  <p><strong>Notes internes :</strong><br><?php echo nl2br(htmlspecialchars($lead['notes'] ?? '')); ?></p>
</div>

<div class="admin-card">
  <div class="admin-section-title">
    <h5>Devis li&eacute;s</h5>
  </div>
  <?php if (!$devis): ?>
    <div class="text-slate-500 text-sm">Aucun devis associ&eacute; pour le moment.</div>
  <?php else: ?>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
      <div class="max-w-full overflow-x-auto">
        <table class="table admin-table min-w-[700px]">
          <thead class="border-b border-slate-100">
            <tr>
              <th class="px-4 py-3 text-start text-slate-500 text-sm">R&eacute;f&eacute;rence</th>
              <th class="px-4 py-3 text-start text-slate-500 text-sm">Montant</th>
              <th class="px-4 py-3 text-start text-slate-500 text-sm">Statut</th>
              <th class="px-4 py-3 text-start text-slate-500 text-sm">Date</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($devis as $d): ?>
              <tr>
                <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($d['reference']); ?></td>
                <td class="px-4 py-3"><?php echo number_format((float)$d['amount'], 0, ',', ' '); ?> <?php echo htmlspecialchars($d['currency']); ?></td>
                <td class="px-4 py-3"><?php echo htmlspecialchars($d['status']); ?></td>
                <td class="px-4 py-3"><?php echo htmlspecialchars($d['created_at']); ?></td>
                <td class="px-4 py-3"><a class="text-teal-700" href="/dashboard/devis/view.php?id=<?php echo (int)$d['id']; ?>">Ouvrir</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
