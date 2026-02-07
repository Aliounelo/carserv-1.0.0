<?php
$_POST = [
  'name' => 'Test Booking',
  'email' => 'test.booking@example.com',
  'phone' => '+221700000002',
  'service' => 'Transport & Logistique',
  'date' => '2026-02-10',
  'details' => 'Demande de test booking.',
  'submission_id' => 'test-sub-001',
  'website' => ''
];
include __DIR__ . '/api/send-booking.php';
