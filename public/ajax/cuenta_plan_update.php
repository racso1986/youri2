<?php
declare(strict_types=1);

// Limpieza de salida y cabecera JSON
ob_start();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
if (!headers_sent()) { header('Content-Type: application/json; charset=UTF-8'); }

// Solo POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' && empty($_POST)) {
  ob_clean();
  echo json_encode(['ok'=>false,'error'=>'Usa POST a este endpoint'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  // Bootstrap conexión tolerante
  $root = realpath(__DIR__ . '/../../');
  foreach ([$root.'/config/config.php',$root.'/config/db.php',dirname($root).'/config/config.php',dirname($root).'/config/db.php'] as $f) {
    if ($f && is_file($f)) require_once $f;
  }
  $pdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo : ((isset($dbh) && $dbh instanceof PDO) ? $dbh : null);
  $mysqli = (isset($mysqli) && $mysqli instanceof mysqli) ? $mysqli : ((isset($conn) && $conn instanceof mysqli) ? $conn : ((isset($link) && $link instanceof mysqli) ? $link : null));
  if (!$pdo && !$mysqli && defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
    try {
      $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, (defined('DB_PASS')?DB_PASS:(defined('DB_PASSWORD')?DB_PASSWORD:'')), [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false
      ]);
    } catch (Throwable $e) {}
  }

  // === ENTRADA tolerante ===
  $raw = (stripos($_SERVER['CONTENT_TYPE']??'', 'application/json')!==false) ? json_decode(file_get_contents('php://input')?:'[]', true) : [];
  $IN  = array_merge($_GET??[], $_POST??[], is_array($raw)?$raw:[]);

  if (isset($IN['__echo']) || isset($_GET['__echo'])) {
    ob_clean();
    echo json_encode(['ok'=>false,'IN'=>$IN,'POST'=>$_POST,'GET'=>$_GET,'CT'=>($_SERVER['CONTENT_TYPE']??'')], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // color (opcional)
  $color = isset($IN['color']) ? strtolower(trim((string)$IN['color'])) : '';
  $colorAllowed = ['', 'rojo','azul','verde','blanco','restablecer'];
  if (!in_array($color, $colorAllowed, true)) { $color = ''; }

  // ID de cuenta
  $id = 0; foreach (['id','cuenta_id','row_id'] as $k) { if (!empty($IN[$k])) { $id = (int)$IN[$k]; break; } }

  // Plan
  $plan = isset($IN['plan']) ? (string)$IN['plan'] : '';
  $allowed = ['individual','standard','premium','estándar','Individual','Standard','Premium','Estándar'];
  if ($plan === '' || !in_array($plan, $allowed, true)) { ob_clean(); echo json_encode(['ok'=>false,'error'=>'Plan inválido']); exit; }
  $map = ['Individual'=>'individual','Standard'=>'standard','Premium'=>'premium','Estándar'=>'standard','estándar'=>'standard'];
  $planSave = $map[$plan] ?? strtolower($plan);

  // Acción secundaria
  $enviarA = isset($IN['enviar_a']) ? strtolower(trim((string)$IN['enviar_a'])) : 'none';

  if (isset($IN['dbg_tag'])) {
    ob_clean();
    echo json_encode([
      'ok' => false,
      'dbg' => ['IN'=>$IN,'GET'=>$_GET ?? [],'POST'=>$_POST ?? [],'RAW'=>$raw ?? null,'CT'=>($_SERVER['CONTENT_TYPE'] ?? '')]
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($id <= 0) { ob_clean(); echo json_encode(['ok'=>false,'error'=>'ID inválido']); exit; }

  // === UPDATE plan en CUENTAS (no tocar fecha_inicio) ===
  $updated = false;

  // helpers para updated_at
  $hasUpdatedAt = false;
  if ($pdo instanceof PDO) {
    $chk = $pdo->prepare("SHOW COLUMNS FROM `cuentas` LIKE 'updated_at'");
    $chk->execute();
    $hasUpdatedAt = (bool)$chk->fetch();
  } elseif ($mysqli instanceof mysqli) {
    $chk = $mysqli->query("SHOW COLUMNS FROM `cuentas` LIKE 'updated_at'");
    $hasUpdatedAt = $chk && $chk->num_rows > 0;
    if ($chk instanceof mysqli_result) { $chk->free(); }
  }

  if ($pdo instanceof PDO) {
    $cur = $pdo->prepare('SELECT id, plan FROM cuentas WHERE id = :id LIMIT 1');
    $cur->execute([':id'=>$id]);
    $row = $cur->fetch();
    if (!$row) { ob_clean(); echo json_encode(['ok'=>false,'error'=>'Registro no existe']); exit; }

    if (strtolower((string)$row['plan']) === $planSave) {
      // Nada que cambiar, pero marcamos updated lógico
      $updated = true;
      if ($hasUpdatedAt) {
        $u0 = $pdo->prepare('UPDATE cuentas SET updated_at = NOW() WHERE id = :id');
        $u0->execute([':id'=>$id]);
      }
    } else {
      $sql = 'UPDATE cuentas SET plan = :p'.($hasUpdatedAt ? ', updated_at = NOW()' : '').' WHERE id = :id';
      $u = $pdo->prepare($sql);
      $updated = $u->execute([':p'=>$planSave, ':id'=>$id]);
    }

  } elseif ($mysqli instanceof mysqli) {
    $sel = $mysqli->prepare('SELECT id, plan FROM cuentas WHERE id = ? LIMIT 1');
    $sel->bind_param('i',$id); $sel->execute(); $res = $sel->get_result(); $row = $res?$res->fetch_assoc():null; $sel->close();
    if (!$row) { ob_clean(); echo json_encode(['ok'=>false,'error'=>'Registro no existe']); exit; }

    if (strtolower((string)$row['plan']) === $planSave) {
      $updated = true;
      if ($hasUpdatedAt) {
        $u0 = $mysqli->prepare('UPDATE cuentas SET updated_at = NOW() WHERE id = ?');
        $u0->bind_param('i',$id); $u0->execute(); $u0->close();
      }
    } else {
      if ($hasUpdatedAt) {
        $u = $mysqli->prepare('UPDATE cuentas SET plan = ?, updated_at = NOW() WHERE id = ?');
      } else {
        $u = $mysqli->prepare('UPDATE cuentas SET plan = ? WHERE id = ?');
      }
      $u->bind_param('si',$planSave,$id); $updated = $u->execute(); $u->close();
    }

  } else {
    ob_clean(); echo json_encode(['ok'=>false,'error'=>'Sin conexión a base de datos']); exit;
  }

  // === Actualizar color si enviar_a='none' y se envió color (no toca fecha_inicio) ===
  if ($enviarA === 'none' && $color !== '') {
    if ($pdo instanceof PDO) {
      if ($color === 'restablecer') {
        $u2 = $pdo->prepare('UPDATE cuentas SET color = NULL'.($hasUpdatedAt?', updated_at = NOW()':'').' WHERE id = :id');
        $u2->execute([':id'=>$id]);
      } else {
        $u2 = $pdo->prepare('UPDATE cuentas SET color = :c'.($hasUpdatedAt?', updated_at = NOW()':'').' WHERE id = :id');
        $u2->execute([':c'=>$color, ':id'=>$id]);
      }
    } elseif ($mysqli instanceof mysqli) {
      if ($color === 'restablecer') {
        if ($hasUpdatedAt) {
          $u2 = $mysqli->prepare('UPDATE cuentas SET color = NULL, updated_at = NOW() WHERE id = ?');
        } else {
          $u2 = $mysqli->prepare('UPDATE cuentas SET color = NULL WHERE id = ?');
        }
        $u2->bind_param('i',$id); $u2->execute(); $u2->close();
      } else {
        if ($hasUpdatedAt) {
          $u2 = $mysqli->prepare('UPDATE cuentas SET color = ?, updated_at = NOW() WHERE id = ?');
        } else {
          $u2 = $mysqli->prepare('UPDATE cuentas SET color = ? WHERE id = ?');
        }
        $u2->bind_param('si',$color,$id); $u2->execute(); $u2->close();
      }
    }
  }

  // === MOVER a STOCK/PAUSA (mantener fecha_inicio original) ===
  $inserted = 0; $moved_rows = []; $moved_to = null;
  if (in_array($enviarA, ['stock','pausa'], true)) {
    $src = 'cuentas';
    $dst = ($enviarA === 'stock') ? 'perfiles_stock' : 'perfiles_pausa';

    // columnas en "cuentas"
    $srcCols = ['streaming_id','correo','password_plain','plan','estado','fecha_inicio','fecha_fin','soles'];
    // columnas destino completas
    $dstCols = ['streaming_id','correo','password_plain','plan','estado','fecha_inicio','fecha_fin','soles','dispositivo','perfil','whatsapp','combo'];

    // normalizar plan para destino
    $planIns = strtolower($planSave);
    if ($planIns === 'standard') $planIns = 'estándar';
    if ($planIns === '') $planIns = 'individual';

    if ($pdo instanceof PDO) {
      $pdo->beginTransaction();

      $sel = $pdo->prepare("SELECT `id`, ".implode(',', array_map(fn($c)=>"`$c`", $srcCols))." FROM `$src` WHERE `id` = :id LIMIT 1");
      $sel->execute([':id'=>$id]);
      $r = $sel->fetch(PDO::FETCH_ASSOC);

      if ($r) {
        // construir payload conservando fecha_inicio original
        $payload = [
          ':streaming_id'   => $r['streaming_id'] ?? null,
          ':correo'         => $r['correo'] ?? null,
          ':password_plain' => $r['password_plain'] ?? null,
          ':plan'           => $planIns,
          ':estado'         => $r['estado'] ?? 'activo',
          ':fecha_inicio'   => $r['fecha_inicio'] ?? null,
          ':fecha_fin'      => $r['fecha_fin'] ?? null,
          ':soles'          => $r['soles'] ?? '0.00',
          ':dispositivo'    => '',
          ':perfil'         => '',
          ':whatsapp'       => '',
          ':combo'          => 0,
        ];

        $sqlI = "INSERT INTO `$dst`
          (`streaming_id`,`correo`,`password_plain`,`plan`,`estado`,`fecha_inicio`,`fecha_fin`,`soles`,`dispositivo`,`perfil`,`whatsapp`,`combo`)
          VALUES
          (:streaming_id,:correo,:password_plain,:plan,:estado,:fecha_inicio,:fecha_fin,:soles,:dispositivo,:perfil,:whatsapp,:combo)";
        $ins = $pdo->prepare($sqlI);
        $ok  = $ins->execute($payload);

        if ($ok) {
          $inserted = 1; $moved_to = $enviarA;
          $newId = (int)$pdo->lastInsertId();
          $moved_rows[] = [
            'id'=>$newId,
            'streaming_id'=>$r['streaming_id'] ?? '',
            'correo'=>$r['correo'] ?? '',
            'password_plain'=>$r['password_plain'] ?? '',
            'plan'=>$planIns
          ];

          // borrar origen
          $del = $pdo->prepare("DELETE FROM `$src` WHERE `id` = :id");
          $del->execute([':id'=>$id]);
        }
      }

      $pdo->commit();

    } elseif ($mysqli instanceof mysqli) {
      $mysqli->begin_transaction();

      $sqlSel = "SELECT `id`, ".implode(',', array_map(fn($c)=>"`$c`", $srcCols))." FROM `$src` WHERE `id` = ? LIMIT 1";
      $sel = $mysqli->prepare($sqlSel);
      $sel->bind_param('i',$id);
      $sel->execute();
      $res = $sel->get_result();
      $r = $res ? $res->fetch_assoc() : null;
      $sel->close();

      if ($r) {
        $vals = [
          $r['streaming_id'] ?? '',
          $r['correo'] ?? '',
          $r['password_plain'] ?? '',
          $planIns,
          $r['estado'] ?? 'activo',
          $r['fecha_inicio'] ?? '',
          $r['fecha_fin'] ?? '',
          $r['soles'] ?? '0.00',
          '', // dispositivo
          '', // perfil
          '', // whatsapp
          '0',// combo
        ];

        $sqlI = "INSERT INTO `$dst`
          (`streaming_id`,`correo`,`password_plain`,`plan`,`estado`,`fecha_inicio`,`fecha_fin`,`soles`,`dispositivo`,`perfil`,`whatsapp`,`combo`)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
        $ins = $mysqli->prepare($sqlI);
        $types = 'ssssssssssss';
        if ($ins && $ins->bind_param($types, ...$vals) && $ins->execute()) {
          $inserted = 1; $moved_to = $enviarA;
          $newId = $ins->insert_id ?? 0;
          $moved_rows[] = [
            'id'=>$newId,
            'streaming_id'=>$r['streaming_id'] ?? '',
            'correo'=>$r['correo'] ?? '',
            'password_plain'=>$r['password_plain'] ?? '',
            'plan'=>$planIns
          ];
          $ins->close();

          $del = $mysqli->prepare("DELETE FROM `$src` WHERE `id` = ?");
          $del->bind_param('i',$id);
          $del->execute();
          $del->close();
        }
      }

      $mysqli->commit();
    }
  }

  ob_clean();
  $resp = [
    'ok'         => true,
    'updated'    => (bool)$updated,
    'plan'       => $planSave,
    'moved_to'   => $moved_to,
    'inserted'   => $inserted,
    'moved_rows' => $moved_rows,
  ];
  if ($color !== '') { $resp['color'] = ($color === 'restablecer') ? null : $color; }
  echo json_encode($resp, JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  ob_clean();
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}
