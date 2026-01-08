<?php
// /api/config.php
return [
  // Destinataires
  'to_contact' => 'contact@marge-expert.com',
  'to_booking' => 'booking@marge-expert.com', // adresse dédiée pour les demandes de réservation

  // Expéditeur (doit être du domaine)
  'from_email' => 'contact@marge-expert.com',
  'from_name'  => 'MARGE',
  // Expéditeur spécifique booking (optionnel)
  'from_booking_email' => 'booking@marge-expert.com',
  'from_booking_name'  => 'MARGE',

  // SMTP o2switch
  'smtp_host' => 'mail.marge-expert.com',
  'smtp_port' => 587,
  'smtp_secure' => 'tls', // tls
  'smtp_user' => 'contact@marge-expert.com',
  'smtp_pass' => 'MargeContact@2025',

  // Anti-spam
  'max_per_15min' => 10
];
