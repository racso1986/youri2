<?php
declare(strict_types=1);

// NO llames session_start() aquí; config.php ya lo hace
require_once __DIR__ . '/../app/helpers.php';     // redirect(), set_flash(), etc.
require_once __DIR__ . '/../config/db.php';        // get_pdo()

/* MODELOS necesarios para esta vista */
require_once __DIR__ . '/../app/models/StreamingModel.php';
require_once __DIR__ . '/../app/models/PerfilModel.php';
require_once __DIR__ . '/../app/models/CuentaModel.php';
require_once __DIR__ . '/../app/models/StockModel.php';
require_once __DIR__ . '/../app/models/PausaModel.php';


ini_set('display_errors', '1');
error_reporting(E_ALL);


/* Autenticación */
if (empty($_SESSION['user_id'])) {
  redirect('index.php');
}

/* ID válido o volvemos al dashboard antes de imprimir HTML */
$streaming_id = (int)($_GET['id'] ?? 0);
if ($streaming_id <= 0) {
  set_flash('warning','ID de streaming inválido.');
  redirect('dashboard.php');
}


// Helper (una sola vez en la vista)
if (!function_exists('format_cliente_num')) {
  function format_cliente_num(string $wa_e164 = '', string $wa_digits = ''): string {
    $digits = ltrim($wa_e164 !== '' ? $wa_e164 : $wa_digits, '+');
    if ($digits === '') return '';
    if (strlen($digits) > 9) {
      $cc    = substr($digits, 0, strlen($digits) - 9);
      $local = substr($digits, -9);
      return '+' . $cc . ' ' .
             substr($local, 0, 3) . ' ' .
             substr($local, 3, 3) . ' ' .
             substr($local, 6, 3);
    }
    if (strlen($digits) === 9) {
      return substr($digits, 0, 3) . ' ' .
             substr($digits, 3, 3) . ' ' .
             substr($digits, 6, 3);
    }
    return ($wa_e164 !== '' && $wa_e164[0] === '+') ? $wa_e164 : ('+' . $digits);
  }
}


/* Carga de datos protegida */
try {
  $streaming = StreamingModel::get($streaming_id);
  if (!$streaming) {
    set_flash('warning','Streaming no encontrado.');
    redirect('dashboard.php');
  }

  // Conexión PDO para consultas puntuales
  $pdo = get_pdo();

  // Perfiles y Cuentas (ordenados por correo para agrupar visualmente)
  // Perfiles — más recientes primero (requiere columna created_at)
// Si tu tabla `perfiles` NO tiene created_at, cambia por: ORDER BY id DESC
$stmt = $pdo->prepare("SELECT * FROM perfiles WHERE streaming_id = ? ORDER BY created_at DESC, id DESC");
$stmt->execute([$streaming_id]);
$perfiles = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Cuentas — más recientes primero (usa created_at que ya definimos en el modelo)
$stmt = $pdo->prepare("SELECT * FROM cuentas WHERE streaming_id = ? ORDER BY created_at DESC, id DESC");
$stmt->execute([$streaming_id]);
$cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);


  // Pausas desde el modelo
  $perfiles_pausa = PausaModel::byStreaming($streaming_id);

  /* Helpers usados por las tablas (locales a este archivo) */
  function estado_badge_class($estado) {
    return $estado === 'pendiente' ? 'bg-warning text-dark'
         : ($estado === 'moroso' ? 'bg-danger'
         : 'bg-light text-dark');
  }
  function row_json_attr(array $row): string {
    $json = json_encode($row, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    return htmlspecialchars($json, ENT_QUOTES, 'UTF-8');
  }

  /* Título de la página (por si tu header lo usa) */
  $pageTitle = sprintf(
    '%s • %s • S/%0.2f',
    (string)($streaming['nombre'] ?? 'Streaming'),
    (string)($streaming['plan']   ?? ''),
    (float)($streaming['precio']  ?? 0)
  );

  /* Normaliza ruta del logo */
  $logo = (string)($streaming['logo'] ?? '');
  if ($logo && strpos($logo, 'uploads/') === false) {
    $logo = 'uploads/' . ltrim($logo, '/');
  }

  /* Fecha hoy */
  $hoy = date('Y-m-d');

  /* Header + Navbar (una sola vez) */
  include __DIR__ . '/../includes/header.php';
  include __DIR__ . '/../includes/navbar.php';

} catch (Throwable $e) {
  error_log('public/streaming.php error: ' . $e->getMessage());
  http_response_code(500);
  // Pintamos un layout mínimo para no dejar pantalla en blanco
  include __DIR__ . '/../includes/header.php';
  include __DIR__ . '/../includes/navbar.php';
  echo '<div class="container py-4"><div class="alert alert-danger">Error interno. Revisa logs.</div></div>';
  include __DIR__ . '/../includes/footer.php';
  exit;
}
?>

<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">
      <?php if (!empty($logo)): ?>
        <img src="<?= htmlspecialchars($logo) ?>"
             alt="<?= htmlspecialchars($streaming['nombre']) ?>"
             class="rounded me-2 align-text-bottom"
             style="height:32px;width:32px;object-fit:contain;">
      <?php endif; ?>
      <?= htmlspecialchars($streaming['nombre']) ?>
      <small class="text-muted">
        (<?= htmlspecialchars((string)$streaming['plan']) ?> • S/<?= number_format((float)$streaming['precio'], 2, '.', '') ?>)
      </small>
    </h3>

    <div class="d-flex gap-2">
     

     

      <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">Volver</a>
    </div>
  </div>

  <ul class="nav nav-tabs" id="streamTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="perfiles-tab" data-bs-toggle="tab" data-bs-target="#perfiles" type="button" role="tab" aria-controls="perfiles" aria-selected="true">Perfiles</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="cuentas-tab" data-bs-toggle="tab" data-bs-target="#cuentas" type="button" role="tab" aria-controls="cuentas" aria-selected="false">Cuenta completa</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock" type="button" role="tab" aria-controls="stock" aria-selected="false">Stock</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="pausa-tab" data-bs-toggle="tab" data-bs-target="#pausa" type="button" role="tab" aria-controls="pausa" aria-selected="false">Cuenta en pausa</button>
    </li>
   <li class="nav-item" role="presentation">
  <button class="nav-link" id="perfiles-familiar-tab"
          data-bs-toggle="tab"
          data-bs-target="#perfiles-familiar"
          type="button" role="tab"
          aria-controls="perfiles-familiar" aria-selected="false">
    Streaming familiar
  </button>
</li>


  </ul>
  
 


  <div class="tab-content border border-top-0 p-3 rounded-bottom shadow-sm bg-white">
      
<!-- PERFILES -->
<div class="tab-pane fade show active" id="perfiles" role="tabpanel" aria-labelledby="perfiles-tab">
  
  <!-- Botón + precio cabecera -->
  <div class="d-flex align-items-center flex-wrap gap-2" style="float: right;">
    <button type="button" class="btn btn-sm btn-primary btn-add-perfil"
            data-bs-toggle="modal" data-bs-target="#perfilModal"
            data-streaming_id="<?= (int)$streaming_id ?>" style="float: right;">
      Agregar perfil
    </button>

    <input
      id="precioPerfilHead"
      name="precioPerfilHead"
      type="number"
      step="0.01"
      min="0"
      class="form-control"
      placeholder="0.00"
      inputmode="decimal"
      style="width:120px"
    >
  </div>

  <!-- Filtros PERFILES (solo esta pestaña) -->
  <div class="__pcFilter__ d-flex flex-wrap align-items-center gap-2 mb-2" data-scope="perfiles">
    <select class="form-select form-select-sm pc-main" style="max-width: 360px;">
      <option value="">— Filtro especial —</option>
      <option value="color_rojo">Color ROJO (padres)</option>
      <option value="color_azul">Color AZUL (padres)</option>
      <option value="color_verde">Color VERDE (padres)</option>
      <option value="pendientes">Pendientes por activar</option>
      <option value="dias_asc">Menos días</option>
      <option value="dias_desc">Mayor días</option>
      <option value="plan">Plan…</option>
    </select>

    <select class="form-select form-select-sm pc-plan" style="max-width: 220px; display: none;">
      <option value="">— Selecciona plan —</option>
      <option value="basico">Básico (incluye “Individual”)</option>
      <option value="estandar">Estándar</option>
      <option value="premium">Premium</option>
    </select>

    <input type="search" placeholder="Buscar por correo o WhatsApp" class="form-control form-control-sm pc-search" style="max-width: 280px;">
    <button type="button" class="btn btn-sm btn-outline-secondary pc-clear">Limpiar</button>
  </div>

  <div class="table-responsive">
    <table class="table align-middle table-bordered" id="perfilesTable">
      <thead>
      <tr>
        <th>Plan</th>
        <th>Correo</th>
        <th>Contraseña</th>
        <th>Inicio</th>
        <th>Fin</th>
        <th>Días</th>
        <th>Perfil</th>
        <th>Cliente</th>
        <th>Precio</th>
        <th>Dispositivo</th>
        <th>Combo</th>
        <th>Estado</th>
        <th>Entrega</th>
        <th>Acciones</th>
      </tr>
      </thead>

      <?php
      // helper para formatear cliente (igual que tenías)
      if (!function_exists('format_cliente_num')) {
        function format_cliente_num(string $wa_e164 = '', string $wa_digits = ''): string {
          $digits = ltrim($wa_e164 !== '' ? $wa_e164 : $wa_digits, '+');
          if ($digits === '') return '';
          if (strlen($digits) > 9) {
            $cc    = substr($digits, 0, strlen($digits) - 9);
            $local = substr($digits, -9);
            return '+' . $cc . ' ' . substr($local,0,3) . ' ' . substr($local,3,3) . ' ' . substr($local,6,3);
          }
          if (strlen($digits) === 9) {
            return substr($digits,0,3) . ' ' . substr($digits,3,3) . ' ' . substr($digits,6,3);
          }
          return ($wa_e164 !== '' && $wa_e164[0] === '+') ? $wa_e164 : ('+' . $digits);
        }
      }

      // ==== PREESCANEO: conteo por correo (como tenías) ====
      $correoCounts = [];
      foreach ($perfiles as $pp) {
        $k = (string)($pp['correo'] ?? '');
        if ($k !== '') $correoCounts[$k] = ($correoCounts[$k] ?? 0) + 1;
      }

      // ==== PREESCANEO NUEVO: primer precio por correo (ANCLA) ====
      // Toma el primer registro que aparezca en $perfiles para cada correo como "primer hijo".
      $firstPriceByCorreo = [];
      foreach ($perfiles as $pp) {
        $correoKey = (string)($pp['correo'] ?? '');
        if ($correoKey === '') continue;
        if (!array_key_exists($correoKey, $firstPriceByCorreo)) {
          $val = isset($pp['soles']) ? (float)$pp['soles'] : null;
          $firstPriceByCorreo[$correoKey] = $val;
        }
      }
      ?>
<?php
// === PREESCANEO: primer created_at por correo (para ordenar por inserción) ===
$firstCreatedByCorreo = [];
foreach ($perfiles as $pp) {
  $correo = (string)($pp['correo'] ?? '');
  if ($correo === '') continue;
  $raw = $pp['created_at'] ?? $pp['createdAt'] ?? $pp['fecha_creacion'] ?? '';
  $ts  = $raw ? strtotime($raw) : 0;
  if (!isset($firstCreatedByCorreo[$correo]) || ($ts > 0 && $ts < $firstCreatedByCorreo[$correo])) {
    $firstCreatedByCorreo[$correo] = $ts;
  }
}
?>

<?php
$correoCounts = [];
foreach ($perfiles as $pp) {
  $k = (string)($pp['correo'] ?? '');
  if ($k !== '') $correoCounts[$k] = ($correoCounts[$k] ?? 0) + 1;
}
?>


     <tbody>
<?php
  // Contar por correo para detectar grupos padre/hijos
  $perfilCorreoCounts = [];
  foreach ($perfiles as $pp) {
    $k = (string)($pp['correo'] ?? '');
    if ($k !== '') $perfilCorreoCounts[$k] = ($perfilCorreoCounts[$k] ?? 0) + 1;
  }

  // Primer precio por correo (ancla visual si ya existen hijos)
  $firstPriceByCorreo = [];
  foreach ($perfiles as $pp) {
    $correoKey = (string)($pp['correo'] ?? '');
    if ($correoKey === '') continue;
    if (!array_key_exists($correoKey, $firstPriceByCorreo)) {
      $val = isset($pp['soles']) ? (float)$pp['soles'] : null;
      $firstPriceByCorreo[$correoKey] = $val;
    }
  }

  $hoy         = date('Y-m-d');
  $lastCorreo  = null;
  $lastDayKey  = null;               // separadores por día
  $meses       = ['', 'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

  foreach ($perfiles as $p):

    // Separador por día (usa created_at; si no existiera, puedes cambiar a id)
    $createdRaw = $p['created_at'] ?? '';
    $ts         = $createdRaw ? strtotime($createdRaw) : 0;
    $dayKey     = $ts ? date('Y-m-d', $ts) : '';
    if ($dayKey && $dayKey !== $lastDayKey) {
      $diaLabel = date('j', $ts) . ' ' . $meses[(int)date('n', $ts)];
      echo '<tr data-sep="1" class="table-light"><td colspan="14" class="py-1 fw-semibold text-muted">'
         . htmlspecialchars($diaLabel, ENT_QUOTES, 'UTF-8')
         . '</td></tr>';
      $lastDayKey = $dayKey;
    }

    $showCorreo  = ($p['correo'] !== $lastCorreo);
    $hasChildren = (($perfilCorreoCounts[$p['correo']] ?? 0) > 1);

    $dias       = (int) floor((strtotime($p['fecha_fin']) - strtotime($hoy))/86400);
    $estadoReal = $dias < 0 ? 'moroso' : $p['estado'];
    $badgeClass = $estadoReal === 'pendiente' ? 'bg-warning text-dark' : ($estadoReal === 'moroso' ? 'bg-danger' : 'bg-light text-dark');

    $plan       = (string)($p['plan'] ?? 'individual');
    $comboLabel = ((int)($p['combo'] ?? 0) === 1) ? 'Sí' : 'No';

    $rowPerfil = [
      'id'            => (int)$p['id'],
      'streaming_id'  => (int)$p['streaming_id'],
      'correo'        => (string)$p['correo'],
      'password_plain'=> (string)$p['password_plain'],
      'perfil'        => (string)$p['perfil'],
      'whatsapp'      => (string)$p['whatsapp'],
      'fecha_inicio'  => (string)$p['fecha_inicio'],
      'fecha_fin'     => (string)$p['fecha_fin'],
      'soles'         => (string)$p['soles'],
      'estado'        => (string)$p['estado'],
      'dispositivo'   => (string)$p['dispositivo'],
      'plan'          => $plan,
      'combo'         => (int)($p['combo'] ?? 0),
    ];

    $ini_fmt = (!empty($p['fecha_inicio']) && $p['fecha_inicio'] !== '0000-00-00' && $p['fecha_inicio'] !== '0000-00-00 00:00:00')
      ? date('d/m/y', strtotime($p['fecha_inicio'])) : '';
    $fin_fmt = (!empty($p['fecha_fin']) && $p['fecha_fin'] !== '0000-00-00' && $p['fecha_fin'] !== '0000-00-00 00:00:00')
      ? date('d/m/y', strtotime($p['fecha_fin'])) : '';

    // WhatsApp / Cliente
    $__correo = $rowPerfil['correo'] ?: $p['correo'];
    $__fin    = $rowPerfil['fecha_fin'] ?: $p['fecha_fin'];
    $__wa     = $rowPerfil['whatsapp'] ?: $p['whatsapp'];
    $__wa     = preg_replace('/\s+/', '', (string)$__wa);
    $__wa     = preg_replace('/(?!^)\+/', '', $__wa);
    $__wa     = preg_replace('/[^\d\+]/', '', $__wa);
    if ($__wa === '+') { $__wa = ''; }
    $wa_num   = ltrim($__wa, '+');
    $tg_phone = ($__wa !== '' && $__wa[0] === '+') ? $__wa : ($__wa !== '' ? ('+' . $__wa) : '');
    $msg      = rawurlencode($__correo . ' - ' . $__fin);

    // Color de fila (opcional)
    $__color      = isset($p['color']) ? strtolower((string)$p['color']) : '';
    $__allowed    = ['rojo','azul','verde','blanco'];
    $__color      = in_array($__color, $__allowed, true) ? $__color : '';
    $__colorClass = $__color ? ' row-color-'.htmlspecialchars($__color, ENT_QUOTES, 'UTF-8') : '';

    // Ancla de precio (si ya existe un primer hijo con precio)
    $anchorAttr = '';
    if ($showCorreo) {
      $fp = $firstPriceByCorreo[$p['correo']] ?? null;
      if ($fp !== null && $fp !== '') {
        $anchorAttr = number_format((float)$fp, 2, '.', '');
      }
    }

    // Atributos del <tr> padre
    $__parentAttrs = '';
    if ($showCorreo) {
      $attrs = [
        'data-id="'.(int)$p['id'].'"',
        'data-entidad="perfil"',
        'data-correo="'.htmlspecialchars($p['correo'], ENT_QUOTES).'"',
        'data-password="'.htmlspecialchars($p['password_plain'], ENT_QUOTES).'"',
        'data-soles="'.htmlspecialchars($p['soles'], ENT_QUOTES).'"',
        'data-plan="'.htmlspecialchars($plan, ENT_QUOTES).'"',
        'data-combo="'.(int)($p['combo'] ?? 0).'"',
        'data-streaming_id="'.(int)$p['streaming_id'].'"',
      ];
      if ($__color) {
        $attrs[] = 'data-color="'.htmlspecialchars($__color, ENT_QUOTES, 'UTF-8').'"';
      }
      if ($anchorAttr !== '') {
        $attrs[] = 'data-anchor-price="'.htmlspecialchars($anchorAttr, ENT_QUOTES).'"';
      }
      // (opcional) timestamp de creación por correo si lo estás usando en JS
      $attrs[] = 'role="button"';
      $attrs[] = 'tabindex="0"';
      $__parentAttrs = ' '.implode(' ', $attrs);
    }
?>
<tr class="<?= trim(($showCorreo ? 'js-parent-row cursor-pointer'.($hasChildren ? ' has-children' : '') : '').$__colorClass) ?>"<?= $__parentAttrs ?>>
  <!-- 1) PLAN -->
  <td class="plan-cell-perfil" data-id="<?= (int)$p['id'] ?>" role="button" tabindex="0">
    <?= $showCorreo ? htmlspecialchars($plan) : '' ?>
  </td>

  <!-- 2) CORREO -->
  <td class="correo-cell"><?= $showCorreo ? htmlspecialchars($p['correo']) : '' ?></td>

  <!-- 3) CONTRASEÑA -->
  <td><?= htmlspecialchars($p['password_plain']) ?></td>

  <!-- 4) INICIO -->
  <td class="fi"><?= $ini_fmt ?></td>

  <!-- 5) FIN -->
  <td class="ff"><?= $fin_fmt ?></td>

  <!-- 6) DÍAS -->
  <td><?= $dias < 0 ? '<span class="text-danger">'.$dias.'</span>' : $dias ?></td>

  <!-- 7) PERFIL -->
  <td><?= htmlspecialchars($p['perfil']) ?></td>

  <!-- 8) CLIENTE -->
  <?php $cliente_display = format_cliente_num($__wa, $wa_num); ?>
  <td class="cliente text-nowrap"><?= htmlspecialchars($cliente_display) ?></td>

  <!-- 9) PRECIO -->
  <td><?= number_format((float)$p['soles'], 2) ?></td>

  <!-- 10) DISPOSITIVO -->
  <td><?= htmlspecialchars($p['dispositivo']) ?></td>

  <!-- 11) COMBO -->
  <td><?= $comboLabel ?></td>

  <!-- 12) ESTADO -->
  <td><span class="badge <?= $badgeClass ?> text-capitalize"><?= htmlspecialchars($estadoReal) ?></span></td>

  <!-- 13) ENTREGA -->
  <td class="whatsapp">
    <?php if ($wa_num !== ''): ?>
      <a class="wa-link"
         href="https://wa.me/<?= htmlspecialchars($wa_num, ENT_QUOTES); ?>?text=<?= $msg; ?>"
         target="_blank" rel="noopener"
         aria-label="WhatsApp" title="WhatsApp">
        <!-- ícono WA -->
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
          <path d="M13.601 2.326A7.854 7.854 0 0 0 8.03.002C3.6.002.008 3.594.008 8.023c0 1.414.37 2.792 1.074 4.005L.01 16l3.996-1.05a7.96 7.96 0 0 0 4.024 1.073h.003c4.43 0 8.022-3.592 8.022-8.021 0-2.144-.835-4.162-2.354-5.676zM8.033 14.5h-.002a6.48 6.48 0 0 1-3.302-.905l-.237-.141-2.371.623.633-2.31-.154-.237A6.47 6.47 0 0 1 1.53 8.02c0-3.575 2.91 6.485 6.5 6.485..."/>
        </svg>
      </a>
    <?php endif; ?>
    <?php if ($tg_phone !== '' && $tg_phone !== '+'): ?>
      <a class="ms-2 tg-link"
         href="#"
         data-phone="<?= htmlspecialchars($tg_phone, ENT_QUOTES); ?>"
         data-no-row-modal="1"
         aria-label="Telegram" title="Telegram">
        <!-- ícono TG -->
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
          <path d="M15.953 1.737a1.01 1.01 0 0 0-1.04-.2L1.253 6.78c-.86.33-.854 1.54..."/>
        </svg>
      </a>
    <?php endif; ?>
  </td>

  <!-- 14) ACCIONES -->
  <td class="text-nowrap">
    <button type="button"
            class="btn btn-sm btn-primary btn-edit-perfil js-row-action"
            data-bs-toggle="modal"
            data-bs-target="#perfilModal"
            data-row='<?= htmlspecialchars(json_encode($rowPerfil, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT), ENT_QUOTES, "UTF-8") ?>'>Editar</button>
    <form action="../app/controllers/PerfilController.php" method="post" class="d-inline form-delete-perfil">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
      <input type="hidden" name="streaming_id" value="<?= (int)$p['streaming_id'] ?>">
      <button type="submit" class="btn btn-sm btn-outline-danger js-row-action">Borrar</button>
    </form>
  </td>
</tr>
<?php
    $lastCorreo = $p['correo'];
  endforeach;
?>
</tbody>

    </table>
  </div>
</div>

    
    
    
    
    
    
    
    
    
    
  
    
    
    

   <!-- CUENTA COMPLETA -->
<!-- CUENTA COMPLETA -->
<div class="tab-pane fade" id="cuentas" role="tabpanel" aria-labelledby="cuentas-tab">
    
     <button type="button" class="btn btn-sm btn-primary btn-add-cuenta"
              data-bs-toggle="modal" data-bs-target="#cuentaModal"
              data-streaming_id="<?= (int)$streaming_id ?>" style="float: right;">Agregar Cuenta</button>
    <input type="number" step="0.01" min="0" style="float: right; margin-right:20px" id="precioCuentaHead" placeholder="S/ 0.00" aria-label="Precio cuenta">
  <!-- Filtros CUENTAS (solo esta pestaña) -->
<div class="__cuFilter__ d-flex flex-wrap align-items-center gap-2 mb-2" data-scope="cuentas">
  <select class="form-select form-select-sm cu-main" style="max-width: 360px;">
    <option value="">— Filtro especial —</option>
    <option value="color_rojo">Color ROJO (padres)</option>
    <option value="color_azul">Color AZUL (padres)</option>
    <option value="color_verde">Color VERDE (padres)</option>
    <option value="pendientes">Pendientes por activar</option>
    <option value="dias_asc">Menos días</option>
    <option value="dias_desc">Mayor días</option>
    <option value="plan">Plan…</option>
  </select>

  <select class="form-select form-select-sm cu-plan" style="max-width: 220px; display: none;">
    <option value="">— Selecciona plan —</option>
    <option value="basico">Básico (incluye “Individual”)</option>
    <option value="estandar">Estándar</option>
    <option value="premium">Premium</option>
  </select>

  <input type="search" placeholder="Buscar por correo o WhatsApp" class="form-control form-control-sm cu-search" style="max-width: 280px;">
  <button type="button" class="btn btn-sm btn-outline-secondary cu-clear">Limpiar</button>
</div>



  <div class="table-responsive">
    <table class="table align-middle table-bordered">
     <!-- THEAD: Perfiles -->
<thead>
  <tr>
    <th>Plan</th>
    <th>Correo</th>
    <th>Contraseña</th>
    <th>Inicio</th>
    <th>Fin</th>
    <th>Días</th>
    <th>Perfil</th>
    <th>Cliente</th>
    <th>Precio</th>
    <th>Dispositivo</th>
    <th>Combo</th>
    <th>Estado</th>
    <th>Entrega</th>
    <th>Acciones</th>
  </tr>
</thead>
<?php
// Pon esto una sola vez antes del foreach (Perfiles y/o Cuentas) en el archivo de la vista:
if (!function_exists('format_cliente_num')) {
  function format_cliente_num(string $wa_e164 = '', string $wa_digits = ''): string {
    $digits = ltrim($wa_e164 !== '' ? $wa_e164 : $wa_digits, '+');
    if ($digits === '') return '';

    // Si vienen 9 dígitos locales + código de país ==> formatear como +CC 123 456 789
    if (strlen($digits) > 9) {
      $cc    = substr($digits, 0, strlen($digits) - 9);
      $local = substr($digits, -9); // 9 dígitos
      return '+' . $cc . ' ' .
             substr($local, 0, 3) . ' ' .
             substr($local, 3, 3) . ' ' .
             substr($local, 6, 3);
    }

    // Si solo hay 9 dígitos (sin CC), agrupar 3-3-3
    if (strlen($digits) === 9) {
      return substr($digits, 0, 3) . ' ' .
             substr($digits, 3, 3) . ' ' .
             substr($digits, 6, 3);
    }

    // Fallback: devolver con '+' si falta
    return ($wa_e164 !== '' && $wa_e164[0] === '+') ? $wa_e164 : ('+' . $digits);
  }
}
?>
      <tbody>
<?php
  // Contar por correo para saber si un padre tiene hijos
  $cuentaCorreoCounts = [];
  foreach ($cuentas as $cc) {
    $k = (string)($cc['correo'] ?? '');
    if ($k !== '') $cuentaCorreoCounts[$k] = ($cuentaCorreoCounts[$k] ?? 0) + 1;
  }

  $lastCorreo = null;
  $lastDayKey = null; // separadores por día
  $meses = ['', 'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

  foreach ($cuentas as $c):

    // Separador por día (usa created_at)
    $createdRaw = $c['created_at'] ?? '';
    $ts = $createdRaw ? strtotime($createdRaw) : 0;
    $dayKey = $ts ? date('Y-m-d', $ts) : '';
    if ($dayKey && $dayKey !== $lastDayKey) {
      $diaLabel = date('j', $ts) . ' ' . $meses[(int)date('n', $ts)];
      echo '<tr data-sep="1" class="table-light"><td colspan="14" class="py-1 fw-semibold text-muted">'
         . htmlspecialchars($diaLabel, ENT_QUOTES, 'UTF-8')
         . '</td></tr>';
      $lastDayKey = $dayKey;
    }

    $showCorreo  = ($c['correo'] !== $lastCorreo);
    $hasChildren = (($cuentaCorreoCounts[$c['correo']] ?? 0) > 1);

    $dias = (int) floor((strtotime($c['fecha_fin']) - strtotime($hoy))/86400);
    $estadoReal = $dias < 0 ? 'moroso' : $c['estado'];
    $badgeClass = estado_badge_class($estadoReal);

    $plan = (string)($c['plan'] ?? 'individual');
    $comboLabel = ((int)($c['combo'] ?? 0) === 1) ? 'Sí' : 'No';

    // Datos para cliente y entrega
    $__correo = (string)$c['correo'];
    $__fin    = (string)$c['fecha_fin'];
    $__wa     = (string)$c['whatsapp'];

    $__wa = preg_replace('/\s+/', '', $__wa);
    $__wa = preg_replace('/(?!^)\+/', '', $__wa);
    $__wa = preg_replace('/[^\d\+]/', '', $__wa);
    if ($__wa === '+') { $__wa = ''; }

    $wa_num   = ltrim($__wa, '+'); // wa.me
    $tg_phone = ($__wa !== '' && $__wa[0] === '+') ? $__wa : ($__wa !== '' ? ('+' . $__wa) : '');
    $msg      = rawurlencode($__correo . ' - ' . $__fin);

    // Color opcional
    $__color = isset($c['color']) ? strtolower((string)$c['color']) : '';
    $__allowed = ['rojo','azul','verde','blanco'];
    $__color = in_array($__color, $__allowed, true) ? $__color : '';
    $__colorClass = $__color ? (' row-color-'.htmlspecialchars($__color, ENT_QUOTES, 'UTF-8')) : '';

    // Fechas formateadas
    $ini_fmt_c = (!empty($c['fecha_inicio']) && $c['fecha_inicio'] !== '0000-00-00' && $c['fecha_inicio'] !== '0000-00-00 00:00:00')
      ? date('d/m/y', strtotime($c['fecha_inicio'])) : '';
    $fin_fmt_c = (!empty($c['fecha_fin']) && $c['fecha_fin'] !== '0000-00-00' && $c['fecha_fin'] !== '0000-00-00 00:00:00')
      ? date('d/m/y', strtotime($c['fecha_fin'])) : '';
?>
<tr class="<?= trim(($showCorreo ? 'js-parent-row cursor-pointer' . ($hasChildren ? ' has-children' : '') : '') . $__colorClass) ?>"
  <?php if ($showCorreo): ?>
    data-id="<?= (int)$c['id'] ?>"
    data-entidad="cuenta"
    data-correo="<?= htmlspecialchars($c['correo'], ENT_QUOTES) ?>"
    data-password="<?= htmlspecialchars($c['password_plain'], ENT_QUOTES) ?>"
    data-soles="<?= htmlspecialchars($c['soles'], ENT_QUOTES) ?>"
    data-plan="<?= htmlspecialchars($plan, ENT_QUOTES) ?>"
    data-combo="<?= (int)($c['combo'] ?? 0) ?>"
    data-streaming_id="<?= (int)$c['streaming_id'] ?>"
    <?= $__color ? 'data-color="'.htmlspecialchars($__color, ENT_QUOTES, 'UTF-8').'"' : '' ?>
    role="button" tabindex="0"
  <?php endif; ?>
>
  <!-- 1) PLAN -->
  <td class="plan-cell-cuenta" data-id="<?= (int)$c['id'] ?>" role="button" tabindex="0" data-cu-id="<?= (int)$c['id'] ?>">
    <?= $showCorreo ? htmlspecialchars($plan) : '' ?>
  </td>

  <!-- 2) CORREO -->
  <td><?= $showCorreo ? htmlspecialchars($c['correo']) : '' ?></td>

  <!-- 3) CONTRASEÑA -->
  <td><?= htmlspecialchars($c['password_plain']) ?></td>

  <!-- 4) INICIO -->
  <td class="fi"><?= $ini_fmt_c ?></td>

  <!-- 5) FIN -->
  <td class="ff"><?= $fin_fmt_c ?></td>

  <!-- 6) DÍAS -->
  <td><?= $dias < 0 ? '<span class="text-danger">'.$dias.'</span>' : $dias ?></td>

  <!-- 7) PERFIL (usa "cuenta") -->
  <td><?= htmlspecialchars($c['cuenta']) ?></td>

  <!-- 8) CLIENTE -->
  <?php $cliente_display = format_cliente_num($__wa, $wa_num); ?>
  <td class="cliente text-nowrap"><?= htmlspecialchars($cliente_display) ?></td>

  <!-- 9) PRECIO -->
  <td><?= number_format((float)$c['soles'], 2) ?></td>

  <!-- 10) DISPOSITIVO -->
  <td><?= htmlspecialchars($c['dispositivo']) ?></td>

  <!-- 11) COMBO -->
  <td><?= $comboLabel ?></td>

  <!-- 12) ESTADO -->
  <td><span class="badge <?= $badgeClass ?> text-capitalize"><?= htmlspecialchars($estadoReal) ?></span></td>

  <!-- 13) ENTREGA -->
  <td class="whatsapp">
    <?php if ($wa_num !== ''): ?>
      <a class="wa-link js-row-action"
         data-no-row-modal="1"
         onclick="event.stopPropagation();"
         href="https://wa.me/<?= htmlspecialchars($wa_num, ENT_QUOTES); ?>?text=<?= $msg; ?>"
         target="_blank" rel="noopener"
         aria-label="WhatsApp" title="WhatsApp">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
          <path d="M13.601 2.326A7.854 7.854 0 0 0 8.03.002C3.6.002.008 3.594.008 8.023c0 1.414.37 2.792 1.074 4.005L.01 16l3.996-1.05a7.96 7.96 0 0 0 4.024 1.073h.003c4.43 0 8.022-3.592 8.022-8.021 0-2.144-.835-4.162-2.354-5.676zM8.033 14.5h-.002a6.48 6.48 0 0 1-3.302-.905l-.237-.141-2.371.623.633-2.31-.154-.237A6.47 6.47 0 0 1 1.53 8.02c0-3.575 2.91-6.485 6.5-6.485 1.738 0 3.37.676 4.598 1.901a6.46 6.46 0 0 1 1.907 4.585c0 3.575-2.91 6.48-6.5 6.48zm3.69-4.844c-.202-.1-1.194-.59-1.378-.657-.184-.068-.318-.101-.452.1-.134.201-.518.657-.635.792-.117.134-.234.151-.436.05-.202-.1-.853-.314-1.625-1.002-.6-.533-1.005-1.19-1.123-1.392-.117-.201-.013-.31.088-.41.09-.089.202-.234.302-.351.101-.117.134-.201.202-.335.067-.134.034-.251-.017-.351-.05-.1-.452-1.09-.619-1.49-.163-.392-.329-.339-.452-.345l-.386-.007c-.118 0-.31.045-.471.224-.16.177-.618.604-.618 1.475s.633 1.71.72 1.83c.084.118 1.245 1.9 3.016 2.665.422.182.75.29 1.006.371.422.134.807.115 1.11.069.339-.05 1.194-.488 1.363-.96.168-.472.168-.877.118-.964-.05-.084-.184-.134-.386-.234z"/>
        </svg>
      </a>
    <?php endif; ?>

    <?php if ($tg_phone !== '' && $tg_phone !== '+'): ?>
      <a class="ms-2 tg-link js-row-action"
         data-no-row-modal="1"
         onclick="event.stopPropagation();"
         href="#"
         data-phone="<?= htmlspecialchars($tg_phone, ENT_QUOTES); ?>"
         aria-label="Telegram" title="Telegram">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
          <path d="M15.953 1.737a1.01 1.01 0 0 0-1.04-.2L1.253 6.78c-.86.33-.854 1.54.01 1.86l3.17 1.18 1.24 3.98c.24.77 1.2.99 1.76.41l2.12-2.18 3.54 2.62c.73.54 1.79.14 1.98-.75l2.34-11.02a1.02 1.02 0 0 0-.46-1.18zM6.26 10.71l-.2 2.35 1.53-1.56 3.56-5.62-4.89 4.83z"/>
        </svg>
      </a>
    <?php endif; ?>
  </td>

  <!-- 14) ACCIONES -->
  <td class="text-nowrap">
    <button type="button"
            class="btn btn-sm btn-primary btn-edit-cuenta js-row-action"
            data-bs-toggle="modal"
            data-bs-target="#cuentaModal"
            data-row='<?= row_json_attr([
              "id"            => (int)$c["id"],
              "streaming_id"  => (int)$c["streaming_id"],
              "correo"        => (string)$c["correo"],
              "password_plain"=> (string)$c["password_plain"],
              "cuenta"        => (string)$c["cuenta"],
              "whatsapp"      => (string)$c["whatsapp"],
              "fecha_inicio"  => (string)$c["fecha_inicio"],
              "fecha_fin"     => (string)$c["fecha_fin"],
              "soles"         => (string)$c["soles"],
              "estado"        => (string)$c["estado"],
              "dispositivo"   => (string)$c["dispositivo"],
              "plan"          => $plan,
              "combo"         => (int)($c["combo"] ?? 0),
            ]) ?>'>Editar</button>

    <form action="../app/controllers/CuentaController.php" method="post" class="d-inline form-delete-cuenta">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <input type="hidden" name="streaming_id" value="<?= (int)$c['streaming_id'] ?>">
      <button type="submit" class="btn btn-sm btn-outline-danger js-row-action" data-no-row-modal="1">Borrar</button>
    </form>
  </td>
</tr>
<?php
    $lastCorreo = $c['correo'];
  endforeach;
?>
</tbody>


    </table>
  </div>
</div>













<!-- STREAMING FAMILIAR -->
<div class="tab-pane fade" id="perfiles-familiar" role="tabpanel" aria-labelledby="perfiles-familiar-tab">

  <!-- Botón + precio cabecera -->
  <div class="d-flex align-items-center flex-wrap gap-2" style="float: right;">
    <button
      type="button"
      class="btn btn-sm btn-primary btn-add-perfil-fam"
      data-bs-toggle="modal"
      data-bs-target="#perfilFamiliarModal"
      data-modal-context="parent"
      data-streaming_id="<?= (int)$streaming_id ?>">
      Agregar perfil (familiar)
    </button>

    <input
      id="precioFamiliarHead"
      name="precioFamiliarHead"
      type="number"
      step="0.01"
      min="0"
      class="form-control"
      placeholder="0.00"
      inputmode="decimal"
      style="width:120px"
    >
  </div>

  <!-- Filtros (aislados por scope) -->
  <div class="__pcFilter__ d-flex flex-wrap align-items-center gap-2 mb-2" data-scope="perfiles-fam">
    <select class="form-select form-select-sm pc-main" style="max-width: 360px;">
      <option value="">— Filtro especial —</option>
      <option value="color_rojo">Color ROJO (padres)</option>
      <option value="color_azul">Color AZUL (padres)</option>
      <option value="color_verde">Color VERDE (padres)</option>
      <option value="pendientes">Pendientes por activar</option>
      <option value="dias_asc">Menos días</option>
      <option value="dias_desc">Mayor días</option>
      <option value="plan">Plan…</option>
    </select>

    <select class="form-select form-select-sm pc-plan" style="max-width: 220px; display: none;">
      <option value="">— Selecciona plan —</option>
      <option value="basico">Básico (incluye “Individual”)</option>
      <option value="estandar">Estándar</option>
      <option value="premium">Premium</option>
    </select>

    <input type="search" placeholder="Buscar por correo o WhatsApp" class="form-control form-control-sm pc-search" style="max-width: 280px;">
    <button type="button" class="btn btn-sm btn-outline-secondary pc-clear">Limpiar</button>
  </div>

  <?php
  // Carga de registros familiar
  $perfilesFam = [];
  try {
    $st = $pdo->prepare('SELECT * FROM perfiles_familiar WHERE streaming_id = :sid ORDER BY correo, created_at, id');
    $st->execute([':sid' => (int)$streaming_id]);
    $perfilesFam = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { $perfilesFam = []; }

  if (!function_exists('format_cliente_num')) {
    function format_cliente_num(string $wa_e164 = '', string $wa_digits = ''): string {
      $digits = ltrim($wa_e164 !== '' ? $wa_e164 : $wa_digits, '+');
      if ($digits === '') return '';
      if (strlen($digits) > 9) {
        $cc    = substr($digits, 0, strlen($digits) - 9);
        $local = substr($digits, -9);
        return '+' . $cc . ' ' . substr($local,0,3) . ' ' . substr($local,3,3) . ' ' . substr($local,6,3);
      }
      if (strlen($digits) === 9) {
        return substr($digits,0,3) . ' ' . substr($digits,3,3) . ' ' . substr($digits,6,3);
      }
      return ($wa_e164 !== '' && $wa_e164[0] === '+') ? $wa_e164 : ('+' . $digits);
    }
  }

  // Conteos por correo (padres/hijos)
  $famCorreoCounts = [];
  foreach ($perfilesFam as $pp) {
    $k = (string)($pp['correo'] ?? '');
    if ($k !== '') $famCorreoCounts[$k] = ($famCorreoCounts[$k] ?? 0) + 1;
  }

  // Primer precio por correo (ancla)
  $famFirstPriceByCorreo = [];
  foreach ($perfilesFam as $pp) {
    $correoKey = (string)($pp['correo'] ?? '');
    if ($correoKey === '') continue;
    if (!array_key_exists($correoKey, $famFirstPriceByCorreo)) {
      $val = isset($pp['soles']) ? (float)$pp['soles'] : null;
      $famFirstPriceByCorreo[$correoKey] = $val;
    }
  }

  $hoy        = date('Y-m-d');
  $lastCorreo = null;
  $meses      = ['', 'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  $lastDayKey = null;
  ?>

  <div class="table-responsive">
    <table class="table align-middle table-bordered" id="perfilesFamiliarTable">
      <thead>
      <tr>
        <th>Plan</th>
        <th>Correo</th>
        <th>Contraseña</th>
        <th>Inicio</th>
        <th>Fin</th>
        <th>Días</th>
        <th>Perfil</th>
        <th>Cliente</th>
        <th>Precio</th>
        <th>Dispositivo</th>
        <th>Combo</th>
        <th>Estado</th>
        <th>Entrega</th>
        <th>Acciones</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($perfilesFam as $p):
        // Separador por día
        $createdRaw = $p['created_at'] ?? '';
        $ts         = $createdRaw ? strtotime($createdRaw) : 0;
        $dayKey     = $ts ? date('Y-m-d', $ts) : '';
        if ($dayKey && $dayKey !== $lastDayKey) {
          $diaLabel = date('j', $ts) . ' ' . $meses[(int)date('n', $ts)];
          echo '<tr data-sep="1" class="table-light"><td colspan="14" class="py-1 fw-semibold text-muted">'
              . htmlspecialchars($diaLabel, ENT_QUOTES, 'UTF-8')
              . '</td></tr>';
          $lastDayKey = $dayKey;
        }

        $showCorreo  = ($p['correo'] !== $lastCorreo);
        $hasChildren = (($famCorreoCounts[$p['correo']] ?? 0) > 1);

        $dias       = (int) floor((strtotime($p['fecha_fin']) - strtotime($hoy))/86400);
        $estadoReal = $dias < 0 ? 'moroso' : $p['estado'];
        $badgeClass = $estadoReal === 'pendiente' ? 'bg-warning text-dark' : ($estadoReal === 'moroso' ? 'bg-danger' : 'bg-light text-dark');

        $plan       = (string)($p['plan'] ?? 'individual');
        $comboLabel = ((int)($p['combo'] ?? 0) === 1) ? 'Sí' : 'No';

        $rowPerfil = [
          'id'            => (int)$p['id'],
          'streaming_id'  => (int)$p['streaming_id'],
          'correo'        => (string)$p['correo'],
          'password_plain'=> (string)$p['password_plain'],
          'perfil'        => (string)$p['perfil'],
          'whatsapp'      => (string)$p['whatsapp'],
          'fecha_inicio'  => (string)$p['fecha_inicio'],
          'fecha_fin'     => (string)$p['fecha_fin'],
          'soles'         => (string)$p['soles'],
          'estado'        => (string)$p['estado'],
          'dispositivo'   => (string)$p['dispositivo'],
          'plan'          => $plan,
          'combo'         => (int)($p['combo'] ?? 0),
        ];

        $ini_fmt = (!empty($p['fecha_inicio']) && $p['fecha_inicio'] !== '0000-00-00' && $p['fecha_inicio'] !== '0000-00-00 00:00:00')
          ? date('d/m/y', strtotime($p['fecha_inicio'])) : '';
        $fin_fmt = (!empty($p['fecha_fin']) && $p['fecha_fin'] !== '0000-00-00' && $p['fecha_fin'] !== '0000-00-00 00:00:00')
          ? date('d/m/y', strtotime($p['fecha_fin'])) : '';

        // WhatsApp / Cliente
        $__wa   = (string)($p['whatsapp'] ?? '');
        $__wa   = preg_replace('/\s+/', '', $__wa);
        $__wa   = preg_replace('/(?!^)\+/', '', $__wa);
        $__wa   = preg_replace('/[^\d\+]/', '', $__wa);
        if ($__wa === '+') { $__wa = ''; }
        $wa_num   = ltrim($__wa, '+');
        $tg_phone = ($__wa !== '' && $__wa[0] === '+') ? $__wa : ($__wa !== '' ? ('+' . $__wa) : '');
        $msg      = rawurlencode(($p['correo'] ?? '') . ' - ' . ($p['fecha_fin'] ?? ''));

        // Color de fila (opcional)
        $__color      = isset($p['color']) ? strtolower((string)$p['color']) : '';
        $__allowed    = ['rojo','azul','verde','blanco'];
        $__color      = in_array($__color, $__allowed, true) ? $__color : '';
        $__colorClass = $__color ? ' row-color-'.htmlspecialchars($__color, ENT_QUOTES, 'UTF-8') : '';

        // Anchor price y señales para el modal
        $anchorPrice = null;
        if ($showCorreo) {
          $fp = $famFirstPriceByCorreo[$p['correo']] ?? null;
          if ($fp !== null && $fp !== '') $anchorPrice = number_format((float)$fp, 2, '.', '');
        }
        $countForCorreo = (int)($famCorreoCounts[$p['correo']] ?? 0);
        $isFirstChild   = ($countForCorreo <= 1);
        $fpAttr         = '';
        if (!$isFirstChild) {
          $fp = $famFirstPriceByCorreo[$p['correo']] ?? null;
          if ($fp !== null && $fp !== '') {
            $fpAttr = ' data-first-child-price="'.htmlspecialchars(number_format((float)$fp,2,'.',''), ENT_QUOTES).'"';
          }
        }
      ?>
   <?php
// /public/streaming.php  (SECCIÓN: Streaming familiar)
// Reemplaza COMPLETO el bloque de apertura del <tr> padre por este (desde "<tr" hasta la ">" de cierre)

$__attrs = [];
if ($showCorreo) {
  $__attrs[] = 'data-id="'.(int)$p['id'].'"';
  $__attrs[] = 'data-entidad="perfil_fam"';
  $__attrs[] = 'data-correo="'.htmlspecialchars($p['correo'], ENT_QUOTES).'"';
  $__attrs[] = 'data-password="'.htmlspecialchars($p['password_plain'] ?? '', ENT_QUOTES).'"';
  $__attrs[] = 'data-soles="'.htmlspecialchars($p['soles'] ?? '', ENT_QUOTES).'"';
  $__attrs[] = 'data-plan="'.htmlspecialchars($plan, ENT_QUOTES).'"';
  $__attrs[] = 'data-combo="'.(int)($p['combo'] ?? 0).'"';
  $__attrs[] = 'data-streaming_id="'.(int)$p['streaming_id'].'"';
  // si ya calculas $anchorPrice antes, lo exponemos como primer precio hijo
  if (!empty($anchorPrice)) {
    $__attrs[] = 'data-first-child-price="'.htmlspecialchars(number_format((float)$anchorPrice, 2, '.', ''), ENT_QUOTES).'"';
  }
  $__attrs[] = 'data-modal-context="child"';
  $__attrs[] = 'data-has-child="'.($isFirstChild ? '0' : '1').'"';
  $__attrs[] = 'role="button"';
  $__attrs[] = 'tabindex="0"';
}
$__parentAttrs = $__attrs ? ' '.implode(' ', $__attrs) : '';
?>
<tr
  class="<?= trim(($showCorreo ? 'js-parent-row cursor-pointer'.($hasChildren ? ' has-children' : '') : '').$__colorClass) ?>"
  <?= $__parentAttrs ?>
>


<?php
// /public/streaming.php  (SECCIÓN: Streaming familiar, COLUMNA Plan)
// Asegúrate de que la celda de plan tenga estos data-* (para el modal chico):
?>
<td
  class="plan-cell-perfil"
  data-id="<?= (int)$p['id'] ?>"
  data-plan="<?= htmlspecialchars($plan, ENT_QUOTES) ?>"
  role="button"
  tabindex="0"
>
  <?= htmlspecialchars($plan, ENT_QUOTES) ?>
</td>


        <td class="correo-cell"><?= $showCorreo ? htmlspecialchars($p['correo']) : '' ?></td>
        <td><?= htmlspecialchars($p['password_plain']) ?></td>
        <td class="fi"><?= $ini_fmt ?></td>
        <td class="ff"><?= $fin_fmt ?></td>
        <td><?= $dias < 0 ? '<span class="text-danger">'.$dias.'</span>' : $dias ?></td>
        <td><?= htmlspecialchars($p['perfil']) ?></td>
        <?php $cliente_display = format_cliente_num($__wa, $wa_num); ?>
        <td class="cliente text-nowrap"><?= htmlspecialchars($cliente_display) ?></td>
        <td><?= number_format((float)$p['soles'], 2) ?></td>
        <td><?= htmlspecialchars($p['dispositivo']) ?></td>
        <td><?= $comboLabel ?></td>
        <td><span class="badge <?= $badgeClass ?> text-capitalize"><?= htmlspecialchars($estadoReal) ?></span></td>
        <td class="whatsapp">
          <?php if ($wa_num !== ''): ?>
            <a class="wa-link"
               href="https://wa.me/<?= htmlspecialchars($wa_num, ENT_QUOTES); ?>?text=<?= $msg; ?>"
               target="_blank" rel="noopener"
               aria-label="WhatsApp" title="WhatsApp">WA</a>
          <?php endif; ?>
        </td>
        <td class="text-nowrap">
          <button type="button"
                  class="btn btn-sm btn-primary js-row-action"
                  data-bs-toggle="modal"
                  data-bs-target="#perfilFamiliarModal"
                  data-row='<?= htmlspecialchars(json_encode($rowPerfil, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT), ENT_QUOTES, "UTF-8") ?>'>
            Editar
          </button>
          <form action="../app/controllers/PerfilFamiliarController.php" method="post" class="d-inline form-delete-perfil-fam">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="streaming_id" value="<?= (int)$p['streaming_id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger js-row-action">Borrar</button>
          </form>
        </td>
      </tr>
      <?php
        $lastCorreo = $p['correo'];
      endforeach; ?>
      </tbody>
    </table>
  </div>
</div>



















   <!-- STOCK -->
<div class="tab-pane fade" id="stock" role="tabpanel" aria-labelledby="stock-tab">
  <div class="d-flex justify-content-end mb-2" style="float: right;">
    <?php
    // Resolver streaming_id para STOCK (fallbacks seguros)
    $__sid = 0;
    if (isset($streaming['id'])) {
      $__sid = (int)$streaming['id'];
    } elseif (isset($_GET['streaming_id'])) {
      $__sid = (int)$_GET['streaming_id'];
    } elseif (isset($_GET['streaming'])) {
      $__sid = (int)$_GET['streaming'];
    }
    ?>
    <button id="btn-add-stock" class="btn btn-sm btn-primary" data-streaming_id="<?= $__sid ?>" style="float: right;">Agregar Stock</button>
  </div>
  
  
  <!-- Filtros STOCK -->
<div class="__spFilter__ d-flex flex-wrap align-items-center gap-2 mb-2" data-scope="stock">
  <select class="form-select form-select-sm sp-color" style="max-width: 220px;">
    <option value="">Color: todos</option>
    <option value="rojo">Rojo</option>
    <option value="azul">Azul</option>
    <option value="verde">Verde</option>
    <option value="blanco">Blanco</option>
  </select>

  <select class="form-select form-select-sm sp-plan" style="max-width: 240px;">
    <option value="">Plan: todos</option>
    <option value="basico">Básico (incluye Individual)</option>
    <option value="estandar">Estándar</option>
    <option value="premium">Premium</option>
  </select>

  <input type="search" class="form-control form-control-sm sp-search" style="max-width: 280px;" placeholder="Buscar correo…">
  <button type="button" class="btn btn-sm btn-outline-secondary sp-clear">Limpiar</button>
</div>


  <div class="table-responsive">
    <table class="table align-middle table-bordered" style="--bs-border-color:#000;" data-no-row-modal="1">
      <thead>
        <tr>
          <th>Plan</th>
          <th>Correo</th>
          <th>Contraseña</th>
          <th>Fecha de hoy</th>
          <th>Fecha de fin</th>
          <th>Días restantes</th>
          <th>WhatsApp</th>
          <th>Perfil</th>
          <th>Combo</th>
          <th>Soles</th>
          <th>Estado</th>
          <th>Dispositivo</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
<?php
  $stmt = $pdo->prepare("
    SELECT id, streaming_id, plan, color, correo, password_plain, whatsapp, perfil, combo, soles, estado, dispositivo, fecha_inicio, fecha_fin, created_at
    FROM perfiles_stock
    WHERE streaming_id = :sid
    ORDER BY created_at DESC, id DESC
  ");
  $stmt->execute([':sid' => $streaming_id]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $lastCorreo = null;
  $lastDayKey = null;
  $meses = ['', 'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

  foreach ($rows as $r):
    // Separador por día
    $createdRaw = $r['created_at'] ?? '';
    $ts = $createdRaw ? strtotime($createdRaw) : 0;
    $dayKey = $ts ? date('Y-m-d', $ts) : '';
    if ($dayKey && $dayKey !== $lastDayKey) {
      $diaLabel = date('j', $ts) . ' ' . $meses[(int)date('n', $ts)];
      echo '<tr data-sep="1" class="table-light"><td colspan="13" class="py-1 fw-semibold text-muted">'
         . htmlspecialchars($diaLabel, ENT_QUOTES, 'UTF-8')
         . '</td></tr>';
      $lastDayKey = $dayKey;
    }

    $isParent = $lastCorreo !== $r['correo'];
    $lastCorreo = $r['correo'];

    $plan   = $r['plan'] ?? 'individual';
    $planLC = mb_strtolower((string)$plan, 'UTF-8');
    $planKey = (strpos($planLC, 'premium') !== false) ? 'premium'
             : ((strpos($planLC, 'estándar') !== false || strpos($planLC, 'estandar') !== false) ? 'estandar' : 'basico');

    $combo  = isset($r['combo']) ? (int)$r['combo'] : 0;

    $hoyDt  = new DateTime('today');
    $finDt  = DateTime::createFromFormat('Y-m-d', (string)$r['fecha_fin']) ?: new DateTime((string)$r['fecha_fin']);
    $dias   = (int)$hoyDt->diff($finDt)->format('%r%a');
    $isMoroso = $dias < 0;

    $whatsNum = preg_replace('/\D+/', '', (string)$r['whatsapp']);
    $waText   = rawurlencode(($r['correo'] ?? '') . ' - ' . ($r['fecha_fin'] ?? ''));
    $waLink   = $whatsNum ? "https://wa.me/{$whatsNum}?text={$waText}" : '#';

    $rowData = [
      'id'            => (int)$r['id'],
      'streaming_id'  => (int)$r['streaming_id'],
      'correo'        => (string)$r['correo'],
      'password_plain'=> (string)$r['password_plain'],
      'whatsapp'      => (string)$r['whatsapp'],
      'fecha_inicio'  => (string)$r['fecha_inicio'],
      'fecha_fin'     => (string)$r['fecha_fin'],
      'perfil'        => (string)($r['perfil'] ?? ''),
      'soles'         => (string)$r['soles'],
      'estado'        => (string)$r['estado'],
      'dispositivo'   => (string)$r['dispositivo'],
      'plan'          => (string)$plan,
      'combo'         => (int)$combo,
    ];
    $dataAttrJson = htmlspecialchars(json_encode($rowData), ENT_QUOTES, 'UTF-8');

    $trClasses = [];
    if ($isParent) $trClasses[] = 'js-parent-row';
    if (!empty($r['color'])) $trClasses[] = 'row-color-'.htmlspecialchars($r['color'], ENT_QUOTES);
    $trStyle = $isParent ? 'cursor:pointer;' : '';

    $trData = [
      'data-prefill'      => $isParent ? '1' : null,
      'data-bs-toggle'    => $isParent ? 'modal' : null,
      'data-bs-target'    => $isParent ? '#stockModal' : null,
      'data-streaming_id' => (int)$streaming_id,
      'data-correo'       => htmlspecialchars($r['correo'], ENT_QUOTES, 'UTF-8'),
      'data-password'     => htmlspecialchars($r['password_plain'], ENT_QUOTES, 'UTF-8'),
      'data-soles'        => htmlspecialchars($r['soles'], ENT_QUOTES, 'UTF-8'),
      'data-plan'         => htmlspecialchars($plan, ENT_QUOTES, 'UTF-8'),
      'data-plan_key'     => $planKey,
      'data-combo'        => (int)$combo,
      'data-color'        => htmlspecialchars($r['color'] ?? '', ENT_QUOTES),
      'data-whatsapp'     => $whatsNum,
      'data-dias'         => $dias,
      'data-estado'       => htmlspecialchars($r['estado'], ENT_QUOTES, 'UTF-8'),
      'data-parent'       => $isParent ? '1' : '0',
    ];
    $attrHtml = '';
    if (!empty($trClasses)) $attrHtml .= ' class="'.implode(' ', $trClasses).'"';
    if (!empty($trStyle))   $attrHtml .= ' style="'.$trStyle.'"';
    foreach ($trData as $k => $v) {
      if ($v === null) continue;
      $attrHtml .= ' '.$k.'="'.$v.'"';
    }
?>
<tr <?= $attrHtml ?>>
  <td class="plan-cell-stock"
      data-id="<?= (int)$r['id'] ?>"
      data-plan="<?= htmlspecialchars($r['plan'] ?? 'premium', ENT_QUOTES, 'UTF-8') ?>"
      role="button" tabindex="0">
    <?= htmlspecialchars($r['plan'] ?? 'premium', ENT_QUOTES, 'UTF-8') ?>
  </td>

  <td><?= htmlspecialchars($r['correo']); ?></td>
  <td><?= htmlspecialchars($r['password_plain']); ?></td>

  <td><?= $isParent ? date('d/m/y') : '' ?></td>
  <td><?= htmlspecialchars(date('d/m/y', strtotime($r['fecha_fin']))) ?></td>
  <td><span class="badge <?= $isMoroso ? 'bg-danger' : 'bg-secondary' ?>"><?= $dias ?></span></td>

  <td class="whatsapp">
    <?php if ($whatsNum): ?>
      <a class="wa-link" href="<?= $waLink ?>" target="_blank" rel="noopener">WhatsApp</a>
    <?php endif; ?>
  </td>

  <td><?= htmlspecialchars($r['perfil'] ?? '') ?></td>
  <td><?= $combo ? 'Sí' : 'No' ?></td>
  <td><?= number_format((float)$r['soles'], 2) ?></td>
  <td><?= htmlspecialchars($r['estado']) ?></td>
  <td><?= htmlspecialchars($r['dispositivo']) ?></td>
  <td>
    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-stock"
            data-bs-toggle="modal" data-bs-target="#stockModal"
            data-row="<?= $dataAttrJson ?>">Editar</button>
    <form method="post" action="../app/controllers/StockController.php" class="d-inline js-delete-form">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <input type="hidden" name="streaming_id" value="<?= (int)$streaming_id ?>">
      <button type="submit" class="btn btn-sm btn-outline-danger">Borrar</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>

<?php if (empty($rows)): ?>
  <tr><td colspan="13" class="text-center">Sin registros</td></tr>
<?php endif; ?>
</tbody>

    </table>
  </div>
</div>

    <!-- PAUSA -->
    <div class="tab-pane fade" id="pausa" role="tabpanel" aria-labelledby="pausa-tab">
      <div class="d-flex justify-content-end mb-2" style="float: right">
       <?php
// resolver $__sid si no está (reuso de stock)
if (!isset($__sid)) {
  $__sid = 0;
  if (isset($streaming['id'])) $__sid = (int)$streaming['id'];
  elseif (isset($_GET['streaming_id'])) $__sid = (int)$_GET['streaming_id'];
  elseif (isset($_GET['streaming'])) $__sid = (int)$_GET['streaming'];
}
?>
<button id="btn-add-pausa" class="btn btn-sm btn-primary" data-streaming_id="<?= $__sid ?>">Agregar cuenta en pausa</button>












      </div>
      
      
      
      
      
      
      
     
      
      
      
      
      
      
      
      
      
      
      
      
    <!-- Filtros PAUSA -->
<div class="__spFilter__ d-flex flex-wrap align-items-center gap-2 mb-2" data-scope="pausa">
  <select class="form-select form-select-sm sp-color" style="max-width: 220px;">
    <option value="">Color: todos</option>
    <option value="rojo">Rojo</option>
    <option value="azul">Azul</option>
    <option value="verde">Verde</option>
    <option value="blanco">Blanco</option>
  </select>

  <select class="form-select form-select-sm sp-plan" style="max-width: 240px;">
    <option value="">Plan: todos</option>
    <option value="basico">Básico (incluye Individual)</option>
    <option value="estandar">Estándar</option>
    <option value="premium">Premium</option>
  </select>

  <input type="search" class="form-control form-control-sm sp-search" style="max-width: 280px;" placeholder="Buscar correo…">
  <button type="button" class="btn btn-sm btn-outline-secondary sp-clear">Limpiar</button>
</div>  
      
      
      
      
      
      <div class="table-responsive">
        <table class="table table-striped align-middle table-bordered" style="--bs-border-color:#000;" data-no-row-modal="1">
          <thead>
            <tr>
              <th>Plan</th>
              <th>Correo</th>
              <th>Contraseña</th>
              <th>Fecha de hoy</th>
              <th>Fecha de fin</th>
              <th>Días restantes</th>
              <th>WhatsApp</th>
              <th>Perfil</th>
              <th>Combo</th>
              <th>Soles</th>
              <th>Estado</th>
              <th>Dispositivo</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
<?php
  $stmt = $pdo->prepare("
    SELECT id, streaming_id, plan, color, correo, password_plain, whatsapp, perfil, combo, soles, estado, dispositivo, fecha_inicio, fecha_fin, created_at
    FROM perfiles_pausa
    WHERE streaming_id = :sid
    ORDER BY created_at DESC, id DESC
  ");
  $stmt->execute([':sid' => $streaming_id]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $lastCorreo = null;
  $lastDayKey = null;
  $meses = ['', 'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

  foreach ($rows as $r):
    // Separador por día
    $createdRaw = $r['created_at'] ?? '';
    $ts = $createdRaw ? strtotime($createdRaw) : 0;
    $dayKey = $ts ? date('Y-m-d', $ts) : '';
    if ($dayKey && $dayKey !== $lastDayKey) {
      $diaLabel = date('j', $ts) . ' ' . $meses[(int)date('n', $ts)];
      echo '<tr data-sep="1" class="table-light"><td colspan="13" class="py-1 fw-semibold text-muted">'
         . htmlspecialchars($diaLabel, ENT_QUOTES, 'UTF-8')
         . '</td></tr>';
      $lastDayKey = $dayKey;
    }

    $isParent = $lastCorreo !== $r['correo'];
    $lastCorreo = $r['correo'];

    $plan  = $r['plan'] ?? 'individual';
    $combo = isset($r['combo']) ? (int)$r['combo'] : 0;

    $hoyDt   = new DateTime('today');
    $finDt   = DateTime::createFromFormat('Y-m-d', (string)$r['fecha_fin']) ?: new DateTime((string)$r['fecha_fin']);
    $dias    = (int)$hoyDt->diff($finDt)->format('%r%a');
    $isMoroso = $dias < 0;

    $whatsNum = preg_replace('/\D+/', '', (string)$r['whatsapp']);
    $waText   = rawurlencode(($r['correo'] ?? '') . ' - ' . ($r['fecha_fin'] ?? ''));
    $waLink   = $whatsNum ? "https://wa.me/{$whatsNum}?text={$waText}" : '#';

    $rowData = [
      'id'            => (int)$r['id'],
      'streaming_id'  => (int)$r['streaming_id'],
      'correo'        => (string)$r['correo'],
      'password_plain'=> (string)$r['password_plain'],
      'whatsapp'      => (string)$r['whatsapp'],
      'fecha_inicio'  => (string)$r['fecha_inicio'],
      'fecha_fin'     => (string)$r['fecha_fin'],
      'perfil'        => (string)($r['perfil'] ?? ''),
      'soles'         => (string)$r['soles'],
      'estado'        => (string)$r['estado'],
      'dispositivo'   => (string)$r['dispositivo'],
      'plan'          => (string)$plan,
      'combo'         => (int)$combo,
    ];
    $dataAttr = htmlspecialchars(json_encode($rowData), ENT_QUOTES, 'UTF-8');
?>
<tr
  <?php if (!empty($r['color'])): ?>
    class="row-color-<?= htmlspecialchars($r['color'], ENT_QUOTES) ?>"
  <?php endif; ?>
  data-color="<?= htmlspecialchars($r['color'] ?? '', ENT_QUOTES) ?>"
>
  <td class="plan-cell-pausa"
      data-id="<?= (int)$r['id'] ?>"
      data-plan="<?= htmlspecialchars($r['plan'] ?? 'premium', ENT_QUOTES, 'UTF-8') ?>"
      role="button" tabindex="0">
    <?= htmlspecialchars($r['plan'] ?? 'premium', ENT_QUOTES, 'UTF-8') ?>
  </td>

  <td><?= htmlspecialchars($r['correo']); ?></td>
  <td><?= htmlspecialchars($r['password_plain']); ?></td>
  <td><?= $isParent ? date('d/m/y') : '' ?></td>
  <td><?= htmlspecialchars(date('d/m/y', strtotime($r['fecha_fin']))) ?></td>
  <td><span class="badge <?= $isMoroso ? 'bg-danger' : 'bg-secondary' ?>"><?= $dias ?></span></td>
  <td><?php if ($whatsNum): ?><a href="<?= $waLink ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></td>
  <td><?= htmlspecialchars($r['perfil'] ?? '') ?></td>
  <td><?= $combo ? 'Sí' : 'No' ?></td>
  <td><?= number_format((float)$r['soles'], 2) ?></td>
  <td><?= htmlspecialchars($r['estado']) ?></td>
  <td><?= htmlspecialchars($r['dispositivo']) ?></td>
  <td>
    <button type="button"
            class="btn btn-sm btn-outline-primary btn-edit-pausa"
            data-bs-toggle="modal" data-bs-target="#pausaModal"
            data-row="<?= $dataAttr ?>">Editar</button>

    <form method="post" action="../app/controllers/PausaController.php" class="d-inline js-delete-form">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <input type="hidden" name="streaming_id" value="<?= (int)$streaming_id ?>">
      <button type="submit" class="btn btn-sm btn-outline-danger">Borrar</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>

<?php if (empty($rows)): ?>
  <tr><td colspan="13" class="text-center">Sin registros</td></tr>
<?php endif; ?>
</tbody>

        </table>
      </div>
    </div>
    
    
    
  




  </div>
</div>
<!-- al final de public/streaming.php -->
<script>
// ===== perfiles_filters_diag.js =====
(function(){
  'use strict';
  if (window.__PF_DIAG_BOUND__) return;
  window.__PF_DIAG_BOUND__ = true;

  const LOG_PREFIX = '[PF-DIAG]';
  const log  = (...a)=>console.log(LOG_PREFIX, ...a);
  const warn = (...a)=>console.warn(LOG_PREFIX, ...a);
  const err  = (...a)=>console.error(LOG_PREFIX, ...a);

  function norm(s){ return String(s||'').toLowerCase().trim(); }

  function findPane(){
    // 1) pane por id "perfiles"
    let pane = document.getElementById('perfiles');
    if (pane) { log('Pane #perfiles OK'); return pane; }
    // 2) plan B: por data-scope
    pane = document.querySelector('[data-scope="perfiles"]')?.closest('.tab-pane, section, div');
    if (pane) { log('Pane por data-scope OK:', pane); return pane; }
    warn('No se encontró el pane de Perfiles (#perfiles). ¿El id de la pestaña es otro?');
    return null;
  }

  function findWrapper(pane){
    let wrap = pane ? pane.querySelector('.__pfFilter__[data-scope="perfiles"]') : null;
    if (wrap) { log('Wrapper filtros OK'); return wrap; }
    warn('No se encontró wrapper de filtros .__pfFilter__[data-scope="perfiles"] dentro del pane.');
    return null;
  }

  function findTable(pane){
    // busca tabla dentro del pane
    let table = pane ? pane.querySelector('table') : null;
    if (table) { log('Tabla encontrada dentro del pane'); return table; }
    // plan B: alguna tabla marcada explícitamente
    table = document.getElementById('perfilesTable') || document.querySelector('[data-perfiles-table]');
    if (table) { log('Tabla encontrada por id/data fuera del pane'); return table; }
    warn('No encontré una tabla de Perfiles.');
    return null;
  }

  function getTBody(table){
    let tbody = table ? table.tBodies[0] : null;
    if (tbody) { log('TBody OK, filas:', tbody.rows.length); return tbody; }
    warn('La tabla no tiene <tbody> (o está vacío).');
    return null;
  }

  function detectGroups(tbody){
    const rows = Array.from(tbody.querySelectorAll('tr'));
    if (!rows.length){ warn('No hay filas en tbody.'); return {groups:[], parents:0, children:0}; }

    let groups = [];
    let curr = null;
    let parents=0, children=0;

    rows.forEach(tr=>{
      const isParent = tr.classList.contains('js-parent-row') || tr.getAttribute('data-parent') === '1';
      if (isParent || !curr) {
        curr = { parent: tr, children: [] };
        groups.push(curr);
        parents++;
      } else {
        curr.children.push(tr);
        children++;
      }
    });

    log('Agrupación detectada -> grupos:', groups.length, 'padres:', parents, 'hijos:', children);
    if (parents === 0) warn('No se detectaron filas PADRE (js-parent-row o data-parent="1").');

    return {groups, parents, children};
  }

  function bindMinimalFilter(pane, tbody){
    // intenta agarrar algún input de búsqueda existente
    const q = pane.querySelector('.pf-search') || pane.querySelector('input[type="search"]') || pane.querySelector('input[type="text"]');
    if (!q){
      warn('No encontré input de búsqueda (.pf-search). Voy a crear un input temporal arriba de la tabla para probar.');
      const tmp = document.createElement('input');
      tmp.type = 'text';
      tmp.placeholder = 'Buscar (diagnóstico Perfiles)…';
      tmp.className = 'form-control mb-2';
      table.parentNode.insertBefore(tmp, table);
      tmp.addEventListener('input', ()=>applyMinimal(tmp.value));
      return;
    }
    log('Input de búsqueda detectado:', q);
    q.addEventListener('input', ()=>applyMinimal(q.value));

    function applyMinimal(val){
      const qv = norm(val);
      const rows = Array.from(tbody.querySelectorAll('tr'));
      let hits=0;
      rows.forEach(tr=>{
        const txt = norm(tr.textContent);
        const match = !qv || txt.includes(qv);
        tr.style.display = match ? '' : 'none';
        if (match) hits++;
      });
      log('Filtro mínimo aplicado. Query:', qv, 'Visibles:', hits, 'Total filas:', rows.length);
    }

    // autoaplicar si ya hay valor
    if (q.value) { q.dispatchEvent(new Event('input')); }
  }

  function reportControls(wrap){
    if (!wrap){ warn('Sin wrapper de filtros, se usará filtro mínimo por texto.'); return; }
    const selMain   = wrap.querySelector('.pf-main');
    const selPlan   = wrap.querySelector('.pf-plan');
    const selEstado = wrap.querySelector('.pf-estado');
    const qInput    = wrap.querySelector('.pf-search');
    const btnClr    = wrap.querySelector('.pf-clear');

    log('Controles encontrados:',
      { main: !!selMain, plan: !!selPlan, estado: !!selEstado, q: !!qInput, clear: !!btnClr }
    );
    if (!selMain && !qInput) warn('No hay ni pf-main ni pf-search; ¿seguro que el wrapper es el correcto?');
  }

  function run(){
    const pane  = findPane();
    if (!pane) return;
    const wrap  = findWrapper(pane); // puede ser null (seguimos con filtro mínimo)
    reportControls(wrap);

    const table = findTable(pane);
    if (!table) return;
    const tbody = getTBody(table);
    if (!tbody) return;

    const {groups, parents, children} = detectGroups(tbody);

    // Si no hay padres, el filtro “por grupos” no puede funcionar. Usamos filtro mínimo por texto.
    if (parents === 0){
      warn('Sin filas padre detectadas. Los filtros de perfiles esperan agrupar por padre/hijos.');
      bindMinimalFilter(pane, tbody);
      return;
    }

    // Para aislar problemas de listeners: probamos un filtro MÍNIMO sobre grupos (por correo del padre).
    const q = wrap?.querySelector('.pf-search');
    if (q){
      log('Enganchando filtro rápido por correo del PADRE para probar.');
      q.addEventListener('input', function(){
        const val = norm(q.value);
        groups.forEach(g=>{
          const correo = norm(g.parent.getAttribute('data-correo') || g.parent.textContent);
          const match = !val || correo.includes(val);
          [g.parent].concat(g.children).forEach(tr => tr.classList.toggle('d-none', !match));
        });
        log('Filtro-TEST aplicado. Query:', val);
      });
    } else {
      bindMinimalFilter(pane, tbody);
    }

    log('DIAGNÓSTICO listo. Ahora escribe en la búsqueda y observa si oculta/ muestra filas. Si NO pasa nada:');
    log('- Revisa si hay CSS que impida display:none (ej: !important en .d-none override).');
    log('- Revisa si otra función vuelve a mostrar filas tras el filtro.');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
</script>
<script>
// --- TEST MINIMAL PERFILES (no depende de wrapper ni selects) ---
(function(){
  // 1) localiza pane, tabla y tbody
  var pane  = document.getElementById('perfiles');
  if (!pane) { console.error('[PF-TEST] no existe #perfiles'); return; }
  var table = pane.querySelector('table');
  if (!table) { console.error('[PF-TEST] no hay <table> dentro de #perfiles'); return; }
  var tbody = table.querySelector('tbody');
  if (!tbody) { console.error('[PF-TEST] la tabla no tiene <tbody>'); return; }

  // 2) agrupa por PADRE/Hijos
  function buildGroups(){
    var groups=[], curr=null;
    Array.from(tbody.querySelectorAll('tr')).forEach(function(tr){
      var isParent = tr.classList.contains('js-parent-row') || tr.getAttribute('data-parent') === '1';
      if (isParent || !curr) { curr = { parent: tr, children: [] }; groups.push(curr); }
      else { curr.children.push(tr); }
    });
    return groups;
  }
  var groups = buildGroups();
  console.log('[PF-TEST] grupos:', groups.length);

  // 3) input de búsqueda (usa el que ya tienes .pc-search)
  var qInput = pane.querySelector('.pc-search');
  if (!qInput) {
    // si no existe, creo uno temporal arriba de la tabla
    qInput = document.createElement('input');
    qInput.className = 'form-control mb-2';
    qInput.placeholder = 'Buscar (test Perfiles)…';
    table.parentNode.insertBefore(qInput, table);
  }

  function norm(s){ return String(s||'').toLowerCase().trim(); }

  // 4) aplicar filtro: correo (padre) o texto en hijos
  function correoFromParentRow(tr){
    var c = tr.getAttribute('data-correo') || '';
    if (!c) {
      var tds = tr.querySelectorAll('td');
      if (tds && tds[1]) c = tds[1].textContent || ''; // 2da col = Correo en tu tabla
    }
    return norm(c);
  }
  function childText(tr){ return norm(tr.textContent); }

  function setHidden(g, hide){
    [g.parent].concat(g.children).forEach(function(tr){ tr.classList.toggle('d-none', !!hide); });
  }

  function apply(){
    var q = norm(qInput.value);
    // mostrar todo
    groups.forEach(function(g){ setHidden(g,false); });
    if (!q) return;

    groups.forEach(function(g){
      var hide = true;
      if (correoFromParentRow(g.parent).includes(q)) hide = false;
      if (hide) {
        for (var i=0;i<g.children.length;i++){
          if (childText(g.children[i]).includes(q)) { hide = false; break; }
        }
      }
      setHidden(g, hide);
    });
  }

  qInput.addEventListener('input', apply);
  apply();

  // 5) sanity check de CSS: intenta ocultar la primera fila
  var tr0 = tbody.rows[0];
  if (tr0) {
    tr0.classList.add('d-none');
    var disp = getComputedStyle(tr0).display;
    console.log('[PF-TEST] display fila 0 =', disp);
    // revertimos
    tr0.classList.remove('d-none');
    if (disp !== 'none') {
      console.warn('[PF-TEST] .d-none NO oculta filas. Revisa tu CSS: alguna regla está pisando display:none.');
    }
  }
})();
</script>


<!-- /public/streaming.php — Pegar AL FINAL del archivo (o justo después de la tabla de #perfiles-familiar) -->
<script>
;(function(){
  'use strict';
  if (window.__famPlanHardGuard) return; window.__famPlanHardGuard = true;

  var tab    = document.getElementById('perfiles-familiar');
  var modal  = document.getElementById('modalCambiarPlanPerfil');
  if (!tab || !modal || !window.bootstrap) return;

  function norm(s){ return String(s||'').trim().toLowerCase(); }

  function suspendRowModal(row){
    if (!row || row.dataset.rowmodalSuspended === '1') return;
    var t  = row.getAttribute('data-bs-toggle');
    var tg = row.getAttribute('data-bs-target');
    if (t  != null) row.dataset.rowmodalToggle  = t;
    if (tg != null) row.dataset.rowmodalTarget  = tg;
    row.removeAttribute('data-bs-toggle');
    row.removeAttribute('data-bs-target');
    row.dataset.rowmodalSuspended = '1';
  }
  function resumeRowModal(row){
    if (!row || row.dataset.rowmodalSuspended !== '1') return;
    var t  = row.dataset.rowmodalToggle;
    var tg = row.dataset.rowmodalTarget;
    if (t  != null) row.setAttribute('data-bs-toggle', t);
    if (tg != null) row.setAttribute('data-bs-target', tg);
    delete row.dataset.rowmodalToggle;
    delete row.dataset.rowmodalTarget;
    delete row.dataset.rowmodalSuspended;
  }

  // 1) pointerdown (antes que click): neutraliza el data-api del <tr>
  document.addEventListener('pointerdown', function(ev){
    var td = ev.target && ev.target.closest && ev.target.closest('#perfiles-familiar .plan-cell-perfil');
    if (!td) return;
    var tr = td.closest('tr.js-parent-row');
    suspendRowModal(tr);
  }, true);

  // 2) click: abre SOLO el modal chico y evita cualquier otro listener
  document.addEventListener('click', function(ev){
    var td = ev.target && ev.target.closest && ev.target.closest('#perfiles-familiar .plan-cell-perfil');
    if (!td) return;

    ev.preventDefault();
    ev.stopPropagation();
    ev.stopImmediatePropagation();

    var tr    = td.closest('tr.js-parent-row');
    suspendRowModal(tr);

    // Prefill del modal chico
    var id    = td.getAttribute('data-id') || (tr && tr.getAttribute('data-id')) || '';
    var plan  = norm(td.getAttribute('data-plan') || td.textContent);
    var color = tr ? (tr.getAttribute('data-color') || '') : '';

    var idEl     = modal.querySelector('#perfilPlanId');
    var planSel  = modal.querySelector('#perfilPlanSelect');
    var colorSel = modal.querySelector('#perfilColorSelect, select[name="color"]');
    var destSel  = modal.querySelector('#perfilEnviarASelect, select[name="enviar_a"]');

    if (idEl)     idEl.value = String(id).replace(/\D+/g,'');
    if (planSel)  planSel.value = plan || 'individual';
    if (colorSel) colorSel.value = color || '';
    if (destSel)  destSel.value = 'none';

    var inst = bootstrap.Modal.getOrCreateInstance(modal);
    modal.addEventListener('hidden.bs.modal', function restoreOnce(){
      resumeRowModal(tr);
      modal.removeEventListener('hidden.bs.modal', restoreOnce);
    }, {once:true});
    inst.show();
  }, true);

  // 3) Teclado (Enter/Espacio) sólo en familiar
  document.addEventListener('keydown', function(ev){
    if (ev.key !== 'Enter' && ev.key !== ' ') return;
    var td = ev.target && ev.target.closest && ev.target.closest('#perfiles-familiar .plan-cell-perfil');
    if (!td) return;

    ev.preventDefault();
    ev.stopPropagation();
    ev.stopImmediatePropagation();

    var tr = td.closest('tr.js-parent-row');
    suspendRowModal(tr);

    var idEl = modal.querySelector('#perfilPlanId');
    if (idEl) idEl.value = String(td.getAttribute('data-id') || tr?.getAttribute('data-id') || '').replace(/\D+/g,'');

    var inst = bootstrap.Modal.getOrCreateInstance(modal);
    modal.addEventListener('hidden.bs.modal', function restoreOnce(){
      resumeRowModal(tr);
      modal.removeEventListener('hidden.bs.modal', restoreOnce);
    }, {once:true});
    inst.show();
  }, true);
})();
</script>



<!-- /public/streaming.php  — SOLO para “Streaming familiar”.
     Pega este bloque INLINE al final del archivo (o justo después de la tabla de #perfiles-familiar). -->
<script>
;(function(){
  'use strict';
  if (window.__famPlanStrictGuard) return; window.__famPlanStrictGuard = true;

  var famPane = document.getElementById('perfiles-familiar');
  var rowModal = document.getElementById('perfilFamiliarModal');        // GRANDE (agregar perfil familiar)
  var planModal = document.getElementById('modalCambiarPlanPerfil');    // CHICO (cambiar plan/color/enviar a)
  if (!famPane || !rowModal || !planModal || !window.bootstrap) return;

  var lastPlanHitTS = 0;

  function norm(s){ return String(s||'').trim().toLowerCase(); }
  function isPlanCellTarget(ev){
    if (!ev || !ev.target) return null;
    var td = ev.target.closest && ev.target.closest('.plan-cell-perfil');
    if (!td) return null;
    if (!famPane.contains(td)) return null; // limitar SOLO a la pestaña Streaming familiar
    return td;
  }
  function prefillPlanModalFromCell(td){
    var tr    = td.closest('tr');
    var id    = td.getAttribute('data-id') || (tr && tr.getAttribute('data-id')) || '';
    var plan  = norm(td.getAttribute('data-plan') || td.textContent);
    var color = tr ? (tr.getAttribute('data-color') || '') : '';

    var idEl     = planModal.querySelector('#perfilPlanId');
    var planSel  = planModal.querySelector('#perfilPlanSelect');
    var colorSel = planModal.querySelector('#perfilColorSelect, select[name="color"]');
    var destSel  = planModal.querySelector('#perfilEnviarASelect, select[name="enviar_a"]');

    if (idEl)     idEl.value = String(id).replace(/\D+/g,'');
    if (planSel)  planSel.value = plan || 'individual';
    if (colorSel) colorSel.value = color || '';
    if (destSel)  destSel.value = 'none';
  }
  function openPlanOnly(td){
    lastPlanHitTS = Date.now();
    prefillPlanModalFromCell(td);
    bootstrap.Modal.getOrCreateInstance(planModal).show();
  }

  // 1) BLOQUEAR en captura cualquier click que nazca en la celda Plan (familiar) y abrir SOLO el modal chico
  document.addEventListener('click', function(ev){
    var td = isPlanCellTarget(ev);
    if (!td) return;
    ev.preventDefault();
    ev.stopPropagation();
    ev.stopImmediatePropagation();
    openPlanOnly(td);
  }, true);

  // 2) También bloquear con teclado (Enter / Space) sobre la celda Plan
  document.addEventListener('keydown', function(ev){
    if (ev.key !== 'Enter' && ev.key !== ' ') return;
    var td = isPlanCellTarget(ev);
    if (!td) return;
    ev.preventDefault();
    ev.stopPropagation();
    ev.stopImmediatePropagation();
    openPlanOnly(td);
  }, true);

  // 3) CORTAFUEGOS definitivo: si, pese a todo, se intenta abrir el modal GRANDE por un listener previo,
  //    lo cancelamos si el click reciente vino de una celda Plan (ventana de 800 ms)
  document.addEventListener('show.bs.modal', function(ev){
    if (ev.target !== rowModal) return;
    if (Date.now() - lastPlanHitTS < 800) {
      ev.preventDefault();
      ev.stopPropagation();
      ev.stopImmediatePropagation();
      try { bootstrap.Modal.getOrCreateInstance(rowModal).hide(); } catch(_){}
    }
  }, true);

  // 4) Extra: en mousedown/pointerdown, marcamos también el “intento plan” lo antes posible
  ['pointerdown','mousedown','touchstart'].forEach(function(type){
    document.addEventListener(type, function(ev){
      var td = isPlanCellTarget(ev);
      if (!td) return;
      lastPlanHitTS = Date.now();
      // No prevenimos aquí para no romper selección de texto; el corte real es en click/show.bs.modal
    }, true);
  });
})();
</script>

<!-- /public/streaming.php — Pegar AL FINAL (o justo después de la tabla de #perfiles-familiar) -->
<script>
;(function(){
  'use strict';
  if (window.__famPlanRouterV2) return; window.__famPlanRouterV2 = true;

  var famTab   = document.getElementById('perfiles-familiar');
  var bigModal = document.getElementById('perfilFamiliarModal');      // grande (agregar hijo)
  var smlModal = document.getElementById('modalCambiarPlanPerfil');   // chico  (cambiar plan/color/enviar)
  if (!famTab || !bigModal || !smlModal || !window.bootstrap) return;

  var blockBigUntil = 0;

  function norm(s){ return String(s||'').trim().toLowerCase(); }
  function prefillSmallFromCell(td){
    var tr    = td.closest('tr');
    var id    = td.getAttribute('data-id') || (tr && tr.getAttribute('data-id')) || '';
    var plan  = norm(td.getAttribute('data-plan') || td.textContent);
    var color = tr ? (tr.getAttribute('data-color') || '') : '';

    var idEl     = smlModal.querySelector('#perfilPlanId');
    var planSel  = smlModal.querySelector('#perfilPlanSelect');
    var colorSel = smlModal.querySelector('#perfilColorSelect, select[name="color"]');
    var destSel  = smlModal.querySelector('#perfilEnviarASelect, select[name="enviar_a"]');

    if (idEl)     idEl.value = String(id).replace(/\D+/g,'');
    if (planSel)  planSel.value = plan || 'individual';
    if (colorSel) colorSel.value = color || '';
    if (destSel)  destSel.value = 'none';
  }

  // 1) Captura de click en celda Plan (familiar): SOLO modal chico + bloquear grande
  document.addEventListener('click', function(ev){
    var td = ev.target && ev.target.closest && ev.target.closest('#perfiles-familiar .plan-cell-perfil');
    if (!td) return;
    blockBigUntil = Date.now() + 800;
    ev.preventDefault(); ev.stopPropagation(); ev.stopImmediatePropagation();
    prefillSmallFromCell(td);
    bootstrap.Modal.getOrCreateInstance(smlModal).show();
  }, true);

  // 2) Accesibilidad (Enter/Espacio) en celda Plan (familiar)
  document.addEventListener('keydown', function(ev){
    if (ev.key !== 'Enter' && ev.key !== ' ') return;
    var td = ev.target && ev.target.closest && ev.target.closest('#perfiles-familiar .plan-cell-perfil');
    if (!td) return;
    blockBigUntil = Date.now() + 800;
    ev.preventDefault(); ev.stopPropagation(); ev.stopImmediatePropagation();
    prefillSmallFromCell(td);
    bootstrap.Modal.getOrCreateInstance(smlModal).show();
  }, true);

  // 3) Cortafuegos: si aún así intenta abrirse el GRANDE por data-api del <tr>, lo cancelamos
  bigModal.addEventListener('show.bs.modal', function(ev){
    var rel = ev.relatedTarget || null;
    var fromPlan = false;
    if (rel && rel.closest) {
      if (rel.closest('#perfiles-familiar .plan-cell-perfil')) fromPlan = true;
      if (rel.hasAttribute && rel.hasAttribute('data-no-row-modal')) fromPlan = true;
    }
    if (fromPlan || Date.now() < blockBigUntil) {
      ev.preventDefault();
      ev.stopImmediatePropagation();
      try { bootstrap.Modal.getInstance(bigModal)?.hide(); } catch(_){}
    }
  }, true);
})();
</script>


<!-- /public/streaming.php — PÉGALO al final del archivo o justo después de la tabla de #perfiles-familiar -->
<script>
;(function(){
  'use strict';
  if (window.__famRowRouterV4) return; window.__famRowRouterV4 = true;

  var famPane   = document.getElementById('perfiles-familiar');
  var bigModal  = document.getElementById('perfilFamiliarModal');      // grande (Agregar a correo…)
  var smallModal= document.getElementById('modalCambiarPlanPerfil');   // chico (cambiar plan/color/enviar)
  if (!famPane || !bigModal || !smallModal || !window.bootstrap) return;

  function q(sel, ctx){ return (ctx||document).querySelector(sel); }
  function norm(s){ return String(s||'').trim().toLowerCase(); }

  function openSmallFromPlanCell(td){
    var tr    = td.closest('tr');
    var id    = td.getAttribute('data-id') || tr?.getAttribute('data-id') || '';
    var plan  = norm(td.getAttribute('data-plan') || td.textContent);
    var color = tr?.getAttribute('data-color') || '';

    var idEl     = q('#perfilPlanId', smallModal);
    var planSel  = q('#perfilPlanSelect', smallModal);
    var colorSel = q('#perfilColorSelect, select[name="color"]', smallModal);
    var destSel  = q('#perfilEnviarASelect, select[name="enviar_a"]', smallModal);

    if (idEl)     idEl.value = String(id).replace(/\D+/g,'');
    if (planSel)  planSel.value = plan || 'individual';
    if (colorSel) colorSel.value = color || '';
    if (destSel)  destSel.value = 'none';

    bootstrap.Modal.getOrCreateInstance(smallModal).show();
  }

  function openBigFromRow(tr){
    // Prefill mínimo para hijo
    var correo  = tr.getAttribute('data-correo') || '';
    var pass    = tr.getAttribute('data-password') || '';
    var sid     = tr.getAttribute('data-streaming_id') || '';
    var combo   = String(tr.getAttribute('data-combo') || '0');

    var title = q('#perfilFamiliarModalLabel', bigModal) || q('[id$="ModalLabel"]', bigModal);
    if (title) title.textContent = correo ? ('Agregar a correo: ' + correo) : 'Agregar a correo';

    var set = function(name, val){ var el=q('[name="'+name+'"]', bigModal); if (el) el.value = val; };
    set('action','create'); set('id',''); set('streaming_id', sid);
    set('correo', correo); set('password_plain', pass);
    set('estado','pendiente'); set('dispositivo','tv'); set('combo', (combo==='1'?'1':'0'));

    var today=new Date(), fin=new Date(today.getTime()+31*86400000);
    var iso = d => d.toISOString().slice(0,10);
    set('fecha_inicio', iso(today)); set('fecha_fin', iso(fin));

    var soles = q('input[name="soles"]', bigModal);
    if (soles) { soles.readOnly=false; soles.removeAttribute('readonly'); soles.value=''; }

    bootstrap.Modal.getOrCreateInstance(bigModal).show();
  }

  // CLICK router SOLO en Streaming familiar
  document.addEventListener('click', function(e){
    if (!famPane.contains(e.target)) return;

    // 1) Plan → SOLO modal chico
    var planCell = e.target.closest('.plan-cell-perfil');
    if (planCell) {
      e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
      openSmallFromPlanCell(planCell);
      return;
    }

    // 2) Fila padre (no plan) → modal grande
    var tr = e.target.closest('tr.js-parent-row[data-entidad="perfil_fam"]');
    if (!tr || e.target.closest('.js-row-action')) return;
    e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
    openBigFromRow(tr);
  }, true);

  // ENTER accesible
  document.addEventListener('keydown', function(e){
    if (e.key !== 'Enter') return;
    if (!famPane.contains(e.target)) return;
    if (e.target.closest('.js-row-action')) return;

    var planCell = e.target.closest('.plan-cell-perfil');
    if (planCell) {
      e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
      openSmallFromPlanCell(planCell);
      return;
    }

    var tr = e.target.closest('tr.js-parent-row[data-entidad="perfil_fam"]');
    if (!tr) return;
    e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
    openBigFromRow(tr);
  }, true);
})();
</script>



<!-- /public/streaming.php  (PEGAR AL FINAL DEL ARCHIVO, después de la tabla de #perfiles-familiar) -->
<script>
;(function(){
  'use strict';
  if (window.__famRowOpenBigV1) return; window.__famRowOpenBigV1 = true;

  var famPane   = document.getElementById('perfiles-familiar');
  var bigModal  = document.getElementById('perfilFamiliarModal');      // GRANDE: agregar hijo
  var smallModal= document.getElementById('modalCambiarPlanPerfil');   // CHICO: cambiar plan/color/enviar
  if (!famPane || !bigModal || !window.bootstrap) return;

  function q(sel, ctx){ return (ctx||document).querySelector(sel); }
  function toISO(d){ return d.toISOString().slice(0,10); }

  function openBigFromRow(tr){
    var correo = tr.getAttribute('data-correo') || '';
    var pass   = tr.getAttribute('data-password') || '';
    var sid    = tr.getAttribute('data-streaming_id') || '';
    var combo  = String(tr.getAttribute('data-combo') || '0');

    var title = q('#perfilFamiliarModalLabel', bigModal) || q('.modal-title', bigModal);
    if (title) title.textContent = correo ? ('Agregar a correo: ' + correo) : 'Agregar a correo';

    // set valores
    var set = function(sel, val){ var el=q(sel, bigModal); if (el) el.value = val; };
    set('input[name="correo"]', correo);
    set('input[name="password_plain"]', pass);
    set('input[name="streaming_id"]', sid);
    set('select[name="estado"]', 'pendiente');
    set('select[name="dispositivo"]', 'tv');
    set('select[name="combo"]', (combo === '1' ? '1' : '0'));

    var hoy = new Date(), fin = new Date(hoy.getTime() + 31*24*60*60*1000);
    set('input[name="fecha_inicio"]', toISO(hoy));
    set('input[name="fecha_fin"]', toISO(fin));

    var soles = q('input[name="soles"]', bigModal);
    if (soles) { soles.readOnly = false; soles.removeAttribute('readonly'); soles.value = ''; }

    bootstrap.Modal.getOrCreateInstance(bigModal).show();
  }

  // CLICK router SOLO en Streaming familiar
  document.addEventListener('click', function(e){
    if (!famPane.contains(e.target)) return;

    // Ignorar click si fue en la celda Plan (esa abre el modal chico)
    if (e.target.closest('.plan-cell-perfil')) return;

    // Ignorar controles/acciones dentro de la fila
    if (e.target.closest('.js-row-action, button, a, input, select, textarea')) return;

    var tr = e.target.closest('tr.js-parent-row[data-entidad="perfil_fam"][data-modal-context="child"]');
    if (!tr) return;

    e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
    // Cerrar el chico si estuviera abierto por algún motivo
    try { if (smallModal) bootstrap.Modal.getInstance(smallModal)?.hide(); } catch(_){}
    openBigFromRow(tr);
  }, true);

  // ENTER/ESPACIO en la fila (no en Plan ni en controles)
  document.addEventListener('keydown', function(e){
    if (!famPane.contains(e.target)) return;
    if (e.key !== 'Enter' && e.key !== ' ') return;

    if (e.target.closest('.plan-cell-perfil')) return;
    if (e.target.closest('.js-row-action, button, a, input, select, textarea')) return;

    var tr = e.target.closest('tr.js-parent-row[data-entidad="perfil_fam"][data-modal-context="child"]');
    if (!tr) return;

    e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
    try { if (smallModal) bootstrap.Modal.getInstance(smallModal)?.hide(); } catch(_){}
    openBigFromRow(tr);
  }, true);
})();
</script>

<!-- /public/streaming.php  ── SOLO para la pestaña “Streaming familiar”
Pega este bloque INLINE al final del archivo (o justo después de la tabla de #perfiles-familiar). -->
<script>
;(function(){
  'use strict';
  if (window.__famBigModalRouterHard) return; window.__famBigModalRouterHard = true;

  var famPane    = document.getElementById('perfiles-familiar');
  var bigModal   = document.getElementById('perfilFamiliarModal');      // GRANDE (Agregar a correo…)
  var smallModal = document.getElementById('modalCambiarPlanPerfil');   // CHICO (Cambiar plan/color/enviar)
  if (!famPane || !bigModal || !window.bootstrap) return;

  // Rellena el modal GRANDE con los datos del <tr>
  function openBigFromRow(tr){
    var qs = function(sel){ return bigModal.querySelector(sel); };
    var set = function(sel, val){ var el = qs(sel); if (el) el.value = val; };

    var correo  = tr.getAttribute('data-correo') || '';
    var pass    = tr.getAttribute('data-password') || '';
    var sid     = tr.getAttribute('data-streaming_id') || '';
    var combo   = String(tr.getAttribute('data-combo') || '0');

    var title = bigModal.querySelector('#perfilFamiliarModalLabel') || bigModal.querySelector('.modal-title');
    if (title) title.textContent = correo ? ('Agregar a correo: ' + correo) : 'Agregar a correo';

    set('input[name="correo"]', correo);
    set('input[name="password_plain"]', pass);
    set('input[name="streaming_id"]', sid);
    set('select[name="estado"]', 'pendiente');
    set('select[name="dispositivo"]', 'tv');
    set('select[name="combo"]', (combo === '1' ? '1' : '0'));

    var hoy = new Date(), fin = new Date(hoy.getTime() + 31*24*60*60*1000);
    var toISO = function(d){ return d.toISOString().slice(0,10); };
    set('input[name="fecha_inicio"]', toISO(hoy));
    set('input[name="fecha_fin"]', toISO(fin));

    var soles = qs('input[name="soles"]');
    if (soles) { soles.readOnly = false; soles.removeAttribute('readonly'); soles.value = ''; }

    bootstrap.Modal.getOrCreateInstance(bigModal).show();
  }

  // Flags de enrutado por evento
  var hitPlanCell = false;
  var hitControl  = false;
  var hitRow      = null;

  // Marcar lo más temprano posible (fase de captura) dónde fue el clic
  ['pointerdown','mousedown','touchstart'].forEach(function(type){
    document.addEventListener(type, function(ev){
      if (!famPane.contains(ev.target)) { hitPlanCell=false; hitControl=false; hitRow=null; return; }
      hitPlanCell = !!(ev.target.closest && ev.target.closest('#perfiles-familiar .plan-cell-perfil'));
      hitControl  = !!(ev.target.closest && ev.target.closest('.js-row-action, button, a, input, select, textarea'));
      hitRow      = (ev.target.closest && ev.target.closest('#perfiles-familiar tr.js-parent-row[data-entidad="perfil_fam"][data-modal-context="child"]')) || null;
    }, true);
  });

  // CLICK (fase de captura): si es fila familiar y NO fue en Plan ni en un control, abrimos SOLO el GRANDE
  document.addEventListener('click', function(ev){
    if (!hitRow) return;
    if (hitPlanCell || hitControl) { hitPlanCell=false; hitControl=false; hitRow=null; return; }

    // Cortar a TODOS los demás listeners que estaban impidiendo el flujo
    ev.preventDefault(); ev.stopPropagation(); ev.stopImmediatePropagation();

    // Por si algún script intenta abrir el chico, lo cerramos
    try { if (smallModal) bootstrap.Modal.getInstance(smallModal)?.hide(); } catch(_){}

    openBigFromRow(hitRow);

    // reset
    hitPlanCell=false; hitControl=false; hitRow=null;
  }, true);

  // ENTER / ESPACIO sobre la fila (fase de captura)
  document.addEventListener('keydown', function(ev){
    if (!famPane.contains(ev.target)) return;
    if (ev.key !== 'Enter' && ev.key !== ' ') return;

    var tr = ev.target.closest && ev.target.closest('#perfiles-familiar tr.js-parent-row[data-entidad="perfil_fam"][data-modal-context="child"]');
    if (!tr) return;

    if (ev.target.closest && (ev.target.closest('#perfiles-familiar .plan-cell-perfil') || ev.target.closest('.js-row-action, button, a, input, select, textarea'))) return;

    ev.preventDefault(); ev.stopPropagation(); ev.stopImmediatePropagation();
    try { if (smallModal) bootstrap.Modal.getInstance(smallModal)?.hide(); } catch(_){}
    openBigFromRow(tr);
  }, true);

  // Corta cualquier intento residual de abrir el grande DESDE un click en Plan (sólo por si acaso)
  bigModal.addEventListener('show.bs.modal', function(ev){
    var rel = ev.relatedTarget || null;
    if (rel && rel.closest && rel.closest('#perfiles-familiar .plan-cell-perfil')) {
      ev.preventDefault(); ev.stopImmediatePropagation();
      try { bootstrap.Modal.getInstance(bigModal)?.hide(); } catch(_){}
    }
  }, true);
})();
</script>


<?php
// Modales y footer
include __DIR__ . '/../includes/modals.php';
include __DIR__ . '/../includes/footer.php';
