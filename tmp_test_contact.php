<?php
$_POST = [
  'name' => 'Test Contact',
  'email' => 'test.contact@example.com',
  'phone' => '+221700000001',
  'subject' => 'Test Contact',
  'message' => 'Message de test contact.',
  'website' => ''
];
include __DIR__ . '/api/send.php';
