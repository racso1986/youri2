<?php /* Modales CRUD */ ?>
<!-- Modal Streaming -->
<!--<div class="modal fade" id="streamingModal" tabindex="-1" aria-hidden="true">-->
<!--  <div class="modal-dialog">-->
<!--    <div class="modal-content">-->
<!--      <div class="modal-header">-->
<!--        <h5 class="modal-title" id="streamingModalLabel">Agregar Streaming</h5>-->
<!--        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>-->
<!--      </div>-->
<!--      <form method="post" action="../app/controllers/StreamingController.php" enctype="multipart/form-data">-->
<!--        <div class="modal-body">-->
<!--          <input type="hidden" name="action" value="create">-->
<!--          <input type="hidden" name="id" value="">-->
<!--          <div class="mb-3">-->
<!--            <label class="form-label">Nombre</label>-->
<!--            <input type="text" name="nombre" class="form-control" required>-->
<!--          </div>-->
<!--          <div class="mb-3">-->
<!--            <label class="form-label">Plan</label>-->
<!--            <input type="text" name="plan" class="form-control" required>-->
<!--          </div>-->
<!--          <div class="mb-3">-->
<!--            <label class="form-label">Precio (S/)</label>-->
<!--            <input type="number" step="0.01" name="precio" class="form-control" required>-->
<!--          </div>-->
<!--          <div class="mb-3">-->
<!--            <label class="form-label">Logo (jpg/png/gif, máx 2MB)</label>-->
<!--            <input type="file" name="logo" class="form-control" accept=".jpg,.jpeg,.png,.gif">-->
<!--          </div>-->
<!--        </div>-->
<!--        <div class="modal-footer">-->
<!--          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>-->
<!--          <button type="submit" class="btn btn-primary">Guardar</button>-->
<!--        </div>-->
<!--      </form>-->
<!--    </div>-->
<!--  </div>-->
<!--</div>-->

<!-- Modal Perfil -->
<div class="modal fade" id="perfilModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="perfilModalLabel">Agregar Perfil</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="actions/perfil_create.php" autocomplete="off">
        <div class="modal-body">
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="id" value="">
          <input type="hidden" name="streaming_id" value="<?php echo intval($_GET['id'] ?? 0); ?>">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Correo</label>
              <input type="email" name="correo" class="form-control" required>
            </div>

            <!-- oculto: plan predeterminado -->
            <input type="hidden" name="plan" value="premium">

            <div class="col-md-4">
              <label class="form-label">Contraseña</label>
              <input type="text" name="password_plain" class="form-control" required autocomplete="off">
            </div>

            <div class="col-md-4">
              <label class="form-label">WhatsApp</label>
              <!-- 2 inputs reales en un input-group -->
              <div class="input-group">
                <span class="input-group-text">+</span>
                <!-- Prefijo: solo dígitos 1–3 -->
                <input type="text"
                       class="form-control"
                       name="wa_cc"
                       inputmode="numeric"
                       pattern="[0-9]{1,3}"
                       maxlength="3"
                       placeholder="51"
                       style="max-width: 90px;">
                <!-- Número: dígitos y espacios 6–20 -->
                <input type="text"
                       class="form-control"
                       name="wa_local"
                       inputmode="numeric"
                       pattern="[0-9 ]{6,20}"
                       maxlength="20"
                       placeholder="977 948 954">
              </div>
              <div class="form-text">Se guardará como “+CC 999 999 999”.</div>
            </div>

           
            <div class="col-md-4">
              <label class="form-label">inicio</label>
              <input type="date" name="fecha_inicio" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="col-md-3">
              <label class="form-label">fin</label>
              <input type="date" name="fecha_fin" id="fecha_fin" class="form-control"
                     value="<?= date('Y-m-d', strtotime('+31 days')) ?>" required>
            </div>

            <div class="col-md-3">
              <label class="form-label">Perfil</label>
              <input type="text" name="perfil" class="form-control">
            </div>

            <div class="col-md-3">
              <label class="form-label">Combo</label>
              <select name="combo" class="form-select">
                <option value="0">No</option>
                <option value="1">Sí</option>
              </select>
            </div>
   <div class="mb-2 col-md-3" id="childPriceGroup">
  <label class="form-label">Precio (S/)</label>
  <div id="childPriceSlot" data-price-slot></div>
</div>










            <!--<div class="col-md-3">-->
            <!--  <label class="form-label">Soles (S/)</label>-->
            <!--  <input type="number" step="0.01" name="soles" class="form-control" value="0.00">-->
            <!--</div>-->

            <div class="col-md-4">
              <label class="form-label">Estado</label>
              <select name="estado" class="form-select form-select-sm" required>
                <option value="activo" selected>activo</option>
                <option value="pendiente">pendiente</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Dispositivo</label>
              <select name="dispositivo" class="form-select">
                <option value="tv">tv</option>
                <option value="smartphone">smartphone</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  const modal = document.getElementById('perfilModal');
  if (!modal) return;

  let lastTrigger = null; // recordamos quién abrió el modal

  function ensureFreshSlot(form){
    const group = form.querySelector('#childPriceGroup');
    if (!group) return null;
    const labelHTML = group.querySelector('label')?.outerHTML || '<label class="form-label">Precio (S/)</label>';
    group.innerHTML = labelHTML + '<div id="childPriceSlot" data-price-slot></div>';
    return group.querySelector('#childPriceSlot');
  }

  function purgeForeignSoles(form, keep){
    form.querySelectorAll('input[name="soles"]').forEach(el => { if (!keep || !keep.contains(el)) el.remove(); });
    form.querySelectorAll('#modalChildPrecio, #modalChildPrecio_display, [data-price-mount]').forEach(el => {
      if (!keep || !keep.contains(el)) el.remove();
    });
  }

  function mountEditable(slot){
    slot.innerHTML = '';
    const inp = document.createElement('input');
    inp.type = 'number';
    inp.step = '0.01';
    inp.min  = '0';
    inp.name = 'soles';
    inp.id   = 'modalChildPrecio';
    inp.className = 'form-control';
    inp.placeholder = 'Ingrese precio';
    inp.autocomplete = 'off';
    slot.appendChild(inp);
    return inp;
  }

  // 1) Montar campo SOLO una vez (antes de animación)
  modal.addEventListener('show.bs.modal', function (ev) {
    lastTrigger = ev.relatedTarget || null;

    const form = modal.querySelector('form');
    if (!form) return;

    const slot = ensureFreshSlot(form);
    purgeForeignSoles(form, slot);
    const inp = mountEditable(slot);

    // enfoque básico
    setTimeout(() => { try { inp.focus(); } catch(_) {} }, 0);
  });

  // 2) Ya visible: si viene del botón “Agregar perfil” (parent), pre-cargar valor
  modal.addEventListener('shown.bs.modal', function () {
    const ctx = (lastTrigger?.getAttribute('data-modal-context') || '').trim(); // 'parent' | 'child'
    if (ctx !== 'parent') return;

    const head = document.getElementById('precioPerfilHead');
    const val  = head ? head.value.trim() : '';
    if (!val) return;

    const inp = modal.querySelector('#modalChildPrecio');
    if (inp) {
      inp.value = val;
      // notifica a otros listeners, por si validan/formatean
      inp.dispatchEvent(new Event('input', { bubbles: true }));
      inp.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });

  // 3) Limpieza al cerrar
  modal.addEventListener('hidden.bs.modal', function () {
    const form = modal.querySelector('form');
    if (!form) return;
    purgeForeignSoles(form, null);
    const slot = form.querySelector('#childPriceSlot');
    if (slot) slot.innerHTML = '';
    lastTrigger = null;
  });
})();
</script>




<!-- Modal Cuenta -->
<div class="modal fade" id="cuentaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cuentaModalLabel">Agregar Cuenta</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="../app/controllers/CuentaController.php" autocomplete="off">
        <div class="modal-body">
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="id" value="">
          <input type="hidden" name="streaming_id" value="<?= (int)($_GET['id'] ?? 0) ?>">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Correo</label>
              <input type="email" name="correo" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Contraseña</label>
              <input type="text" name="password_plain" class="form-control" required autocomplete="off">
            </div>

            <div class="col-md-4">
              <label class="form-label">WhatsApp</label>
              <div class="input-group">
                <span class="input-group-text">+</span>
                <!-- Prefijo -->
                <input type="text"
                       class="form-control"
                       name="wa_cc"
                       inputmode="numeric"
                       pattern="[0-9]{1,3}"
                       maxlength="3"
                       placeholder="51"
                       style="max-width: 90px;">
                <!-- Número -->
                <input type="text"
                       class="form-control"
                       name="wa_local"
                       inputmode="numeric"
                       pattern="[0-9 ]{6,20}"
                       maxlength="20"
                       placeholder="977 948 954">
              </div>
              <div class="form-text">Formato: +CC 999 999 999.</div>
            </div>

            <?php
            // Fechas por defecto SOLO UI (America/Lima)
            $__tz  = new DateTimeZone('America/Lima');
            $__hoy = new DateTime('now', $__tz);
            $__fin = (clone $__hoy)->modify('+31 days');
            ?>

            <div class="col-md-3">
              <label class="form-label">Fecha inicio</label>
              <input type="date"
                     name="fecha_inicio"
                     id="fecha_inicio_cuenta"
                     class="form-control"
                     value="<?= htmlspecialchars($__hoy->format('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Fecha fin</label>
              <input type="date"
                     name="fecha_fin"
                     id="fecha_fin_cuenta"
                     class="form-control"
                     value="<?= htmlspecialchars($__fin->format('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Cuenta</label>
              <input type="text" name="cuenta" class="form-control">
            </div>
            <!--<div class="col-md-3">-->
            <!--  <label class="form-label">Soles (S/)</label>-->
            <!--  <input type="number" step="0.01" name="soles" class="form-control" value="0.00">-->
            <!--</div>-->
            <!-- /includes/modals.php -->
<div class="mb-2 col-md-3">
  <label class="form-label mb-1">Precio (S/)</label>
  <input
    type="number"
    step="0.01"
    min="0"
    class="form-control"
    name="soles"
    id="modalPerfilPrecio"
    value=""
    data-default-soles=""
    autocomplete="off">
</div>


            <div class="col-md-4">
              <label class="form-label">Estado</label>
              <select name="estado" class="form-select">
                <option value="pendiente">pendiente</option>
                <option value="activo">activo</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Dispositivo</label>
              <select name="dispositivo" class="form-select">
                <option value="tv">tv</option>
                <option value="smartphone">smartphone</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Modal Streaming -->
<div class="modal fade" id="streamingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Agregar Streaming</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="post" action="../app/controllers/StreamingController.php" enctype="multipart/form-data" autocomplete="off">
        <div class="modal-body">
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="id" value="">
          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control" required>
          </div>
          <div class="mb-3">
            <label for="streaming_plan_add" class="form-label mb-1">Plan</label>
            <select id="streaming_plan_add" name="plan" class="form-select form-select-sm" required autocomplete="off">
              <option value="individual">individual</option>
              <option value="standard">standard</option>
              <option value="premium" selected>premium</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Precio (S/)</label>
            <input type="number" step="0.01" name="precio" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Logo (JPG/PNG/GIF, máx 2MB)</label>
            <input type="file" name="logo" class="form-control">
          </div>
          <img id="logoPreview" class="img-fluid rounded d-none mt-2" alt="Vista previa">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>




<!-- /includes/modals.php — AGREGA ESTOS DOS MODALS (manteniendo el estilo del Modal Perfil) -->

<!-- Modal Stock -->
<div class="modal fade" id="stockModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Agregar Stock</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="../app/controllers/StockController.php" autocomplete="off">
        <div class="modal-body">
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="id" value="">
          <input type="hidden" name="streaming_id" value="<?= (int)($_GET['id'] ?? 0) ?>">
          <input type="hidden" name="fecha_inicio" value="<?= date('Y-m-d') ?>">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Plan</label>
              <select name="plan" class="form-select">
                <option value="individual">individual</option>
                <option value="estándar">estándar</option>
                <option value="premium">premium</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Correo</label>
              <input type="email" name="correo" class="form-control" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Contraseña</label>
              <input type="text" name="password_plain" class="form-control" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Fecha de hoy</label>
              <input type="text" class="form-control" value="<?= date('d/m/y') ?>" readonly>
            </div>

            <div class="col-md-4">
              <label class="form-label">Fecha fin</label>
              <input type="date" name="fecha_fin" class="form-control" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">WhatsApp</label>
              <input type="text" name="whatsapp" class="form-control" placeholder="Ej: 51987654321">
            </div>

            <div class="col-md-4">
              <label class="form-label">Perfil</label>
              <input type="text" name="perfil" class="form-control">
            </div>

            <div class="col-md-4">
              <label class="form-label">Combo</label>
              <select name="combo" class="form-select">
                <option value="0">No</option>
                <option value="1">Sí</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Soles (S/)</label>
              <input type="number" step="0.01" name="soles" class="form-control" value="0.00">
            </div>

            <div class="col-md-4">
              <label class="form-label">Estado</label>
              <select name="estado" class="form-select">
                <option value="pendiente">pendiente</option>
                <option value="activo">activo</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Dispositivo</label>
              <select name="dispositivo" class="form-select">
                <option value="tv">tv</option>
                <option value="smartphone">smartphone</option>
              </select>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Pausa -->
<div class="modal fade" id="pausaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Agregar Cuenta en pausa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="../app/controllers/PausaController.php" autocomplete="off">
        <div class="modal-body">
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="id" value="">
          <input type="hidden" name="streaming_id" value="<?= (int)($_GET['id'] ?? 0) ?>">
          <input type="hidden" name="fecha_inicio" value="<?= date('Y-m-d') ?>">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Plan</label>
              <select name="plan" class="form-select">
                <option value="individual">individual</option>
                <option value="estándar">estándar</option>
                <option value="premium">premium</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Correo</label>
              <input type="email" name="correo" class="form-control" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Contraseña</label>
              <input type="text" name="password_plain" class="form-control" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Fecha de hoy</label>
              <input type="text" class="form-control" value="<?= date('d/m/y') ?>" readonly>
            </div>

            <div class="col-md-4">
              <label class="form-label">Fecha fin</label>
              <input type="date" name="fecha_fin" class="form-control" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">WhatsApp</label>
              <input type="text" name="whatsapp" class="form-control" placeholder="Ej: 51987654321">
            </div>

            <div class="col-md-4">
              <label class="form-label">Perfil</label>
              <input type="text" name="perfil" class="form-control">
            </div>

            <div class="col-md-4">
              <label class="form-label">Combo</label>
              <select name="combo" class="form-select">
                <option value="0">No</option>
                <option value="1">Sí</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Soles (S/)</label>
              <input type="number" step="0.01" name="soles" class="form-control" value="0.00">
            </div>

            <div class="col-md-4">
              <label class="form-label">Estado</label>
              <select name="estado" class="form-select">
                <option value="pendiente">pendiente</option>
                <option value="activo">activo</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Dispositivo</label>
              <select name="dispositivo" class="form-select">
                <option value="tv">tv</option>
                <option value="smartphone">smartphone</option>
              </select>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
// Asegurar $__sid disponible en modales (si no viene de streaming.php)
if (!isset($__sid)) {
  $__sid = 0;
  if (isset($streaming['id'])) {
    $__sid = (int)$streaming['id'];
  } elseif (isset($_GET['streaming_id'])) {
    $__sid = (int)$_GET['streaming_id'];
  } elseif (isset($_GET['streaming'])) {
    $__sid = (int)$_GET['streaming'];
  }
}
?>

<?php
// Defaults SOLO UI (America/Lima) para Stock
$__tz  = new DateTimeZone('America/Lima');
$__hoy = new DateTime('now', $__tz);
$__fin = (clone $__hoy)->modify('+31 days');

// Plan por defecto (si existe $streaming['plan'], úsalo; si no, 'premium')
// Normaliza a enum esperado en stock/pausa (usa 'estándar' con acento):
$__plan_default = isset($streaming['plan']) ? (string)$streaming['plan'] : 'premium';
$__plan_default = strtolower($__plan_default) === 'standard' ? 'estándar' : $__plan_default;
?>

<!-- Modal Agregar Stock -->
<div class="modal fade" id="modalAgregarStock" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title">Agregar (Stock)</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="post" action="../app/controllers/StockController.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="streaming_id" value="<?= $__sid ?>">
          <input type="hidden" name="streaming" value="<?= $__sid ?>">
          <input type="hidden" name="id_streaming" value="<?= $__sid ?>">

          <!-- Campos requeridos por el controlador (ocultos) -->
          <input type="hidden" name="plan" value="<?= htmlspecialchars($__plan_default, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="estado" value="activo">
          <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($__hoy->format('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($__fin->format('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="soles" value="0.00">
          <input type="hidden" name="dispositivo" value="tv">
          <input type="hidden" name="perfil" value="">
          <input type="hidden" name="whatsapp" value="">
          <input type="hidden" name="combo" value="0">

          <div class="mb-2">
            <label for="agregar_stock_correo" class="form-label mb-1">Correo</label>
            <input type="email" class="form-control" id="agregar_stock_correo" name="correo" required>
          </div>
          <div class="mb-2">
            <label for="agregar_stock_password" class="form-label mb-1">Contraseña</label>
            <input type="text" class="form-control" id="agregar_stock_password" name="password_plain" required>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Editar Stock -->
<div class="modal fade" id="modalEditarStock" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title">Editar (Stock)</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="post" action="../app/controllers/StockController.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" id="editar_stock_id">

          <input type="hidden" name="streaming_id" value="<?= $__sid ?>">
          <input type="hidden" name="streaming" value="<?= $__sid ?>">
          <input type="hidden" name="id_streaming" value="<?= $__sid ?>">

          <!-- Mantener/forzar requeridos también en editar (si el form no los pinta) -->
          <input type="hidden" name="plan" value="<?= htmlspecialchars($__plan_default, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="estado" value="activo">
          <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($__hoy->format('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($__fin->format('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="soles" value="0.00">
          <input type="hidden" name="dispositivo" value="tv">
          <input type="hidden" name="perfil" value="">
          <input type="hidden" name="whatsapp" value="">
          <input type="hidden" name="combo" value="0">

          <div class="mb-2">
            <label for="editar_stock_correo" class="form-label mb-1">Correo</label>
            <input type="email" class="form-control" id="editar_stock_correo" name="correo" required>
          </div>
          <div class="mb-2">
            <label for="editar_stock_password" class="form-label mb-1">Contraseña</label>
            <input type="text" class="form-control" id="editar_stock_password" name="password_plain" required>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
// defaults UI (America/Lima) y plan por defecto (reuso de stock)
if (!isset($__tz))  $__tz  = new DateTimeZone('America/Lima');
if (!isset($__hoy)) $__hoy = new DateTime('now', $__tz);
$__fin = (clone $__hoy)->modify('+31 days');
if (!isset($__plan_default)) {
  $__plan_default = isset($streaming['plan']) ? (string)$streaming['plan'] : 'premium';
  $__plan_default = strtolower($__plan_default) === 'standard' ? 'estándar' : $__plan_default;
}
if (!isset($__sid)) {
  $__sid = 0;
  if (isset($streaming['id'])) $__sid = (int)$streaming['id'];
  elseif (isset($_GET['streaming_id'])) $__sid = (int)$_GET['streaming_id'];
  elseif (isset($_GET['streaming'])) $__sid = (int)$_GET['streaming'];
}
?>

<!-- Modal Agregar Pausa -->
<div class="modal fade" id="modalAgregarPausa" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title">Agregar (Cuenta en pausa)</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="post" action="../app/controllers/PausaController.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="streaming_id" value="<?= $__sid ?>">
          <input type="hidden" name="streaming" value="<?= $__sid ?>">
          <input type="hidden" name="id_streaming" value="<?= $__sid ?>">

          <!-- requeridos ocultos (igual que stock) -->
          <input type="hidden" name="plan" value="<?= htmlspecialchars($__plan_default, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="estado" value="activo">
          <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($__hoy->format('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($__fin->format('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="soles" value="0.00">
          <input type="hidden" name="dispositivo" value="tv">
          <input type="hidden" name="perfil" value="">
          <input type="hidden" name="whatsapp" value="">
          <input type="hidden" name="combo" value="0">

          <div class="mb-2">
            <label for="agregar_pausa_correo" class="form-label mb-1">Correo</label>
            <input type="email" class="form-control" id="agregar_pausa_correo" name="correo" required>
          </div>
          <div class="mb-2">
            <label for="agregar_pausa_password" class="form-label mb-1">Contraseña</label>
            <input type="text" class="form-control" id="agregar_pausa_password" name="password_plain" required>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Editar Pausa -->
<div class="modal fade" id="modalEditarPausa" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title">Editar (Cuenta en pausa)</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="post" action="../app/controllers/PausaController.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" id="editar_pausa_id">

          <input type="hidden" name="streaming_id" value="<?= $__sid ?>">
          <input type="hidden" name="streaming" value="<?= $__sid ?>">
          <input type="hidden" name="id_streaming" value="<?= $__sid ?>">

          <!-- requeridos ocultos -->
          <input type="hidden" name="plan" value="<?= htmlspecialchars($__plan_default, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="estado" value="activo">
          <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($__hoy->format('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($__fin->format('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="soles" value="0.00">
          <input type="hidden" name="dispositivo" value="tv">
          <input type="hidden" name="perfil" value="">
          <input type="hidden" name="whatsapp" value="">
          <input type="hidden" name="combo" value="0">

          <div class="mb-2">
            <label for="editar_pausa_correo" class="form-label mb-1">Correo</label>
            <input type="email" class="form-control" id="editar_pausa_correo" name="correo" required>
          </div>
          <div class="mb-2">
            <label for="editar_pausa_password" class="form-label mb-1">Contraseña</label>
            <input type="text" class="form-control" id="editar_pausa_password" name="password_plain" required>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal chico: Cambiar plan (Stock / Pausa) -->
<div class="modal fade" id="modalCambiarPlanStockPausa" tabindex="-1" aria-labelledby="labelCambiarPlanStockPausa" aria-hidden="true" data-no-row-modal="1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form id="formPlanStockPausa" autocomplete="off">
        <div class="modal-header py-2">
          <h6 class="modal-title" id="labelCambiarPlanStockPausa">Cambiar plan</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>

        <div class="modal-body py-3">
          <input type="hidden" name="id" id="spp_id">
          <input type="hidden" name="tipo" id="spp_tipo"> <!-- 'stock' | 'pausa' -->

          <div class="mb-2">
            <label for="spp_plan" class="form-label mb-1">Plan</label>
            <select class="form-select form-select-sm" name="plan" id="spp_plan" required>
              <option value="individual">individual</option>
              <option value="estándar">estándar</option>
              <option value="premium">premium</option>
            </select>
          </div>

          <div class="mb-0">
            <label for="spp_destino" class="form-label mb-1">Enviar a (opcional)</label>
            <!-- El JS repoblará estas opciones según 'tipo' -->
            <select class="form-select form-select-sm" name="destino" id="spp_destino">
              <!-- opciones dinámicas: '', perfiles, cuentas, stock -->
            </select>
            <div class="form-text">Déjalo en blanco para solo cambiar el plan.</div>
          </div>

          <div class="mb-0 mt-2">
            <label for="spp_color" class="form-label mb-1">Color (opcional)</label>
            <select class="form-select form-select-sm" name="color" id="spp_color">
              <option value="">— Sin cambios —</option>
              <option value="rojo">Rojo</option>
              <option value="azul">Azul</option>
              <option value="verde">Verde</option>
              <option value="blanco">Blanco</option>
              <option value="restablecer">Restablecer</option>
            </select>
            <div class="form-text">Pinta la fila con el color elegido; “Restablecer” quita cualquier color.</div>
          </div>
        </div>

        <div class="modal-footer py-2">
          <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
// Asegurar $__sid disponible en modales (si no viene de streaming.php)
if (!isset($__sid)) {
  $__sid = 0;
  if (isset($streaming['id'])) {
    $__sid = (int)$streaming['id'];
  } elseif (isset($_GET['streaming_id'])) {
    $__sid = (int)$_GET['streaming_id'];
  } elseif (isset($_GET['streaming'])) {
    $__sid = (int)$_GET['streaming'];
  }
}
?>

<?php
// Defaults SOLO UI (America/Lima) para Stock/Pausa y plan por defecto
$__tz  = new DateTimeZone('America/Lima');
$__hoy = new DateTime('now', $__tz);
$__fin = (clone $__hoy)->modify('+31 days');
$__plan_default = isset($streaming['plan']) ? (string)$streaming['plan'] : 'premium';
$__plan_default = strtolower($__plan_default) === 'standard' ? 'estándar' : $__plan_default;
?>

<!-- Modal Cambiar Plan (Cuenta completa) -->
<div class="modal fade" id="modalCambiarPlanCuenta" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title">Cambiar plan</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form id="formCambiarPlanCuenta" action="ajax/cuenta_plan_update.php" method="post">
        <div class="modal-body">
          <input type="hidden" name="id" id="cuentaPlanId">
          <div class="mb-2">
            <label for="cuentaPlanSelect" class="form-label mb-1">Plan</label>
            <select id="cuentaPlanSelect" name="plan" class="form-select form-select-sm" required>
              <option value="individual">individual</option>
              <option value="standard">standard</option>
              <option value="premium">premium</option>
            </select>
          </div>

          <div class="mb-0">
            <label for="cuentaEnviarASelect" class="form-label mb-1">Enviar a</label>
            <select id="cuentaEnviarASelect" name="enviar_a" class="form-select form-select-sm">
              <option value="none">(sin acción)</option>
              <option value="stock">Stock</option>
              <option value="pausa">Cuenta en pausa</option>
            </select>
          </div>

          <div class="mb-0 mt-2">
            <label for="spp_color" class="form-label mb-1">Color (opcional)</label>
            <select class="form-select form-select-sm" name="color" id="spp_color">
              <option value="">— Sin cambios —</option>
              <option value="rojo">Rojo</option>
              <option value="azul">Azul</option>
              <option value="verde">Verde</option>
              <option value="blanco">Blanco</option>
              <option value="restablecer">Restablecer</option>
            </select>
            <div class="form-text">Pinta la fila con el color elegido; “Restablecer” quita cualquier color.</div>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarPlanCuenta">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>











<!-- Modal: Agregar IPTV -->
<!--<div class="modal fade" id="modalAgregarIptv" tabindex="-1" aria-hidden="true">-->
<!--  <div class="modal-dialog modal-lg">-->
<!--    <div class="modal-content">-->
<!--      <div class="modal-header">-->
<!--        <h5 class="modal-title">Agregar IPTV</h5>-->
<!--        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>-->
<!--      </div>-->
<!--      <form id="formAgregarIptv" method="post" action="../app/controllers/IptvController.php" autocomplete="off">-->
<!--  <input type="hidden" name="action" value="create">-->
<!--  <div class="row g-3">-->
<!--    <div class="col-md-4">-->
<!--      <label class="form-label">Nombre</label>-->
<!--      <input type="text" name="nombre" class="form-control" required>-->
<!--    </div>-->
<!--    <div class="col-md-4">-->
<!--      <label class="form-label">Usuario</label>-->
<!--      <input type="text" name="usuario" class="form-control" required>-->
<!--    </div>-->
<!--    <div class="col-md-4">-->
<!--      <label class="form-label">Contraseña</label>-->
<!--      <input type="text" name="password_plain" class="form-control" required>-->
<!--    </div>-->
<!--    <div class="col-md-6">-->
<!--      <label class="form-label">URL</label>-->
<!--      <input type="url" name="url" class="form-control" required placeholder="http://...">-->
<!--    </div>-->
<!--    <div class="col-md-6">-->
<!--      <label class="form-label">WhatsApp</label>-->
<!--      <div class="input-group">-->
<!--        <span class="input-group-text">+</span>-->
<!--        <input type="text" class="form-control" name="wa_cc" inputmode="numeric" pattern="\d*" maxlength="3" placeholder="51" style="max-width: 90px;">-->
<!--        <input type="text" class="form-control" name="wa_local" inputmode="numeric" pattern="[0-9 ]{6,20}" maxlength="20" placeholder="977 948 954">-->
<!--      </div>-->
<!--    </div>-->
<!--    <div class="col-md-3">-->
<!--      <label class="form-label">Fecha inicio</label>-->
<!--      <input type="date" name="fecha_inicio" class="form-control" value="<?= date('Y-m-d') ?>" required>-->
<!--    </div>-->
<!--    <div class="col-md-3">-->
<!--      <label class="form-label">Fecha fin</label>-->
<!--      <input type="date" name="fecha_fin" class="form-control" value="<?= date('Y-m-d', strtotime('+31 days')) ?>" required>-->
<!--    </div>-->
<!--    <div class="col-md-3">-->
<!--      <label class="form-label">Perfil</label>-->
<!--      <input type="text" name="perfil" class="form-control">-->
<!--    </div>-->
<!--    <div class="col-md-3">-->
<!--      <label class="form-label">Soles (S/)</label>-->
<!--      <input type="number" step="0.01" name="soles" class="form-control" value="0.00">-->
<!--    </div>-->
<!--    <div class="col-md-3">-->
<!--      <label class="form-label">Estado</label>-->
<!--      <select name="estado" class="form-select">-->
<!--        <option value="activo" selected>activo</option>-->
<!--        <option value="pendiente">pendiente</option>-->
<!--      </select>-->
<!--    </div>-->
<!--    <div class="col-md-3">-->
<!--      <label class="form-label">Dispositivo</label>-->
<!--      <select name="dispositivo" class="form-select">-->
<!--        <option value="tv" selected>tv</option>-->
<!--        <option value="smartphone">smartphone</option>-->
<!--      </select>-->
<!--    </div>-->
<!--    <div class="col-md-3">-->
<!--      <label class="form-label">Combo</label>-->
<!--      <select name="combo" class="form-select">-->
<!--        <option value="0" selected>No</option>-->
<!--        <option value="1">Sí</option>-->
<!--      </select>-->
<!--    </div>-->
<!--  </div>-->
<!--  <div class="modal-footer">-->
<!--    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>-->
<!--    <button type="submit" class="btn btn-primary">Guardar</button>-->
<!--  </div>-->
<!--</form>-->


<!--    </div>-->
<!--  </div>-->
<!--</div>-->

<!-- Modal: Editar IPTV -->
<!--<div class="modal fade" id="modalEditarIptv" tabindex="-1" aria-hidden="true">-->
<!--  <div class="modal-dialog modal-lg">-->
<!--    <div class="modal-content">-->
<!--      <div class="modal-header">-->
<!--        <h5 class="modal-title">Editar IPTV</h5>-->
<!--        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>-->
<!--      </div>-->
<!--      <form id="formEditarIptv" method="post" action="../app/controllers/IptvController.php" autocomplete="off">-->
<!--        <div class="modal-body">-->
<!--          <input type="hidden" name="action" value="update">-->
<!--          <input type="hidden" name="id" id="iptv_edit_id">-->
<!--          <div class="row g-3">-->

<!--            <div class="col-md-4">-->
<!--              <label class="form-label">Nombre</label>-->
<!--              <input type="text" name="nombre" id="iptv_edit_nombre" class="form-control" required>-->
<!--            </div>-->

<!--            <div class="col-md-4">-->
<!--              <label class="form-label">Usuario</label>-->
<!--              <input type="text" name="usuario" id="iptv_edit_usuario" class="form-control" required>-->
<!--            </div>-->

<!--            <div class="col-md-4">-->
<!--              <label class="form-label">Contraseña</label>-->
<!--              <input type="text" name="password_plain" id="iptv_edit_password" class="form-control" required>-->
<!--            </div>-->

<!--            <div class="col-md-6">-->
<!--              <label class="form-label">URL</label>-->
<!--              <input type="url" name="url" id="iptv_edit_url" class="form-control" required>-->
<!--            </div>-->

<!--            <div class="col-md-6">-->
<!--              <label class="form-label">WhatsApp</label>-->
<!--              <div class="input-group">-->
<!--                <span class="input-group-text">+</span>-->
<!--                <input type="text" class="form-control" id="iptv_edit_wa_cc" name="wa_cc" inputmode="numeric" pattern="\d*" maxlength="3" placeholder="51" style="max-width: 90px;">-->
<!--                <input type="text" class="form-control" id="iptv_edit_wa_local" name="wa_local" inputmode="numeric" pattern="[0-9 ]{6,20}" maxlength="20" placeholder="977 948 954">-->
<!--              </div>-->
<!--            </div>-->

<!--            <div class="col-md-3">-->
<!--              <label class="form-label">Fecha inicio</label>-->
<!--              <input type="date" name="fecha_inicio" id="iptv_edit_fi" class="form-control" required>-->
<!--            </div>-->

<!--            <div class="col-md-3">-->
<!--              <label class="form-label">Fecha fin</label>-->
<!--              <input type="date" name="fecha_fin" id="iptv_edit_ff" class="form-control" required>-->
<!--            </div>-->

<!--            <div class="col-md-3">-->
<!--              <label class="form-label">Perfil</label>-->
<!--              <input type="text" name="perfil" id="iptv_edit_perfil" class="form-control">-->
<!--            </div>-->

<!--            <div class="col-md-3">-->
<!--              <label class="form-label">Soles (S/)</label>-->
<!--              <input type="number" step="0.01" name="soles" id="iptv_edit_soles" class="form-control" value="0.00">-->
<!--            </div>-->

<!--            <div class="col-md-3">-->
<!--              <label class="form-label">Estado</label>-->
<!--              <select name="estado" id="iptv_edit_estado" class="form-select">-->
<!--                <option value="activo">activo</option>-->
<!--                <option value="pendiente">pendiente</option>-->
<!--              </select>-->
<!--            </div>-->

<!--            <div class="col-md-3">-->
<!--              <label class="form-label">Dispositivo</label>-->
<!--              <select name="dispositivo" id="iptv_edit_dispositivo" class="form-select">-->
<!--                <option value="tv">tv</option>-->
<!--                <option value="smartphone">smartphone</option>-->
<!--              </select>-->
<!--            </div>-->

<!--            <div class="col-md-3">-->
<!--              <label class="form-label">Combo</label>-->
<!--              <select name="combo" id="iptv_edit_combo" class="form-select">-->
<!--                <option value="0">No</option>-->
<!--                <option value="1">Sí</option>-->
<!--              </select>-->
<!--            </div>-->

<!--          </div>-->
<!--        </div>-->
<!--        <div class="modal-footer">-->
<!--          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>-->
<!--          <button type="submit" class="btn btn-primary">Guardar</button>-->
<!--        </div>-->
<!--      </form>-->
<!--    </div>-->
<!--  </div>-->
<!--</div>-->














<!-- Modal: Agregar IPTV -->
<div class="modal fade" id="modalAgregarIptv" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" action="../app/controllers/IptvController.php" method="post" id="formAgregarIptv">
      <input type="hidden" name="action" value="create">
      <!-- Hidden para el controller (se llenan desde el input WhatsApp) -->
      <input type="hidden" name="wa_cc" id="iptvAddWaCc">
      <input type="hidden" name="wa_local" id="iptvAddWaLocal">
      <input type="hidden" name="whatsapp" id="iptv_whatsapp">

      <div class="modal-header">
        <h5 class="modal-title">Agregar IPTV</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label">Nombre</label>
            <input type="text" class="form-control" name="nombre" placeholder="(opcional)">
          </div>
          <div class="col-md-4">
            <label class="form-label">Usuario</label>
            <input type="text" class="form-control" name="usuario" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Contraseña</label>
            <input type="text" class="form-control" name="password_plain" required>
          </div>

          <div class="col-12">
            <label class="form-label">URL</label>
            <input type="url" class="form-control" name="url" placeholder="http://..." required>
          </div>

         
       <!-- WhatsApp -->
<div class="col-md-6">
  <label class="form-label">WhatsApp</label>
  <div class="row g-2">
    <div class="col-4">
      <input
        type="text"
        class="form-control form-control-sm"
        id="wa_cc"
        name="wa_cc"
        placeholder="+51"
        inputmode="numeric"
        pattern="\d{1,4}"
        maxlength="4">
    </div>
    <div class="col-8">
      <input
        type="text"
        class="form-control form-control-sm"
        id="wa_local"
        name="wa_local"
        placeholder="999 999 999"
        inputmode="numeric"
        pattern="[0-9 ]{9,13}"
        maxlength="11">
    </div>
  </div>
  <div class="form-text">Solo números. El local se auto-formatea 3-3-3.</div>
</div>



          <div class="col-md-4">
            <label class="form-label">Inicio</label>
            <input type="date" class="form-control" name="fecha_inicio" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Fin</label>
            <input type="date" class="form-control" name="fecha_fin" value="<?= date('Y-m-d', strtotime('+31 days')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Precio (S/)</label>
            <input type="number" class="form-control" step="0.01" min="0" name="soles" value="0.00">
          </div>

          <div class="col-md-4">
            <label class="form-label">Estado</label>
            <select class="form-select" name="estado">
              <option value="activo" selected>activo</option>
              <option value="pendiente">pendiente</option>
            </select>
          </div>
         
          <div class="col-md-4 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="combo" value="1" id="iptvAddCombo">
              <label class="form-check-label" for="iptvAddCombo">Combo</label>
            </div>
          </div>
        </div>
      </div>

     <div class="modal-footer">
  <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
  <button type="submit" class="btn btn-sm btn-primary">Guardar</button>

</div>

    </form>
  </div>
</div>











<div class="modal fade" id="modalEditarIptv" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title">Editar IPTV</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>









      <form method="post" action="../app/controllers/IptvController.php" autocomplete="off">
        <div class="modal-body">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="">
          <input type="hidden" name="whatsapp" value="">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Nombre</label>
              <input type="text" name="nombre" class="form-control">
            </div>

            <div class="col-md-4">
              <label class="form-label">Usuario</label>
              <input type="text" name="usuario" class="form-control" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Contraseña</label>
              <input type="text" name="password_plain" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">URL</label>
              <input type="url" name="url" class="form-control">
            </div>

           <!-- WhatsApp -->
<div class="col-md-6">
  <label class="form-label">WhatsApp</label>
  <div class="row g-2">
    <div class="col-4">
      <input
        type="text"
        class="form-control form-control-sm"
        id="wa_cc"
        name="wa_cc"
        placeholder="+51"
        inputmode="numeric"
        pattern="\d{1,4}"
        maxlength="4">
    </div>
    <div class="col-8">
      <input
        type="text"
        class="form-control form-control-sm"
        id="wa_local"
        name="wa_local"
        placeholder="999 999 999"
        inputmode="numeric"
        pattern="[0-9 ]{9,13}"
        maxlength="11">
    </div>
    <input type="hidden" name="whatsapp" id="iptv_whatsapp">
  </div>
  <div class="form-text">Solo números. El local se auto-formatea 3-3-3.</div>
</div>




           

            <div class="col-md-4">
              <label class="form-label">Inicio</label>
              <input type="date" name="fecha_inicio" class="form-control">
            </div>

            <div class="col-md-4">
              <label class="form-label">Fin</label>
              <input type="date" name="fecha_fin" class="form-control">
            </div>

            <div class="col-md-3">
              <label class="form-label">Soles (S/)</label>
              <input type="number" step="0.01" name="soles" class="form-control">
            </div>

            <div class="col-md-3">
              <label class="form-label">Estado</label>
              <select name="estado" class="form-select">
                <option value="activo">activo</option>
                <option value="pendiente">pendiente</option>
                <option value="moroso">moroso</option>
              </select>
            </div>


            <div class="col-md-3">
              <label class="form-label">Combo</label>
              <select name="combo" class="form-select">
                <option value="0">No</option>
                <option value="1">Sí</option>
              </select>
            </div>
          </div>
        </div>

        <div class="modal-footer">
  <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
  <button type="submit" class="btn btn-sm btn-primary">Guardar</button>

</div>

      </form>
      
      
      
      
      
      
    </div>
  </div>
</div>





<!-- Modal Cambiar Plan (Perfiles) -->
<div class="modal fade" id="modalCambiarPlanPerfil" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title">Cambiar plan</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body py-3">
        <input type="hidden" id="perfilPlanId" value="">

        <div class="mb-2">
          <label for="perfilPlanSelect" class="form-label mb-1">Plan</label>
          <select id="perfilPlanSelect" class="form-select form-select-sm">
            <option value="individual">individual</option>
            <option value="standard">standard</option>
            <option value="premium">premium</option>
          </select>
        </div>

        <div class="mb-2">
          <label for="perfilColorSelect" class="form-label mb-1">Color de la fila</label>
          <select id="perfilColorSelect" class="form-select form-select-sm">
            <option value="">(sin color)</option>
            <option value="rojo">rojo</option>
            <option value="azul">azul</option>
            <option value="verde">verde</option>
            <option value="blanco">blanco</option>
          </select>
        </div>

        <div class="mb-2">
          <label for="perfilEnviarASelect" class="form-label mb-1">¿Adónde se envía?</label>
          <select id="perfilEnviarASelect" class="form-select form-select-sm">
            <option value="none">(mantener en perfiles)</option>
            <option value="stock">mover a Stock</option>
            <option value="pausa">mover a Pausa</option>
          </select>
        </div>
      </div>

      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="btnGuardarPlanPerfil" class="btn btn-sm btn-primary">Guardar</button>
      </div>
    </div>
  </div>
</div>































<!-- Modal: Agregar/Editar Perfil Familiar -->
<div class="modal fade" id="perfilFamiliarModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="perfilFamiliarModalLabel">Agregar Perfil (familiar)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form method="post" action="actions/perfil_familiar_create.php" autocomplete="off">
        <div class="modal-body">
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="id" value="">
          <input type="hidden" name="streaming_id" value="<?= (int)$streaming_id ?>">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Correo</label>
              <input type="email" name="correo" class="form-control" required>
            </div>

            <input type="hidden" name="plan" value="premium">

            <div class="col-md-4">
              <label class="form-label">Contraseña</label>
              <input type="text" name="password_plain" class="form-control" required autocomplete="off">
            </div>

            <div class="col-md-4">
              <label class="form-label">WhatsApp</label>
              <div class="input-group">
                <span class="input-group-text">+</span>
                <input type="text" class="form-control" name="wa_cc" inputmode="numeric" pattern="[0-9]{1,3}" maxlength="3" placeholder="51" style="max-width: 90px;">
                <input type="text" class="form-control" name="wa_local" inputmode="numeric" pattern="[0-9 ]{6,20}" maxlength="20" placeholder="977 948 954">
              </div>
              <div class="form-text">Se guardará como “+CC 999 999 999”.</div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Inicio</label>
              <input type="date" name="fecha_inicio" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="col-md-3">
              <label class="form-label">Fin</label>
              <input type="date" name="fecha_fin" class="form-control" value="<?= date('Y-m-d', strtotime('+31 days')) ?>" required>
            </div>

            <div class="col-md-3">
              <label class="form-label">Perfil</label>
              <input type="text" name="perfil" class="form-control">
            </div>

            <div class="col-md-3">
              <label class="form-label">Combo</label>
              <select name="combo" class="form-select">
                <option value="0">No</option>
                <option value="1">Sí</option>
              </select>
            </div>

            <!-- Precio (S/) — el JS montará aquí -->
            <div class="mb-2 col-md-3" id="famChildPriceGroup">
              <label class="form-label">Precio (S/)</label>
              <div id="famChildPriceSlot" data-price-slot></div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Estado</label>
              <select name="estado" class="form-select form-select-sm" required>
                <option value="activo" selected>activo</option>
                <option value="pendiente">pendiente</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Dispositivo</label>
              <select name="dispositivo" class="form-select">
                <option value="tv">tv</option>
                <option value="smartphone">smartphone</option>
              </select>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>






















<!-- hook inline: streaming familiar (PADRE/HIJO) -->
<script>
;(function(){
  'use strict';
  if (window.__famInlineHookV2) return; window.__famInlineHookV2 = true;

  var tabPane = document.getElementById('perfiles-familiar');
  var famModal = document.getElementById('perfilFamiliarModal');
  var perfModal = document.getElementById('perfilModal');
  var headFam  = document.getElementById('precioFamiliarHead');
  if (!tabPane || !famModal) return;

  function q(sel, ctx){ return (ctx||document).querySelector(sel); }
  function ensureEditable(inp){ if(!inp) return; inp.readOnly=false; inp.removeAttribute('readonly'); inp.disabled=false; inp.classList.remove('bg-light','disabled'); }
  function famSlot(form){
    var grp = q('#famChildPriceGroup', famModal); if (!grp) return null;
    var lbl = grp.querySelector('label') ? grp.querySelector('label').outerHTML : '<label class="form-label">Precio (S/)</label>';
    grp.innerHTML = lbl + '<div id="famChildPriceSlot" data-price-slot></div>';
    return grp.querySelector('#famChildPriceSlot');
  }
  function famRealInput(form){
    var r = q('input[name="soles"]', famModal);
    if (r) return r;
    r = document.createElement('input'); r.type='number'; r.step='0.01'; r.name='soles'; r.className='form-control';
    return r;
  }
  function purgeSoles(form, keep){ (form||famModal).querySelectorAll('input[name="soles"]').forEach(function(x){ if(x!==keep) x.remove(); }); }
  function setVal(form, sel, val){ var el=(form||famModal).querySelector(sel); if (el) el.value = val; }
  function famActive(){ return tabPane.classList.contains('show') || tabPane.classList.contains('active'); }

  /* Cancelar #perfilModal si la pestaña familiar está activa */
  if (perfModal) {
    document.addEventListener('show.bs.modal', function(ev){
      if (ev.target !== perfModal) return;
      if (!famActive()) return;
      ev.preventDefault(); ev.stopImmediatePropagation();
      try { bootstrap.Modal.getInstance(perfModal)?.hide(); } catch(_){}
    }, true);
  }

  /* Marcar prefijo de HIJO ANTES que Bootstrap abra el familiar (captura), usando el TR trigger */
  document.addEventListener('click', function(e){
    var trigger = e.target && e.target.closest && e.target.closest('[data-bs-toggle="modal"][data-bs-target="#perfilFamiliarModal"]');
    if (!trigger) return;
    if (!trigger.matches('tr.js-parent-row')) return;
    if ((trigger.getAttribute('data-entidad')||'') !== 'perfil_fam') return;
    if ((trigger.getAttribute('data-modal-context')||'child') !== 'child') return;

    // No evitamos el default: solo preparamos los datos ANTES de show.bs.modal
    famModal.dataset.prefill       = '1';
    famModal.dataset.correo        = trigger.getAttribute('data-correo') || '';
    famModal.dataset.password      = trigger.getAttribute('data-password') || '';
    famModal.dataset.soles         = trigger.getAttribute('data-soles') || '';
    famModal.dataset.streaming_id  = trigger.getAttribute('data-streaming_id') || '';
    famModal.dataset.plan          = trigger.getAttribute('data-plan') || 'individual';
    famModal.dataset.combo         = (String(trigger.getAttribute('data-combo')) === '1' ? '1' : '0');
  }, true);

  document.addEventListener('keydown', function(e){
    if (e.key !== 'Enter') return;
    var trigger = e.target && e.target.closest && e.target.closest('[data-bs-toggle="modal"][data-bs-target="#perfilFamiliarModal"]');
    if (!trigger) return;
    if (!trigger.matches('tr.js-parent-row')) return;
    if ((trigger.getAttribute('data-entidad')||'') !== 'perfil_fam') return;
    if ((trigger.getAttribute('data-modal-context')||'child') !== 'child') return;

    famModal.dataset.prefill       = '1';
    famModal.dataset.correo        = trigger.getAttribute('data-correo') || '';
    famModal.dataset.password      = trigger.getAttribute('data-password') || '';
    famModal.dataset.soles         = trigger.getAttribute('data-soles') || '';
    famModal.dataset.streaming_id  = trigger.getAttribute('data-streaming_id') || '';
    famModal.dataset.plan          = trigger.getAttribute('data-plan') || 'individual';
    famModal.dataset.combo         = (String(trigger.getAttribute('data-combo')) === '1' ? '1' : '0');
  }, true);

  /* Diferenciación PADRE/HIJO con ev.relatedTarget del data-api (sin depender de otros scripts) */
  famModal.addEventListener('show.bs.modal', function(ev){
    var t = ev.relatedTarget || null;
    var form = q('form', famModal); if (!form) return;
    var slot = famSlot(form); if (!slot) return;

    // limpiar inputs previos
    purgeSoles(form, null);
    var inp = famRealInput(form);
    slot.appendChild(inp);

    var isChild = false, isParent = false;

    if (t) {
      // TR fila (hijo)
      if (t.matches && t.matches('tr.js-parent-row[data-entidad="perfil_fam"][data-modal-context="child"]')) isChild = true;
      // Botón de cabecera (padre)
      if (!isChild && (t.matches && (t.matches('.btn-add-perfil-fam,[data-modal-context="parent"]')))) isParent = true;
    }

    // Si algún script abrió programáticamente sin relatedTarget, usa dataset.prefill como fallback
    if (!isChild && !isParent && famModal.dataset.prefill === '1') isChild = true;

    if (isChild) {
      var correo = famModal.dataset.correo || (t && t.getAttribute && t.getAttribute('data-correo')) || '';
      var title  = document.getElementById('perfilFamiliarModalLabel');
      if (title) title.textContent = 'Agregar a correo: ' + correo;

      setVal(form, 'input[name="correo"]', correo);
      setVal(form, 'input[name="password_plain"]', famModal.dataset.password || '');
      setVal(form, 'select[name="plan"]', famModal.dataset.plan || 'individual');
      setVal(form, 'select[name="combo"]', famModal.dataset.combo === '1' ? '1' : '0');
      var sid = famModal.dataset.streaming_id || form.querySelector('input[name="streaming_id"]')?.value || '';
      setVal(form, 'input[name="streaming_id"]', sid);

      var selE = q('select[name="estado"]', famModal); if (selE) selE.value = 'pendiente';
      var selD = q('select[name="dispositivo"]', famModal); if (selD) selD.value = 'tv';

      ensureEditable(inp);
      inp.value = '';

      if (!q('input[name="action_child"]', famModal)) {
        var h = document.createElement('input'); h.type='hidden'; h.name='action_child'; h.value='1';
        form.appendChild(h);
      }
      return; // ← evita lógica de PADRE
    }

    // PADRE por defecto
    var title = document.getElementById('perfilFamiliarModalLabel');
    if (title) title.textContent = 'Agregar Perfil (familiar)';
    ensureEditable(inp);
    if (headFam) inp.value = headFam.value || '';
  }, true);

  famModal.addEventListener('hidden.bs.modal', function(){
    delete famModal.dataset.prefill;
    delete famModal.dataset.correo;
    delete famModal.dataset.password;
    delete famModal.dataset.soles;
    delete famModal.dataset.streaming_id;
    delete famModal.dataset.plan;
    delete famModal.dataset.combo;
  });
})();
</script>
