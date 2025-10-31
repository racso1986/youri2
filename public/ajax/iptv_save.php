<?php
// /public/ajax/iptv_save.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$nonce = isset($input['nonce']) ? trim((string)$input['nonce']) : '';
if ($nonce !== '') {
  $_SESSION['iptv_seen'] = $_SESSION['iptv_seen'] ?? [];
  if (isset($_SESSION['iptv_seen'][$nonce])) {
    echo json_encode(['ok'=>true,'duplicate'=>1]); exit;
  }
  $_SESSION['iptv_seen'][$nonce] = time();
}



// ---- Helpers mínimos ----
function iptv_table_for(string $tipo): string {
  if (defined('IPTV_SPLIT_TABLES') && IPTV_SPLIT_TABLES) {
    return ($tipo === 'perfil') ? 'iptv_perfiles' : 'iptv_cuentas';
  }
  return 'iptv'; // legacy
}
function read_input(): array {
  // 1) JSON
  $raw = file_get_contents('php://input');
  $json = json_decode($raw ?? '', true);
  if (is_array($json) && !empty($json)) return $json;

  // 2) x-www-form-urlencoded / multipart
  if (!empty($_POST)) return $_POST;

  // 3) Nada útil
  return [];
}
function norm_str($v): string {
  return trim((string)$v);
}
function build_whatsapp(?string $wa_cc, ?string $wa_local, ?string $whatsappDirect = null): string {
  // si viene ya 'whatsapp', úsalo
  $whatsappDirect = norm_str($whatsappDirect ?? '');
  if ($whatsappDirect !== '') return $whatsappDirect;

  $cc    = norm_str($wa_cc ?? '');
  $local = norm_str($wa_local ?? '');
  $cc    = preg_replace('/\s+/', '', $cc);
  $local = preg_replace('/\s+/', '', $local);

  $digits = ltrim($cc, '+') . $local;
  $digits = preg_replace('/\D+/', '', $digits);

  if ($digits === '') return '';
  // Intento de E.164 si hay > 9 dígitos
  if (strlen($digits) > 9) return '+' . $digits;
  return $digits;
}

try {
  $in = read_input();
  if (empty($in)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Sin datos']);
    exit;
  }

  $action = strtolower(norm_str($in['action'] ?? 'create'));
  $tipo   = (norm_str($in['tipo'] ?? '') === 'perfil') ? 'perfil' : 'cuenta';
  $id     = (int)($in['id'] ?? 0);

  $nombre   = norm_str($in['nombre'] ?? '');
  $usuario  = norm_str($in['usuario'] ?? '');
  // Acepta password_plain o password por compatibilidad
  $password = norm_str($in['password_plain'] ?? ($in['password'] ?? ''));
  $url      = norm_str($in['url'] ?? '');

  $wa_cc    = $in['wa_cc']    ?? null;
  $wa_local = $in['wa_local'] ?? null;
  $whatsapp = build_whatsapp($wa_cc, $wa_local, $in['whatsapp'] ?? null);

  $fecha_inicio = norm_str($in['fecha_inicio'] ?? '');
  $fecha_fin    = norm_str($in['fecha_fin'] ?? '');
  $soles        = norm_str($in['soles'] ?? '0.00');
  $estado       = (norm_str($in['estado'] ?? '') === 'pendiente') ? 'pendiente' : 'activo';
  $combo        = (int)($in['combo'] ?? 0);

  // Validación base (tanto create como update)
  if ($usuario === '' || $password === '' || $url === '') {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'Usuario, contraseña y URL son obligatorios']);
    exit;
  }

  $pdo   = get_pdo();
  $table = iptv_table_for($tipo);

  if ($action === 'update') {
    if ($id <= 0) {
      http_response_code(422);
      echo json_encode(['ok'=>false,'error'=>'ID inválido para update']);
      exit;
    }

    // Campos comunes
    $sql = "UPDATE {$table}
               SET nombre = ?, usuario = ?, password_plain = ?, url = ?,
                   whatsapp = ?, fecha_inicio = ?, fecha_fin = ?,
                   soles = ?, estado = ?, combo = ?
             WHERE id = ?";
    $st = $pdo->prepare($sql);
    $st->execute([
      $nombre, $usuario, $password, $url,
      $whatsapp, ($fecha_inicio ?: null), ($fecha_fin ?: null),
      ($soles !== '' ? $soles : null), $estado, $combo,
      $id
    ]);

    echo json_encode(['ok'=>true, 'id'=>$id, 'mode'=>'update']);
    exit;
  }

  // CREATE por defecto
  $sql = "INSERT INTO {$table}
            (nombre, usuario, password_plain, url, whatsapp,
             fecha_inicio, fecha_fin, soles, estado, combo, created_at)
          VALUES (?,?,?,?,?,?,?,?,?,?,NOW())";
  $st = $pdo->prepare($sql);
  $st->execute([
    $nombre, $usuario, $password, $url, $whatsapp,
    ($fecha_inicio ?: null), ($fecha_fin ?: null),
    ($soles !== '' ? $soles : null), $estado, $combo
  ]);
  $newId = (int)$pdo->lastInsertId();

  echo json_encode(['ok'=>true, 'id'=>$newId, 'mode'=>'create']);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Error en iptv_save: '.$e->getMessage()]);
}
