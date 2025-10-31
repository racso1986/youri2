<?php
// ===== DEBUG opcional =====
// Abre /public/iptv.php?debug=1 para ver errores en pantalla
$DEBUG = isset($_GET['debug']) ? 1 : 0;
if ($DEBUG) { ini_set('display_errors','1'); error_reporting(E_ALL); }

// ===== CARGAS BÁSICAS =====
require_once __DIR__ . '/../config/db.php';   // Debe exponer get_pdo()

// -------- Helpers fallback --------
if (!function_exists('estado_badge_class')) {
  function estado_badge_class(string $estado): string {
    $e = strtolower(trim($estado));
    return match($e) {
      'activo'    => 'bg-success',
      'pendiente' => 'bg-warning',
      'moroso'    => 'bg-danger',
      default     => 'bg-secondary',
    };
  }
}
if (!function_exists('row_json_attr')) {
  function row_json_attr(array $row): string {
    return htmlspecialchars(
      json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
      ENT_QUOTES, 'UTF-8'
    );
  }
}
if (!function_exists('format_cliente_num')) {
  function format_cliente_num(string $wa_e164 = '', string $wa_digits = ''): string {
    $digits = ltrim($wa_e164 !== '' ? $wa_e164 : $wa_digits, '+');
    if ($digits === '') return '';
    if (strlen($digits) > 9) {
      $cc    = substr($digits, 0, strlen($digits) - 9);
      $local = substr($digits, -9);
      return '+' . $cc . ' '
           . substr($local, 0, 3) . ' '
           . substr($local, 3, 3) . ' '
           . substr($local, 6, 3);
    }
    if (strlen($digits) === 9) {
      return substr($digits, 0, 3) . ' '
           . substr($digits, 3, 3) . ' '
           . substr($digits, 6, 3);
    }
    return ($wa_e164 !== '' && $wa_e164[0] === '+') ? $wa_e164 : ('+' . $digits);
  }
}
// ---------------------------------------------------------------------------

// ===== CONEXIÓN PDO =====
try {
  $pdo = get_pdo();
} catch (Throwable $e) {
  if ($DEBUG) {
    echo "<pre>DB ERROR en get_pdo(): " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
  }
  http_response_code(500);
  exit;
}

// ===== CARGA DEL MODELO / DATOS =====
$iptv_perfiles = [];
$iptv_cuentas  = [];
try {
  $use_fallback = false;
  // Si no tienes /app/ no pasa nada; entramos al fallback.
  $modelPath = __DIR__ . '/../app/models/IptvModel.php';
  if (is_file($modelPath)) {
    require_once $modelPath;
    if (!class_exists('IptvModel')) $use_fallback = true;
  } else {
    $use_fallback = true;
  }

  // SPLIT en tablas (flag en config/config.php): IPTV_SPLIT_TABLES
  if (defined('IPTV_SPLIT_TABLES') && IPTV_SPLIT_TABLES) {
    if ($use_fallback || !method_exists('IptvModel','allFrom')) {
      // Fallback directo por tabla
      $stmt = $pdo->query("SELECT id, nombre, usuario, password_plain, url, whatsapp,
                                  fecha_inicio, fecha_fin, soles, estado, combo, color, created_at
                             FROM iptv_perfiles ORDER BY id DESC");
      $iptv_perfiles = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
      $stmt = $pdo->query("SELECT id, nombre, usuario, password_plain, url, whatsapp,
                                  fecha_inicio, fecha_fin, soles, estado, combo, color, created_at
                             FROM iptv_cuentas ORDER BY id DESC");
      $iptv_cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
      $iptv_perfiles = IptvModel::allFrom('perfil');
      $iptv_cuentas  = IptvModel::allFrom('cuenta');
    }
  } else {
    // Legacy: una sola tabla -> se mostrará como "Cuentas"
    if ($use_fallback || !method_exists('IptvModel','all')) {
      $stmt  = $pdo->query("SELECT id, nombre, usuario, password_plain, url, whatsapp,
                                   fecha_inicio, fecha_fin, soles, estado, combo,
                                   color, created_at
                              FROM iptv
                             ORDER BY id DESC");
      $iptv_cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
      $iptv_cuentas = IptvModel::all();
    }
  }

} catch (Throwable $e) {
  if ($DEBUG) {
    echo "<pre>QUERY ERROR: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
  }
  http_response_code(500);
  exit;
}

$hoy = date('Y-m-d');

// === Determinar el "padre" por correo: el registro más antiguo (id más pequeño) ===
function iptv_heads_by_usuario(array $rows): array {
  $heads = [];
  foreach ($rows as $r) {
    $u = strtolower(trim((string)($r['usuario'] ?? '')));
    if ($u === '') continue;
    $id = (int)($r['id'] ?? 0);
    if (!isset($heads[$u]) || $id < $heads[$u]) {
      $heads[$u] = $id; // padre = id más pequeño de ese correo
    }
  }
  return $heads;
}
$IPTV_HEADS_PERFILES = iptv_heads_by_usuario($iptv_perfiles);
$IPTV_HEADS_CUENTAS  = iptv_heads_by_usuario($iptv_cuentas);

// ===== Includes de layout =====
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';

// ===== RUTAS (sin app/, solo /public/ajax/...) =====
$BASE = rtrim(dirname($_SERVER['PHP_SELF']), '/');          // /public
$saveFile   = is_file(__DIR__ . '/ajax/iptv_save.php')   ? 'iptv_save.php'
           : (is_file(__DIR__ . '/ajax/ipt_save.php')    ? 'ipt_save.php' : '');
$deleteFile = is_file(__DIR__ . '/ajax/iptv_delete.php') ? 'iptv_delete.php' : '';
$colorFile  = is_file(__DIR__ . '/ajax/iptv_color.php')  ? 'iptv_color.php'  : '';

$SAVE_URL   = $saveFile   ? ($BASE . '/ajax/' . $saveFile)   : '';
$DELETE_URL = $deleteFile ? ($BASE . '/ajax/' . $deleteFile) : '';
$COLOR_URL  = $colorFile  ? ($BASE . '/ajax/' . $colorFile)  : '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>IPTV</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .row-color-rojo   { background: #ffe5e5 !important; }
    .row-color-azul   { background: #e5f0ff !important; }
    .row-color-verde  { background: #e9ffe5 !important; }
    .row-color-blanco { background: #ffffff !important; }
    .cursor-pointer   { cursor: pointer; }
    .plan-cell-perfil { min-width: 140px; }
    .correo-cell      { min-width: 180px; }
    .text-truncate    { max-width: 260px; }

    /* === IPTV Perfiles — hueco igual a Streaming (cols 1–4 de HIJOS) === */
    #iptv-perfiles table.table.table-bordered tbody tr.js-parent-row > td:nth-child(-n+4){
      border-bottom: 0 !important;
    }
    #iptv-perfiles table.table.table-bordered tbody tr.js-child-row > td:nth-child(-n+4){
      border-top: 0 !important;
      border-bottom: 0 !important;
      border-left: 0 !important;
      border-right: 0 !important;
      background: #fff !important;
      color: transparent !important;
      pointer-events: none;
    }
    #iptv-perfiles table.table.table-bordered tbody tr.js-child-row{
      border-top-width: 0 !important;
      border-bottom-width: 0 !important;
    }
    #iptv-perfiles table.table.table-bordered tbody tr.js-child-row > td:first-child{
      border-left-width: 1px !important;
      border-left-style: solid !important;
      border-left-color: #000 !important;
    }
    #iptv-perfiles table.table.table-bordered{
      border-bottom: 1px solid #000 !important;
    }
    #iptv-perfiles table.table.table-bordered > :not(caption) > tbody > tr:last-child,
    #iptv-perfiles table.table.table-bordered tbody tr:last-child > td{
      border-bottom-width: 1px !important;
      border-bottom-style: solid !important;
      border-bottom-color: #000 !important;
    }

    /* Celda clickeable para IPTV (Nombre) */
    .iptv-cell-perfil { min-width: 140px; cursor: pointer; }

    /* ===== Filtros locales: reset de cualquier filtro heredado (streamings) ===== */
    body[data-page="iptv"] #filterBar,
    body[data-page="iptv"] .filters,
    body[data-page="iptv"] .filters-bar,
    body[data-page="iptv"] .toolbar-filtros,
    body[data-page="iptv"] .js-filters-root {
      display: none !important;
    }
    .iptv-local-filters { display: block; }
    .tab-pane:not(.active) .iptv-local-filters { display: none !important; }
    .iptv-local-filters .form-control,
    .iptv-local-filters .form-select { height: calc(1.5em + .5rem + 2px); }
    
    
    #iptv-perfiles .__filtersWrap__ {
        display: none !important;
    }
     #iptv-cuentas .__filtersWrap__ {
        display: none !important;
    }
  </style>
</head>
<body data-page="iptv">

<div class="container py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="m-0">IPTV</h5>

    <?php if (!defined('IPTV_SPLIT_TABLES') || !IPTV_SPLIT_TABLES): ?>
      <!-- Botón Agregar IPTV (solo modo legacy: una sola tabla) -->
      <button type="button"
              class="btn btn-sm btn-success"
              data-bs-toggle="modal"
              data-bs-target="#modalEditarIptv"
              id="btnAgregarIptv"
              data-row='<?= row_json_attr([
                "id" => 0,
                "nombre" => "",
                "usuario" => "",
                "password_plain" => "",
                "url" => "",
                "whatsapp" => "",
                "fecha_inicio" => date('Y-m-d'),
                "fecha_fin" => date('Y-m-d', strtotime('+31 days')),
                "soles" => "0.00",
                "estado" => "activo",
                "combo" => 0,
                "tipo" => "cuenta"
              ]) ?>'>
        + Agregar IPTV
      </button>
    <?php endif; ?>
  </div>

  <?php if (defined('IPTV_SPLIT_TABLES') && IPTV_SPLIT_TABLES): ?>
    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" id="iptvTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="iptv-perfiles-tab" data-bs-toggle="tab"
                data-bs-target="#iptv-perfiles" type="button" role="tab"
                aria-controls="iptv-perfiles" aria-selected="true">Perfiles</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="iptv-cuentas-tab" data-bs-toggle="tab"
                data-bs-target="#iptv-cuentas" type="button" role="tab"
                aria-controls="iptv-cuentas" aria-selected="false">Cuentas</button>
      </li>
    </ul>

    <div class="tab-content">
      <!-- TAB: PERFILES -->
      <div class="tab-pane fade show active" id="iptv-perfiles" role="tabpanel" aria-labelledby="iptv-perfiles-tab" data-tipo="perfil">
        <!-- Botón Agregar SOLO Perfiles -->
        <div class="d-flex justify-content-end mb-2">
          <button type="button" class="btn btn-sm btn-primary" id="btnAddPerfil"
                  data-bs-toggle="modal" data-bs-target="#modalAgregarPerfil">
            + Agregar Perfil IPTV
          </button>
        </div>

        <!-- Filtro local: PERFILES -->
        <div class="iptv-local-filters mb-2" data-scope="perfiles">
          <div class="row g-2 align-items-center">
            <div class="col-sm-4">
              <input type="search" class="form-control form-control-sm" placeholder="Buscar nombre, correo o URL" data-role="search">
            </div>
            <div class="col-sm-2">
              <select class="form-select form-select-sm" data-role="estado">
                <option value="">Estado: Todos</option>
                <option value="activo">Activo</option>
                <option value="pendiente">Pendiente</option>
                <option value="moroso">Moroso</option>
              </select>
            </div>
            <div class="col-sm-2">
              <select class="form-select form-select-sm" data-role="combo">
                <option value="">Combo: Todos</option>
                <option value="1">Sí</option>
                <option value="0">No</option>
              </select>
            </div>
            <div class="col-sm-2">
              <select class="form-select form-select-sm" data-role="color">
                <option value="">Color: Todos</option>
                <option value="rojo">Rojo</option>
                <option value="azul">Azul</option>
                <option value="verde">Verde</option>
                <option value="blanco">Blanco</option>
              </select>
            </div>
            <div class="col-sm-2">
              <button class="btn btn-sm btn-outline-secondary w-100" data-role="clear">Limpiar</button>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table align-middle table-bordered table-compact">
            <thead>
              <tr>
                <th>Nombre</th><th>Correo</th><th>Contraseña</th><th>URL</th>
                <th>Inicio</th><th>Fin</th><th>Días</th><th>Cliente</th>
                <th>Precio</th><th>Combo</th><th>Estado</th><th>Entrega</th><th>Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php
              foreach ($iptv_perfiles as $p):
                $id            = (int)($p['id'] ?? 0);
                $nombre        = trim((string)($p['nombre'] ?? ''));
                $usuario       = (string)($p['usuario'] ?? '');
                $password      = (string)($p['password_plain'] ?? '');
                $url_raw       = trim((string)($p['url'] ?? ''));
                $wa_raw        = (string)($p['whatsapp'] ?? '');
                $fecha_inicio  = (string)($p['fecha_inicio'] ?? '');
                $fecha_fin     = (string)($p['fecha_fin'] ?? '');
                $soles         = (string)($p['soles'] ?? '0.00');
                $estado        = (string)($p['estado'] ?? 'activo');
                $combo         = (int)($p['combo'] ?? 0);

                $url_href = $url_raw && !preg_match('#^https?://#i', $url_raw) ? ('https://' . $url_raw) : $url_raw;
                $nombre_ui = ($nombre !== '' ? $nombre : '(sin nombre)');

                $fi_ok  = ($fecha_inicio && $fecha_inicio !== '0000-00-00');
                $ff_ok  = ($fecha_fin    && $fecha_fin    !== '0000-00-00');
                $fi_fmt = $fi_ok ? date('d/m/y', strtotime($fecha_inicio)) : '';
                $ff_fmt = $ff_ok ? date('d/m/y', strtotime($fecha_fin))    : '';

                $dias = $ff_ok ? (int) floor((strtotime($fecha_fin) - strtotime($hoy)) / 86400) : null;
                $estadoReal = ($ff_ok && $dias < 0) ? 'moroso' : $estado;
                $badgeClass = estado_badge_class($estadoReal);
                $comboLabel = $combo === 1 ? 'Sí' : 'No';

                $__wa = preg_replace('/\s+/', '', $wa_raw);
                $__wa = preg_replace('/(?!^)\+/', '', $__wa);
                $__wa = preg_replace('/[^\d\+]/', '', $__wa);
                if ($__wa === '+') $__wa = '';

                $wa_num          = ltrim($__wa, '+');
                $tg_phone        = ($__wa !== '' && $__wa[0] === '+') ? $__wa : ($__wa !== '' ? ('+' . $__wa) : '');
                $cliente_display = format_cliente_num($__wa, $wa_num);

                $__color      = isset($p['color']) ? strtolower((string)$p['color']) : '';
                $__allowedCol = ['rojo','azul','verde','blanco'];
                $__color      = in_array($__color, $__allowedCol, true) ? $__color : '';
                $__colorClass = $__color ? ' row-color-'.htmlspecialchars($__color, ENT_QUOTES, 'UTF-8') : '';

                // --- padre/hijo por correo (padre = id más antiguo para ese correo)
                $u_key       = strtolower(trim($usuario));
                $isHead      = ($id === ($IPTV_HEADS_PERFILES[$u_key] ?? -1));
                $showCorreo  = $isHead;

                // Payload para modal EDITAR (incluye tipo)
                $rowIptv = [
                  'id'             => $id,
                  'nombre'         => $nombre,
                  'usuario'        => $usuario,
                  'password_plain' => $password,
                  'url'            => (string)$p['url'],
                  'whatsapp'       => $wa_raw,
                  'fecha_inicio'   => $fecha_inicio,
                  'fecha_fin'      => $fecha_fin,
                  'soles'          => $soles,
                  'estado'         => $estado,
                  'combo'          => $combo,
                  'tipo'           => 'perfil',
                ];

                $lines = ['Le hacemos la entrega de su IPTV'];
                if ($nombre_ui !== '') { $lines[] = "Nombre: {$nombre_ui}"; }
                $lines[] = "Usuario: {$usuario}";
                $lines[] = "Contraseña: {$password}";
                if ($url_raw !== '') { $lines[] = "URL: {$url_raw}"; }
                $lines[] = "Nota: no compartir su acceso, por favor.";
                $iptv_msg = rawurlencode(implode("\n", $lines));
            ?>
            <tr
  class="<?= trim(($showCorreo ? 'js-parent-row cursor-pointer' : 'js-child-row') . $__colorClass) ?>"
  data-row-kind="<?= $showCorreo ? 'parent' : 'child' ?>"
  data-color="<?= $__color ?: '' ?>"
  data-estado="<?= htmlspecialchars($estadoReal, ENT_QUOTES) ?>"
  data-combo="<?= $combo === 1 ? '1' : '0' ?>"
  data-nombre="<?= htmlspecialchars($nombre_ui, ENT_QUOTES) ?>"
  data-usuario="<?= htmlspecialchars($usuario, ENT_QUOTES) ?>"
  data-url="<?= htmlspecialchars($url_raw, ENT_QUOTES) ?>"
>
              <td class="iptv-cell-perfil" data-id="<?= $id ?>" role="button" tabindex="0">
                <?= $showCorreo ? htmlspecialchars($nombre_ui) : '' ?>
              </td>
              <td class="correo-cell"><?= $showCorreo ? htmlspecialchars($usuario) : '' ?></td>
              <td><?= $isHead ? htmlspecialchars($password) : '' ?></td>
              <td class="text-truncate">
                <?php if ($isHead && $url_raw !== ''): ?>
                  <a href="<?= htmlspecialchars($url_href, ENT_QUOTES) ?>" target="_blank" rel="noopener">
                    <?= htmlspecialchars($url_raw, ENT_QUOTES) ?>
                  </a>
                <?php endif; ?>
              </td>
              <td><?= $fi_fmt ?></td>
              <td><?= $ff_fmt ?></td>
              <td>
                <?php if ($dias === null): ?>
                <?php elseif ($dias < 0): ?>
                  <span class="text-danger"><?= $dias ?></span>
                <?php else: ?>
                  <?= $dias ?>
                <?php endif; ?>
              </td>
              <td class="cliente text-nowrap"><?= htmlspecialchars($cliente_display) ?></td>
              <td><?= $isHead ? '' : number_format((float)$soles, 2) ?></td>
              <td><?= $isHead ? '' : $comboLabel ?></td>
              <td>
                <?php if (!$isHead): ?>
                  <span class="badge <?= $badgeClass ?> text-capitalize"><?= htmlspecialchars($estadoReal) ?></span>
                <?php endif; ?>
              </td>
              <td class="whatsapp">
                <?php if (!$isHead && $wa_num !== ''): ?>
                  <a class="iptv-wa-link js-row-action"
                     data-scope="iptv" data-no-row-modal="1"
                     onclick="event.stopPropagation();"
                     href="https://wa.me/<?= htmlspecialchars($wa_num, ENT_QUOTES); ?>?text=<?= $iptv_msg; ?>"
                     target="_blank" rel="noopener"
                     aria-label="WhatsApp" title="WhatsApp">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                         fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                      <path d="M13.601 2.326A7.854 7.854 0 0 0 8.03.002C3.6.002.008 3.594.008 8.023c0 1.414.37 2.792 1.074 4.005L.01 16l3.996-1.05a7.96 7.96 0 0 0 4.024 1.073h.003c4.43 0 8.022-3.592 8.022-8.021 0-2.144-.835-4.162-2.354-5.676zM8.033 14.5h-.002a6.48 6.48 0 0 1-3.302-.905l-.237-.141-2.371.623.633-2.31-.154-.237A6.47 6.47 0 0 1 1.53 8.02c0-3.575 2.91-6.485 6.5-6.485 1.738 0 3.37.676 4.598 1.901a6.46 6.46 0 0 1 1.907 4.585c0 3.575-2.91 6.48-6.5 6.48zm3.69-4.844c-.202-.1-1.194-.59-1.378-.657-.184-.068-.318-.101-.452.1-.134.201-.518.657-.635.792-.117.134-.234.151-.436.05-.202-.1-.853-.314-1.625-1.002-.6-.533-1.005-1.19-1.123-1.392-.117-.201-.013-.31.088-.41.09-.089.202-.234.302-.351.101-.117.134-.201.202-.335.067-.134.034-.251-.017-.351-.05-.1-.452-1.09-.619-1.49-.163-.392-.329-.339-.452-.345l-.386-.007c-.118 0-.31.045-.471.224-.16.177-.618.604-.618 1.475s.633 1.71.72 1.83c.084.118 1.245 1.9 3.016 2.665.422.182.75.29 1.006.371.422.134.807.115 1.11.069.339-.05 1.194-.488 1.363-.96.168-.472.168-.877.118-.964-.05-.084-.184-.134-.386-.234z"/>
                    </svg>
                  </a>
                <?php endif; ?>
                <?php if (!$isHead && $tg_phone !== '' && $tg_phone !== '+'): ?>
                  <a class="ms-2 iptv-tg-link js-row-action"
                     data-scope="iptv" data-no-row-modal="1"
                     onclick="event.stopPropagation();"
                     href="https://t.me/share/url?url=&text=<?= $iptv_msg; ?>"
                     target="_blank" rel="noopener"
                     aria-label="Telegram" title="Telegram">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                         fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                      <path d="M15.953 1.737a1.01 1.01 0 0 0-1.04-.2L1.253 6.78c-.86.33-.854 1.54.01 1.86l3.17 1.18 1.24 3.98c.24.77 1.2.99 1.76.41l2.12-2.18 3.54 2.62c.73.54 1.79.14 1.98-.75l2.34-11.02a1.02 1.02 0 0 0-.46-1.18zM6.26 10.71l-.2 2.35 1.53-1.56 3.56-5.62-4.89 4.83z"/>
                    </svg>
                  </a>
                <?php endif; ?>
              </td>
              <td class="text-nowrap">
                <button type="button"
                        class="btn btn-sm btn-primary btn-edit js-row-action"
                        data-bs-toggle="modal"
                        data-bs-target="#modalEditarIptv"
                        data-row='<?= row_json_attr($rowIptv) ?>'>Editar</button>

                <form method="post" class="d-inline js-delete-form" action="<?= htmlspecialchars($DELETE_URL ?: '#', ENT_QUOTES) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $id ?>">
                  <input type="hidden" name="tipo" value="perfil">
                  <button type="submit" class="btn btn-sm btn-outline-danger js-row-action" <?= $DELETE_URL ? '' : 'disabled title="No hay endpoint de borrado"' ?>>Borrar</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB: CUENTAS -->
      <div class="tab-pane fade" id="iptv-cuentas" role="tabpanel" aria-labelledby="iptv-cuentas-tab" data-tipo="cuenta">
        <div class="d-flex justify-content-end mb-2">
          <button type="button" class="btn btn-sm btn-primary" id="btnAddCuenta"
                  data-bs-toggle="modal" data-bs-target="#modalAgregarCuenta">
            + Agregar Cuenta IPTV
          </button>
        </div>

        <!-- Filtro local: CUENTAS -->
        <div class="iptv-local-filters mb-2" data-scope="cuentas">
          <div class="row g-2 align-items-center">
            <div class="col-sm-4">
              <input type="search" class="form-control form-control-sm" placeholder="Buscar nombre, correo o URL" data-role="search">
            </div>
            <div class="col-sm-2">
              <select class="form-select form-select-sm" data-role="estado">
                <option value="">Estado: Todos</option>
                <option value="activo">Activo</option>
                <option value="pendiente">Pendiente</option>
                <option value="moroso">Moroso</option>
              </select>
            </div>
            <div class="col-sm-2">
              <select class="form-select form-select-sm" data-role="combo">
                <option value="">Combo: Todos</option>
                <option value="1">Sí</option>
                <option value="0">No</option>
              </select>
            </div>
            <div class="col-sm-2">
              <select class="form-select form-select-sm" data-role="color">
                <option value="">Color: Todos</option>
                <option value="rojo">Rojo</option>
                <option value="azul">Azul</option>
                <option value="verde">Verde</option>
                <option value="blanco">Blanco</option>
              </select>
            </div>
            <div class="col-sm-2">
              <button class="btn btn-sm btn-outline-secondary w-100" data-role="clear">Limpiar</button>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table align-middle table-bordered table-compact">
            <thead>
              <tr>
                <th>Nombre</th><th>Correo</th><th>Contraseña</th><th>URL</th>
                <th>Inicio</th><th>Fin</th><th>Días</th><th>Cliente</th>
                <th>Precio</th><th>Combo</th><th>Estado</th><th>Entrega</th><th>Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php
              foreach ($iptv_cuentas as $p):
                $id            = (int)($p['id'] ?? 0);
                $nombre        = trim((string)($p['nombre'] ?? ''));
                $usuario       = (string)($p['usuario'] ?? '');
                $password      = (string)($p['password_plain'] ?? '');
                $url_raw       = trim((string)($p['url'] ?? ''));
                $wa_raw        = (string)($p['whatsapp'] ?? '');
                $fecha_inicio  = (string)($p['fecha_inicio'] ?? '');
                $fecha_fin     = (string)($p['fecha_fin'] ?? '');
                $soles         = (string)($p['soles'] ?? '0.00');
                $estado        = (string)($p['estado'] ?? 'activo');
                $combo         = (int)($p['combo'] ?? 0);

                $url_href  = $url_raw && !preg_match('#^https?://#i', $url_raw) ? ('https://' . $url_raw) : $url_raw;
                $nombre_ui = ($nombre !== '' ? $nombre : '(sin nombre)');

                $fi_ok  = ($fecha_inicio && $fecha_inicio !== '0000-00-00');
                $ff_ok  = ($fecha_fin    && $fecha_fin    !== '0000-00-00');
                $fi_fmt = $fi_ok ? date('d/m/y', strtotime($fecha_inicio)) : '';
                $ff_fmt = $ff_ok ? date('d/m/y', strtotime($fecha_fin))    : '';

                $dias = $ff_ok ? (int) floor((strtotime($fecha_fin) - strtotime($hoy)) / 86400) : null;
                $estadoReal = ($ff_ok && $dias < 0) ? 'moroso' : $estado;
                $badgeClass = estado_badge_class($estadoReal);
                $comboLabel = $combo === 1 ? 'Sí' : 'No';

                $__wa = preg_replace('/\s+/', '', $wa_raw);
                $__wa = preg_replace('/(?!^)\+/', '', $__wa);
                $__wa = preg_replace('/[^\d\+]/', '', $__wa);
                if ($__wa === '+') $__wa = '';

                $wa_num          = ltrim($__wa, '+');
                $tg_phone        = ($__wa !== '' && $__wa[0] === '+') ? $__wa : ($__wa !== '' ? ('+' . $__wa) : '');
                $cliente_display = format_cliente_num($__wa, $wa_num);

                $__color      = isset($p['color']) ? strtolower((string)$p['color']) : '';
                $__allowedCol = ['rojo','azul','verde','blanco'];
                $__color      = in_array($__color, $__allowedCol, true) ? $__color : '';
                $__colorClass = $__color ? ' row-color-'.htmlspecialchars($__color, ENT_QUOTES, 'UTF-8') : '';

                $lines = ['Le hacemos la entrega de su IPTV'];
                if ($nombre_ui !== '') { $lines[] = "Nombre: {$nombre_ui}"; }
                $lines[] = "Usuario: {$usuario}";
                $lines[] = "Contraseña: {$password}";
                if ($url_raw !== '') { $lines[] = "URL: {$url_raw}"; }
                $lines[] = "Nota: no compartir su acceso, por favor.";
                $iptv_msg = rawurlencode(implode("\n", $lines));
            ?>
            <tr
  class="<?= trim($__colorClass) ?>"
  data-row-kind="single"
  data-color="<?= $__color ?: '' ?>"
  data-estado="<?= htmlspecialchars($estadoReal, ENT_QUOTES) ?>"
  data-combo="<?= $combo === 1 ? '1' : '0' ?>"
  data-nombre="<?= htmlspecialchars($nombre_ui, ENT_QUOTES) ?>"
  data-usuario="<?= htmlspecialchars($usuario, ENT_QUOTES) ?>"
  data-url="<?= htmlspecialchars($url_raw, ENT_QUOTES) ?>"
>
              <td class="iptv-cell-perfil" data-id="<?= $id ?>" role="button" tabindex="0">
                <?= htmlspecialchars($nombre_ui) ?>
              </td>
              <td class="correo-cell"><?= htmlspecialchars($usuario) ?></td>
              <td><?= htmlspecialchars($password) ?></td>
              <td class="text-truncate">
                <?php if ($url_raw !== ''): ?>
                  <a href="<?= htmlspecialchars($url_href, ENT_QUOTES) ?>" target="_blank" rel="noopener">
                    <?= htmlspecialchars($url_raw, ENT_QUOTES) ?>
                  </a>
                <?php endif; ?>
              </td>
              <td><?= $fi_fmt ?></td>
              <td><?= $ff_fmt ?></td>
              <td>
                <?php if ($dias === null): ?>
                <?php elseif ($dias < 0): ?>
                  <span class="text-danger"><?= $dias ?></span>
                <?php else: ?>
                  <?= $dias ?>
                <?php endif; ?>
              </td>
              <td class="cliente text-nowrap"><?= htmlspecialchars($cliente_display) ?></td>
              <td><?= number_format((float)$soles, 2) ?></td>
              <td><?= $comboLabel ?></td>
              <td><span class="badge <?= $badgeClass ?> text-capitalize"><?= htmlspecialchars($estadoReal) ?></span></td>
              <td class="whatsapp">
                <?php if ($wa_num !== ''): ?>
                  <a class="iptv-wa-link js-row-action"
                     data-scope="iptv" data-no-row-modal="1"
                     onclick="event.stopPropagation();"
                     href="https://wa.me/<?= htmlspecialchars($wa_num, ENT_QUOTES); ?>?text=<?= $iptv_msg; ?>"
                     target="_blank" rel="noopener"
                     aria-label="WhatsApp" title="WhatsApp">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                         fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                      <path d="M13.601 2.326A7.854 7.854 0 0 0 8.03.002C3.6.002.008 3.594.008 8.023c0 1.414.37 2.792 1.074 4.005L.01 16l3.996-1.05a7.96 7.96 0 0 0 4.024 1.073h.003c4.43 0 8.022-3.592 8.022-8.021 0-2.144-.835-4.162-2.354-5.676zM8.033 14.5h-.002a6.48 6.48 0 0 1-3.302-.905l-.237-.141-2.371.623.633-2.31-.154-.237A6.47 6.47 0 0 1 1.53 8.02c0-3.575 2.91-6.485 6.5-6.485 1.738 0 3.37.676 4.598 1.901a6.46 6.46 0 0 1 1.907 4.585c0 3.575-2.91 6.48-6.5 6.48zm3.69-4.844c-.202-.1-1.194-.59-1.378-.657-.184-.068-.318-.101-.452.1-.134.201-.518.657-.635.792-.117.134-.234.151-.436.05-.202-.1-.853-.314-1.625-1.002-.6-.533-1.005-1.19-1.123-1.392-.117-.201-.013-.31.088-.41.09-.089.202-.234.302-.351.101-.117.134-.201.202-.335.067-.134.034-.251-.017-.351-.05-.1-.452-1.09-.619-1.49-.163-.392-.329-.339-.452-.345l-.386-.007c-.118 0-.31.045-.471.224-.16.177-.618.604-.618 1.475s.633 1.71.72 1.83c.084.118 1.245 1.9 3.016 2.665.422.182.75.29 1.006.371.422.134.807.115 1.11.069.339-.05 1.194-.488 1.363-.96.168-.472.168-.877.118-.964-.05-.084-.184-.134-.386-.234z"/>
                    </svg>
                  </a>
                <?php endif; ?>
                <?php if ($tg_phone !== '' && $tg_phone !== '+'): ?>
                  <a class="ms-2 iptv-tg-link js-row-action"
                     data-scope="iptv" data-no-row-modal="1"
                     onclick="event.stopPropagation();"
                     href="https://t.me/share/url?url=&text=<?= $iptv_msg; ?>"
                     target="_blank" rel="noopener"
                     aria-label="Telegram" title="Telegram">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                         fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                      <path d="M15.953 1.737a1.01 1.01 0 0 0-1.04-.2L1.253 6.78c-.86.33-.854 1.54.01 1.86l3.17 1.18 1.24 3.98c.24.77 1.2.99 1.76.41l2.12-2.18 3.54 2.62c.73.54 1.79.14 1.98-.75l2.34-11.02a1.02 1.02 0 0 0-.46-1.18zM6.26 10.71l-.2 2.35 1.53-1.56 3.56-5.62-4.89 4.83z"/>
                    </svg>
                  </a>
                <?php endif; ?>
              </td>
              <td class="text-nowrap">
                <button type="button"
                        class="btn btn-sm btn-primary btn-edit js-row-action"
                        data-bs-toggle="modal"
                        data-bs-target="#modalEditarIptv"
                        data-row='<?= row_json_attr([
                          "id"=>$id,"nombre"=>$nombre,"usuario"=>$usuario,"password_plain"=>$password,
                          "url"=>(string)$p["url"],"whatsapp"=>$wa_raw,"fecha_inicio"=>$fecha_inicio,
                          "fecha_fin"=>$fecha_fin,"soles"=>$soles,"estado"=>$estado,"combo"=>$combo,"tipo"=>"cuenta"
                        ]) ?>'>Editar</button>

                <form method="post" class="d-inline js-delete-form" action="<?= htmlspecialchars($DELETE_URL ?: '#', ENT_QUOTES) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $id ?>">
                  <input type="hidden" name="tipo" value="cuenta">
                  <button type="submit" class="btn btn-sm btn-outline-danger js-row-action" <?= $DELETE_URL ? '' : 'disabled title="No hay endpoint de borrado"' ?>>Borrar</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  <?php else: ?>
    <!-- LEGACY: una sola tabla (se muestra como "Cuentas") -->
    <div class="table-responsive">
      <table class="table align-middle table-bordered table-compact">
        <thead>
          <tr>
            <th>Nombre</th><th>Correo</th><th>Contraseña</th><th>URL</th>
            <th>Inicio</th><th>Fin</th><th>Días</th><th>Cliente</th>
            <th>Precio</th><th>Combo</th><th>Estado</th><th>Entrega</th><th>Acciones</th>
          </tr>
        </thead>
        <tbody>
<?php
  foreach ($iptv_cuentas as $p):
    $id            = (int)($p['id'] ?? 0);
    $nombre        = trim((string)($p['nombre'] ?? ''));
    $usuario       = (string)($p['usuario'] ?? '');
    $password      = (string)($p['password_plain'] ?? '');
    $url_raw       = trim((string)($p['url'] ?? ''));
    $wa_raw        = (string)($p['whatsapp'] ?? '');
    $fecha_inicio  = (string)($p['fecha_inicio'] ?? '');
    $fecha_fin     = (string)($p['fecha_fin'] ?? '');
    $soles         = (string)($p['soles'] ?? '0.00');
    $estado        = (string)($p['estado'] ?? 'activo');
    $combo         = (int)($p['combo'] ?? 0);

    $url_href = $url_raw && !preg_match('#^https?://#i', $url_raw) ? ('https://' . $url_raw) : $url_raw;
    $nombre_ui = ($nombre !== '' ? $nombre : '(sin nombre)');

    $fi_ok  = ($fecha_inicio && $fecha_inicio !== '0000-00-00');
    $ff_ok  = ($fecha_fin    && $fecha_fin    !== '0000-00-00');
    $fi_fmt = $fi_ok ? date('d/m/y', strtotime($fecha_inicio)) : '';
    $ff_fmt = $ff_ok ? date('d/m/y', strtotime($fecha_fin))    : '';

    $dias = $ff_ok ? (int) floor((strtotime($fecha_fin) - strtotime($hoy)) / 86400) : null;
    $estadoReal = ($ff_ok && $dias < 0) ? 'moroso' : $estado;
    $badgeClass = estado_badge_class($estadoReal);
    $comboLabel = $combo === 1 ? 'Sí' : 'No';

    $__wa = preg_replace('/\s+/', '', $wa_raw);
    $__wa = preg_replace('/(?!^)\+/', '', $__wa);
    $__wa = preg_replace('/[^\d\+]/', '', $__wa);
    if ($__wa === '+') $__wa = '';

    $wa_num          = ltrim($__wa, '+');
    $tg_phone        = ($__wa !== '' && $__wa[0] === '+') ? $__wa : ($__wa !== '' ? ('+' . $__wa) : '');
    $cliente_display = format_cliente_num($__wa, $wa_num);

    $__color      = isset($p['color']) ? strtolower((string)$p['color']) : '';
    $__allowedCol = ['rojo','azul','verde','blanco'];
    $__color      = in_array($__color, $__allowedCol, true) ? $__color : '';
    $__colorClass = $__color ? ' row-color-'.htmlspecialchars($__color, ENT_QUOTES, 'UTF-8') : '';

    // --- padre/hijo por correo (legacy usa cuentas)
    $u_key       = strtolower(trim($usuario));
    $isHead      = ($id === ($IPTV_HEADS_CUENTAS[$u_key] ?? -1));
    $showCorreo  = $isHead;

    $rowIptv = [
      'id'             => $id,
      'nombre'         => $nombre,
      'usuario'        => $usuario,
      'password_plain' => $password,
      'url'            => (string)$p['url'],
      'whatsapp'       => $wa_raw,
      'fecha_inicio'   => $fecha_inicio,
      'fecha_fin'      => $fecha_fin,
      'soles'          => $soles,
      'estado'         => $estado,
      'combo'          => $combo,
      'tipo'           => 'cuenta',
    ];

    $lines = ['Le hacemos la entrega de su IPTV'];
    if ($nombre_ui !== '') { $lines[] = "Nombre: {$nombre_ui}"; }
    $lines[] = "Usuario: {$usuario}";
    $lines[] = "Contraseña: {$password}";
    if ($url_raw !== '') { $lines[] = "URL: {$url_raw}"; }
    $lines[] = "Nota: no compartir su acceso, por favor.";
    $iptv_msg = rawurlencode(implode("\n", $lines));
?>
<tr class="<?= trim(($showCorreo ? 'js-parent-row cursor-pointer' : 'js-child-row') . $__colorClass) ?>">

  <td class="iptv-cell-perfil" data-id="<?= $id ?>" role="button" tabindex="0">
    <?= $showCorreo ? htmlspecialchars($nombre_ui) : '' ?>
  </td>
  <td class="correo-cell"><?= $showCorreo ? htmlspecialchars($usuario) : '' ?></td>
  <td><?= $isHead ? htmlspecialchars($password) : '' ?></td>
  <td class="text-truncate">
    <?php if ($isHead && $url_raw !== ''): ?>
      <a href="<?= htmlspecialchars($url_href, ENT_QUOTES) ?>" target="_blank" rel="noopener">
        <?= htmlspecialchars($url_raw, ENT_QUOTES) ?>
      </a>
    <?php endif; ?>
  </td>
  <td><?= $fi_fmt ?></td>
  <td><?= $ff_fmt ?></td>
  <td>
    <?php if ($dias === null): ?>
    <?php elseif ($dias < 0): ?>
      <span class="text-danger"><?= $dias ?></span>
    <?php else: ?>
      <?= $dias ?>
    <?php endif; ?>
  </td>
  <td class="cliente text-nowrap"><?= htmlspecialchars($cliente_display) ?></td>
  <td><?= $isHead ? '' : number_format((float)$soles, 2) ?></td>
  <td><?= $isHead ? '' : $comboLabel ?></td>
  <td>
    <?php if (!$isHead): ?>
      <span class="badge <?= $badgeClass ?> text-capitalize"><?= htmlspecialchars($estadoReal) ?></span>
    <?php endif; ?>
  </td>
  <td class="whatsapp">
    <?php if (!$isHead && $wa_num !== ''): ?>
      <a class="iptv-wa-link js-row-action"
         data-scope="iptv" data-no-row-modal="1"
         onclick="event.stopPropagation();"
         href="https://wa.me/<?= htmlspecialchars($wa_num, ENT_QUOTES); ?>?text=<?= $iptv_msg; ?>"
         target="_blank" rel="noopener"
         aria-label="WhatsApp" title="WhatsApp">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M13.601 2.326A7.854 7.854 0 0 0 8.03.002C3.6.002.008 3.594.008 8.023c0 1.414.37 2.792 1.074 4.005L.01 16l3.996-1.05a7.96 7.96 0  0 0 4.024 1.073h.003c4.43 0 8.022-3.592 8.022-8.021 0-2.144-.835-4.162-2.354-5.676zM8.033 14.5h-.002a6.48 6.48 0 0 1-3.302-.905l-.237-.141-2.371.623.633-2.31-.154-.237A6.47 6.47 0 0 1 1.53 8.02c0-3.575 2.91-6.485 6.5-6.485 1.738 0 3.37.676 4.598 1.901a6.46 6.46 0 0 1 1.907 4.585c0 3.575-2.91 6.48-6.5 6.48zm3.69-4.844c-.202-.1-1.194-.59-1.378-.657-.184-.068-.318-.101-.452.1-.134.201-.518.657-.635.792-.117.134-.234.151-.436.05-.202-.1-.853-.314-1.625-1.002-.6-.533-1.005-1.19-1.123-1.392-.117-.201-.013-.31.088-.41.09-.089.202-.234.302-.351.101-.117.134-.201.202-.335.067-.134.034-.251-.017-.351-.05-.1-.452-1.09-.619-1.49-.163-.392-.329-.339-.452-.345l-.386-.007c-.118 0-.31.045-.471.224-.16.177-.618.604-.618 1.475s.633 1.71.72 1.83c.084.118 1.245 1.9 3.016 2.665.422.182.75.29 1.006.371.422.134.807.115 1.11.069.339-.05 1.194-.488 1.363-.96.168-.472.168-.877.118-.964-.05-.084-.184-.134-.386-.234z"/></svg>
      </a>
    <?php endif; ?>
    <?php if (!$isHead && $tg_phone !== '' && $tg_phone !== '+'): ?>
      <a class="ms-2 iptv-tg-link js-row-action"
         data-scope="iptv" data-no-row-modal="1"
         onclick="event.stopPropagation();"
         href="https://t.me/share/url?url=&text=<?= $iptv_msg; ?>"
         target="_blank" rel="noopener"
         aria-label="Telegram" title="Telegram">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M15.953 1.737a1.01 1.01 0 0 0-1.04-.2L1.253 6.78c-.86.33-.854 1.54.01 1.86l3.17 1.18 1.24 3.98c.24.77 1.2.99 1.76.41l2.12-2.18 3.54 2.62c.73.54 1.79.14 1.98-.75l2.34-11.02a1.02 1.02 0 0 0-.46-1.18z"/></svg>
      </a>
    <?php endif; ?>
  </td>
  <td class="text-nowrap">
    <button type="button"
            class="btn btn-sm btn-primary btn-edit js-row-action"
            data-bs-toggle="modal"
            data-bs-target="#modalEditarIptv"
            data-row='<?= row_json_attr($rowIptv) ?>'>Editar</button>

    <form method="post" class="d-inline js-delete-form" action="<?= htmlspecialchars($DELETE_URL ?: '#', ENT_QUOTES) ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="tipo" value="cuenta">
      <button type="submit" class="btn btn-sm btn-outline-danger js-row-action" <?= $DELETE_URL ? '' : 'disabled title="No hay endpoint de borrado"' ?>>Borrar</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ===================== MODAL EDITAR ===================== -->
<div class="modal fade" id="modalEditarIptv" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" action="<?= htmlspecialchars($SAVE_URL, ENT_QUOTES) ?>" method="post" id="formEditarIptv">
      <div class="modal-header">
        <h5 class="modal-title" id="editTitle">Editar IPTV</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="id" id="iptv_id" value="0">
        <input type="hidden" name="tipo" id="iptv_tipo" value="cuenta">

        <div class="mb-2">
          <label class="form-label form-label-sm">Nombre</label>
          <input type="text" class="form-control form-control-sm" name="nombre" id="iptv_nombre" autocomplete="off">
        </div>
        <div class="mb-2">
          <label class="form-label form-label-sm">Usuario (correo/alias)</label>
          <input type="text" class="form-control form-control-sm" name="usuario" id="iptv_usuario" autocomplete="off" required>
        </div>
        <div class="mb-2">
          <label class="form-label form-label-sm">Contraseña</label>
          <input type="text" class="form-control form-control-sm" name="password_plain" id="iptv_password" autocomplete="off" required>
        </div>
        <div class="mb-2">
          <label class="form-label form-label-sm">URL</label>
          <input type="text" class="form-control form-control-sm" name="url" id="iptv_url" placeholder="https://..." autocomplete="off" required>
        </div>

        <div class="mb-2">
          <label class="form-label form-label-sm d-block">WhatsApp</label>
          <div class="input-group input-group-sm" style="max-width: 320px;">
            <input type="text" class="form-control" name="wa_cc" id="iptv_wa_cc" placeholder="+51" inputmode="numeric" pattern="[0-9+]{1,5}" style="max-width: 80px;">
            <span class="input-group-text">—</span>
            <input type="text" class="form-control" name="wa_local" id="iptv_wa_local" placeholder="977 948 954" inputmode="numeric" pattern="[0-9 ]{6,20}" maxlength="20">
          </div>
          <div class="form-text">Escribe el número local en grupos (3-3-3). El prefijo es opcional.</div>
        </div>

        <div class="row g-2">
          <div class="col-6">
            <label class="form-label form-label-sm">Inicio</label>
            <input type="date" class="form-control form-control-sm" name="fecha_inicio" id="iptv_fi">
          </div>
          <div class="col-6">
            <label class="form-label form-label-sm">Fin</label>
            <input type="date" class="form-control form-control-sm" name="fecha_fin" id="iptv_ff">
          </div>
        </div>

        <div class="row g-2 mt-1">
          <div class="col-4">
            <label class="form-label form-label-sm">Precio (S/)</label>
            <input type="text" class="form-control form-control-sm" name="soles" id="iptv_soles" placeholder="0.00">
          </div>
          <div class="col-4">
            <label class="form-label form-label-sm">Estado</label>
            <select class="form-select form-select-sm" name="estado" id="iptv_estado">
              <option value="activo">Activo</option>
              <option value="pendiente">Pendiente</option>
            </select>
          </div>
        </div>

        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" value="1" id="iptv_combo" name="combo">
          <label class="form-check-label" for="iptv_combo">Combo</label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- ===================== MODAL AGREGAR PERFIL ===================== -->
<div class="modal fade" id="modalAgregarPerfil" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" action="<?= htmlspecialchars($SAVE_URL, ENT_QUOTES) ?>" method="post" id="formAgregarPerfil">
      <div class="modal-header">
        <h5 class="modal-title">Agregar Perfil IPTV</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" value="0">
        <input type="hidden" name="tipo" value="perfil">

        <div class="mb-2">
          <label class="form-label form-label-sm">Nombre</label>
          <input type="text" class="form-control form-control-sm" name="nombre" autocomplete="off">
        </div>
        <div class="mb-2">
          <label class="form-label form-label-sm">Usuario (correo/alias)</label>
          <input type="text" class="form-control form-control-sm" name="usuario" autocomplete="off" required>
        </div>
        <div class="mb-2">
          <label class="form-label form-label-sm">Contraseña</label>
          <input type="text" class="form-control form-control-sm" name="password_plain" autocomplete="off" required>
        </div>
        <div class="mb-2">
          <label class="form-label form-label-sm">URL</label>
          <input type="text" class="form-control form-control-sm" name="url" placeholder="https://..." autocomplete="off" required>
        </div>

        <div class="mb-2">
          <label class="form-label form-label-sm d-block">WhatsApp</label>
          <div class="input-group input-group-sm" style="max-width: 320px;">
            <input type="text" class="form-control" name="wa_cc" placeholder="+51" inputmode="numeric" pattern="[0-9+]{1,5}" style="max-width: 80px;">
            <span class="input-group-text">—</span>
            <input type="text" class="form-control" name="wa_local" placeholder="977 948 954" inputmode="numeric" pattern="[0-9 ]{6,20}" maxlength="20">
          </div>
        </div>

        <div class="row g-2">
          <div class="col-6">
            <label class="form-label form-label-sm">Inicio</label>
            <input type="date" class="form-control form-control-sm" name="fecha_inicio" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-6">
            <label class="form-label form-label-sm">Fin</label>
            <input type="date" class="form-control form-control-sm" name="fecha_fin" value="<?= date('Y-m-d', strtotime('+31 days')) ?>">
          </div>
        </div>

        <div class="row g-2 mt-1">
          <div class="col-4">
            <label class="form-label form-label-sm">Precio (S/)</label>
            <input type="text" class="form-control form-control-sm" name="soles" placeholder="0.00" value="0.00">
          </div>
          <div class="col-4">
            <label class="form-label form-label-sm">Estado</label>
            <select class="form-select form-select-sm" name="estado">
              <option value="activo" selected>Activo</option>
              <option value="pendiente">Pendiente</option>
            </select>
          </div>
        </div>

        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" value="1" id="perfil_combo" name="combo">
          <label class="form-check-label" for="perfil_combo">Combo</label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- ===================== MODAL AGREGAR CUENTA ===================== -->
<div class="modal fade" id="modalAgregarCuenta" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" action="<?= htmlspecialchars($SAVE_URL, ENT_QUOTES) ?>" method="post" id="formAgregarCuenta">
      <div class="modal-header">
        <h5 class="modal-title">Agregar Cuenta IPTV</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" value="0">
        <input type="hidden" name="tipo" value="cuenta">

        <div class="mb-2">
          <label class="form-label form-label-sm">Nombre</label>
          <input type="text" class="form-control form-control-sm" name="nombre" autocomplete="off">
        </div>
        <div class="mb-2">
          <label class="form-label form-label-sm">Usuario (correo/alias)</label>
          <input type="text" class="form-control form-control-sm" name="usuario" autocomplete="off" required>
        </div>
        <div class="mb-2">
          <label class="form-label form-label-sm">Contraseña</label>
          <input type="text" class="form-control form-control-sm" name="password_plain" autocomplete="off" required>
        </div>
        <div class="mb-2">
          <label class="form-label form-label-sm">URL</label>
          <input type="text" class="form-control form-control-sm" name="url" placeholder="https://..." autocomplete="off" required>
        </div>

        <div class="mb-2">
          <label class="form-label form-label-sm d-block">WhatsApp</label>
          <div class="input-group input-group-sm" style="max-width: 320px;">
            <input type="text" class="form-control" name="wa_cc" placeholder="+51" inputmode="numeric" pattern="[0-9+]{1,5}" style="max-width: 80px;">
            <span class="input-group-text">—</span>
            <input type="text" class="form-control" name="wa_local" placeholder="977 948 954" inputmode="numeric" pattern="[0-9 ]{6,20}" maxlength="20">
          </div>
        </div>

        <div class="row g-2">
          <div class="col-6">
            <label class="form-label form-label-sm">Inicio</label>
            <input type="date" class="form-control form-control-sm" name="fecha_inicio" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-6">
            <label class="form-label form-label-sm">Fin</label>
            <input type="date" class="form-control form-control-sm" name="fecha_fin" value="<?= date('Y-m-d', strtotime('+31 days')) ?>">
          </div>
        </div>

        <div class="row g-2 mt-1">
          <div class="col-4">
            <label class="form-label form-label-sm">Precio (S/)</label>
            <input type="text" class="form-control form-control-sm" name="soles" placeholder="0.00" value="0.00">
          </div>
          <div class="col-4">
            <label class="form-label form-label-sm">Estado</label>
            <select class="form-select form-select-sm" name="estado">
              <option value="activo" selected>Activo</option>
              <option value="pendiente">Pendiente</option>
            </select>
          </div>
        </div>

        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" value="1" id="cuenta_combo" name="combo">
          <label class="form-check-label" for="cuenta_combo">Combo</label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- ===================== MODAL AGREGAR PERFIL A CORREO (HIJO) ===================== -->
<div class="modal fade" id="modalAgregarPerfilHijo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" action="<?= htmlspecialchars($SAVE_URL, ENT_QUOTES) ?>" method="post" id="formAgregarPerfilHijo">
      <div class="modal-header">
        <h5 class="modal-title">Agregar perfil al correo: <span id="correoHijoTitle"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="id" value="0">
        <input type="hidden" name="tipo" value="perfil">
        <input type="hidden" name="usuario" id="iptv_hijo_usuario">

        <div class="mb-2">
          <label class="form-label form-label-sm">Correo</label>
          <input type="text" class="form-control form-control-sm" id="iptv_hijo_usuario_view" readonly>
          <div class="form-text">Este correo se hereda del registro padre y no se puede modificar.</div>
        </div>

        <div class="mb-2">
          <label class="form-label form-label-sm">Nombre (heredado)</label>
          <input type="text" class="form-control form-control-sm" name="nombre" id="iptv_hijo_nombre" readonly>
          <div class="form-text">Si el padre no tiene nombre, este campo quedará en blanco.</div>
        </div>

        <div class="mb-2">
          <label class="form-label form-label-sm">Contraseña (heredada)</label>
          <input type="text" class="form-control form-control-sm" name="password_plain" id="iptv_hijo_password" readonly>
          <div class="form-text">Se copia del padre para visualizarla, pero no se edita aquí.</div>
        </div>

        <div class="mb-2">
          <label class="form-label form-label-sm">URL (heredada)</label>
          <input type="text" class="form-control form-control-sm" name="url" id="iptv_hijo_url" readonly>
          <div class="form-text">Se copia del padre para visualizarla, pero no se edita aquí.</div>
        </div>

        <div class="mb-2">
          <label class="form-label form-label-sm d-block">WhatsApp</label>
          <div class="input-group input-group-sm" style="max-width: 320px;">
            <input type="text" class="form-control" name="wa_cc" placeholder="+51" inputmode="numeric" pattern="[0-9+]{1,5}" style="max-width: 80px;">
            <span class="input-group-text">—</span>
            <input type="text" class="form-control" name="wa_local" placeholder="977 948 954" inputmode="numeric" pattern="[0-9 ]{6,20}" maxlength="20">
          </div>
        </div>

        <div class="row g-2">
          <div class="col-6">
            <label class="form-label form-label-sm">Inicio</label>
            <input type="date" class="form-control form-control-sm" name="fecha_inicio">
          </div>
          <div class="col-6">
            <label class="form-label form-label-sm">Fin</label>
            <input type="date" class="form-control form-control-sm" name="fecha_fin">
          </div>
        </div>

        <div class="row g-2 mt-1">
          <div class="col-4">
            <label class="form-label form-label-sm">Precio (S/)</label>
            <input type="text" class="form-control form-control-sm" name="soles" placeholder="0.00" value="0.00">
          </div>
          <div class="col-4">
            <label class="form-label form-label-sm">Estado</label>
            <select class="form-select form-select-sm" name="estado">
              <option value="activo" selected>Activo</option>
              <option value="pendiente">Pendiente</option>
            </select>
          </div>
        </div>

        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" value="1" id="perfil_hijo_combo" name="combo">
          <label class="form-check-label" for="perfil_hijo_combo">Combo</label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- ===================== MODAL CAMBIAR COLOR ===================== -->
<div class="modal fade" id="modalCambiarColor" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="formCambiarColor">
      <div class="modal-header">
        <h5 class="modal-title">Cambiar color</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="cc_id" value="0">
        <input type="hidden" name="tipo" id="cc_tipo" value="">
        <div class="mb-2">
          <label class="form-label form-label-sm">Color de la fila</label>
          <select class="form-select form-select-sm" name="color" id="cc_color" required>
            <option value="">Sin color</option>
            <option value="blanco">Blanco</option>
            <option value="rojo">Rojo</option>
            <option value="azul">Azul</option>
            <option value="verde">Verde</option>
          </select>
          <div class="form-text">Se aplicará al grupo (fila actual).</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- ===================== /MODALES ===================== -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Endpoints expuestos a JS (renderizados por PHP)
window.IPTV_ENDPOINTS = {
  save: '<?= htmlspecialchars($SAVE_URL, ENT_QUOTES) ?>',
  del : '<?= htmlspecialchars($DELETE_URL, ENT_QUOTES) ?>',
  color: '<?= htmlspecialchars($COLOR_URL, ENT_QUOTES) ?>'
};
</script>

<script>
// ----------- ÚNICO BLOQUE JS (CRUD + acciones) -----------
(function () {
  const EP = window.IPTV_ENDPOINTS || {};
  const SAVE_URL   = EP.save  || '';
  const DELETE_URL = EP.del   || '';

  function swalOK(t,m){return window.Swal?.fire?Swal.fire({icon:'success',title:t,text:m}):(alert(t+'\n'+m),Promise.resolve());}
  function swalWarn(t,m){return window.Swal?.fire?Swal.fire({icon:'warning',title:t,text:m}):(alert(t+'\n'+m),Promise.resolve());}
  function swalErr(t,m){return window.Swal?.fire?Swal.fire({icon:'error',title:t,text:m}):(alert(t+'\n'+m),Promise.resolve());}

  document.addEventListener('click', function (ev) {
    const btn = ev.target.closest('.btn-edit[data-bs-target="#modalEditarIptv"]');
    if (!btn) return;
    let data = {};
    try { data = JSON.parse(btn.getAttribute('data-row') || '{}'); } catch(e){}
    const $ = (id)=> document.getElementById(id);
    const set = (id,v)=>{ const el=$(id); if(el) el.value = (v ?? ''); };

    set('iptv_id', data.id || 0);
    set('iptv_tipo', (data.tipo === 'perfil') ? 'perfil' : 'cuenta');
    set('iptv_nombre', data.nombre ?? '');
    set('iptv_usuario', data.usuario ?? '');
    set('iptv_password', data.password_plain ?? '');
    set('iptv_url', data.url ?? '');
    set('iptv_soles', data.soles ?? '0.00');
    set('iptv_estado', (data.estado === 'pendiente') ? 'pendiente' : 'activo');
    const combo = document.getElementById('iptv_combo'); if (combo) combo.checked = !!(Number(data.combo ?? 0) === 1);

    const fi = (data.fecha_inicio && data.fecha_inicio !== '0000-00-00') ? data.fecha_inicio : '';
    const ff = (data.fecha_fin    && data.fecha_fin    !== '0000-00-00') ? data.fecha_fin    : '';
    set('iptv_fi', fi); set('iptv_ff', ff);

    const raw = (data.whatsapp ?? '').toString().trim();
    let digits = raw.replace(/\s+/g,'').replace(/(?!^)\+/g,'').replace(/[^\d\+]/g,''); if (digits === '+') digits = '';
    let cc = '', local = ''; const justNums = digits.replace(/\D/g, '');
    if (justNums.length > 9) { cc = justNums.slice(0, justNums.length - 9); local = justNums.slice(-9); }
    else { local = justNums; }
    set('iptv_wa_cc', cc ? ('+'+cc) : '');
    set('iptv_wa_local', local ? local.replace(/(\d{3})(?=\d)/g,'$1 ').trim() : '');

    const title = document.getElementById('editTitle');
    if (title) title.textContent = 'Editar ' + (data.tipo === 'perfil' ? 'Perfil' : 'Cuenta') + ' IPTV';
  });

  async function saveForm(form, override = {}) {
    if (!SAVE_URL) { await swalErr('Config', 'SAVE_URL no está configurado'); return; }
    if (form.dataset.sending === '1') return;

    const q = (n)=> form.querySelector(`[name="${n}"]`);
    const v = (n)=> (q(n)?.value || '').trim();
    const c = (n)=> !!q(n)?.checked;

    const id = Number(v('id') || 0);
    const allowBlankCore = !!override.allowBlankCore;
    const cleanOverride = {...override}; delete cleanOverride.allowBlankCore;

    const payload = {
      action: id > 0 ? 'update' : 'create',
      id,
      tipo: (v('tipo') === 'perfil') ? 'perfil' : 'cuenta',
      nombre: v('nombre'),
      usuario: v('usuario'),
      password_plain: v('password_plain'),
      url: v('url'),
      wa_cc: v('wa_cc'),
      wa_local: v('wa_local'),
      fecha_inicio: v('fecha_inicio'),
      fecha_fin: v('fecha_fin'),
      soles: v('soles') || '0.00',
      estado: (v('estado') === 'pendiente') ? 'pendiente' : 'activo',
      combo: c('combo') ? 1 : 0,
      ...cleanOverride
    };

    if (!payload.usuario || (!allowBlankCore && (!payload.password_plain || !payload.url))) {
      await swalWarn('Campos incompletos', allowBlankCore
        ? 'Falta el usuario.'
        : 'Usuario, contraseña y URL son obligatorios.');
      return;
    }

    try {
      form.dataset.sending = '1';
      const res = await fetch(SAVE_URL, {
        method: 'POST',
        headers: {'Content-Type':'application/json','Accept':'application/json'},
        body: JSON.stringify(payload),
        credentials: 'same-origin'
      });
      const js = await res.json().catch(()=>({ok:false,error:'Respuesta inválida'}));
      if (!res.ok || !js.ok) throw new Error(js.error || ('HTTP '+res.status));

      const modalEl = form.closest('.modal');
      if (modalEl && window.bootstrap?.Modal) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      await swalOK(id>0?'Actualizado':'Guardado', 'Operación exitosa.');
      location.reload();
    } catch(e) {
      await swalErr('Error', e.message || 'No se pudo guardar');
    } finally {
      form.dataset.sending = '';
    }
  }

  function bindOnce(id, handler){
    const f = document.getElementById(id);
    if (!f || f.dataset.bound) return;
    f.dataset.bound = '1';
    f.addEventListener('submit', handler);
  }

  bindOnce('formAgregarPerfil', function(ev){
    ev.preventDefault(); ev.stopPropagation();
    this.querySelector('[name="id"]')?.setAttribute('value','0');
    this.querySelector('[name="tipo"]')?.setAttribute('value','perfil');
    saveForm(this, {action:'create', id:0, tipo:'perfil'});
  });

  bindOnce('formAgregarPerfilHijo', function(ev){
    ev.preventDefault(); ev.stopPropagation();
    this.querySelector('[name="id"]')?.setAttribute('value','0');
    this.querySelector('[name="tipo"]')?.setAttribute('value','perfil');
    saveForm(this, {action:'create', id:0, tipo:'perfil', allowBlankCore:true});
  });

  bindOnce('formAgregarCuenta', function(ev){
    ev.preventDefault(); ev.stopPropagation();
    this.querySelector('[name="id"]')?.setAttribute('value','0');
    this.querySelector('[name="tipo"]')?.setAttribute('value','cuenta');
    saveForm(this, {action:'create', id:0, tipo:'cuenta'});
  });

  bindOnce('formEditarIptv', function(ev){
    ev.preventDefault(); ev.stopPropagation();
    saveForm(this);
  });

  document.addEventListener('submit', async function(ev){
    const f = ev.target.closest('.js-delete-form');
    if (!f) return;
    ev.preventDefault(); ev.stopPropagation();

    if (!DELETE_URL && f.action === '#') { await swalErr('Config', 'DELETE_URL no está configurado'); return; }

    const ok = window.Swal?.fire
      ? await Swal.fire({icon:'warning',title:'Confirmar borrado',text:'¿Borrar este registro?',showCancelButton:true,confirmButtonText:'Sí, borrar'})
          .then(r=>r.isConfirmed)
      : confirm('¿Borrar este registro?');
    if (!ok) return;

    const body = new URLSearchParams(new FormData(f));
    try {
      const res = await fetch(DELETE_URL || f.action, {
        method: 'POST',
        headers: {'Accept':'application/json'},
        body,
        credentials: 'same-origin'
      });
      const js = await res.json().catch(()=>({ok:false,error:'Respuesta inválida'}));
      if (!res.ok || !js.ok) throw new Error(js.error || ('HTTP '+res.status));
      await swalOK('Borrado','Registro eliminado.');
      location.reload();
    } catch(e) {
      await swalErr('Error al borrar', e.message || 'No se pudo borrar');
    }
  }, true);
})();
</script>

<script>
/* Click en fila PADRE de PERFILES -> abrir modal HIJO con correo y campos heredados */
(function () {
  if (window.__iptvPerfilRowClickInit2) return;
  window.__iptvPerfilRowClickInit2 = true;

  const cont = document.querySelector('#iptv-perfiles');
  if (!cont) return;

  cont.addEventListener('click', function (e) {
    if (e.target.closest('.js-row-action, a, button, input, select, textarea, .whatsapp, form, td.iptv-cell-perfil')) return;

    const tr = e.target.closest('tr.js-parent-row');
    if (!tr) return;

    const raw = (tr.querySelector('.correo-cell')?.textContent || '').trim();
    if (!raw) return;
    const correo = raw.toLowerCase();

    const modalEl = document.getElementById('modalAgregarPerfilHijo');
    const form    = document.getElementById('formAgregarPerfilHijo');
    if (!modalEl || !form) return;

    let data = {};
    try {
      const btnEdit = tr.querySelector('.btn-edit[data-row]');
      if (btnEdit) data = JSON.parse(btnEdit.getAttribute('data-row') || '{}');
    } catch(_){}

    form.reset();
    form.querySelector('[name="id"]').value   = '0';
    form.querySelector('[name="tipo"]').value = 'perfil';
    form.querySelector('#iptv_hijo_usuario').value      = correo;
    form.querySelector('#iptv_hijo_usuario_view').value = correo;
    const ttl = document.getElementById('correoHijoTitle'); if (ttl) ttl.textContent = correo;

    const iNombre = form.querySelector('#iptv_hijo_nombre');
    const iPass   = form.querySelector('#iptv_hijo_password');
    const iUrl    = form.querySelector('#iptv_hijo_url');

    if (iNombre) iNombre.value = (data?.nombre ?? '');
    if (iPass)   iPass.value   = (data?.password_plain ?? '');
    if (iUrl)    iUrl.value    = (data?.url ?? '');

    const today = new Date();
    const plus31 = new Date(today); plus31.setDate(today.getDate() + 31);
    const yyyyMmDd = (d)=> d.toISOString().slice(0,10);
    const fi = form.querySelector('[name="fecha_inicio"]');
    const ff = form.querySelector('[name="fecha_fin"]');
    if (fi) fi.value = yyyyMmDd(today);
    if (ff) ff.value = yyyyMmDd(plus31);
    const soles = form.querySelector('[name="soles"]'); if (soles) soles.value = '0.00';
    const estado= form.querySelector('[name="estado"]'); if (estado) estado.value = 'activo';
    const combo = form.querySelector('[name="combo"]'); if (combo) combo.checked = false;

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    setTimeout(() => form.querySelector('[name="fecha_inicio"]')?.focus(), 60);
  });
})();
</script>

<script>
/* IPTV: Cambiar color en click de la 1ª columna (Nombre) */
(function () {
  if (window.__iptvCambiarColorInit) return; window.__iptvCambiarColorInit = true;

  const COLOR_URL = window.IPTV_ENDPOINTS?.color || '';
  const modalEl   = document.getElementById('modalCambiarColor');
  const formEl    = document.getElementById('formCambiarColor');
  const idEl      = document.getElementById('cc_id');
  const tipoEl    = document.getElementById('cc_tipo');
  const colorEl   = document.getElementById('cc_color');

  function abrirCambiarColorDesde(td){
    const tr  = td.closest('tr');
    const tab = td.closest('[data-tipo]');
    if (!tr || !tab) return;
    const id   = Number(td.getAttribute('data-id') || 0);
    const tipo = (tab.getAttribute('data-tipo') === 'perfil') ? 'perfil' : 'cuenta';
    if (!id) return;

    const cls = tr.className;
    let current = '';
    if (/\brow-color-rojo\b/.test(cls))   current = 'rojo';
    else if (/\brow-color-azul\b/.test(cls))  current = 'azul';
    else if (/\brow-color-verde\b/.test(cls)) current = 'verde';
    else if (/\brow-color-blanco\b/.test(cls)) current = 'blanco';

    idEl.value = id;
    tipoEl.value = tipo;
    colorEl.value = current;

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }

  ['#iptv-perfiles', '#iptv-cuentas'].forEach((sel) => {
    const root = document.querySelector(sel);
    if (!root) return;
    root.addEventListener('click', function (e) {
      const td = e.target.closest('td.iptv-cell-perfil');
      if (!td || !root.contains(td)) return;
      if (e.target.closest('.js-row-action, a, button, input, select, textarea, form')) return;

      e.preventDefault();
      if (e.stopImmediatePropagation) e.stopImmediatePropagation();
      e.stopPropagation();
      abrirCambiarColorDesde(td);
    }, true);
  });

  formEl?.addEventListener('submit', async function (ev) {
    ev.preventDefault(); ev.stopPropagation();
    if (!COLOR_URL) { alert('COLOR_URL no configurado'); return; }

    const id    = Number(idEl.value || 0);
    const tipo  = tipoEl.value === 'perfil' ? 'perfil' : 'cuenta';
    const color = colorEl.value;

    try {
      const res = await fetch(COLOR_URL, {
        method: 'POST',
        headers: {'Content-Type':'application/json','Accept':'application/json'},
        body: JSON.stringify({action:'color', id, tipo, color}),
        credentials: 'same-origin'
      });
      const js = await res.json().catch(()=>({ok:false,error:'Respuesta inválida'}));
      if (!res.ok || !js.ok) throw new Error(js.error || ('HTTP '+res.status));

      const td = document.querySelector(
        `[data-tipo="${tipo}"] table tbody tr td.iptv-cell-perfil[data-id="${id}"]`
      );
      const tr = td?.closest('tr');
      if (tr) {
        tr.classList.remove('row-color-rojo','row-color-azul','row-color-verde','row-color-blanco');
        if (color) tr.classList.add('row-color-' + color);
      }

      bootstrap.Modal.getOrCreateInstance(modalEl).hide();
    } catch (e) {
      alert('No se pudo cambiar el color: ' + (e.message || 'Error'));
    }
  });
})();
</script>

<!-- ============ MOTOR DE FILTROS LOCALES (Perfiles/Cuentas) ============ -->
<script>
(function(){
  document.body.setAttribute('data-page', 'iptv');

  const norm = (s) => (s||'')
    .toString()
    .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
    .toLowerCase().trim();

  function readControls(scope){
    const box = document.querySelector(`.iptv-local-filters[data-scope="${scope}"]`);
    if (!box) return null;
    return {
      q:      norm(box.querySelector('[data-role="search"]')?.value || ''),
      estado: (box.querySelector('[data-role="estado"]')?.value || '').toLowerCase(),
      combo:  (box.querySelector('[data-role="combo"]')?.value ?? ''),
      color:  (box.querySelector('[data-role="color"]')?.value || '').toLowerCase(),
      box
    };
  }

  function applyPerfiles(){
    const c = readControls('perfiles');
    const tbody = document.querySelector('#iptv-perfiles table tbody');
    if (!c || !tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr'));
    let showGroup = true;

    rows.forEach(tr => {
      const kind   = tr.dataset.rowKind;         // parent|child
      const color  = (tr.dataset.color||'').toLowerCase();
      const estado = (tr.dataset.estado||'').toLowerCase();
      const combo  = (tr.dataset.combo||'');
      const text   = norm(
        (tr.dataset.nombre||'')+' '+
        (tr.dataset.usuario||'')+' '+
        (tr.dataset.url||'')+' '+
        tr.textContent
      );

      if (kind === 'parent') {
        let match = true;
        if (c.q && !text.includes(c.q))             match = false;
        if (c.color && color !== c.color)           match = false;
        if (c.estado && estado !== c.estado)        match = false;
        if (c.combo !== '' && combo !== c.combo)    match = false;
        showGroup = match;
        tr.classList.toggle('d-none', !match);
      } else {
        tr.classList.toggle('d-none', !showGroup);
      }
    });
  }

  function applyCuentas(){
    const c = readControls('cuentas');
    const tbody = document.querySelector('#iptv-cuentas table tbody');
    if (!c || !tbody) return;

    Array.from(tbody.querySelectorAll('tr')).forEach(tr => {
      const color  = (tr.dataset.color||'').toLowerCase();
      const estado = (tr.dataset.estado||'').toLowerCase();
      const combo  = (tr.dataset.combo||'');
      const text   = norm(
        (tr.dataset.nombre||'')+' '+
        (tr.dataset.usuario||'')+' '+
        (tr.dataset.url||'')+' '+
        tr.textContent
      );

      let match = true;
      if (c.q && !text.includes(c.q))             match = false;
      if (c.color && color !== c.color)           match = false;
      if (c.estado && estado !== c.estado)        match = false;
      if (c.combo !== '' && combo !== c.combo)    match = false;

      tr.classList.toggle('d-none', !match);
    });
  }

  function bindLocalFilter(scope, applyFn){
    const box = document.querySelector(`.iptv-local-filters[data-scope="${scope}"]`);
    if (!box || box.dataset.bound) return;
    box.dataset.bound = '1';

    const onChange = () => applyFn();
    box.querySelectorAll('[data-role="search"],[data-role="estado"],[data-role="combo"],[data-role="color"]')
      .forEach(el => el.addEventListener('input', onChange));
    box.querySelectorAll('[data-role="estado"],[data-role="combo"],[data-role="color"]')
      .forEach(el => el.addEventListener('change', onChange));
    const clearBtn = box.querySelector('[data-role="clear"]');
    if (clearBtn) {
      clearBtn.addEventListener('click', (e)=>{
        e.preventDefault();
        box.querySelectorAll('[data-role="search"]').forEach(i=> i.value='');
        box.querySelectorAll('[data-role="estado"],[data-role="combo"],[data-role="color"]')
          .forEach(s=> s.value='');
        applyFn();
      });
    }
  }

  function boot(){
    bindLocalFilter('perfiles', applyPerfiles);
    bindLocalFilter('cuentas',  applyCuentas);

    const active = document.querySelector('.tab-pane.active');
    if (active?.id === 'iptv-perfiles') applyPerfiles();
    if (active?.id === 'iptv-cuentas')  applyCuentas();
  }

  document.addEventListener('shown.bs.tab', (e)=>{
    const target = e.target?.getAttribute('data-bs-target');
    if (target === '#iptv-perfiles') { bindLocalFilter('perfiles', applyPerfiles); applyPerfiles(); }
    if (target === '#iptv-cuentas')  { bindLocalFilter('cuentas',  applyCuentas);  applyCuentas();  }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  window.addEventListener('load', boot);
})();
</script>

<?php if ($DEBUG): ?>
<pre style="position:fixed;bottom:0;left:0;right:0;max-height:40vh;overflow:auto;background:#111;color:#0f0;padding:8px;margin:0;font-size:12px">
IPTV perfiles: <?= (int)count($iptv_perfiles) . PHP_EOL ?>
IPTV cuentas : <?= (int)count($iptv_cuentas) . PHP_EOL ?>
SAVE_URL    : <?= $SAVE_URL . PHP_EOL ?>
DELETE_URL  : <?= $DELETE_URL . PHP_EOL ?>
COLOR_URL  : <?= $COLOR_URL . PHP_EOL ?>
__DIR__     : <?= __DIR__ . PHP_EOL ?>
PHP_SELF    : <?= (($_SERVER['PHP_SELF'] ?? '') . PHP_EOL) ?>
</pre>
<?php endif; ?>

</body>
</html>

<?php
// Modales y footer
include __DIR__ . '/../includes/modals.php';
include __DIR__ . '/../includes/footer.php';
