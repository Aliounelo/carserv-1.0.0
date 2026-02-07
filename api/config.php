<?php
// /api/config.php
return [
  // Destinataires
  'to_contact' => 'contact@marge-expert.com',
  'to_booking' => 'booking@marge-expert.com',

  // Exp?diteur
  'from_email' => 'contact@marge-expert.com',
  'from_name'  => 'MARGE',
  'from_booking_email' => 'booking@marge-expert.com',
  'from_booking_name'  => 'MARGE',

  // SMTP o2switch
  'smtp_host' => 'mail.marge-expert.com',
  'smtp_port' => 587,
  'smtp_secure' => 'tls',
  'smtp_user' => 'contact@marge-expert.com',
  'smtp_pass' => 'MargeContact@2025',

  // Anti-spam
  'max_per_15min' => 10,

  // DB (SQLite by default)
  'db_driver' => 'sqlite',
  'db_sqlite_path' => __DIR__ . '/data/marge.sqlite',
  'db_host' => 'localhost',
  'db_name' => 'marge',
  'db_user' => 'marge_user',
  'db_pass' => '',
  'db_charset' => 'utf8mb4'
];
