<?php
// /app/controllers/PerfilController.php
// Reemplaza TODO el archivo por este contenido (acepta precio manual para hijos; si hijo y precio vacío -> 0.00; no hereda del padre)
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../models/PerfilModel.php';

if (empty($_SESSION['user_id'])) {
    redirect('../../public/index.php');
}

$action       = $_POST['action']        ?? '';
$streaming_id = (int)($_POST['streaming_id'] ?? 0);
$back         = '../../public/streaming.php?id=' . $streaming_id;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$action || $streaming_id <= 0) {
    set_flash('warning','Acción inválida.');
    redirect($back);
}

$allowedPlans        = ['individual','estándar','premium'];
$allowedEstados      = ['pendiente','activo'];
$allowedDispositivos = ['tv','smartphone'];

function norm_money($in): string {
    $s = is_string($in) ? trim($in) : (string)$in;
    if ($s === '') return '0.00';
    $s = preg_replace('/[^0-9\.,-]/', '', $s) ?? '';
    if ($s === '' || $s === '.' || $s === ',' || $s === '-.' || $s === '-,') return '0.00';
    if (strpos($s, ',') !== false && strpos($s, '.') === false) {
        $s = str_replace(',', '.', $s);
    } else {
        $s = str_replace([','], [''], $s);
    }
    $f = (float)$s;
    return number_format($f, 2, '.', '');
}

try {
    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);

        $plan = $_POST['plan'] ?? 'individual';
        if (!in_array($plan, $allowedPlans, true)) $plan = 'individual';

        $estado = $_POST['estado'] ?? 'activo';
        if (!in_array($estado, $allowedEstados, true)) $estado = 'activo';

        $dispositivo = $_POST['dispositivo'] ?? 'tv';
        if (!in_array($dispositivo, $allowedDispositivos, true)) $dispositivo = 'tv';

        $correo         = trim((string)($_POST['correo'] ?? ''));
        $password_plain = trim((string)($_POST['password_plain'] ?? ''));

        // WhatsApp: +CC y número local con espacios
        $digits = static fn(string $s): string => preg_replace('/\D+/', '', $s) ?? '';
        $cc     = $digits((string)($_POST['wa_cc'] ?? ''));
        $local  = $digits((string)($_POST['wa_local'] ?? ''));
        if ($local !== '') {
            $localFmt = trim(preg_replace('/(\d{3})(?=\d)/', '$1 ', $local) ?? $local);
            $wa = ($cc !== '' ? ('+' . $cc . ' ') : '') . $localFmt;
        } else {
            $wa = '';
        }
        $_POST['whatsapp'] = $wa;

        $perfil         = trim((string)($_POST['perfil'] ?? ''));
        $combo          = (int)($_POST['combo'] ?? 0);

        $solesIn        = (string)($_POST['soles'] ?? '');
        $soles          = norm_money($solesIn);

        $fecha_inicio   = (string)($_POST['fecha_inicio'] ?? date('Y-m-d'));
        $fecha_fin_in   = (string)($_POST['fecha_fin'] ?? '');

        if ($correo === '' || $password_plain === '' || $fecha_fin_in === '') {
            set_flash('warning','Completa los campos requeridos.');
            redirect($back);
        }

        $fi = new DateTime($fecha_inicio);
        $ff = new DateTime($fecha_fin_in);
        if ($ff < $fi) {
            set_flash('warning','La fecha fin no puede ser menor a la fecha de inicio.');
            redirect($back);
        }
        $ff->modify('+1 day'); // regla original

        // Reglas de precio:
        //  - Padre (perfil == ''): usa lo ingresado (si viene vacío -> 0.00).
        //  - Hijo  (perfil != ''): NO heredar del padre; si viene vacío -> 0.00; si usuario ingresó valor -> respetar.
        if ($perfil === '' && $soles === '') {
            $soles = '0.00';
        }
        if ($perfil !== '' && $soles === '') {
            $soles = '0.00';
        }
        // $soles ya normalizado en todos los casos

        $data = [
            'streaming_id'   => $streaming_id,
            'plan'           => $plan,
            'correo'         => $correo,
            'password_plain' => $password_plain,
            'fecha_inicio'   => $fi->format('Y-m-d'),
            'fecha_fin'      => $ff->format('Y-m-d'),
            'whatsapp'       => $wa,
            'perfil'         => $perfil,
            'combo'          => $combo ? 1 : 0,
            'soles'          => $soles,
            'estado'         => $estado,
            'dispositivo'    => $dispositivo,
        ];

        if ($action === 'create') {
            PerfilModel::create($data);
            set_flash('success','Perfil creado.');
        } else {
            PerfilModel::update($id, $data);
            set_flash('success','Perfil actualizado.');
        }

        redirect($back);
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        PerfilModel::delete($id);
        set_flash('success','Perfil eliminado.');
        redirect($back);
    }

    set_flash('warning','Acción no soportada.');
    redirect($back);

} catch (Throwable $e) {
    error_log('PerfilController error: ' . $e->getMessage());
    set_flash('danger','Error: ' . $e->getMessage());
    redirect($back);
}


?>











<script>

// /public/assets/js/app.js
// === Modal Perfiles: default SOLO para padre; no readonly ni sobre-escrituras al escribir "perfil" o "precio" ===
(function () {
  function isPerfilModal(m){ return !!(m && m.querySelector('form[action*="PerfilController.php"]')); }
  function getInputs(m){
    return {
      perfil: m.querySelector('input[name="perfil"]'),
      soles:  m.querySelector('input[name="soles"]'),
      plan:   m.querySelector('[name="plan"]')
    };
  }
  function isChild(perfilEl){ return !!(perfilEl && perfilEl.value && perfilEl.value.trim() !== ''); }
  function ensureEditable(el){ if (el){ el.readOnly = false; try{ el.removeAttribute('readonly'); }catch(_){} } }
  function readToolbarDefault(){
    // Fuente estable del default: marca el input del toolbar con data-role="default-soles"
    var el = document.querySelector('[data-role="default-soles"]');
    return (el && typeof el.value === 'string') ? el.value.trim() : '';
  }

  // (A) Apertura: fijar estado inicial y aplicar default SOLO si es PADRE
  document.addEventListener('show.bs.modal', function(ev){
    var m = ev.target;
    if (!isPerfilModal(m)) return;

    var I = getInputs(m);
    if (!I.soles) return;

    // Estado limpio y editable
    ensureEditable(I.soles);
    delete I.soles.dataset.userTyped;
    delete I.soles.dataset.autoFill;

    // Persistimos el default del toolbar en data-*
    var def = I.soles.getAttribute('data-default-soles') || readToolbarDefault() || '';
    if (def) I.soles.setAttribute('data-default-soles', def);

    // Default SOLO para padre (perfil vacío) y si el campo está vacío
    if (!isChild(I.perfil) && I.soles.value === '' && def !== '') {
      I.soles.value = def;
      I.soles.dataset.autoFill = '1';
    }

    // Si ya abre como hijo: no auto-rellenar (pero respeta si el usuario ya escribió algo antes)
    if (isChild(I.perfil) && !I.soles.dataset.userTyped && I.soles.dataset.autoFill === '1') {
      I.soles.value = '';
      delete I.soles.dataset.autoFill;
    }

    // Marcar cuando el usuario escribe precio → desde aquí no tocamos su valor
    ['beforeinput','input','change','keydown','keyup','paste'].forEach(function(evt){
      I.soles.addEventListener(evt, function(){
        ensureEditable(I.soles);
        I.soles.dataset.userTyped = '1';
        delete I.soles.dataset.autoFill;
      }, true);
    });

    // (B) Escribir en PERFIL: cambiar padre↔hijo en vivo sin perder lo que el usuario haya escrito
    if (I.perfil) {
      ['input','change','keyup'].forEach(function(evt){
        I.perfil.addEventListener(evt, function(){
          ensureEditable(I.soles);

          // Si pasa a HIJO y el precio era auto (no del usuario), limpiar una sola vez
          if (isChild(I.perfil)) {
            if (I.soles.dataset.autoFill === '1' && !I.soles.dataset.userTyped) {
              I.soles.value = '';
              delete I.soles.dataset.autoFill;
            }
            return;
          }

          // Si vuelve a PADRE y no hay precio manual ni valor, aplicar default
          var d = I.soles.getAttribute('data-default-soles') || '';
          if (!I.soles.dataset.userTyped && I.soles.value === '' && d !== '') {
            I.soles.value = d;
            I.soles.dataset.autoFill = '1';
          }
        }, true);
      });
    }

    // (C) Cambio de plan: no tocar precio si es hijo o si el usuario ya escribió
    if (I.plan) {
      I.plan.addEventListener('change', function(){
        ensureEditable(I.soles);
        if (isChild(I.perfil) || I.soles.dataset.userTyped) return;
        // Padre + sin precio manual: permitir que otro script ponga default si lo hace;
        // si no, mantenemos el actual (no forzamos nada aquí).
      }, true);
    }

    // (D) Si existe una función global que autocompleta precio por plan, la envolvemos de forma no invasiva
    if (typeof window.setSolesByPlan === 'function') {
      var __orig = window.setSolesByPlan;
      window.setSolesByPlan = function(){
        // Si hay un modal de perfiles abierto y está en modo HIJO o el usuario ya escribió, no forzar cambios
        if (document.body.contains(m) && isPerfilModal(m)) {
          var J = getInputs(m);
          if (isChild(J.perfil) || (J.soles && J.soles.dataset.userTyped)) {
            ensureEditable(J.soles);
            return; // no hacer nada
          }
        }
        return __orig.apply(this, arguments);
      };
    }
  }, true);

  // (E) Tras mostrar: última pasada por si otro script impuso readonly o reinyectó el default del padre
  document.addEventListener('shown.bs.modal', function(ev){
    var m = ev.target;
    if (!isPerfilModal(m)) return;
    var I = getInputs(m);
    ensureEditable(I.soles);

    if (isChild(I.perfil) && I.soles.dataset && I.soles.dataset.autoFill === '1' && !I.soles.dataset.userTyped) {
      I.soles.value = '';
      delete I.soles.dataset.autoFill;
    }
  }, true);
})();
</script>
