<?php
function client_ip(): string {
  foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
    if (!empty($_SERVER[$k])) return trim(explode(',', $_SERVER[$k])[0]);
  }
  return '0.0.0.0';
}

function rate_limit_or_die(int $max, int $windowSeconds = 900): void {
  $ip  = client_ip();
  $dir = sys_get_temp_dir() . '/marge_rate/';
  if (!is_dir($dir)) @mkdir($dir, 0700, true);
  $file = $dir . md5($ip) . '.json';

  $now = time();
  $data = ['start' => $now, 'count' => 0];

  if (file_exists($file)) {
    $json = json_decode(file_get_contents($file), true);
    if (is_array($json) && isset($json['start'],$json['count'])) $data = $json;
  }

  if (($now - (int)$data['start']) > $windowSeconds) {
    $data = ['start' => $now, 'count' => 0];
  }

  $data['count'] = (int)$data['count'] + 1;
  file_put_contents($file, json_encode($data));

  if ($data['count'] > $max) {
    http_response_code(429);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'Trop de tentatives. Réessayez plus tard.']);
    exit;
  }
}
