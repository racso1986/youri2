<?php
declare(strict_types=1);

// --- limpieza de salida / cabecera ---
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
  // === Conexión ===
  $root = realpath(__DIR__ . '/../../');
  foreach ([$root.'/config/config.php', $root.'/config/db.php', dirname($root).'/config/config.php', dirname($root).'/config/db.php'] as $f) {
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

  // === Entrada (tolerante) ===
  $rawJson = null;
  if (isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    if ($raw) { try { $rawJson = json_decode($raw, true); } catch (Throwable $e) { $rawJson = null; } }
  }
  $IN = array_merge($_GET ?? [], $_POST ?? [], is_array($rawJson) ? $rawJson : []);

  // ID del perfil (evitamos confundir con cuenta_id)
  $id = 0;
  foreach (['perfil_id','perfiles_id','id','row_id'] as $k) {
    if (!empty($IN[$k])) { $id = (int)$IN[$k]; break; }
  }
  if ($id <= 0) { ob_clean(); echo json_encode(['ok'=>false,'error'=>'ID inválido']); exit; }

  // Plan
  $plan = isset($IN['plan']) ? (string)$IN['plan'] : '';
  $allowedPlans = ['individual','standard','premium','estándar','Individual','Standard','Premium','Estándar'];
  if ($plan === '' || !in_array($plan, $allowedPlans, true)) { ob_clean(); echo json_encode(['ok'=>false,'error'=>'Plan inválido']); exit; }
  $planMap = ['Individual'=>'individual','Standard'=>'standard','Premium'=>'premium','Estándar'=>'standard','estándar'=>'standard'];
  $planSave = $planMap[$plan] ?? strtolower($plan);

  // Acción secundaria
  $enviarA = isset($IN['enviar_a']) ? strtolower(trim((string)$IN['enviar_a'])) : 'none';

  // Color (opcional)
  $colorIn = isset($IN['color']) ? strtolower(trim((string)$IN['color'])) : '';
  $colorSave = null; // null => escribir NULL (restablecer)
  if ($colorIn !== '') {
    $allowedColors = ['rojo','azul','verde','blanco','restablecer'];
    if (!in_array($colorIn, $allowedColors, true)) { $colorIn = ''; }
    else { $colorSave = ($colorIn === 'restablecer') ? null : $colorIn; }
  }

  // ¿tiene updated_at?
  $hasUpdatedAt = false;
  if ($pdo instanceof PDO) {
    $chk = $pdo->prepare("SHOW COLUMNS FROM `perfiles` LIKE 'updated_at'");
    $chk->execute(); $hasUpdatedAt = (bool)$chk->fetch();
  } elseif ($mysqli instanceof mysqli) {
    $chk = $mysqli->query("SHOW COLUMNS FROM `perfiles` LIKE 'updated_at'");
    $hasUpdatedAt = $chk && $chk->num_rows > 0;
    if ($chk instanceof mysqli_result) { $chk->free(); }
  }

  // === Update plan en PERFILES (no tocar fecha_inicio) ===
  $updated = false;
  if ($pdo instanceof PDO) {
    $cur = $pdo->prepare('SELECT plan FROM perfiles WHERE id = :id LIMIT 1');
    $cur->execute([':id'=>$id]);
    $row = $cur->fetch();
    if (!$row) { ob_clean(); echo json_encode(['ok'=>false,'error'=>'Registro no existe']); exit; }

    if (strtolower((string)$row['plan']) === $planSave) {
      $updated = true;
      if ($hasUpdatedAt) {
        $pdo->prepare('UPDATE perfiles SET updated_at = NOW() WHERE id = :id')->execute([':id'=>$id]);
      }
    } else {
      $sql = 'UPDATE perfiles SET plan = :plan'.($hasUpdatedAt?', updated_at = NOW()':'').' WHERE id = :id';
      $stmt = $pdo->prepare($sql);
      $updated = $stmt->execute([':plan'=>$planSave, ':id'=>$id]);
    }

  } elseif ($mysqli instanceof mysqli) {
    $sel = $mysqli->prepare('SELECT plan FROM perfiles WHERE id = ? LIMIT 1');
    $sel->bind_param('i',$id); $sel->execute();
    $res = $sel->get_result(); $row = $res ? $res->fetch_assoc() : null; $sel->close();
    if (!$row) { ob_clean(); echo json_encode(['ok'=>false,'error'=>'Registro no existe']); exit; }

    if (strtolower((string)$row['plan']) === $planSave) {
      $updated = true;
      if ($hasUpdatedAt) {
        $u0 = $mysqli->prepare('UPDATE perfiles SET updated_at = NOW() WHERE id = ?');
        $u0->bind_param('i',$id); $u0->execute(); $u0->close();
      }
    } else {
      if ($hasUpdatedAt) {
        $stmt = $mysqli->prepare('UPDATE perfiles SET plan = ?, updated_at = NOW() WHERE id = ?');
      } else {
        $stmt = $mysqli->prepare('UPDATE perfiles SET plan = ? WHERE id = ?');
      }
      $stmt->bind_param('si',$planSave,$id);
      $updated = $stmt->execute();
      $stmt->close();
    }

  } else {
    ob_clean(); echo json_encode(['ok'=>false,'error'=>'Sin conexión a base de datos']); exit;
  }

  // === Propagar plan (y color) a perfiles con el mismo correo cuando NO hay movimiento ===
  if ($enviarA === 'none' || !in_array($enviarA, ['stock','pausa'], true)) {
    if ($pdo instanceof PDO) {
      $q = $pdo->prepare('SELECT correo FROM perfiles WHERE id = :id LIMIT 1');
      $q->execute([':id'=>$id]);
      $rc = $q->fetch(PDO::FETCH_ASSOC);

      if ($rc && !empty($rc['correo'])) {
        $correo = (string)$rc['correo'];

        $pdo->prepare('UPDATE perfiles SET plan = :p'.($hasUpdatedAt?', updated_at = NOW()':'').' WHERE correo = :c')
            ->execute([':p'=>$planSave, ':c'=>$correo]);

        if ($colorIn !== '') {
          if ($colorSave === null) {
            $pdo->prepare('UPDATE perfiles SET color = NULL'.($hasUpdatedAt?', updated_at = NOW()':'').' WHERE correo = :c')
                ->execute([':c'=>$correo]);
          } else {
            $pdo->prepare('UPDATE perfiles SET color = :color'.($hasUpdatedAt?', updated_at = NOW()':'').' WHERE correo = :c')
                ->execute([':color'=>$colorSave, ':c'=>$correo]);
          }
        }
      }

    } elseif ($mysqli instanceof mysqli) {
      $q = $mysqli->prepare('SELECT correo FROM perfiles WHERE id = ? LIMIT 1');
      $q->bind_param('i',$id); $q->execute();
      $res = $q->get_result(); $rc = $res ? $res->fetch_assoc() : null; $q->close();

      if ($rc && !empty($rc['correo'])) {
        $correo = (string)$rc['correo'];

        if ($hasUpdatedAt) {
          $u = $mysqli->prepare('UPDATE perfiles SET plan = ?, updated_at = NOW() WHERE correo = ?');
        } else {
          $u = $mysqli->prepare('UPDATE perfiles SET plan = ? WHERE correo = ?');
        }
        $u->bind_param('ss',$planSave,$correo); $u->execute(); $u->close();

        if ($colorIn !== '') {
          if ($colorSave === null) {
            if ($hasUpdatedAt) {
              $u2 = $mysqli->prepare('UPDATE perfiles SET color = NULL, updated_at = NOW() WHERE correo = ?');
            } else {
              $u2 = $mysqli->prepare('UPDATE perfiles SET color = NULL WHERE correo = ?');
            }
            $u2->bind_param('s',$correo); $u2->execute(); $u2->close();
          } else {
            if ($hasUpdatedAt) {
              $u2 = $mysqli->prepare('UPDATE perfiles SET color = ?, updated_at = NOW() WHERE correo = ?');
            } else {
              $u2 = $mysqli->prepare('UPDATE perfiles SET color = ? WHERE correo = ?');
            }
            $u2->bind_param('ss',$colorSave,$correo); $u2->execute(); $u2->close();
          }
        }
      }
    }
  }

  // === Mover PERFILES -> STOCK/PAUSA (grupo por correo, conservando fecha_inicio) ===
  $inserted = 0; $moved_rows = []; $moved_to = null;
  if (in_array($enviarA, ['stock','pausa'], true)) {
    $srcTable = 'perfiles';
    $dstTable = ($enviarA === 'stock') ? 'perfiles_stock' : 'perfiles_pausa';
    $cols = ['streaming_id','correo','password_plain','plan','estado','fecha_inicio','fecha_fin','soles','dispositivo','perfil','whatsapp','combo'];

    if ($pdo instanceof PDO) {
      $pdo->beginTransaction();

      $padre = $pdo->prepare("SELECT correo, password_plain FROM `$srcTable` WHERE id = :id LIMIT 1");
      $padre->execute([':id'=>$id]);
      $p = $padre->fetch(PDO::FETCH_ASSOC);

      if ($p && !empty($p['correo'])) {
        $correoPadre = (string)$p['correo'];
        $passPadre   = (string)$p['password_plain'];

        $sel = $pdo->prepare("SELECT id, ".implode(',', array_map(fn($c)=>"`$c`",$cols))." FROM `$srcTable` WHERE correo = :correo");
        $sel->execute([':correo'=>$correoPadre]);
        $rows = $sel->fetchAll(PDO::FETCH_ASSOC);

        if ($rows) {
          $ins = $pdo->prepare("INSERT INTO `$dstTable`
            (`streaming_id`,`correo`,`password_plain`,`plan`,`estado`,`fecha_inicio`,`fecha_fin`,`soles`,`dispositivo`,`perfil`,`whatsapp`,`combo`)
            VALUES (:streaming_id,:correo,:password_plain,:plan,:estado,:fecha_inicio,:fecha_fin,:soles,:dispositivo,:perfil,:whatsapp,:combo)");

          foreach ($rows as $r) {
            $planIns = strtolower($planSave);
            if ($planIns === 'standard') $planIns = 'estándar';
            if ($planIns === '') $planIns = 'individual';

            $ok = $ins->execute([
              ':streaming_id'   => $r['streaming_id']   ?? null,
              ':correo'         => $correoPadre,
              ':password_plain' => $passPadre,
              ':plan'           => $planIns,
              ':estado'         => $r['estado']         ?? 'activo',
              ':fecha_inicio'   => $r['fecha_inicio']   ?? null,
              ':fecha_fin'      => $r['fecha_fin']      ?? null,
              ':soles'          => $r['soles']          ?? '0.00',
              ':dispositivo'    => $r['dispositivo']    ?? '',
              ':perfil'         => $r['perfil']         ?? '',
              ':whatsapp'       => $r['whatsapp']       ?? '',
              ':combo'          => $r['combo']          ?? 0,
            ]);
            if ($ok) {
              $inserted++;
              $moved_rows[] = [
                'id'           => (int)$pdo->lastInsertId(),
                'streaming_id' => $r['streaming_id'] ?? '',
                'correo'       => $correoPadre,
                'password_plain'=>$passPadre,
                'plan'         => $planIns
              ];
            }
          }

          if ($inserted > 0) {
            $pdo->prepare("DELETE FROM `$srcTable` WHERE correo = :c")->execute([':c'=>$correoPadre]);
          }
          $moved_to = $enviarA;
        }
      }

      $pdo->commit();

    } elseif ($mysqli instanceof mysqli) {
      $mysqli->begin_transaction();

      $q0 = $mysqli->prepare("SELECT correo, password_plain FROM `$srcTable` WHERE id = ? LIMIT 1");
      $q0->bind_param('i',$id); $q0->execute();
      $res0 = $q0->get_result(); $p = $res0 ? $res0->fetch_assoc() : null; $q0->close();

      if ($p && !empty($p['correo'])) {
        $correoPadre = (string)$p['correo'];
        $passPadre   = (string)$p['password_plain'];

        $sel = $mysqli->prepare("SELECT id, ".implode(',', array_map(fn($c)=>"`$c`",$cols))." FROM `$srcTable` WHERE correo = ?");
        $sel->bind_param('s',$correoPadre); $sel->execute();
        $res = $sel->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        $sel->close();

        if ($rows) {
          $ins = $mysqli->prepare("INSERT INTO `$dstTable`
            (`streaming_id`,`correo`,`password_plain`,`plan`,`estado`,`fecha_inicio`,`fecha_fin`,`soles`,`dispositivo`,`perfil`,`whatsapp`,`combo`)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
          $types = 'ssssssssssss';

          foreach ($rows as $r) {
            $planIns = strtolower($planSave);
            if ($planIns === 'standard') $planIns = 'estándar';
            if ($planIns === '') $planIns = 'individual';

            $vals = [
              $r['streaming_id'] ?? '',
              $correoPadre,
              $passPadre,
              $planIns,
              $r['estado']       ?? 'activo',
              $r['fecha_inicio'] ?? '',
              $r['fecha_fin']    ?? '',
              $r['soles']        ?? '0.00',
              $r['dispositivo']  ?? '',
              $r['perfil']       ?? '',
              $r['whatsapp']     ?? '',
              (string)($r['combo'] ?? '0'),
            ];
            if ($ins && $ins->bind_param($types, ...$vals) && $ins->execute()) {
              $inserted++;
              $moved_rows[] = [
                'id'            => (int)($ins->insert_id ?? 0),
                'streaming_id'  => $r['streaming_id'] ?? '',
                'correo'        => $correoPadre,
                'password_plain'=> $passPadre,
                'plan'          => $planIns
              ];
            }
          }
          if ($ins) { $ins->close(); }

          if ($inserted > 0) {
            $del = $mysqli->prepare("DELETE FROM `$srcTable` WHERE correo = ?");
            $del->bind_param('s',$correoPadre);
            $del->execute(); $del->close();
          }
          $moved_to = $enviarA;
        }
      }

      $mysqli->commit();
    }
  }

  // === Respuesta ===
  ob_clean();
  echo json_encode([
    'ok'         => true,
    'updated'    => (bool)$updated,
    'plan'       => $planSave,
    'color'      => ($colorIn === '' ? null : ($colorSave === null ? 'NULL(restablecido)' : $colorSave)),
    'moved_to'   => $moved_to,
    'inserted'   => (int)$inserted,
    'moved_rows' => $moved_rows,
  ], JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  ob_clean();
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}
