<?php
require_once __DIR__ . '/../_inc/auth.php';
require_login();
require_admin();
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../_inc/layout.php';

$cfg = require __DIR__ . '/../../api/config.php';

render_header('Configuration');
?>
<div class="admin-card">
  <h5>Base de donn&eacute;es</h5>
  <p><strong>Driver :</strong> <?php echo htmlspecialchars($cfg['db_driver'] ?? 'sqlite'); ?></p>
  <p><strong>SQLite :</strong> <?php echo htmlspecialchars($cfg['db_sqlite_path'] ?? ''); ?></p>
  <p><strong>MySQL host :</strong> <?php echo htmlspecialchars($cfg['db_host'] ?? ''); ?></p>
  <p><strong>MySQL DB :</strong> <?php echo htmlspecialchars($cfg['db_name'] ?? ''); ?></p>
  <p><strong>MySQL user :</strong> <?php echo htmlspecialchars($cfg['db_user'] ?? ''); ?></p>
  <p><strong>MySQL pass :</strong> <?php echo !empty($cfg['db_pass']) ? '********' : '(vide)'; ?></p>
  <p class="admin-note">Pour passer sur MySQL, change <code>db_driver</code> et compl&egrave;te les champs dans <code>api/config.php</code>.</p>
</div>
<?php render_footer(); ?>
