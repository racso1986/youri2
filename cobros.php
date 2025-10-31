<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/header.php';

// ini_set('display_errors','1');
// error_reporting(E_ALL);

/* === Conexión tolerante === */
$base = realpath(__DIR__.'/..');
foreach ([$base.'/config/config.php',$base.'/config/db.php',dirname($base).'/config/config.php',dirname($base).'/config/db.php'] as $f) { if ($f && is_file($f)) require_once $f; }
$pdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo : ((isset($dbh) && $dbh instanceof PDO) ? $dbh : null);
if (!$pdo && function_exists('getPDO')) $pdo = getPDO();
if (!$pdo && defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
  try {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, (defined('DB_PASS')?DB_PASS:(defined('DB_PASSWORD')?DB_PASSWORD:'')), [
      PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false
    ]);
  } catch(Throwable $e) {}
}

/* ===== Helpers ===== */
if (!function_exists('format_cliente_num')) {
  function format_cliente_num(string $wa_e164 = '', string $wa_digits = ''): string {
    $digits = ltrim($wa_e164 !== '' ? $wa_e164 : $wa_digits, '+');
    if ($digits === '') return '';
    if (strlen($digits) > 9) {
      $cc    = substr($digits, 0, strlen($digits) - 9);
      $local = substr($digits, -9);
      return '+' . $cc . ' ' . substr($local, 0, 3) . ' ' . substr($local, 3, 3) . ' ' . substr($local, 6, 3);
    }
    if (strlen($digits) === 9) {
      return substr($digits, 0, 3) . ' ' . substr($digits, 3, 3) . ' ' . substr($digits, 6, 3);
    }
    return ($wa_e164 !== '' && $wa_e164[0] === '+') ? $wa_e164 : ('+' . $digits);
  }
}
$DEFAULT_CC = '51';

/* --- Detección país por prefijo --- */
function msisdn_detect_cc_iso2(string $raw): array {
  $d = preg_replace('/\s+/', '', (string)$raw);
  $d = preg_replace('/(?!^)\+/', '', $d);
  $d = preg_replace('/[^\d\+]/', '', $d);
  $d = ltrim($d, '+');
  if ($d === '') return ['', ''];
  $prefixMap = [
    '598'=>'UY','597'=>'SR','596'=>'MQ','595'=>'PY','594'=>'GF','593'=>'EC','592'=>'GY','591'=>'BO','590'=>'BL',
    '509'=>'HT','507'=>'PA','506'=>'CR','505'=>'NI','504'=>'HN','503'=>'SV','502'=>'GT',
    '58'=>'VE','57'=>'CO','56'=>'CL','55'=>'BR','54'=>'AR','53'=>'CU','52'=>'MX','51'=>'PE',
    '34'=>'ES','351'=>'PT','39'=>'IT','33'=>'FR','49'=>'DE','44'=>'GB','81'=>'JP','82'=>'KR','86'=>'CN','61'=>'AU','64'=>'NZ',
    '1'=>'US','7'=>'RU'
  ];
  $cands = array_map('strval', array_keys($prefixMap));
  usort($cands, fn($a,$b)=>strlen($b)<=>strlen($a));
  foreach ($cands as $cc) {
    if (strncmp($d, $cc, strlen($cc)) === 0) return [$cc, $prefixMap[$cc]];
  }
  return ['', ''];
}
function is_latam(string $iso2): bool {
  static $LATAM = ['AR','BO','BR','CL','CO','CR','CU','DO','EC','SV','GT','HT','HN','MX','NI','PA','PY','PE','PR','UY','VE'];
  return in_array(strtoupper($iso2), $LATAM, true);
}

/* === Mes seleccionado === */
$mes = (isset($_GET['mes']) && preg_match('/^\d{4}\-\d{2}$/', $_GET['mes'])) ? $_GET['mes'] : date('Y-m');

/* === Precalcular clientes/día (para futuros usos) === */
$clientesList = [];
$byKey = [];

try {
  $sqlP = "
    SELECT TRIM(COALESCE(NULLIF(correo,''), NULLIF(whatsapp,''))) AS cliente,
           MIN(DAY(fecha_fin)) AS dia
    FROM perfiles
    WHERE TRIM(COALESCE(correo, whatsapp)) <> ''
      AND fecha_fin IS NOT NULL AND fecha_fin <> '0000-00-00'
    GROUP BY TRIM(COALESCE(NULLIF(correo,''), NULLIF(whatsapp,'')))
  ";
  foreach (($pdo->query($sqlP)->fetchAll() ?: []) as $r) {
    $cli = (string)($r['cliente'] ?? '');
    if ($cli === '') continue;
    $dia = isset($r['dia']) ? (int)$r['dia'] : null;
    $byKey[$cli] = count($clientesList);
    $clientesList[] = ['cliente'=>$cli,'dia'=>($dia>0?$dia:null)];
  }

  $sqlC = "
    SELECT TRIM(COALESCE(NULLIF(correo,''), NULLIF(whatsapp,''))) AS cliente,
           MIN(DAY(fecha_fin)) AS dia
    FROM cuentas
    WHERE TRIM(COALESCE(correo, whatsapp)) <> ''
      AND fecha_fin IS NOT NULL AND fecha_fin <> '0000-00-00'
    GROUP BY TRIM(COALESCE(NULLIF(correo,''), NULLIF(whatsapp,'')))
  ";
  foreach (($pdo->query($sqlC)->fetchAll() ?: []) as $r) {
    $cli = (string)($r['cliente'] ?? '');
    if ($cli === '') continue;
    $dia = isset($r['dia']) ? (int)$r['dia'] : null;
    if (isset($byKey[$cli])) {
      $i = $byKey[$cli];
      $curr = $clientesList[$i]['dia'];
      $nuevo = ($dia>0?$dia:null);
      if ($curr === null) $clientesList[$i]['dia'] = $nuevo;
      elseif ($nuevo !== null) $clientesList[$i]['dia'] = min($curr, $nuevo);
    } else {
      $byKey[$cli] = count($clientesList);
      $clientesList[] = ['cliente'=>$cli,'dia'=>($dia>0?$dia:null)];
    }
  }

  $sqlI = "SELECT TRIM(usuario) AS cliente FROM iptv WHERE usuario IS NOT NULL AND TRIM(usuario) <> ''";
  foreach (($pdo->query($sqlI)->fetchAll() ?: []) as $r) {
    $cli = (string)($r['cliente'] ?? '');
    if ($cli === '') continue;
    if (!isset($byKey[$cli])) {
      $byKey[$cli] = count($clientesList);
      $clientesList[] = ['cliente'=>$cli,'dia'=>null];
    }
  }

  usort($clientesList, function($a,$b){
    $da=$a['dia']; $db=$b['dia'];
    if ($da===null && $db===null) return strcasecmp($a['cliente'],$b['cliente']);
    if ($da===null) return 1;
    if ($db===null) return -1;
    return ($da===$db) ? strcasecmp($a['cliente'],$b['cliente']) : ($da<=>$db);
  });
} catch (Throwable $e) {}

/* === Cobros del mes actual (por si los usas) === */
$cobrosMap = [];
try {
  $q = $pdo->prepare('SELECT cliente, servicio, dia FROM cobros WHERE yyyymm=:m');
  $q->execute([':m'=>$mes]);
  foreach ($q->fetchAll() as $r) {
    $c = (string)$r['cliente']; $s = (string)$r['servicio'];
    $cobrosMap[$c][$s] = (int)$r['dia'];
  }
} catch(Throwable $e) {}

/* === Servicios (para encabezados y celdas a la derecha) === */
$mapServicios = []; // [id] => nombre
try {
  $st = $pdo->query('SELECT id, nombre FROM streamings ORDER BY id');
  foreach ($st->fetchAll() as $r) {
    $mapServicios[(int)$r['id']] = (string)$r['nombre'];
  }
} catch(Throwable $e) {}
$nombreIPTV   = 'IPTV';
$serviciosCols = array_values(array_unique(array_merge(array_values($mapServicios), [$nombreIPTV])));
sort($serviciosCols, SORT_NATURAL|SORT_FLAG_CASE);

/* === Recolección de suscripciones por cliente === */
$clientes = []; // key => ['correo','whatsapp','tg','fin_min','pago_total','servicios'=> [NombreSrv => ['plan','fin','dias','monto']] ]
$hoyTs = strtotime(date('Y-m-d'));
$calcDias = function (?string $fin) use ($hoyTs) {
  if (!$fin || $fin === '0000-00-00') return null;
  $ts = strtotime($fin); if ($ts === false) return null;
  return (int) floor(($ts - $hoyTs) / 86400);
};

/* Perfiles */
try {
  $sql = "SELECT p.correo, p.whatsapp, p.fecha_fin, p.soles, p.plan, p.streaming_id
          FROM perfiles p
          WHERE TRIM(COALESCE(p.correo,'')) <> '' OR TRIM(COALESCE(p.whatsapp,'')) <> ''";
  foreach ($pdo->query($sql) as $r) {
    $correo = trim((string)($r['correo'] ?? ''));
    $wa     = trim((string)($r['whatsapp'] ?? ''));
    $key    = $correo !== '' ? $correo : $wa;
    if ($key === '') continue;
    if (!isset($clientes[$key])) $clientes[$key] = ['correo'=>$correo,'whatsapp'=>$wa,'tg'=>$wa,'fin_min'=>null,'pago_total'=>0.0,'servicios'=>[]];

    $srvName = $mapServicios[(int)$r['streaming_id']] ?? '';
    if ($srvName === '') continue;

    $fin  = (string)($r['fecha_fin'] ?? '');
    $dias = $calcDias($fin);
    $plan = (string)($r['plan'] ?? '');
    $pago = (float)($r['soles'] ?? 0);

    $prev = $clientes[$key]['servicios'][$srvName] ?? null;
    if (!$prev) {
      $clientes[$key]['servicios'][$srvName] = ['plan'=>$plan,'fin'=>$fin,'dias'=>$dias,'monto'=>$pago];
    } else {
      $prev['monto'] = (float)($prev['monto'] ?? 0) + $pago;
      if ($plan !== '' && ($prev['plan'] ?? '') === '') $prev['plan'] = $plan;
      if ($fin && (!$prev['fin'] || strtotime($fin) < strtotime((string)$prev['fin']))) {
        $prev['fin'] = $fin; $prev['dias'] = $dias;
      }
      $clientes[$key]['servicios'][$srvName] = $prev;
    }

    if ($fin && $fin !== '0000-00-00') {
      $curr = $clientes[$key]['fin_min'];
      if (!$curr || strtotime($fin) < strtotime($curr)) $clientes[$key]['fin_min'] = $fin;
    }
    $clientes[$key]['pago_total'] += $pago;
  }
} catch (Throwable $e) {}

/* Cuentas */
try {
  $sql = "SELECT c.correo, c.whatsapp, c.fecha_fin, c.soles, c.plan, c.streaming_id
          FROM cuentas c
          WHERE TRIM(COALESCE(c.correo,'')) <> '' OR TRIM(COALESCE(c.whatsapp,'')) <> ''";
  foreach ($pdo->query($sql) as $r) {
    $correo = trim((string)($r['correo'] ?? ''));
    $wa     = trim((string)($r['whatsapp'] ?? ''));
    $key    = $correo !== '' ? $correo : $wa;
    if ($key === '') continue;
    if (!isset($clientes[$key])) $clientes[$key] = ['correo'=>$correo,'whatsapp'=>$wa,'tg'=>$wa,'fin_min'=>null,'pago_total'=>0.0,'servicios'=>[]];

    $srvName = $mapServicios[(int)$r['streaming_id']] ?? '';
    if ($srvName === '') continue;

    $fin  = (string)($r['fecha_fin'] ?? '');
    $dias = $calcDias($fin);
    $plan = (string)($r['plan'] ?? '');
    $pago = (float)($r['soles'] ?? 0);

    $prev = $clientes[$key]['servicios'][$srvName] ?? null;
    if (!$prev) {
      $clientes[$key]['servicios'][$srvName] = ['plan'=>$plan,'fin'=>$fin,'dias'=>$dias,'monto'=>$pago];
    } else {
      $prev['monto'] = (float)($prev['monto'] ?? 0) + $pago;
      if ($plan !== '' && ($prev['plan'] ?? '') === '') $prev['plan'] = $plan;
      if ($fin && (!$prev['fin'] || strtotime($fin) < strtotime((string)$prev['fin']))) {
        $prev['fin'] = $fin; $prev['dias'] = $dias;
      }
      $clientes[$key]['servicios'][$srvName] = $prev;
    }

    if ($fin && $fin !== '0000-00-00') {
      $curr = $clientes[$key]['fin_min'];
      if (!$curr || strtotime($fin) < strtotime($curr)) $clientes[$key]['fin_min'] = $fin;
    }
    $clientes[$key]['pago_total'] += $pago;
  }
} catch (Throwable $e) {}

/* IPTV */
try {
  $sql = "SELECT usuario, soles, nombre, fecha_fin FROM iptv WHERE TRIM(COALESCE(usuario,'')) <> ''";
  foreach ($pdo->query($sql) as $r) {
    $user = trim((string)($r['usuario'] ?? '')); if ($user === '') continue;
    $monto = (float)($r['soles'] ?? 0);
    $planI = (string)($r['nombre'] ?? '');
    $finI  = (string)($r['fecha_fin'] ?? '');
    $diasI = $calcDias($finI);

    if (!isset($clientes[$user])) $clientes[$user] = ['correo'=>$user,'whatsapp'=>'','tg'=>'','fin_min'=>null,'pago_total'=>0.0,'servicios'=>[]];

    $prev = $clientes[$user]['servicios'][$nombreIPTV] ?? null;
    if (!$prev) {
      $clientes[$user]['servicios'][$nombreIPTV] = ['plan'=>$planI,'fin'=>$finI,'dias'=>$diasI,'monto'=>$monto];
    } else {
      $prev['monto'] = (float)($prev['monto'] ?? 0) + $monto;
      if ($planI !== '' && ($prev['plan'] ?? '') === '') $prev['plan'] = $planI;
      if ($finI && (!$prev['fin'] || strtotime($finI) < strtotime((string)$prev['fin']))) {
        $prev['fin'] = $finI; $prev['dias'] = $diasI;
      }
      $clientes[$user]['servicios'][$nombreIPTV] = $prev;
    }

    if ($finI && $finI !== '0000-00-00') {
      $curr = $clientes[$user]['fin_min'];
      if (!$curr || strtotime($finI) < strtotime($curr)) $clientes[$user]['fin_min'] = $finI;
    }
    $clientes[$user]['pago_total'] += $monto;
  }
} catch (Throwable $e) {}

/* Orden filas por fin_min asc */
uasort($clientes, function ($a,$b) {
  $fa=$a['fin_min']; $fb=$b['fin_min'];
  if ($fa===$fb) return strcasecmp((string)$a['correo'], (string)$b['correo']);
  if ($fa===null) return 1;
  if ($fb===null) return -1;
  return strtotime($fa) <=> strtotime($fb);
});

/* Normalizador WA */
$normPhone = function (?string $v) {
  $v = trim((string)$v);
  $v = preg_replace('/\s+/', '', $v);
  $v = preg_replace('/(?!^)\+/', '', $v);
  $v = preg_replace('/[^\d\+]/', '', $v);
  if ($v === '+') $v = '';
  return $v;
};
?>
<style>
  .tr-hoy    { background-color:#ffe5e5 !important; }
  .tr-manana { background-color:#ffeacc !important; }
  .tr-pasado { background-color:#fff7cc !important; }
</style>

<div class="container py-3">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">Cobros</h5>
    <div class="d-flex gap-2">
      <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">Dashboard</a>
      <a href="streaming.php" class="btn btn-sm btn-primary">Streaming</a>
      <a href="iptv.php" class="btn btn-sm btn-outline-primary">IPTV</a>
    </div>
  </div>

  <div class="d-flex align-items-center gap-2 mb-2">
    <div class="btn-group btn-group-sm" role="group" aria-label="Filtro por vencimiento">
      <button type="button" class="btn btn-outline-secondary active" data-filter="all">Todos</button>
      <button type="button" class="btn btn-outline-secondary" data-filter=".tr-hoy">Hoy / vencidos</button>
      <button type="button" class="btn btn-outline-secondary" data-filter=".tr-manana">Mañana</button>
      <button type="button" class="btn btn-outline-secondary" data-filter=".tr-pasado">Pasado mañana</button>
      <button type="button" class="btn btn-outline-secondary" data-filter="otros">Otros</button>
    </div>
    <small class="text-muted ms-2">(filtra por color/fecha)</small>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered align-middle" style="--bs-border-color:#000;" data-no-row-modal="1" id="tabla-cobros">
      <thead>
        <tr>
          <th style="width:90px">Fin</th>
          <th style="width:160px">Número</th>
          <th style="width:90px">WhatsApp</th>
          <th style="width:90px">Pago</th>
          <?php foreach ($serviciosCols as $svc): ?>
            <th class="text-center"><?= htmlspecialchars($svc, ENT_QUOTES) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
<?php
$granAcum = 0.0;
$hoyTs    = strtotime(date('Y-m-d'));

foreach ($clientes as $cli => $info):
  $fin    = $info['fin_min'];

  // Fin formateado: "24 octubre"
  $finTxt = '';
  if ($fin && $fin !== '0000-00-00') {
    $ts = strtotime($fin);
    $meses = ['', 'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $finTxt = date('j', $ts) . ' ' . $meses[(int)date('n', $ts)];
  }

  // color de fila por días para el fin mínimo
  $rowClass = '';
  if ($fin && $fin !== '0000-00-00') {
    $diasFila = (int) floor((strtotime($fin) - $hoyTs) / 86400);
    if ($diasFila <= 0)      $rowClass = 'tr-hoy';
    elseif ($diasFila === 1) $rowClass = 'tr-manana';
    elseif ($diasFila === 2) $rowClass = 'tr-pasado';
  }

  // Número/WA
  $wa_raw    = $normPhone($info['whatsapp']);
  $wa_for_wa = ltrim($wa_raw, '+');
  $numero_display = '';
  if ($wa_raw !== '') {
    $numero_display = format_cliente_num($wa_raw, $wa_for_wa);
    if ($numero_display !== '' && strpos($numero_display, '+') !== 0) {
      $numero_display = '+' . $DEFAULT_CC . ' ' . $numero_display;
    }
  }
  $tg_phone  = $wa_raw ? ($wa_raw[0] === '+' ? $wa_raw : '+'.$wa_raw) : '';

  /* ====== Mensaje y total del DÍA (solo servicios cuyo fin == $fin) ====== */
  $montoFila = 0.0;
  $lineas = [];
  foreach ($info['servicios'] as $nomSrv => $sv) {
    $finSvc = (string)($sv['fin'] ?? '');
    if ($fin && $finSvc && $finSvc === $fin) {
      $monto = (float)($sv['monto'] ?? 0);
      $plan  = trim((string)($sv['plan'] ?? ''));
      $label = $nomSrv . ($plan !== '' ? ' (' . $plan . ')' : '');
      $lineas[] = $label . ': S/ ' . number_format($monto, 2);
      $montoFila += $monto;
    }
  }
  $detalleServicios = implode("\n", $lineas);
  $mensaje = "Hola! 👋\n"
           . "Pagos del {$finTxt}:\n"
           . ($detalleServicios !== '' ? $detalleServicios . "\n" : "")
           . "Total: S/ " . number_format($montoFila, 2);
  $wa_msg = rawurlencode($mensaje);

  // acumular total global con lo que realmente vence ese día
  $granAcum += $montoFila;
?>
        <tr class="<?= $rowClass ?>" data-cliente="<?= htmlspecialchars($cli, ENT_QUOTES) ?>">
          <!-- FIN -->
          <td class="text-center"><?= htmlspecialchars($finTxt) ?></td>

          <!-- NÚMERO -->
          <td class="text-nowrap"><?= htmlspecialchars($numero_display) ?></td>

          <!-- WHATSAPP / TELEGRAM -->
          <td class="whatsapp text-center">
            <?php if ($wa_for_wa): ?>
              <a class="cobro-wa"
                 data-no-row-modal="1"
                 data-msg="<?= htmlspecialchars($mensaje, ENT_QUOTES) ?>"
                 onclick="event.stopPropagation();"
                 href="https://wa.me/<?= htmlspecialchars($wa_for_wa) ?>?text=<?= $wa_msg ?>"
                 target="_blank" rel="noopener"
                 aria-label="WhatsApp" title="WhatsApp">
                <!-- ícono WA -->
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                  <path d="M13.601 2.326A7.854 7.854 0 0 0 8.03.002C3.6.002.008 3.594.008 8.023c0 1.414.37 2.792 1.074 4.005L.01 16l3.996-1.05a7.96 7.96 0 0 0 4.024 1.073h.003c4.43 0 8.022-3.592 8.022-8.021 0-2.144-.835-4.162-2.354-5.676zM8.033 14.5h-.002a6.48 6.48 0 0 1-3.302-.905l-.237-.141-2.371.623.633-2.31-.154-.237A6.47 6.47 0 0 1 1.53 8.02c0-3.575 2.91 6.485 6.5 6.485 1.738 0 3.37.676 4.598 1.901a6.46 6.46 0 0 1 1.907 4.585c0 3.575-2.91 6.48-6.5 6.48zm3.69-4.844c-.202-.1-1.194-.59-1.378-.657-.184-.068-.318-.101-.452.1-.134.201-.518.657-.635.792-.117.134-.234.151-.436.05-.202-.1-.853-.314-1.625-1.002-.6-.533-1.005-1.19-1.123-1.392-.117-.201-.013-.31.088-.41.09-.089.202-.234.302-.351.101-.117.134-.201.202-.335.067-.134.034-.251-.017-.351-.05-.1-.452-1.09-.619-1.49-.163-.392-.329-.339-.452-.345l-.386-.007c-.118 0-.31.045-.471.224-.16.177-.618.604-.618 1.475s.633 1.71.72 1.83c.084.118 1.245 1.9 3.016 2.665.422.182.75.29 1.006.371.422.134.807.115 1.11.069.339-.05 1.194-.488 1.363-.96.168-.472.168-.877.118-.964-.05-.084-.184-.134-.386-.234z"/>
                </svg>
              </a>
            <?php endif; ?>
            <?php if ($tg_phone && $tg_phone !== '+'): ?>
              <a class="ms-2 cobro-tg"
                 data-no-row-modal="1"
                 onclick="event.stopPropagation();"
                 href="#"
                 data-phone="<?= htmlspecialchars($tg_phone, ENT_QUOTES) ?>"
                 data-msg="<?= htmlspecialchars($mensaje, ENT_QUOTES) ?>"
                 aria-label="Telegram" title="Telegram">
                <!-- ícono TG -->
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                  <path d="M15.953 1.737a1.01 1.01 0 0 0-1.04-.2L1.253 6.78c-.86.33-.854 1.54.01 1.86l3.17 1.18 1.24 3.98c.24.77 1.2.99 1.76.41l2.12-2.18 3.54 2.62c.73.54 1.79.14 1.98-.75l2.34-11.02a1.02 1.02 0 0 0-.46-1.18zM6.26 10.71l-.2 2.35 1.53-1.56 3.56-5.62-4.89 4.83z"/>
                </svg>
              </a>
            <?php endif; ?>
          </td>

          <!-- PAGO (total del día para ese usuario) -->
          <td class="text-end"><?= number_format($montoFila, 2) ?></td>

          <?php
          // Por cada servicio (streamings + IPTV) dibuja una celda.
          // Solo muestra contenido si ese servicio vence el MISMO día que la fila ($fin).
          foreach ($serviciosCols as $svcName):
              $cell = '';
              if (isset($info['servicios'][$svcName])) {
                  $sv     = $info['servicios'][$svcName]; // ['plan','fin','dias','monto']
                  $finSvc = (string)($sv['fin'] ?? '');
                  if ($finSvc && $fin && $finSvc === $fin) {
                      $plan  = trim((string)($sv['plan'] ?? ''));
                      $monto = (float)($sv['monto'] ?? 0);
                      $cell  = ($plan !== '' ? ($plan.' — ') : '') . 'S/ ' . number_format($monto, 2);
                  }
              }
          ?>
            <td class="text-nowrap text-center"><?= htmlspecialchars($cell, ENT_QUOTES) ?></td>
          <?php endforeach; ?>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>

    <div class="mt-2 text-end">
      <strong>Total a cobrar:</strong> S/ <?= number_format($granAcum, 2) ?>
    </div>
  </div>
</div>

<script>
(function () {
  const table = document.getElementById('tabla-cobros');
  if (!table) return;

  const buttons = document.querySelectorAll('[data-filter]');
  const rows    = table.querySelectorAll('tbody tr');

  function applyFilter(f) {
    rows.forEach(tr => {
      tr.style.display = '';
      if (f === 'all') return;
      if (f === 'otros') {
        if (tr.classList.contains('tr-hoy') ||
            tr.classList.contains('tr-manana') ||
            tr.classList.contains('tr-pasado')) {
          tr.style.display = 'none';
        }
      } else {
        if (!tr.matches(f)) tr.style.display = 'none';
      }
    });
  }

  buttons.forEach(btn => {
    btn.addEventListener('click', () => {
      buttons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      applyFilter(btn.getAttribute('data-filter'));
    });
  });

  /* ===== Blindaje contra handlers globales ===== */
  // WhatsApp: dejamos navegar al href, pero impedimos que otros listeners reemplacen el mensaje.
  document.querySelectorAll('#tabla-cobros a.cobro-wa').forEach(a => {
    a.addEventListener('click', function(e){
      e.stopPropagation();
      if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
      // No preventDefault: queremos que abra el href que ya lleva ?text=
    }, true); // captura
  });

  // Telegram: construimos el share con el mensaje correcto y bloqueamos cualquier otro handler.
  document.querySelectorAll('#tabla-cobros a.cobro-tg').forEach(a => {
    a.addEventListener('click', function(e){
      e.stopPropagation();
      if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
      e.preventDefault();
      const msg = a.getAttribute('data-msg') || '';
      const share = 'https://t.me/share/url?url=&text=' + encodeURIComponent(msg);
      window.open(share, '_blank', 'noopener');
      return false;
    }, true); // captura
  });

})();
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
