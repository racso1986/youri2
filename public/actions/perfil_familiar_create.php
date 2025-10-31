<?php
// ====== Diagnóstico opcional ======
$DEBUG = (isset($_GET['debug']) && $_GET['debug'] === '1');
if ($DEBUG) { header('Content-Type: text/plain; charset=utf-8'); ini_set('display_errors', 1); error_reporting(E_ALL); echo "[DEBUG ON]\n"; }

// ====== Resolver y cargar db.php (o config equivalente) ======
$here = __DIR__;
$candidates = [
  dirname($here, 2) . '/app/db.php',         // /bol/app/db.php
  dirname($here, 2) . '/config/db.php',      // /bol/config/db.php
  dirname($here, 3) . '/app/db.php',
  dirname($here, 1) . '/../app/db.php',
];
$DB_HIT = null;
foreach ($candidates as $p) {
  if (is_file($p)) { require_once $p; $DB_HIT = $p; break; }
}
if ($DEBUG) { echo $DB_HIT ? "[✓] db.php encontrado en: $DB_HIT\n" : "[X] No se encontró db.php en candidatos\n"; }

// ====== Intentar obtener $pdo desde varios mecanismos ======
function _try_bootstrap_pdo() {
  // 1) Si ya existe global $pdo válido
  if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) return $GLOBALS['pdo'];

  // 2) Helpers comunes
  if (function_exists('db'))      { $x = db();      if ($x instanceof PDO) return $x; }
  if (function_exists('getPDO'))  { $x = getPDO();  if ($x instanceof PDO) return $x; }

  // 3) Clases comunes
  if (class_exists('DB')) {
    if (method_exists('DB','pdo'))           { $x = DB::pdo();           if ($x instanceof PDO) return $x; }
    if (method_exists('DB','getConnection')) { $x = DB::getConnection(); if ($x instanceof PDO) return $x; }
  }
  if (class_exists('Database') && method_exists('Database','getConnection')) {
    $x = Database::getConnection(); if ($x instanceof PDO) return $x;
  }

  // 4) Intentar construir con constantes de config
  //    Aseguramos cargar config.php si existe
  $here = __DIR__;
  $conf_candidates = [
    dirname($here, 2) . '/config/config.php',
    dirname($here, 2) . '/app/config.php',
    dirname($here, 3) . '/config/config.php',
  ];
  foreach ($conf_candidates as $cp) { if (is_file($cp)) { require_once $cp; } }

  $host = defined('DB_HOST') ? DB_HOST : (defined('DB_SERVER') ? DB_SERVER : null);
  $name = defined('DB_NAME') ? DB_NAME : (defined('DATABASE_NAME') ? DATABASE_NAME : null);
  $user = defined('DB_USER') ? DB_USER : (defined('DB_USERNAME') ? DB_USERNAME : null);
  $pass = defined('DB_PASS') ? DB_PASS : (defined('DB_PASSWORD') ? DB_PASSWORD : null);

  if ($host && $name && $user !== null) {
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    $opt = [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    return new PDO($dsn, $user, (string)$pass, $opt);
  }

  // 5) Variables de entorno (último recurso)
  $envDsn  = getenv('DB_DSN');
  $envUser = getenv('DB_USER');
  $envPass = getenv('DB_PASS');
  if ($envDsn && $envUser !== false) {
    $opt = [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    return new PDO($envDsn, $envUser, (string)$envPass, $opt);
  }

  return null;
}

$pdo = _try_bootstrap_pdo();

if ($DEBUG) {
  if ($DB_HIT) { echo "[i] Incluido: $DB_HIT\n"; }
  echo $pdo instanceof PDO ? "[✓] PDO OK\n" : "[X] \$pdo no disponible tras bootstrap\n";
  // No insertamos en debug, solo diagnóstico:
  if ($pdo instanceof PDO) {
    try { $pdo->query("SELECT 1 FROM perfiles_familiar LIMIT 1"); echo "[✓] Tabla perfiles_familiar OK\n"; }
    catch (Throwable $e) { echo "[X] Tabla perfiles_familiar falta/difiere: ".$e->getMessage()."\n"; }
  }
  exit;
}

if (!($pdo instanceof PDO)) {
  http_response_code(500);
  exit('Error interno: no hay conexión PDO.');
}

// ====== Lógica de creación ======
try {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
  }

  $sid     = (int)($_POST['streaming_id'] ?? 0);
  $correo  = trim((string)($_POST['correo'] ?? ''));
  $pass    = trim((string)($_POST['password_plain'] ?? ''));
  $plan    = trim((string)($_POST['plan'] ?? 'premium'));
  $perfil  = trim((string)($_POST['perfil'] ?? ''));
  $estado  = trim((string)($_POST['estado'] ?? 'activo'));
  $disp    = trim((string)($_POST['dispositivo'] ?? 'tv'));
  $combo   = (int)($_POST['combo'] ?? 0);
  $fi      = (string)($_POST['fecha_inicio'] ?? date('Y-m-d'));
  $ff      = (string)($_POST['fecha_fin'] ?? date('Y-m-d', strtotime('+31 days')));

  // WhatsApp
  $wa_cc    = preg_replace('/\D+/', '', (string)($_POST['wa_cc'] ?? ''));
  $wa_local = preg_replace('/\D+/', '', (string)($_POST['wa_local'] ?? ''));
  $whatsapp = ($wa_cc !== '' || $wa_local !== '') ? ('+'.$wa_cc.$wa_local) : '';

  // Precio
  $solesRaw = (string)($_POST['soles'] ?? '0');
  $solesRaw = str_replace(',', '.', $solesRaw);
  $soles    = number_format((float)$solesRaw, 2, '.', '');

  if ($sid <= 0 || $correo === '' || $pass === '') {
    http_response_code(400);
    exit('Faltan datos obligatorios.');
  }

  $sql = "INSERT INTO perfiles_familiar
            (streaming_id, correo, password_plain, plan, whatsapp,
             fecha_inicio, fecha_fin, perfil, combo, soles,
             estado, dispositivo, created_at)
          VALUES
            (:sid, :correo, :pass, :plan, :wa,
             :fi, :ff, :perfil, :combo, :soles,
             :estado, :disp, NOW())";
  $st = $pdo->prepare($sql);
  $st->execute([
    ':sid'    => $sid,
    ':correo' => $correo,
    ':pass'   => $pass,
    ':plan'   => $plan,
    ':wa'     => $whatsapp,
    ':fi'     => $fi,
    ':ff'     => $ff,
    ':perfil' => $perfil,
    ':combo'  => $combo,
    ':soles'  => $soles,
    ':estado' => $estado,
    ':disp'   => $disp,
  ]);

  header('Location: ../streaming.php?id=' . $sid . '#perfiles-familiar');
  exit;

} catch (Throwable $e) {
  error_log('[perfil_familiar_create] ' . $e->getMessage());
  http_response_code(500);
  exit('Error interno. Revisa logs.');
}
