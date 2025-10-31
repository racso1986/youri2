/* app_perfiles_modal_fix.js — Parche focalizado Perfiles (PADRE/Hijo precio)
   Carga este archivo DESPUÉS de tu app.js actual.
   Objetivo:
   - PADRE: el precio del header (#precioPerfilHead) prellena y se BLOQUEA (readonly) sólo en el modal del padre.
   - HIJO: primer hijo -> precio vacío y editable; si ya existe ancla en la fila padre, bloquear con ese valor.
   - Anti-duplicados name="soles" (elimina hiddens/clones residuales antes de enviar).
*/
;(function(){
  'use strict';
  if (window.__pfPricePatchBound) return;
  window.__pfPricePatchBound = true;

  var modal = document.getElementById('perfilModal');
  var head  = document.getElementById('precioPerfilHead');
  if (!modal) return; // no estás en la vista

  function qForm(){ return modal.querySelector('form'); }
  function qPrice(){ return modal.querySelector('input[name="soles"]'); }
  function clearHiddenSoles(){
    var f = qForm(); if (!f) return;
    f.querySelectorAll('input[type="hidden"][name="soles"]').forEach(function(x){ x.remove(); });
  }

  // --- Determinar contexto de apertura ---
  // Click en botón Agregar perfil => PADRE
  document.addEventListener('click', function(e){
    var btn = e.target && e.target.closest && e.target.closest('.btn-add-perfil');
    if (!btn) return;
    modal.dataset.mode = 'parent';
    modal.dataset.lockVal = head ? (head.value || '') : '';
  }, true);

  // Click en fila padre => HIJO (rackea anchor si existe)
  document.addEventListener('click', function(e){
    var row = e.target && e.target.closest && e.target.closest('tr.js-parent-row');
    if (!row) return;
    if (e.target.closest('.js-row-action')) return; // no al editar/borrar
    modal.dataset.mode = 'child';
    modal.dataset.childAnchor = row.getAttribute('data-anchor-price') || '';
  }, true);

  // --- Al MOSTRAR ---
  modal.addEventListener('shown.bs.modal', function(){
    var mode  = modal.dataset.mode || 'parent';
    var price = qPrice();
    if (!price) return;

    // Limpia residuos
    clearHiddenSoles();
    price.readOnly = false; price.removeAttribute('readonly'); price.classList.remove('bg-light');

    if (mode === 'parent') {
      var v = modal.dataset.lockVal || (head ? head.value : '') || price.value || '';
      price.value = v;
      price.readOnly = true; price.setAttribute('readonly','readonly'); price.classList.add('bg-light');
      return;
    }

    // child
    var anchor = modal.dataset.childAnchor || '';
    if (anchor !== '') {
      price.value = anchor;
      price.readOnly = true; price.setAttribute('readonly','readonly'); price.classList.add('bg-light');

      // hidden para POST (evita que readonly sea manipulado por otros scripts)
      var f = qForm();
      if (f) {
        var h = document.createElement('input');
        h.type = 'hidden'; h.name = 'soles'; h.value = anchor;
        f.appendChild(h);
      }
    } else {
      // primer hijo => libre
      price.value = '';
      price.readOnly = false; price.removeAttribute('readonly'); price.classList.remove('bg-light');
    }
  }, true);

  // Si cambias el header mientras está abierto el PADRE, sincroniza
  if (head) head.addEventListener('input', function(){
    if (!modal.classList.contains('show')) return;
    if (modal.dataset.mode !== 'parent') return;
    var price = qPrice(); if (!price) return;
    price.value = head.value || '';
  }, {passive:true});

  // --- Al CERRAR ---
  modal.addEventListener('hidden.bs.modal', function(){
    clearHiddenSoles();
    delete modal.dataset.mode;
    delete modal.dataset.lockVal;
    delete modal.dataset.childAnchor;
    var price = qPrice();
    if (price) { price.readOnly = false; price.removeAttribute('readonly'); price.classList.remove('bg-light'); }
  }, true);

  // --- Seguridad al ENVIAR: dedupe name="soles" y asegura el valor correcto ---
  document.addEventListener('submit', function(ev){
    var form = ev.target;
    if (!form || form !== qForm()) return;
    // quita duplicados name="soles" ocultos
    var inputs = form.querySelectorAll('input[name="soles"]');
    if (inputs.length > 1) {
      // conserva el último (el visible o el hidden recién agregado) y elimina anteriores vacíos
      for (var i=0;i<inputs.length-1;i++){
        if (inputs[i].type === 'hidden' || inputs[i].value === '') inputs[i].remove();
      }
    }
  }, true);
})();