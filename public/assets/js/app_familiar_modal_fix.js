(function () {
  'use strict';

  if (window.__APP_FAMILIAR_MODAL_FIX_INSTALLED) return;
  window.__APP_FAMILIAR_MODAL_FIX_INSTALLED = true;

  const MODAL_ID = 'modalCambiarPlanPerfil';
  const BTN_ID = 'btnGuardarPlanPerfil';
  const FAMILIAR_ENDPOINT = '/public/ajax/perfiles_familiar_plan_update.php';

  function $ (sel, ctx) { return (ctx || document).querySelector(sel); }

  function showRawInModal(modal, text) {
    try {
      let ph = modal.querySelector('.modal-body .alert-placeholder');
      if (!ph) {
        ph = document.createElement('div'); ph.className = 'alert-placeholder';
        const body = modal.querySelector('.modal-body');
        if (body && body.firstChild) body.insertBefore(ph, body.firstChild);
        else if (body) body.appendChild(ph);
        else modal.appendChild(ph);
      }
      ph.innerHTML = '';
      const pre = document.createElement('pre');
      pre.style.maxHeight = '320px';
      pre.style.overflow = 'auto';
      pre.style.whiteSpace = 'pre-wrap';
      pre.style.background = '#111';
      pre.style.color = '#fff';
      pre.style.padding = '8px';
      pre.textContent = (text || '').slice(0, 20000);
      ph.appendChild(pre);
      try { const w = window.open(); if (w) { w.document.open(); w.document.write(text || ''); w.document.close(); } } catch(_){})
    } catch (e) { console && console.error && console.error('showRawInModal error', e); }
  }

  function updateRowUI(perfilId, updated) {
    try {
      if (!perfilId) return;
      const selectors = [
        `tr[data-perfil-id="${perfilId}"]`,
        `tr[data-id="${perfilId}"]`,
        `tr[data-entidad-id="${perfilId}"]`,
        `tr[data-entidad="${perfilId}"]`
      ];
      let row = null;
      for (let s of selectors) { row = document.querySelector(s); if (row) break; }
      if (!row) return;
      const planCell = row.querySelector('[data-col="plan"], .col-plan');
      if (planCell && updated.plan_label) planCell.textContent = updated.plan_label || planCell.textContent;
      if (updated.plan_color) {
        const colorEl = row.querySelector('.plan-color, .badge-plan-color');
        if (colorEl) colorEl.style.backgroundColor = updated.plan_color;
        else if (planCell) planCell.style.backgroundColor = updated.plan_color;
        row.dataset.planColor = updated.plan_color;
      }
      if (updated.plan_id) row.dataset.planId = updated.plan_id;
    } catch (e) { console && console.warn && console.warn('updateRowUI error', e); }
  }

  async function sendFamiliarUpdate(modal, payload) {
    try {
      const perfilId = payload.perfil_id || '';
      const plan = payload.plan || '';
      const color = payload.color || '';
      const enviar_a = payload.enviar_a || '';
      if (!perfilId) {
        const ph = modal.querySelector('.modal-body .alert-placeholder') || (()=>{ const d=document.createElement('div'); d.className='alert-placeholder'; modal.querySelector('.modal-body')?.insertBefore(d, modal.querySelector('.modal-body').firstChild); return d; })();
        ph.textContent = 'perfil_id faltante';
        return;
      }
      const fd = new FormData(); fd.append('perfil_id', perfilId); if (plan) fd.append('plan', plan); if (color) fd.append('color', color); if (enviar_a) fd.append('enviar_a', enviar_a);
      const btn = document.getElementById(BTN_ID);
      let prevText = '', wasDisabled = false; if (btn) { prevText = btn.innerHTML; wasDisabled = btn.disabled; btn.innerHTML = 'Guardando...'; btn.disabled = true; }
      const resp = await fetch(FAMILIAR_ENDPOINT, { method: 'POST', credentials: 'same-origin', body: fd });
      const text = await resp.text();
      let json = null; try { json = text ? JSON.parse(text) : null; } catch(_) { json = null; }
      if (!json) { console && console.error && console.error('[familiar] respuesta no JSON', text); showRawInModal(modal, text || ''); return; }
      const ok = (json.success === 1) || (json.ok === true);
      if (!ok) { const msg = json.message || json.error || 'Error al actualizar plan'; const ph = modal.querySelector('.modal-body .alert-placeholder') || (()=>{ const d=document.createElement('div'); d.className='alert-placeholder'; modal.querySelector('.modal-body')?.insertBefore(d, modal.querySelector('.modal-body').firstChild); return d; })(); ph.textContent = msg; return; }
      const updated = json.updated || {}; const returnedId = updated.perfil_id || perfilId || json.perfil_id || json.id || '';
      updateRowUI(returnedId, updated); try { bootstrap.Modal.getInstance(modal)?.hide(); } catch(_){})
    } catch (err) { console && console.error && console.error('sendFamiliarUpdate exception', err); const ph = document.getElementById(MODAL_ID)?.querySelector('.modal-body .alert-placeholder'); if (ph) ph.textContent = 'Error inesperado'; }
    finally { const btn = document.getElementById(BTN_ID); if (btn) { btn.innerHTML = prevText || 'Guardar'; btn.disabled = !!wasDisabled; } }
  }

  // Strict capture: only intercept when click is inside the small modal and modal.dataset.originPerfilId is present
  document.addEventListener('click', function (ev) {
    try {
      // find the closest button id match
      const clickedBtn = ev.target && ev.target.closest ? ev.target.closest('#' + BTN_ID) : null;
      if (!clickedBtn) return; // not the save button

      // ensure the clicked button is inside the small modal
      const modal = document.getElementById(MODAL_ID);
      if (!modal) return;
      if (!modal.contains(clickedBtn)) return; // safeguard: only handle clicks inside this modal

      const originEntity = (modal.dataset.originEntity || '').toLowerCase();
      const perfilId = modal.dataset.originPerfilId || '';
      const isFamiliar = (originEntity === 'perfil_fam' || originEntity === 'perfil_familiar' || originEntity === 'perfil-fam') && !!perfilId;
      if (!isFamiliar) return; // do nothing for parent or unknown

      // prevent other handlers
      if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();
      if (ev.preventDefault) ev.preventDefault();
      if (ev.stopPropagation) ev.stopPropagation();

      const payload = {
        perfil_id: perfilId,
        plan: ($('#perfilPlanSelect') && $('#perfilPlanSelect').value) || '',
        color: ($('#perfilColorSelect') && $('#perfilColorSelect').value) || '',
        enviar_a: ($('#perfilEnviarASelect') && $('#perfilEnviarASelect').value) || ''
      };
      sendFamiliarUpdate(modal, payload);
    } catch (e) { console && console.error && console.error('click capture error', e); }
  }, true);

  // Monkeypatch submit but strictly for the small modal form only
  (function patchSubmit() {
    try {
      if (!window.HTMLFormElement) return;
      const orig = HTMLFormElement.prototype.submit;
      HTMLFormElement.prototype.submit = function () {
        try {
          const form = this;
          const modal = form.closest && form.closest('#' + MODAL_ID);
          if (modal && modal.dataset && modal.dataset.originPerfilId) {
            // intercept and send via fetch
            const fd = new FormData(form);
            const payload = {};
            if (fd.get('perfil_id')) payload.perfil_id = fd.get('perfil_id');
            else payload.perfil_id = modal.dataset.originPerfilId;
            payload.plan = fd.get('plan') || ($('#perfilPlanSelect') && $('#perfilPlanSelect').value) || '';
            payload.color = fd.get('color') || ($('#perfilColorSelect') && $('#perfilColorSelect').value) || '';
            payload.enviar_a = fd.get('enviar_a') || ($('#perfilEnviarASelect') && $('#perfilEnviarASelect').value) || '';
            sendFamiliarUpdate(modal, payload);
            return; // do not call original submit
          }
        } catch (e) { console && console.error && console.error('patch submit error', e); }
        return orig.apply(this, arguments);
      };
    } catch (e) { console && console.error && console.error('monkeypatch submit failed', e); }
  })();

  // show.bs.modal handler: set dataset context and force form action when small modal is opened from a familiar row
  (function attachShowHandler(){
    const modal = document.getElementById(MODAL_ID);
    if (!modal) return;
    if (modal.__fpi_show_attached) return; modal.__fpi_show_attached = true;

    modal.addEventListener('show.bs.modal', function(ev){
      try {
        const trigger = ev.relatedTarget || null;
        let entidad = '', perfilId = '', planId = '', planColor = '';
        if (trigger) {
          const row = trigger.closest && trigger.closest('tr');
          if (row) {
            entidad = row.getAttribute('data-entidad') || row.getAttribute('data-entity') || '';
            perfilId = row.getAttribute('data-perfil-id') || row.getAttribute('data-id') || row.getAttribute('data-entidad-id') || '';
            planId = row.getAttribute('data-plan-id') || row.getAttribute('data-plan') || '';
            planColor = row.getAttribute('data-plan-color') || row.getAttribute('data-color') || '';
          }
          if (trigger.dataset) {
            entidad = trigger.dataset.entidad || trigger.dataset.entity || entidad;
            perfilId = trigger.dataset.perfilId || trigger.dataset.perfilid || perfilId;
            planId = trigger.dataset.planId || trigger.dataset.planid || planId;
            planColor = trigger.dataset.planColor || trigger.dataset.plancolor || planColor;
          }
        }
        if (entidad) modal.dataset.originEntity = entidad;
        if (perfilId) modal.dataset.originPerfilId = perfilId;
        if (planId) modal.dataset.originPlanId = planId;
        if (planColor) modal.dataset.originPlanColor = planColor;

        const isFamiliar = (String(entidad || '').toLowerCase() === 'perfil_fam' || String(entidad || '').toLowerCase() === 'perfil_familiar' || String(entidad || '').toLowerCase() === 'perfil-fam') && !!perfilId;
        const form = modal.querySelector('form');
        if (form) {
          if (!form.dataset.originalAction) form.dataset.originalAction = form.getAttribute('action') || '';
          if (isFamiliar) { form.setAttribute('action', FAMILIAR_ENDPOINT); form.dataset.fpi = '1'; }
          else { if (form.dataset.originalAction) form.setAttribute('action', form.dataset.originalAction); delete form.dataset.fpi; }
        }
      } catch(e) { console && console.error && console.error('show.bs.modal handler error', e); }
    }, true);

    modal.addEventListener('hidden.bs.modal', function(){
      try {
        const f = modal.querySelector('form');
        if (f && f.dataset && f.dataset.originalAction !== undefined) {
          if (f.dataset.originalAction) f.setAttribute('action', f.dataset.originalAction); else f.removeAttribute('action');
          delete f.dataset.originalAction;
        }
        delete modal.dataset.originEntity; delete modal.dataset.originPerfilId; delete modal.dataset.originPlanId; delete modal.dataset.originPlanColor; delete modal.dataset.fpi;
      } catch(_){})
    }, true);
  })();

  console.log('[app_familiar_modal_fix] strict handler installed');
})();