// /public/assets/js/app_familiar_modal_fix.js  (v6)
;(function(){
  'use strict';
  if (window.__famPatchV6) return; window.__famPatchV6 = true;

  var tabPane   = document.getElementById('perfiles-familiar');
  var famModal  = document.getElementById('perfilFamiliarModal');
  var perfModal = document.getElementById('perfilModal');
  var headFam   = document.getElementById('precioFamiliarHead');
  if (!tabPane || !famModal) return;

  function q(sel, ctx){ return (ctx||document).querySelector(sel); }
  function qa(sel, ctx){ return Array.prototype.slice.call((ctx||document).querySelectorAll(sel)); }
  function famActive(){ return tabPane.classList.contains('show') || tabPane.classList.contains('active'); }
  function ensureEditable(inp){ if(!inp) return; inp.readOnly=false; inp.removeAttribute('readonly'); inp.disabled=false; inp.classList.remove('bg-light','disabled'); }
  function famSlot(form){
    var grp = form.querySelector('#famChildPriceGroup'); if (!grp) return null;
    var lbl = grp.querySelector('label') ? grp.querySelector('label').outerHTML : '<label class="form-label">Precio (S/)</label>';
    grp.innerHTML = lbl + '<div id="famChildPriceSlot" data-price-slot></div>';
    return grp.querySelector('#famChildPriceSlot');
  }
  function famRealInput(form){
    var r = form.querySelector('input[name="soles"]'); if (r) return r;
    r = document.createElement('input'); r.type='number'; r.step='0.01'; r.name='soles'; r.className='form-control';
    return r;
  }
  function purgeForeignSoles(form, keep){ qa('input[name="soles"]', form).forEach(function(el){ if(el!==keep) el.remove(); }); }
  function setVal(form, sel, val){ var el=form.querySelector(sel); if (el) el.value = val; }

  // 1) BLOQUEAR que #perfilModal se abra en la pestaña familiar
  if (perfModal) {
    document.addEventListener('show.bs.modal', function(ev){
      if (ev.target !== perfModal) return;
      if (!famActive()) return;
      ev.preventDefault(); ev.stopPropagation(); ev.stopImmediatePropagation();
      try { bootstrap.Modal.getInstance(perfModal)?.hide(); } catch(_){}
    }, true);
  }

  // 2) CAPTURA de click EXACTO sobre el trigger que abre #perfilFamiliarModal desde la FILA (modo HIJO)
  //    Importante: NO nos salimos por encontrar [data-bs-target], aquí es justo lo que queremos interceptar.
  document.addEventListener('click', function(e){
    // Busca el elemento que realmente disparará el data-api de Bootstrap hacia #perfilFamiliarModal
    var trigger = e.target && e.target.closest && e.target.closest('[data-bs-toggle="modal"][data-bs-target="#perfilFamiliarModal"]');
    if (!trigger) return;
    // Solo si es la FILA PADRE de familiar en modo child
    if (!trigger.matches('tr.js-parent-row')) return;
    if ((trigger.getAttribute('data-entidad')||'') !== 'perfil_fam') return;
    if ((trigger.getAttribute('data-modal-context')||'child') !== 'child') return;

    // Marca prefill ANTES de que Bootstrap dispare show.bs.modal
    famModal.dataset.prefill       = '1';
    famModal.dataset.correo        = trigger.getAttribute('data-correo') || '';
    famModal.dataset.password      = trigger.getAttribute('data-password') || '';
    famModal.dataset.soles         = trigger.getAttribute('data-soles') || '';
    famModal.dataset.streaming_id  = trigger.getAttribute('data-streaming_id') || '';
    famModal.dataset.plan          = trigger.getAttribute('data-plan') || 'individual';
    famModal.dataset.combo         = (String(trigger.getAttribute('data-combo')) === '1' ? '1' : '0');
  }, true); // CAPTURA para ganarle al data-api

  // 3) ENTER accesible sobre la FILA (mismo marcado de prefill)
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

  // 4) show.bs.modal del FAMILIAR: PADRE vs HIJO (prefill)
  famModal.addEventListener('show.bs.modal', function(ev){
    var form = q('form', famModal); if (!form) return;

    // ======= HIJO (prefill por fila) =======
    if (famModal.dataset.prefill === '1') {
      form.reset();
      setVal(form, 'input[name="action"]', 'create');
      setVal(form, 'input[name="id"]', '');

      var sid = famModal.dataset.streaming_id || form.querySelector('input[name="streaming_id"]')?.value || '';
      setVal(form, 'input[name="streaming_id"]', sid);

      setVal(form, 'input[name="correo"]', famModal.dataset.correo || '');
      setVal(form, 'input[name="password_plain"]', famModal.dataset.password || '');
      setVal(form, 'select[name="plan"]', famModal.dataset.plan || 'individual');
      setVal(form, 'select[name="combo"]', famModal.dataset.combo === '1' ? '1' : '0');

      var selE = form.querySelector('select[name="estado"]'); if (selE) selE.value = 'pendiente';
      var selD = form.querySelector('select[name="dispositivo"]'); if (selD) selD.value = 'tv';

      var title = famModal.querySelector('#perfilFamiliarModalLabel');
      if (title) title.textContent = 'Agregar a correo: ' + (famModal.dataset.correo || '');

      var sb = form.querySelector('button[type="submit"]'); if (sb) sb.textContent = 'Guardar';

      var slot = famSlot(form); if (!slot) return;
      purgeForeignSoles(form, null);
      var real = famRealInput(form);
      slot.appendChild(real);
      ensureEditable(real);
      real.value = '';

      if (!form.querySelector('input[name="action_child"]')) {
        var h = document.createElement('input'); h.type='hidden'; h.name='action_child'; h.value='1'; form.appendChild(h);
      }
      return; // ← evita lógica de PADRE
    }

    // ======= PADRE (botón "Agregar perfil (familiar)") =======
    var slot = famSlot(form); if (!slot) return;
    purgeForeignSoles(form, null);
    var real = famRealInput(form);
    slot.appendChild(real);
    ensureEditable(real);
    if (headFam && headFam.value) real.value = headFam.value;

    var title = famModal.querySelector('#perfilFamiliarModalLabel');
    if (title) title.textContent = 'Agregar Perfil (familiar)';
  }, true);

  // 5) Limpieza al cerrar
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
