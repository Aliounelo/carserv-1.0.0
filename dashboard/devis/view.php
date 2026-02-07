<?php
declare(strict_types=1);

require_once __DIR__ . '/../_inc/auth.php';
require_login();
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_inc/layout.php';

$pdo = db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT d.*, l.name AS lead_name, l.email AS lead_email, l.phone AS lead_phone, l.status AS lead_status FROM devis d LEFT JOIN leads l ON l.id = d.lead_id WHERE d.id = :id');
$stmt->execute([':id' => $id]);
$devis = $stmt->fetch();
if (!$devis) {
  header('Location: /dashboard/devis/list.php');
  exit;
}

$itemStmt = $pdo->prepare('SELECT * FROM devis_items WHERE devis_id = :id ORDER BY id ASC');
$itemStmt->execute([':id' => $id]);
$items = $itemStmt->fetchAll();

function update_status(PDO $pdo, int $id, string $status): void {
  $now = date('Y-m-d H:i:s');
  $pdo->prepare('UPDATE devis SET status = :s, updated_at = :u WHERE id = :id')
      ->execute([':s' => $status, ':u' => $now, ':id' => $id]);
  $pdo->prepare('UPDATE leads SET status = :s, updated_at = :u WHERE id = (SELECT lead_id FROM devis WHERE id = :id)')
      ->execute([':s' => 'Devis envoyé', ':u' => $now, ':id' => $id]);
}

$total = 0.0;
if ($items) {
  foreach ($items as $it) {
    $total += (float)$it['total'];
  }
} else {
  $total = (float)$devis['amount'];
}

$whatsappText = rawurlencode(
  "Devis {$devis['reference']}\n" .
  "Client: " . ($devis['client_name'] ?: $devis['lead_name']) . "\n" .
  "Montant: " . number_format($total, 0, ',', ' ') . " {$devis['currency']}\n" .
  "Merci."
);
$whatsappUrl = "https://wa.me/22177777218?text={$whatsappText}";

$action = $_GET['action'] ?? '';
$printMode = false;
if ($action === 'send') {
  $config = require __DIR__ . '/../../api/config.php';
  $subject = 'Devis ' . $devis['reference'] . ' - MARGE';
  $to = $devis['client_email'] ?: $devis['lead_email'];
  $logoUrl = $config['site_logo'] ?? 'https://marge-expert.com/img/logo-marge.png';

  $rowsHtml = '';
  foreach ($items as $it) {
    $rowsHtml .= '<tr>'
      . '<td style="padding:8px 0;">' . htmlspecialchars($it['label']) . '</td>'
      . '<td style="padding:8px 0;text-align:center;">' . htmlspecialchars((string)$it['quantity']) . '</td>'
      . '<td style="padding:8px 0;text-align:right;">' . number_format((float)$it['unit_price'], 0, ',', ' ') . '</td>'
      . '<td style="padding:8px 0;text-align:right;">' . number_format((float)$it['total'], 0, ',', ' ') . '</td>'
      . '</tr>';
  }

  $body = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="font-family:Arial,Helvetica,sans-serif;color:#0f172a;background:#f8fafc;margin:0;padding:24px;">'
    . '<div style="max-width:720px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">'
    . '<div style="background:#0ea5a4;color:#fff;padding:18px 24px;display:flex;align-items:center;gap:12px;">'
    . '<img src="' . htmlspecialchars($logoUrl) . '" alt="MARGE" style="height:34px;display:block;">'
    . '<div><strong style="font-size:16px;">Devis ' . htmlspecialchars($devis['reference']) . '</strong><div style="font-size:12px;opacity:.9;">Mobilité & Logistique</div></div>'
    . '</div>'
    . '<div style="padding:24px;">'
    . '<p>Bonjour ' . htmlspecialchars($devis['client_name'] ?: $devis['lead_name']) . ',</p>'
    . '<p>Veuillez trouver votre devis ci-dessous.</p>'
    . '<table width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;font-size:14px;">'
    . '<thead><tr>'
    . '<th style="text-align:left;padding:8px 0;color:#64748b;">Service</th>'
    . '<th style="text-align:center;padding:8px 0;color:#64748b;">Qté</th>'
    . '<th style="text-align:right;padding:8px 0;color:#64748b;">PU</th>'
    . '<th style="text-align:right;padding:8px 0;color:#64748b;">Total</th>'
    . '</tr></thead>'
    . '<tbody>' . $rowsHtml . '</tbody>'
    . '</table>'
    . '<p style="margin-top:12px;text-align:right;font-size:16px;"><strong>Total :</strong> ' . number_format($total, 0, ',', ' ') . ' ' . htmlspecialchars($devis['currency']) . '</p>'
    . '<p style="margin-top:16px;font-size:12px;color:#6b7280;">MARGE - marge-expert.com</p>'
    . '</div></div></body></html>';

  $headers = [];
  if (!empty($config['from_email'])) {
    $fromName = $config['from_name'] ?? 'MARGE';
    $headers[] = 'From: ' . $fromName . ' <' . $config['from_email'] . '>';
  }
  $headers[] = 'MIME-Version: 1.0';
  $headers[] = 'Content-Type: text/html; charset=UTF-8';
  $headersStr = implode("\r\n", $headers);
  if ($to) { @mail($to, $subject, $body, $headersStr); }
  update_status($pdo, $id, 'Envoyé');
  $devis['status'] = 'Envoyé';
  audit_log('send_devis_email', 'devis', $id, ['reference' => $devis['reference']], current_user(), current_role());
}
if ($action === 'whatsapp') {
  update_status($pdo, $id, 'Envoyé');
  audit_log('send_devis_whatsapp', 'devis', $id, ['reference' => $devis['reference']], current_user(), current_role());
  header('Location: ' . $whatsappUrl);
  exit;
}
if ($action === 'print') {
  update_status($pdo, $id, 'Envoyé');
  $devis['status'] = 'Envoyé';
  $printMode = true;
  audit_log('print_devis', 'devis', $id, ['reference' => $devis['reference']], current_user(), current_role());
}

render_header('Devis ' . $devis['reference']);
?>
<?php if ($printMode): ?>
<style>
  .admin-sidebar, .admin-topbar, .admin-breadcrumbs, .admin-footer, .admin-shell nav { display: none !important; }
  .admin-main { padding: 0 !important; background: #fff !important; }
  .admin-card { box-shadow: none !important; border: none !important; }
  .print-only { display: block !important; }
  .no-print { display: none !important; }
  @media print {
    .no-print { display: none !important; }
    body { background: #fff !important; }
    body * { visibility: hidden !important; }
    .print-area, .print-area * { visibility: visible !important; }
    .print-area { position: absolute; left: 0; top: 0; width: 100%; }
  }
</style>
<?php endif; ?>
<div class="admin-card print-area">
  <div class="admin-section-title no-print">
    <h5>Devis <?php echo htmlspecialchars($devis['reference']); ?></h5>
    <div class="d-flex gap-2">
      <?php if (($devis['status'] ?? '') !== 'Envoyé'): ?>
        <a class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-700 text-sm hover:bg-slate-50" href="/dashboard/devis/edit.php?id=<?php echo $id; ?>">Modifier</a>
      <?php endif; ?>
      <?php if (is_admin()): ?>
        <form method="post" action="/dashboard/devis/archive.php">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
          <button class="px-3 py-1.5 rounded-lg border border-rose-200 text-rose-600 text-sm hover:bg-rose-50" type="submit">Archiver</button>
        </form>
      <?php endif; ?>
      <a class="px-3 py-1.5 rounded-lg border border-teal-200 text-teal-700 text-sm hover:bg-teal-50" href="/dashboard/devis/view.php?id=<?php echo $id; ?>&action=send">Envoyer par email</a>
      <a class="px-3 py-1.5 rounded-lg border border-teal-200 text-teal-700 text-sm hover:bg-teal-50" href="/dashboard/devis/view.php?id=<?php echo $id; ?>&action=print" target="_blank">Imprimer</a>
      <a class="px-3 py-1.5 rounded-lg border border-teal-200 text-teal-700 text-sm hover:bg-teal-50" href="/dashboard/devis/view.php?id=<?php echo $id; ?>&action=whatsapp" target="_blank">WhatsApp</a>
    </div>
  </div>

  <div class="p-4 border border-slate-200 rounded-xl bg-white">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <img src="/img/logo-marge.png" alt="MARGE" style="height:84px;">
        <div class="text-sm text-slate-500">MARGE - Mobilité & Logistique</div>
      </div>
      <div class="text-end">
        <div class="text-sm text-slate-500">Référence</div>
        <div class="fw-bold"><?php echo htmlspecialchars($devis['reference']); ?></div>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <div class="text-sm text-slate-500">Client</div>
        <div class="fw-semibold"><?php echo htmlspecialchars($devis['client_name'] ?: $devis['lead_name']); ?></div>
        <div class="text-sm"><?php echo htmlspecialchars($devis['client_company']); ?></div>
        <div class="text-sm"><?php echo htmlspecialchars($devis['client_address']); ?></div>
        <div class="text-sm"><?php echo htmlspecialchars($devis['client_city']); ?> <?php echo htmlspecialchars($devis['client_country']); ?></div>
        <div class="text-sm"><?php echo htmlspecialchars($devis['client_email'] ?: $devis['lead_email']); ?></div>
        <div class="text-sm"><?php echo htmlspecialchars($devis['client_phone'] ?: $devis['lead_phone']); ?></div>
      </div>
      <div class="col-md-6 text-md-end">
        <div class="text-sm text-slate-500">Statut</div>
        <div class="fw-semibold"><?php echo htmlspecialchars($devis['status']); ?></div>
        <div class="text-sm text-slate-500">Date</div>
        <div><?php echo htmlspecialchars($devis['created_at']); ?></div>
      </div>
    </div>

    <div class="mb-3">
      <div class="text-sm text-slate-500">Services</div>
      <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="max-w-full overflow-x-auto">
          <table class="table admin-table min-w-[700px]">
            <thead>
              <tr>
                <th class="px-4 py-3 text-start text-slate-500 text-sm">Service</th>
                <th class="px-4 py-3 text-start text-slate-500 text-sm">Qté</th>
                <th class="px-4 py-3 text-start text-slate-500 text-sm">Prix unitaire</th>
                <th class="px-4 py-3 text-start text-slate-500 text-sm">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($items as $it): ?>
                <tr>
                  <td class="px-4 py-3"><?php echo htmlspecialchars($it['label']); ?></td>
                  <td class="px-4 py-3"><?php echo htmlspecialchars((string)$it['quantity']); ?></td>
                  <td class="px-4 py-3"><?php echo number_format((float)$it['unit_price'], 0, ',', ' '); ?> <?php echo htmlspecialchars($devis['currency']); ?></td>
                  <td class="px-4 py-3"><?php echo number_format((float)$it['total'], 0, ',', ' '); ?> <?php echo htmlspecialchars($devis['currency']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <?php if (!empty($devis['notes'])): ?>
      <div class="mb-3">
        <div class="text-sm text-slate-500">Notes internes</div>
        <div class="text-sm text-slate-600"><?php echo nl2br(htmlspecialchars($devis['notes'])); ?></div>
      </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center border-top pt-3">
      <div class="text-sm text-slate-500">Total</div>
      <div class="h4 mb-0"><?php echo number_format($total, 0, ',', ' '); ?> <?php echo htmlspecialchars($devis['currency']); ?></div>
    </div>
  </div>
</div>
<?php if ($printMode): ?>
<script>
  document.title = "Devis-<?php echo htmlspecialchars($devis['reference']); ?>";
  window.print();
</script>
<?php endif; ?>
<?php render_footer(); ?>
