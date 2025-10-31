<?php
// /public/ajax/iptv_color.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';

try {
  $raw = file_get_contents('php://input');
  $in  = json_decode($raw ?: '[]', true);
  if (!is_array($in)) throw new Exception('JSON inválido');

  $id    = isset($in['id']) ? (int)$in['id'] : 0;
  $tipo  = ($in['tipo'] ?? '') === 'perfil' ? 'perfil' : 'cuenta';
  $color = trim(strtolower((string)($in['color'] ?? '')));

  $allowed = ['','rojo','azul','verde','blanco'];
  if ($id <= 0 || !in_array($color, $allowed, true)) throw new Exception('Parámetros inválidos');

  $table = ($tipo === 'perfil') ? 'iptv_perfiles' : 'iptv_cuentas';

  $pdo = get_pdo();
  $stmt = $pdo->prepare("UPDATE {$table} SET color = :color WHERE id = :id");
  $ok = $stmt->execute([':color' => $color, ':id' => $id]);

  echo json_encode(['ok' => (bool)$ok]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
