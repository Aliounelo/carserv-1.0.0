<?php
declare(strict_types=1);

require __DIR__ . '/api/db.php';

$pdo = db();

$names = [
  'Awa Ndiaye','Mamadou Diop','Fatou Sow','Cheikh Ba','Mariama Fall','Ibrahima Seck',
  'Khady Diallo','Ousmane Faye','Adama Kane','Aissatou Ba','Boubacar Sy','Ngor Mbaye',
  'Sokhna Niang','Pape Sarr','Mame Diarra', 'Samba Ka'
];
$services = [
  'Transport & Logistique','Vente & Location','Services Techniques','Pièces certifiées','Maintenance flotte'
];
$statuses = ['Nouveau','En cours de traitement','Contacté','Devis envoyé','Converti','Abandonné'];
$priorities = ['Basse','Normal','Haute','Urgent'];
$types = ['contact','booking'];

$rows = 120;
for ($i=0; $i<$rows; $i++) {
  $type = $types[array_rand($types)];
  $name = $names[array_rand($names)];
  $email = strtolower(str_replace(' ', '.', $name)) . '+' . rand(10,99) . '@example.com';
  $phone = '+2217' . rand(0,9) . rand(0,9) . rand(0,9) . rand(0,9) . rand(0,9) . rand(0,9) . rand(0,9);
  $service = $services[array_rand($services)];
  $subject = $type === 'contact' ? 'Demande d\'information' : null;
  $message = $type === 'contact' ? 'Message de test pour le dashboard.' : null;
  $details = $type === 'booking' ? 'Besoin d\'une solution rapide. Ceci est un test.' : null;
  $status = $statuses[array_rand($statuses)];
  $priority = $priorities[array_rand($priorities)];
  $daysAgo = rand(0, 45);
  $created = date('Y-m-d H:i:s', strtotime("-$daysAgo days"));

  $stmt = $pdo->prepare('INSERT INTO leads (type, name, email, phone, service, requested_date, subject, message, details, status, priority, notes, source, created_at, updated_at)
    VALUES (:type, :name, :email, :phone, :service, :requested_date, :subject, :message, :details, :status, :priority, :notes, :source, :created_at, :updated_at)');

  $stmt->execute([
    ':type' => $type,
    ':name' => $name,
    ':email' => $email,
    ':phone' => $phone,
    ':service' => $service,
    ':requested_date' => $type === 'booking' ? date('Y-m-d', strtotime('+'.rand(1,30).' days')) : null,
    ':subject' => $subject,
    ':message' => $message,
    ':details' => $details,
    ':status' => $status,
    ':priority' => $priority,
    ':notes' => null,
    ':source' => 'seed',
    ':created_at' => $created,
    ':updated_at' => $created,
  ]);
}

echo "Seeded $rows leads\n";
