<?php
declare(strict_types=1);

require_once __DIR__ . '/../_inc/auth.php';
require_login();
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_inc/layout.php';
if (!can_edit_devis()) {
  http_response_code(403);
  exit('Acces refuse');
}

$pdo = db();

$leads = $pdo->query('SELECT id, name, email, phone, service, subject FROM leads ORDER BY id DESC LIMIT 300')->fetchAll();
$prefillLeadId = (int)($_GET['lead_id'] ?? 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $leadId = (int)($_POST['lead_id'] ?? 0);
  $reference = trim((string)($_POST['reference'] ?? ''));
  $currency = trim((string)($_POST['currency'] ?? 'XOF'));
  $notes = trim((string)($_POST['notes'] ?? ''));

  $clientName = trim((string)($_POST['client_name'] ?? ''));
  $clientEmail = trim((string)($_POST['client_email'] ?? ''));
  $clientPhone = trim((string)($_POST['client_phone'] ?? ''));
  $clientCompany = trim((string)($_POST['client_company'] ?? ''));
  $clientAddress = trim((string)($_POST['client_address'] ?? ''));
  $clientCity = trim((string)($_POST['client_city'] ?? ''));
  $clientCountry = trim((string)($_POST['client_country'] ?? ''));

  $labels = $_POST['item_label'] ?? [];
  $qtys = $_POST['item_qty'] ?? [];
  $prices = $_POST['item_price'] ?? [];

  $items = [];
  $total = 0.0;
  $count = max(count($labels), count($qtys), count($prices));
  for ($i = 0; $i < $count; $i++) {
    $label = trim((string)($labels[$i] ?? ''));
    $qty = (float)($qtys[$i] ?? 0);
    $price = (float)($prices[$i] ?? 0);
    if ($label === '' || $qty <= 0) {
      continue;
    }
    $lineTotal = $qty * $price;
    $items[] = [
      'label' => $label,
      'qty' => $qty,
      'price' => $price,
      'total' => $lineTotal
    ];
    $total += $lineTotal;
  }

  if (!$leadId || $reference === '' || !$items) {
    $error = 'Veuillez renseigner un lead, une reference et au moins une ligne de service.';
  } else {
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare('INSERT INTO devis (lead_id, reference, amount, currency, status, description, client_name, client_email, client_phone, client_company, client_address, client_city, client_country, notes, created_at, updated_at) VALUES (:lead_id, :reference, :amount, :currency, :status, :description, :client_name, :client_email, :client_phone, :client_company, :client_address, :client_city, :client_country, :notes, :created_at, :updated_at)');
    $stmt->execute([
      ':lead_id' => $leadId,
      ':reference' => $reference,
      ':amount' => $total,
      ':currency' => $currency,
      ':status' => 'Brouillon',
      ':description' => '',
      ':client_name' => $clientName,
      ':client_email' => $clientEmail,
      ':client_phone' => $clientPhone,
      ':client_company' => $clientCompany,
      ':client_address' => $clientAddress,
      ':client_city' => $clientCity,
      ':client_country' => $clientCountry,
      ':notes' => $notes,
      ':created_at' => $now,
      ':updated_at' => $now,
    ]);

    $newId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare('INSERT INTO devis_items (devis_id, label, quantity, unit_price, total, created_at) VALUES (:devis_id, :label, :quantity, :unit_price, :total, :created_at)');
    foreach ($items as $it) {
      $itemStmt->execute([
        ':devis_id' => $newId,
        ':label' => $it['label'],
        ':quantity' => $it['qty'],
        ':unit_price' => $it['price'],
        ':total' => $it['total'],
        ':created_at' => $now,
      ]);
    }

    $pdo->prepare('UPDATE leads SET status = :status, updated_at = :u WHERE id = :id AND status = "Nouveau"')
        ->execute([':status' => 'En cours de traitement', ':u' => $now, ':id' => $leadId]);

    $newId = (int)$pdo->lastInsertId();
    audit_log('create_devis', 'devis', $newId, ['reference' => $reference, 'amount' => $total], current_user(), current_role());

    header('Location: /dashboard/devis/view.php?id=' . $newId);
    exit;
  }
}

render_header('Creer un devis');
?>
<div class="admin-card">
  <div class="admin-section-title">
    <h5>Nouveau devis</h5>
  </div>
  <style>
    input[type="number"]::-webkit-outer-spin-button,
    input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    input[type="number"] { -moz-appearance: textfield; }
  </style>
  <?php if ($error): ?>
    <div class="p-3 mb-4 rounded-lg bg-red-50 text-red-700 text-sm border border-red-100"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <form method="post" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-3">
      <div class="p-4 rounded-xl border border-slate-200 bg-white">
        <h6 class="text-sm font-semibold text-slate-600 mb-3">Informations generales</h6>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium text-slate-600">Lead associe</label>
            <select name="lead_id" class="ta-input w-full" required>
              <option value="">Choisir un lead</option>
              <?php foreach ($leads as $l): ?>
                <option value="<?php echo (int)$l['id']; ?>" <?php echo $prefillLeadId === (int)$l['id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($l['name']); ?> - <?php echo htmlspecialchars($l['email']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="text-sm font-medium text-slate-600">Reference</label>
            <input name="reference" class="ta-input w-full" required placeholder="DEV-2026-001">
          </div>
          <div>
            <label class="text-sm font-medium text-slate-600">Devise</label>
            <input name="currency" class="ta-input w-full" value="XOF">
          </div>
          <div>
            <label class="text-sm font-medium text-slate-600">Total</label>
            <input id="totalAmount" class="ta-input w-full" value="0" readonly>
          </div>
        </div>
      </div>
    </div>

    <div class="lg:col-span-2">
      <div class="p-4 rounded-xl border border-slate-200 bg-white">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="text-sm font-semibold text-slate-600">Lignes de service</h6>
          <button type="button" class="px-3 py-1.5 rounded-lg border border-teal-200 text-teal-700 text-sm" id="addRow">Ajouter une ligne</button>
        </div>
        <div class="table-responsive">
          <table class="table admin-table min-w-[700px]" id="itemsTable">
            <thead>
              <tr>
                <th class="text-start text-slate-500 text-sm">Service</th>
                <th class="text-start text-slate-500 text-sm" style="width:120px;">Quantite</th>
                <th class="text-start text-slate-500 text-sm" style="width:160px;">Prix unitaire</th>
                <th class="text-start text-slate-500 text-sm" style="width:160px;">Total</th>
                <th style="width:60px;"></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><input name="item_label[]" class="ta-input w-full" placeholder="Ex: Transport & logistique"></td>
                <td><input name="item_qty[]" type="number" step="0.01" class="ta-input w-full item-qty" value="1"></td>
                <td><input name="item_price[]" type="number" step="0.01" class="ta-input w-full item-price" value="0"></td>
                <td><input class="ta-input w-full item-total" value="0" readonly></td>
                <td><button type="button" class="text-slate-400 hover:text-red-500 remove-row">Supprimer</button></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div>
      <div class="p-4 rounded-xl border border-slate-200 bg-white">
        <h6 class="text-sm font-semibold text-slate-600 mb-3">Informations client</h6>
        <div class="space-y-3">
          <div>
            <label class="text-sm font-medium text-slate-600">Nom</label>
            <input name="client_name" class="ta-input w-full">
          </div>
          <div>
            <label class="text-sm font-medium text-slate-600">Email</label>
            <input name="client_email" class="ta-input w-full">
          </div>
          <div>
            <label class="text-sm font-medium text-slate-600">Telephone</label>
            <input name="client_phone" class="ta-input w-full">
          </div>
          <div>
            <label class="text-sm font-medium text-slate-600">Entreprise</label>
            <input name="client_company" class="ta-input w-full">
          </div>
          <div>
            <label class="text-sm font-medium text-slate-600">Adresse</label>
            <input name="client_address" class="ta-input w-full">
          </div>
          <div>
            <label class="text-sm font-medium text-slate-600">Ville</label>
            <input name="client_city" class="ta-input w-full">
          </div>
          <div>
            <label class="text-sm font-medium text-slate-600">Pays</label>
            <input name="client_country" class="ta-input w-full">
          </div>
        </div>
      </div>
    </div>

    <div class="lg:col-span-3">
      <div class="p-4 rounded-xl border border-slate-200 bg-white">
        <h6 class="text-sm font-semibold text-slate-600 mb-3">Notes internes</h6>
        <textarea name="notes" class="ta-input w-full" rows="3" placeholder="Ex: conditions particulieres, delais, options..."></textarea>
      </div>
    </div>

    <div class="lg:col-span-3 flex gap-2">
      <button class="px-4 py-2.5 rounded-xl bg-teal-600 text-white font-semibold" type="submit">Creer le devis</button>
      <a class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700" href="/dashboard/devis/list.php">Annuler</a>
    </div>
  </form>
</div>
<script>
  const leads = <?php echo json_encode($leads, JSON_UNESCAPED_UNICODE); ?>;
  const leadSelect = document.querySelector('select[name="lead_id"]');
  const refInput = document.querySelector('input[name="reference"]');
  const nameInput = document.querySelector('input[name="client_name"]');
  const emailInput = document.querySelector('input[name="client_email"]');
  const phoneInput = document.querySelector('input[name="client_phone"]');
  let refManuallyEdited = false;
  if (refInput) {
    refInput.addEventListener('input', () => { refManuallyEdited = true; });
  }

  function pad(n) { return n.toString().padStart(3, '0'); }
  function setRefAuto(id) {
    if (!refInput || refManuallyEdited) return;
    if (!refInput.value || /^DEV-\d{4}-\d{3}$/.test(refInput.value)) {
      const year = new Date().getFullYear();
      refInput.value = `DEV-${year}-${pad(id)}`;
    }
  }
  function fillFromLead() {
    const id = parseInt(leadSelect.value || '0', 10);
    const lead = leads.find(l => parseInt(l.id, 10) === id);
    if (!lead) return;
    if (!nameInput.value) nameInput.value = lead.name || '';
    if (!emailInput.value) emailInput.value = lead.email || '';
    if (!phoneInput.value) phoneInput.value = lead.phone || '';
    setRefAuto(id);
  }
  leadSelect.addEventListener('change', fillFromLead);
  if (leadSelect.value) fillFromLead();

  const tableBody = document.querySelector('#itemsTable tbody');
  const totalInput = document.getElementById('totalAmount');

  function recalc() {
    let total = 0;
    tableBody.querySelectorAll('tr').forEach(row => {
      const qty = parseFloat(row.querySelector('.item-qty')?.value || '0');
      const price = parseFloat(row.querySelector('.item-price')?.value || '0');
      const line = qty * price;
      const totalField = row.querySelector('.item-total');
      if (totalField) totalField.value = isNaN(line) ? '0' : line.toFixed(2);
      total += isNaN(line) ? 0 : line;
    });
    totalInput.value = total.toFixed(2);
  }

  function addRow() {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td><input name="item_label[]" class="ta-input w-full" placeholder="Ex: Transport & logistique"></td>
      <td><input name="item_qty[]" type="number" step="0.01" class="ta-input w-full item-qty" value="1"></td>
      <td><input name="item_price[]" type="number" step="0.01" class="ta-input w-full item-price" value="0"></td>
      <td><input class="ta-input w-full item-total" value="0" readonly></td>
      <td><button type="button" class="text-slate-400 hover:text-red-500 remove-row">Supprimer</button></td>
    `;
    tableBody.appendChild(row);
  }

  document.getElementById('addRow').addEventListener('click', () => {
    addRow();
    recalc();
  });

  tableBody.addEventListener('input', (e) => {
    if (e.target.classList.contains('item-qty') || e.target.classList.contains('item-price')) {
      recalc();
    }
  });

  tableBody.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-row')) {
      const row = e.target.closest('tr');
      if (row) row.remove();
      recalc();
    }
  });

  recalc();
</script>
<?php render_footer(); ?>
