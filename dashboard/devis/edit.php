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
$id = (int)($_GET['id'] ?? 0);

$devisStmt = $pdo->prepare('SELECT * FROM devis WHERE id = :id');
$devisStmt->execute([':id' => $id]);
$devis = $devisStmt->fetch();
if (!$devis) {
  header('Location: /dashboard/devis/list.php');
  exit;
}

$itemStmt = $pdo->prepare('SELECT * FROM devis_items WHERE devis_id = :id ORDER BY id ASC');
$itemStmt->execute([':id' => $id]);
$items = $itemStmt->fetchAll();

$locked = ($devis['status'] ?? '') === "Envoy\u{00E9}";
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($locked) {
    $error = 'Ce devis est deja envoye et ne peut plus etre modifie.';
  } else {
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

    $newItems = [];
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
      $newItems[] = [
        'label' => $label,
        'qty' => $qty,
        'price' => $price,
        'total' => $lineTotal
      ];
      $total += $lineTotal;
    }

    if ($reference === '' || !$newItems) {
      $error = 'Veuillez renseigner une reference et au moins une ligne de service.';
    } else {
      $now = date('Y-m-d H:i:s');
      $upd = $pdo->prepare('UPDATE devis SET reference = :reference, amount = :amount, currency = :currency, description = :description, client_name = :client_name, client_email = :client_email, client_phone = :client_phone, client_company = :client_company, client_address = :client_address, client_city = :client_city, client_country = :client_country, notes = :notes, updated_at = :updated_at WHERE id = :id');
      $upd->execute([
        ':reference' => $reference,
        ':amount' => $total,
        ':currency' => $currency,
        ':description' => '',
        ':client_name' => $clientName,
        ':client_email' => $clientEmail,
        ':client_phone' => $clientPhone,
        ':client_company' => $clientCompany,
        ':client_address' => $clientAddress,
        ':client_city' => $clientCity,
        ':client_country' => $clientCountry,
        ':notes' => $notes,
        ':updated_at' => $now,
        ':id' => $id,
      ]);

      $pdo->prepare('DELETE FROM devis_items WHERE devis_id = :id')->execute([':id' => $id]);
      $itemIns = $pdo->prepare('INSERT INTO devis_items (devis_id, label, quantity, unit_price, total, created_at) VALUES (:devis_id, :label, :quantity, :unit_price, :total, :created_at)');
      foreach ($newItems as $it) {
        $itemIns->execute([
          ':devis_id' => $id,
          ':label' => $it['label'],
          ':quantity' => $it['qty'],
          ':unit_price' => $it['price'],
          ':total' => $it['total'],
          ':created_at' => $now,
        ]);
      }

      audit_log('update_devis', 'devis', $id, ['reference' => $reference, 'amount' => $total], current_user(), current_role());
      header('Location: /dashboard/devis/view.php?id=' . $id);
      exit;
    }
  }
}

render_header('Modifier devis');
?>
<div class="admin-card">
  <div class="admin-section-title">
    <h5>Modifier devis</h5>
  </div>
  <style>
    input[type="number"]::-webkit-outer-spin-button,
    input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    input[type="number"] { -moz-appearance: textfield; }
  </style>
  <?php if ($error): ?>
    <div class="p-3 mb-4 rounded-lg bg-red-50 text-red-700 text-sm border border-red-100"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if ($locked): ?>
    <div class="p-3 mb-4 rounded-lg bg-amber-50 text-amber-800 text-sm border border-amber-100">Ce devis est verrouille car il est deja envoye.</div>
  <?php endif; ?>
  <form method="post" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-3">
      <div class="p-4 rounded-xl border border-slate-200 bg-white">
        <h6 class="text-sm font-semibold text-slate-600 mb-3">Informations generales</h6>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium text-slate-600">Reference</label>
            <input name="reference" class="ta-input w-full" required value="<?php echo htmlspecialchars($devis['reference']); ?>" <?php echo $locked ? 'readonly' : ''; ?> >
          </div>
          <div>
            <label class="text-sm font-medium text-slate-600">Devise</label>
            <input name="currency" class="ta-input w-full" value="<?php echo htmlspecialchars($devis['currency']); ?>" <?php echo $locked ? 'readonly' : ''; ?> >
          </div>
          <div>
            <label class="text-sm font-medium text-slate-600">Total</label>
            <input id="totalAmount" class="ta-input w-full" value="<?php echo htmlspecialchars((string)$devis['amount']); ?>" readonly>
          </div>
        </div>
      </div>
    </div>

    <div class="lg:col-span-2">
      <div class="p-4 rounded-xl border border-slate-200 bg-white">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="text-sm font-semibold text-slate-600">Lignes de service</h6>
          <button type="button" class="px-3 py-1.5 rounded-lg border border-teal-200 text-teal-700 text-sm" id="addRow" <?php echo $locked ? 'disabled' : ''; ?>>Ajouter une ligne</button>
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
              <?php if ($items): ?>
                <?php foreach ($items as $it): ?>
                  <tr>
                    <td><input name="item_label[]" class="ta-input w-full" value="<?php echo htmlspecialchars($it['label']); ?>" <?php echo $locked ? 'readonly' : ''; ?>></td>
                    <td><input name="item_qty[]" type="number" step="0.01" class="ta-input w-full item-qty" value="<?php echo htmlspecialchars((string)$it['quantity']); ?>" <?php echo $locked ? 'readonly' : ''; ?>></td>
                    <td><input name="item_price[]" type="number" step="0.01" class="ta-input w-full item-price" value="<?php echo htmlspecialchars((string)$it['unit_price']); ?>" <?php echo $locked ? 'readonly' : ''; ?>></td>
                    <td><input class="ta-input w-full item-total" value="<?php echo htmlspecialchars((string)$it['total']); ?>" readonly></td>
                    <td><button type="button" class="text-slate-400 hover:text-red-500 remove-row" <?php echo $locked ? 'disabled' : ''; ?>>Supprimer</button></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td><input name="item_label[]" class="ta-input w-full" placeholder="Ex: Transport & logistique"></td>
                  <td><input name="item_qty[]" type="number" step="0.01" class="ta-input w-full item-qty" value="1"></td>
                  <td><input name="item_price[]" type="number" step="0.01" class="ta-input w-full item-price" value="0"></td>
                  <td><input class="ta-input w-full item-total" value="0" readonly></td>
                  <td><button type="button" class="text-slate-400 hover:text-red-500 remove-row">Supprimer</button></td>
                </tr>
              <?php endif; ?>
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
            <input name="client_name" class="ta-input w-full" value="<?php echo htmlspecialchars($devis['client_name'] ?? ''); ?>" <?php echo $locked ? 'readonly' : ''; ?> >
          </div>
          <div>
            <label class="text-sm font-medium text-slate-600">Email</label>
            <input name="client_email" class="ta-input w-full" value="<?php echo htmlspecialchars($devis['client_email'] ?? ''); ?>" <?php echo $locked ? 'readonly' : ''; ?> >
          </div>
          <div>
            <label class="text-sm font-medium text-slate-600">Telephone</label>
            <input name="client_phone" class="ta-input w-full" value="<?php echo htmlspecialchars($devis['client_phone'] ?? ''); ?>" <?php echo $locked ? 'readonly' : ''; ?> >
          </div>
          <div>
            <label class="text-sm font-medium text-slate-600">Entreprise</label>
            <input name="client_company" class="ta-input w-full" value="<?php echo htmlspecialchars($devis['client_company'] ?? ''); ?>" <?php echo $locked ? 'readonly' : ''; ?> >
          </div>
          <div>
            <label class="text-sm font-medium text-slate-600">Adresse</label>
            <input name="client_address" class="ta-input w-full" value="<?php echo htmlspecialchars($devis['client_address'] ?? ''); ?>" <?php echo $locked ? 'readonly' : ''; ?> >
          </div>
          <div>
            <label class="text-sm font-medium text-slate-600">Ville</label>
            <input name="client_city" class="ta-input w-full" value="<?php echo htmlspecialchars($devis['client_city'] ?? ''); ?>" <?php echo $locked ? 'readonly' : ''; ?> >
          </div>
          <div>
            <label class="text-sm font-medium text-slate-600">Pays</label>
            <input name="client_country" class="ta-input w-full" value="<?php echo htmlspecialchars($devis['client_country'] ?? ''); ?>" <?php echo $locked ? 'readonly' : ''; ?> >
          </div>
        </div>
      </div>
    </div>

    <div class="lg:col-span-3">
      <div class="p-4 rounded-xl border border-slate-200 bg-white">
        <h6 class="text-sm font-semibold text-slate-600 mb-3">Notes internes</h6>
        <textarea name="notes" class="ta-input w-full" rows="3" <?php echo $locked ? 'readonly' : ''; ?>><?php echo htmlspecialchars($devis['notes'] ?? ''); ?></textarea>
      </div>
    </div>

    <div class="lg:col-span-3 flex gap-2">
      <button class="px-4 py-2.5 rounded-xl bg-teal-600 text-white font-semibold" type="submit" <?php echo $locked ? 'disabled' : ''; ?>>Enregistrer</button>
      <a class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700" href="/dashboard/devis/view.php?id=<?php echo (int)$id; ?>">Annuler</a>
    </div>
  </form>
</div>
<script>
  const locked = <?php echo $locked ? 'true' : 'false'; ?>;
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
    if (locked) return;
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

  const addBtn = document.getElementById('addRow');
  if (addBtn) {
    addBtn.addEventListener('click', () => {
      addRow();
      recalc();
    });
  }

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
