<?php

require_once __DIR__ . '/../_inc/auth.php';
require_login();
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_inc/layout.php';

$pdo = db();
$since = date('Y-m-d 00:00:00', strtotime('-30 days'));

$stmt = $pdo->prepare('SELECT status, COUNT(*) AS c FROM leads WHERE created_at >= :since GROUP BY status');
$stmt->execute([':since' => $since]);
$byStatus = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT service, COUNT(*) AS c FROM leads WHERE created_at >= :since GROUP BY service ORDER BY c DESC');
$stmt->execute([':since' => $since]);
$byService = $stmt->fetchAll();

$statusLabels = [];
$statusValues = [];
foreach ($byStatus as $row) {
  $statusLabels[] = $row['status'];
  $statusValues[] = (int)$row['c'];
}

$serviceLabels = [];
$serviceValues = [];
foreach ($byService as $row) {
  $serviceLabels[] = $row['service'] ?? 'Autre';
  $serviceValues[] = (int)$row['c'];
}

render_header('Statistiques');
?>
<div class="grid grid-cols-12 gap-4 md:gap-6">
  <div class="col-span-12 xl:col-span-6">
    <div class="admin-card">
      <div class="admin-section-title">
        <h5>Leads par statut (30 jours)</h5>
      </div>
      <div class="h-64">
        <canvas id="statusChart"></canvas>
      </div>
    </div>
  </div>
  <div class="col-span-12 xl:col-span-6">
    <div class="admin-card">
      <div class="admin-section-title">
        <h5>Leads par service (30 jours)</h5>
      </div>
      <div class="h-64">
        <canvas id="serviceChart"></canvas>
      </div>
    </div>
  </div>
</div>
<?php render_footer(); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
<script>
  const statusLabels = <?php echo json_encode($statusLabels, JSON_UNESCAPED_UNICODE); ?>;
  const statusValues = <?php echo json_encode($statusValues, JSON_UNESCAPED_UNICODE); ?>;
  const serviceLabels = <?php echo json_encode($serviceLabels, JSON_UNESCAPED_UNICODE); ?>;
  const serviceValues = <?php echo json_encode($serviceValues, JSON_UNESCAPED_UNICODE); ?>;

  new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
      labels: statusLabels,
      datasets: [{
        data: statusValues,
        backgroundColor: ['#fde68a','#bfdbfe','#bbf7d0','#fed7aa','#86efac','#fecaca']
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } }
    }
  });

  new Chart(document.getElementById('serviceChart'), {
    type: 'bar',
    data: {
      labels: serviceLabels,
      datasets: [{
        label: 'Leads',
        data: serviceValues,
        backgroundColor: '#0ea5a4'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });
</script>
