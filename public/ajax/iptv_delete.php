<?php
// /public/ajax/iptv_delete.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';

try {
  // Lee JSON o POST form
  $raw = file_get_contents('php://input');
  $in = json_decode($raw, true);
  if (!is_array($in)) $in = $_POST;

  $id   = (int)($in['id'] ?? 0);
  $tipo = (string)($in['tipo'] ?? 'cuenta');
  $tipo = ($tipo === 'perfil') ? 'perfil' : 'cuenta';

  if ($id <= 0) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'ID inválido']); exit; }

  $pdo = get_pdo();
  $table = ($tipo === 'perfil') ? 'iptv_perfiles' : 'iptv_cuentas';
  $st = $pdo->prepare("DELETE FROM {$table} WHERE id=?");
  $st->execute([$id]);

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'No se pudo borrar: '.$e->getMessage()]);
}
