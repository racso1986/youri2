<?php
declare(strict_types=1);
if (!headers_sent()) {
  header('Content-Type: application/json; charset=UTF-8');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' && empty($_POST)) {
  ob_clean();
  echo json_encode(['ok'=>false,'error'=>'Usa POST a este endpoint'], JSON_UNESCAPED_UNICODE);
  exit;
}

ob_start();
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (!ini_get('error_log')) {
  @ini_set('error_log', __DIR__.'/../../storage/logs/stock_pausa_plan_update.log');
}

try {
  // ========== Bootstrap de CONEXIÓN robusto (PDO/MySQLi) ==========
  $pdo = null; $mysqli = null;

  $here = realpath(__DIR__); // /public/ajax
  $levels = [$here, dirname($here), dirname($here,2), dirname($here,3), dirname($here,4)];
  $rel = [
    '/config/config.php','/config/db.php',
    '/includes/config.php','/includes/db.php',
    '/app/config.php','/app/db.php','/app/bootstrap.php',
    '/config/database.php','/core/db.php'
  ];
  foreach ($levels as $base) {
    foreach ($rel as $r) {
      $p = $base.$r;
      if (is_file($p)) { require_once $p; }
    }
  }

  // detectar instancias existentes
  if (isset($pdo) && $pdo instanceof PDO) {
    // ok
  } elseif (isset($dbh) && $dbh instanceof PDO) {
    $pdo = $dbh;
  } elseif (function_exists('getPDO')) {
    $pdo = @getPDO();
  } elseif (function_exists('pdo')) {
    $pdo = @pdo();
  } elseif (class_exists('DB')) {
    foreach (['pdo','get','connection','conn','getConnection'] as $m) {
      if (method_exists('DB',$m)) { $pdo = @DB::$m(); if ($pdo instanceof PDO) break; }
    }
  }

  if (!$pdo) {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
      // ok
    } elseif (isset($conn) && $conn instanceof mysqli) {
      $mysqli = $conn;
    } elseif (isset($link) && $link instanceof mysqli) {
      $mysqli = $link;
    } elseif (function_exists('db')) {
      $tmp = @db();
      if ($tmp instanceof mysqli) { $mysqli = $tmp; }
    }
  }

  // intento final: construir PDO desde constantes/entorno
  if (!$pdo && !$mysqli) {
    $host = defined('DB_HOST') ? DB_HOST : (defined('DATABASE_HOST') ? DATABASE_HOST : getenv('DB_HOST'));
    $name = defined('DB_NAME') ? DB_NAME : (defined('DATABASE_NAME') ? DATABASE_NAME : getenv('DB_NAME'));
    $user = defined('DB_USER') ? DB_USER : (defined('DATABASE_USER') ? DATABASE_USER : getenv('DB_USER'));
    $pass = defined('DB_PASS') ? DB_PASS : (defined('DATABASE_PASSWORD') ? DATABASE_PASSWORD ?? DB_PASSWORD : getenv('DB_PASS'));
    if ($host && $name && $user) {
      $dsn = 'mysql:host='.$host.';dbname='.$name.';charset=utf8mb4';
      try {
        $pdo = new PDO($dsn, (string)$user, (string)$pass, [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES => false,
        ]);
      } catch (Throwable $e) {
        // seguirá validación abajo
      }
    }
  }

  // ===== Helpers =====
  $normalizePlan = static function (?string $p): string {
    $p = (string)$p;
    $map = [
      'standard'=>'estándar','Standard'=>'estándar','Estándar'=>'estándar','estandar'=>'estándar','estándard'=>'estándar',
      'premium'=>'premium','Premium'=>'premium',
      'individual'=>'individual','Individual'=>'individual',
      'estándar'=>'estándar'
    ];
    $p = $map[$p] ?? strtolower($p);
    if (!in_array($p, ['individual','estándar','premium'], true)) {
      $p = 'individual';
    }
    return $p;
  };

  // ========== Entrada ==========
  $id       = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  $plan     = $normalizePlan($_POST['plan'] ?? '');
  // 'tipo' y 'destino'. Compat con 'tabla' antigua.
  $tipo     = isset($_POST['tipo']) ? (string)$_POST['tipo'] : '';
  $tablaOld = isset($_POST['tabla']) ? (string)$_POST['tabla'] : '';
  $destino  = isset($_POST['destino']) ? trim((string)$_POST['destino']) : '';

  // color (opcional)
  $color = isset($_POST['color']) ? strtolower(trim((string)$_POST['color'])) : '';
  $colorAllowed = ['', 'rojo','azul','verde','blanco','restablecer'];
  if (!in_array($color, $colorAllowed, true)) { $color = ''; }

  if ($id <= 0) { throw new RuntimeException('ID inválido'); }

  // Normalizar tipo a partir de 'tipo' o 'tabla' legacy
  $t = strtolower(trim($tipo ?: $tablaOld));
  if ($t === 'perfiles_stock') $t = 'stock';
  if ($t === 'perfiles_pausa') $t = 'pausa';
  if (!in_array($t, ['stock','pausa'], true)) {
    throw new RuntimeException('Tipo/Tabla inválida');
  }

  // Validar destinos permitidos
  // public/ajax/stock_pausa_plan_update.php
$allowed = ($t === 'stock') ? ['','perfiles','cuentas','pausa'] : ['','perfiles','cuentas','stock'];

  if (!in_array($destino, $allowed, true)) {
    throw new RuntimeException('Destino no permitido para el tipo indicado');
  }

  // Resolver tabla origen/destino
  $tablaOrigen = ($t === 'stock') ? 'perfiles_stock' : 'perfiles_pausa';
  // public/ajax/stock_pausa_plan_update.php
$mapDestino  = [
  'perfiles' => 'perfiles',
  'cuentas'  => 'cuentas',
  'stock'    => 'perfiles_stock',
  'pausa'    => 'perfiles_pausa', // NUEVO
];


  // ============================ RAMA PDO ============================
  if ($pdo instanceof PDO) {
    $pdo->beginTransaction();
    try {
      // 1) Leer y bloquear origen
      $sel = $pdo->prepare("SELECT * FROM `{$tablaOrigen}` WHERE `id` = :id FOR UPDATE");
      $sel->execute([':id'=>$id]);
      $row = $sel->fetch();
      if (!$row) {
        $pdo->rollBack();
        throw new RuntimeException('Registro no existe');
      }

      // Forzar plan elegido en la copia de trabajo
      $row['plan'] = $plan;

      // UPDATE en origen (sin movimiento)
      if ($destino === '') {
        // plan
        $upd = $pdo->prepare("UPDATE `{$tablaOrigen}` SET `plan` = :plan WHERE `id` = :id");
        $upd->execute([':plan' => $plan, ':id' => $id]);

        // color (si se envió)
        if ($color !== '') {
          if ($color === 'restablecer') {
            $u2 = $pdo->prepare("UPDATE `{$tablaOrigen}` SET `color` = NULL WHERE `id` = :id");
            $u2->execute([':id' => $id]);
          } else {
            $u2 = $pdo->prepare("UPDATE `{$tablaOrigen}` SET `color` = :c WHERE `id` = :id");
            $u2->execute([':c' => $color, ':id' => $id]);
          }
        }

        $pdo->commit();
        @ob_clean();
        echo json_encode(['ok' => true, 'updated' => true], JSON_UNESCAPED_UNICODE);
        exit;
      }

      // 2) Movimiento: tabla destino
      $tablaDestino = $mapDestino[$destino] ?? '';
      if ($tablaDestino === '') { throw new RuntimeException('Tabla destino desconocida'); }

      // 3) Helper para encontrar target por claves mínimas (normalizando collations)
      $findTargetId = function(PDO $pdoL, string $table, array $r, array $extra = []): ?string {
        $params = [':sid' => $r['streaming_id'], ':correo' => $r['correo']];
        $where  = "streaming_id = :sid AND CONVERT(correo USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(:correo USING utf8mb4) COLLATE utf8mb4_unicode_ci";

        if ($table === 'perfiles') {
          $params[':perfil'] = $r['perfil'] ?? '';
          $where .= " AND COALESCE(CONVERT(perfil USING utf8mb4) COLLATE utf8mb4_unicode_ci,'') = COALESCE(CONVERT(:perfil USING utf8mb4) COLLATE utf8mb4_unicode_ci,'')";
        } elseif ($table === 'cuentas') {
          $params[':cuenta'] = $extra['cuenta'] ?? ($r['perfil'] ?? '');
          $where .= " AND COALESCE(CONVERT(cuenta USING utf8mb4) COLLATE utf8mb4_unicode_ci,'') = COALESCE(CONVERT(:cuenta USING utf8mb4) COLLATE utf8mb4_unicode_ci,'')";
        }

        $sql = "SELECT id FROM `$table` WHERE $where LIMIT 1";
        $st  = $pdoL->prepare($sql);
        $st->execute($params);
        $id  = $st->fetchColumn();
        return $id ? (string)$id : null;
      };

      // 4) ¿Ya existe en destino?
      $targetId = $findTargetId($pdo, $tablaDestino, $row, ['cuenta' => ($row['perfil'] ?? '')]);

      // Determinar color a insertar en destino (si destino es stock/pausa)
      $colorToInsert = null;
      if ($color !== '') {
        $colorToInsert = ($color === 'restablecer') ? null : $color;
      } else {
        $colorToInsert = array_key_exists('color', $row) ? $row['color'] : null;
      }

      // 5) Insert si no existe
      $inserted = false;
      if ($targetId === null) {
        if ($tablaDestino === 'perfiles') {
          // fechas: hoy y +31 días
          $__hoy = new DateTime('today');
          $__fin = (clone $__hoy)->modify('+31 days');
          $fecha_inicio = $__hoy->format('Y-m-d');
          $fecha_fin    = $__fin->format('Y-m-d');

          $sqlIns = "INSERT INTO `perfiles`
            (`streaming_id`,`correo`,`plan`,`password_plain`,`fecha_inicio`,`fecha_fin`,`whatsapp`,`perfil`,`combo`,`soles`,`estado`,`dispositivo`,`created_at`)
            VALUES
            (:streaming_id,:correo,:plan,:password_plain,:fecha_inicio,:fecha_fin,:whatsapp,:perfil,:combo,:soles,:estado,:dispositivo,NOW())";
          $args = [
            ':streaming_id'   => $row['streaming_id'],
            ':correo'         => $row['correo'],
            ':plan'           => $plan,
            ':password_plain' => $row['password_plain'],
            ':fecha_inicio'   => $fecha_inicio,
            ':fecha_fin'      => $fecha_fin,
            ':whatsapp'       => $row['whatsapp'] ?? null,
            ':perfil'         => $row['perfil'] ?? null,
            ':combo'          => (int)($row['combo'] ?? 0),
            ':soles'          => $row['soles'],
            ':estado'         => $row['estado'],
            ':dispositivo'    => $row['dispositivo'],
          ];
          $pdo->prepare($sqlIns)->execute($args);
          $inserted = true;

        } elseif ($tablaDestino === 'cuentas') {
          // fechas: hoy y +31 días
          $__hoy = new DateTime('today');
          $__fin = (clone $__hoy)->modify('+31 days');
          $fecha_inicio = $__hoy->format('Y-m-d');
          $fecha_fin    = $__fin->format('Y-m-d');

          $sqlIns = "INSERT INTO `cuentas`
            (`streaming_id`,`correo`,`plan`,`password_plain`,`fecha_inicio`,`fecha_fin`,`whatsapp`,`cuenta`,`soles`,`estado`,`dispositivo`,`created_at`)
            VALUES
            (:streaming_id,:correo,:plan,:password_plain,:fecha_inicio,:fecha_fin,:whatsapp,:cuenta,:soles,:estado,:dispositivo,NOW())";
          $args = [
            ':streaming_id'   => $row['streaming_id'],
            ':correo'         => $row['correo'],
            ':plan'           => $plan,
            ':password_plain' => $row['password_plain'],
            ':fecha_inicio'   => $fecha_inicio,
            ':fecha_fin'      => $fecha_fin,
            ':whatsapp'       => $row['whatsapp'] ?? null,
            ':cuenta'         => $row['perfil'] ?? '', // mapeo perfil->cuenta
            ':soles'          => $row['soles'],
            ':estado'         => $row['estado'],
            ':dispositivo'    => $row['dispositivo'],
          ];
          $pdo->prepare($sqlIns)->execute($args);
          $inserted = true;

        } elseif ($tablaDestino === 'perfiles_stock') {
          $sqlIns = "INSERT INTO `perfiles_stock`
            (`streaming_id`,`plan`,`color`,`correo`,`password_plain`,`whatsapp`,`perfil`,`combo`,`soles`,`estado`,`dispositivo`,`fecha_inicio`,`fecha_fin`,`created_at`,`updated_at`)
            VALUES
            (:streaming_id,:plan,:color,:correo,:password_plain,:whatsapp,:perfil,:combo,:soles,:estado,:dispositivo,:fecha_inicio,:fecha_fin,NOW(),NOW())";
          $args = [
            ':streaming_id'   => $row['streaming_id'],
            ':plan'           => $plan,
            ':color'          => $colorToInsert,
            ':correo'         => $row['correo'],
            ':password_plain' => $row['password_plain'],
            ':whatsapp'       => $row['whatsapp'] ?? null,
            ':perfil'         => $row['perfil'] ?? null,
            ':combo'          => (int)($row['combo'] ?? 0),
            ':soles'          => $row['soles'],
            ':estado'         => $row['estado'],
            ':dispositivo'    => $row['dispositivo'],
            ':fecha_inicio'   => $row['fecha_inicio'],
            ':fecha_fin'      => $row['fecha_fin'],
          ];
          $pdo->prepare($sqlIns)->execute($args);
          $inserted = true;

        
            // public/ajax/stock_pausa_plan_update.php
} elseif ($tablaDestino === 'perfiles_pausa') {
  // fechas: hoy y +31 días
  $__hoy = new DateTime('today');
  $__fin = (clone $__hoy)->modify('+31 days');
  $fecha_inicio = $__hoy->format('Y-m-d');
  $fecha_fin    = $__fin->format('Y-m-d');

  $sqlIns = "INSERT INTO `perfiles_pausa`
    (`streaming_id`,`plan`,`color`,`correo`,`password_plain`,`whatsapp`,`perfil`,`combo`,`soles`,`estado`,`dispositivo`,`fecha_inicio`,`fecha_fin`,`created_at`,`updated_at`)
    VALUES
    (:streaming_id,:plan,:color,:correo,:password_plain,:whatsapp,:perfil,:combo,:soles,:estado,:dispositivo,:fecha_inicio,:fecha_fin,NOW(),NOW())";

  // Color: respeta 'restablecer' -> NULL o arrastra el color de origen
  $pdo->prepare($sqlIns)->execute([
    ':streaming_id'   => $row['streaming_id'],
    ':plan'           => $plan,
    ':color'          => ($color === 'restablecer' ? null : ($color !== '' ? $color : ($row['color'] ?? null))),
    ':correo'         => $row['correo'],
    ':password_plain' => $row['password_plain'],
    ':whatsapp'       => $row['whatsapp'] ?? null,
    ':perfil'         => $row['perfil'] ?? null,
    ':combo'          => (int)($row['combo'] ?? 0),
    ':soles'          => $row['soles'],
    ':estado'         => $row['estado'],
    ':dispositivo'    => $row['dispositivo'],
    ':fecha_inicio'   => $fecha_inicio,
    ':fecha_fin'      => $fecha_fin,
  ]);
  $inserted = true;

            
            
            
            
            
            
            
        } else {
          throw new RuntimeException('Tabla destino desconocida');
        }

        // Reconfirmar que ahora existe
        $targetId = $findTargetId($pdo, $tablaDestino, $row, ['cuenta' => ($row['perfil'] ?? '')]);
      }

      // 6) Solo borrar de origen si confirmamos presencia en destino
      if ($targetId === null) {
        $pdo->rollBack();
        throw new RuntimeException('No se pudo crear/confirmar el registro en destino.');
      }

      // 7) Borrar de origen y confirmar
      $pdo->prepare("DELETE FROM `{$tablaOrigen}` WHERE `id` = :id")->execute([':id'=>$id]);

      $pdo->commit();
      @ob_clean();
      echo json_encode(['ok'=>true,'moved_to'=>$destino,'inserted'=>$inserted,'target_id'=>$targetId], JSON_UNESCAPED_UNICODE);
      exit;

    } catch (Throwable $txe) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      throw $txe;
    }
  }

  // ============================ RAMA MYSQLI ============================
  if ($mysqli instanceof mysqli) {

    $mysqli->begin_transaction();
    try {
      // 1) Leer y bloquear origen
      $sel = $mysqli->prepare("SELECT * FROM `{$tablaOrigen}` WHERE `id` = ? FOR UPDATE");
      if (!$sel) { throw new RuntimeException('Error SELECT'); }
      $sel->bind_param('i', $id);
      $sel->execute();
      $res = $sel->get_result();
      $row = $res ? $res->fetch_assoc() : null;
      $sel->close();
      if (!$row) { throw new RuntimeException('Registro no existe'); }
      $row['plan'] = $plan;

      // UPDATE en origen (sin movimiento)
      if ($destino === '') {
        // plan
        $stmt = $mysqli->prepare("UPDATE `{$tablaOrigen}` SET `plan` = ? WHERE `id` = ?");
        if (!$stmt) { throw new RuntimeException('Error UPDATE'); }
        $stmt->bind_param('si', $plan, $id);
        $stmt->execute();
        $stmt->close();

        // color (si se envió)
        if ($color !== '') {
          if ($color === 'restablecer') {
            $stmt = $mysqli->prepare("UPDATE `{$tablaOrigen}` SET `color` = NULL WHERE `id` = ?");
            $stmt->bind_param('i', $id);
          } else {
            $stmt = $mysqli->prepare("UPDATE `{$tablaOrigen}` SET `color` = ? WHERE `id` = ?");
            $stmt->bind_param('si', $color, $id);
          }
          $stmt->execute();
          $stmt->close();
        }

        $mysqli->commit();
        echo json_encode(['ok'=>true,'updated'=>true,'plan'=>$plan], JSON_UNESCAPED_UNICODE);
        exit;
      }

      // 2) Movimiento: tabla destino
      $tablaDestino = $mapDestino[$destino] ?? '';
      if ($tablaDestino === '') { throw new RuntimeException('Tabla destino desconocida'); }

      // Color a insertar en destino
      $colorToInsert = null;
      if ($color !== '') {
        $colorToInsert = ($color === 'restablecer') ? null : $color;
      } else {
        $colorToInsert = array_key_exists('color', $row) ? $row['color'] : null;
      }

      // 3) ¿Existe ya?
      $mysqliFindTargetId = static function(mysqli $db, string $table, array $r, array $extra = []): ?string {
        $where  = "streaming_id = ? AND (correo COLLATE utf8mb4_unicode_ci) = (? COLLATE utf8mb4_unicode_ci)";
        $params = [$r['streaming_id'], $r['correo']];
        $types  = 'is';

        if ($table === 'perfiles') {
          $where .= " AND COALESCE(perfil,'') COLLATE utf8mb4_unicode_ci = COALESCE(?,'') COLLATE utf8mb4_unicode_ci";
          $params[] = $r['perfil'] ?? '';
          $types   .= 's';
        } elseif ($table === 'cuentas') {
          $where .= " AND COALESCE(cuenta,'') COLLATE utf8mb4_unicode_ci = COALESCE(?,'') COLLATE utf8mb4_unicode_ci";
          $params[] = $extra['cuenta'] ?? ($r['perfil'] ?? '');
          $types   .= 's';
        }

        $sql = "SELECT id FROM `$table` WHERE $where LIMIT 1";
        $stmt = $db->prepare($sql);
        if (!$stmt) { throw new RuntimeException('Error EXISTS'); }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $id  = $res && ($row = $res->fetch_row()) ? (string)$row[0] : null;
        $stmt->close();
        return $id;
      };

      $targetId = $mysqliFindTargetId($mysqli, $tablaDestino, $row, ['cuenta' => ($row['perfil'] ?? '')]);

      // 4) Insert si no existe
      if ($targetId === null) {
        if ($tablaDestino === 'perfiles') {
          $sqlIns = "INSERT INTO `perfiles`
            (`streaming_id`,`correo`,`plan`,`password_plain`,`fecha_inicio`,`fecha_fin`,`whatsapp`,`perfil`,`combo`,`soles`,`estado`,`dispositivo`,`created_at`)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?, NOW())";
          $stmt = $mysqli->prepare($sqlIns);
          if (!$stmt) { throw new RuntimeException('Error INSERT perfiles'); }
          $perfil = $row['perfil'] ?? null;
          $combo  = (int)($row['combo'] ?? 0);

          // fechas: hoy y +31 días
          $__hoy = new DateTime('today');
          $__fin = (clone $__hoy)->modify('+31 days');
          $fecha_inicio = $__hoy->format('Y-m-d');
          $fecha_fin    = $__fin->format('Y-m-d');

          $stmt->bind_param(
            'isssssssiisss',
            $row['streaming_id'], $row['correo'], $plan, $row['password_plain'],
            $fecha_inicio, $fecha_fin, $row['whatsapp'],
            $perfil, $combo, $row['soles'], $row['estado'], $row['dispositivo']
          );
          $stmt->execute();
          $stmt->close();

        } elseif ($tablaDestino === 'cuentas') {
          $sqlIns = "INSERT INTO `cuentas`
            (`streaming_id`,`correo`,`plan`,`password_plain`,`fecha_inicio`,`fecha_fin`,`whatsapp`,`cuenta`,`soles`,`estado`,`dispositivo`,`created_at`)
            VALUES (?,?,?,?,?,?,?,?,?,?,?, NOW())";
          $stmt = $mysqli->prepare($sqlIns);
          if (!$stmt) { throw new RuntimeException('Error INSERT cuentas'); }
          $cuenta = $row['perfil'] ?? '';

          // fechas: hoy y +31 días
          $__hoy = new DateTime('today');
          $__fin = (clone $__hoy)->modify('+31 days');
          $fecha_inicio = $__hoy->format('Y-m-d');
          $fecha_fin    = $__fin->format('Y-m-d');

          $stmt->bind_param(
            'isssssssiiss',
            $row['streaming_id'], $row['correo'], $plan, $row['password_plain'],
            $fecha_inicio, $fecha_fin, $row['whatsapp'],
            $cuenta, $row['soles'], $row['estado'], $row['dispositivo']
          );
          $stmt->execute();
          $stmt->close();

        } elseif ($tablaDestino === 'perfiles_stock') {
          $sqlIns = "INSERT INTO `perfiles_stock`
            (`streaming_id`,`plan`,`color`,`correo`,`password_plain`,`whatsapp`,`perfil`,`combo`,`soles`,`estado`,`dispositivo`,`fecha_inicio`,`fecha_fin`,`created_at`,`updated_at`)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?, NOW(), NOW())";
          $stmt = $mysqli->prepare($sqlIns);
          if (!$stmt) { throw new RuntimeException('Error INSERT stock'); }
          $perfil = $row['perfil'] ?? null;
          $combo  = (int)($row['combo'] ?? 0);
          $stmt->bind_param(
            'isssssssiisss',
            $row['streaming_id'], $plan, $colorToInsert, $row['correo'], $row['password_plain'],
            $row['whatsapp'], $perfil, $combo, $row['soles'], $row['estado'], $row['dispositivo'],
            $row['fecha_inicio'], $row['fecha_fin']
          );
          $stmt->execute();
          $stmt->close();

        
            
            
            
            
            // public/ajax/stock_pausa_plan_update.php
} elseif ($tablaDestino === 'perfiles_pausa') {
  $sqlIns = "INSERT INTO `perfiles_pausa`
    (`streaming_id`,`plan`,`color`,`correo`,`password_plain`,`whatsapp`,`perfil`,`combo`,`soles`,`estado`,`dispositivo`,`fecha_inicio`,`fecha_fin`,`created_at`,`updated_at`)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, NOW(), NOW())";
  $stmt = $mysqli->prepare($sqlIns);
  if (!$stmt) { throw new RuntimeException('Error INSERT pausa'); }

  // fechas: hoy y +31 días
  $__hoy = new DateTime('today');
  $__fin = (clone $__hoy)->modify('+31 days');
  $fecha_inicio = $__hoy->format('Y-m-d');
  $fecha_fin    = $__fin->format('Y-m-d');

  $perfil = $row['perfil'] ?? null;
  $combo  = (int)($row['combo'] ?? 0);
  $colorToInsert = ($color === 'restablecer' ? null : ($color !== '' ? $color : ($row['color'] ?? null)));

  // Tipos: i s s s s s s i d s s s
  $stmt->bind_param(
    'isssssssiisss',
    $row['streaming_id'], $plan, $colorToInsert, $row['correo'], $row['password_plain'],
    $row['whatsapp'], $perfil, $combo, $row['soles'], $row['estado'], $row['dispositivo'],
    $fecha_inicio, $fecha_fin
  );
  $stmt->execute();
  $stmt->close();

            
            
            
            
            
            
            
            
            
            
        } else {
          throw new RuntimeException('Tabla destino desconocida');
        }

        // re-check
        $targetId = $mysqliFindTargetId($mysqli, $tablaDestino, $row, ['cuenta' => ($row['perfil'] ?? '')]);
      }

      // 5) Confirmación dura de existencia
      if ($targetId === null) {
        $mysqli->rollback();
        throw new RuntimeException('No se pudo crear/confirmar el registro en destino.');
      }

      // 6) Borrar origen y confirmar
      $stmt = $mysqli->prepare("DELETE FROM `{$tablaOrigen}` WHERE `id` = ?");
      if (!$stmt) { throw new RuntimeException('Error DELETE'); }
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $stmt->close();

      $mysqli->commit();
      echo json_encode(['ok'=>true,'moved_to'=>$destino,'inserted'=>($targetId ? true : false),'target_id'=>$targetId], JSON_UNESCAPED_UNICODE);
      exit;

    } catch (Throwable $txe) {
      $mysqli->rollback();
      throw $txe;
    }
  }

  throw new RuntimeException('Sin conexión a base de datos');

} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}
