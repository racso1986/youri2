/* perfiles_filters.fixed.js — Filtro PERFILES + orden por creación (desc)
   - Compatible con tu HTML actual (#perfiles, #perfilesTable, wrapper __pcFilter__ ...)
   - Sin dependencias externas. Carga este archivo después de Bootstrap, antes/tras app.js indistinto.
*/
;(function(){
  'use strict';
  if (window.__perfilesFilterBound) return; // anti-doble carga
  window.__perfilesFilterBound = true;

  var pane  = document.getElementById('perfiles');
  if (!pane) return console.warn('[PF] no existe #perfiles (pestaña Perfiles)');

  var table = pane.querySelector('#perfilesTable') || pane.querySelector('table');
  if (!table) return console.warn('[PF] no hay #perfilesTable dentro de #perfiles');

  var tbody = table.tBodies && table.tBodies[0];
  if (!tbody) return console.warn('[PF] la tabla no tiene <tbody>');

  // === Helpers
  function norm(s){ return String(s||'').toLowerCase().trim(); }
  function digits(s){ return String(s||'').replace(/\D+/g,''); }

  // Agrupar por padre + hijos contiguos
  function buildGroups(){
    var groups=[], curr=null;
    Array.from(tbody.querySelectorAll('tr')).forEach(function(tr){
      var isParent = tr.classList.contains('js-parent-row') || tr.getAttribute('data-parent') === '1';
      if (isParent || !curr){ curr = { parent: tr, children: [] }; groups.push(curr); }
      else { curr.children.push(tr); }
    });
    return groups;
  }
  function reappendGroups(groups){
    groups.forEach(function(g){
      tbody.appendChild(g.parent);
      g.children.forEach(function(ch){ tbody.appendChild(ch); });
    });
  }
  function createdTsFromRow(tr){
    var v = tr.getAttribute('data-created-ts'); // esperado UNIX ts (seg) o ms
    if (!v) return 0;
    var n = parseInt(v,10);
    if (isNaN(n)) return 0;
    // si viene en segundos, pásalo a ms para consistencia visual; el orden relativo no cambia
    return (n < 1e12 ? n*1000 : n);
  }
  function sortGroupsByCreated(groups, dir){
    var asc = (dir === 'asc');
    groups.sort(function(a,b){
      var av = createdTsFromRow(a.parent), bv = createdTsFromRow(b.parent);
      return asc ? (av - bv) : (bv - av);
    });
  }
  function reindexGroups(groups){
    var i = 0;
    groups.forEach(function(g){ g.parent.dataset.idx = String(++i); });
  }

  // === Controles
  var wrap   = pane.querySelector('.__pcFilter__[data-scope="perfiles"]') || pane;
  var input  = wrap.querySelector('.pc-search');
  var main   = wrap.querySelector('.pc-main');
  var planEl = wrap.querySelector('.pc-plan');

  // === Aplicar búsqueda + filtros
  function planKeyFromText(txt){
    var s = norm(txt);
    if (s.indexOf('premium') !== -1) return 'premium';
    if (s.indexOf('estándar') !== -1 || s.indexOf('estandar') !== -1 || s.indexOf('standard') !== -1) return 'estandar';
    return 'basico'; // incluye “individual”
  }
  function planFromParent(tr){
    var td = tr.querySelector('.plan-cell-perfil,[data-plan]');
    var val = td ? (td.getAttribute('data-plan') || td.textContent || '') : (tr.getAttribute('data-plan')||'');
    return planKeyFromText(val);
  }
  function estadoFromParent(tr){
    var est = norm(tr.getAttribute('data-estado') || '');
    if (est) return est;
    var badge = tr.querySelector('.badge'); return badge ? norm(badge.textContent) : '';
  }
  function colorFromParent(tr){
    var c = norm(tr.getAttribute('data-color') || '');
    if (c) return c;
    if (tr.classList.contains('row-color-rojo'))   return 'rojo';
    if (tr.classList.contains('row-color-azul'))   return 'azul';
    if (tr.classList.contains('row-color-verde'))  return 'verde';
    if (tr.classList.contains('row-color-blanco')) return 'blanco';
    return '';
  }
  function correoFromParent(tr){
    var c = tr.getAttribute('data-correo') || '';
    if (!c){
      var ccell = tr.querySelector('.correo-cell,[data-correo-cell]');
      c = ccell ? ccell.textContent : ((tr.children[1] && tr.children[1].textContent) || '');
    }
    return norm(c);
  }
  function waDigitsFromParent(tr){
    var a = tr.querySelector('.wa-link');
    var raw = a && a.href ? a.href : (tr.getAttribute('data-whatsapp') || tr.textContent || '');
    return digits(raw);
  }
  function childText(tr){ return norm(tr.textContent); }

  function hideGroup(g, hide){
    var on = !!hide;
    [g.parent].concat(g.children).forEach(function(tr){
      tr.classList.toggle('d-none', on);
      tr.style.setProperty('display', on ? 'none' : '', on ? 'important' : '');
    });
  }

  var groups = buildGroups();
  // Auto-orden por fecha de inserción (desc) al cargar
  sortGroupsByCreated(groups, 'desc');
  reappendGroups(groups);
  reindexGroups(groups);

  function apply(){
    // Primero aplica select principal (color/pendientes/plan)
    var vMain = main ? main.value : '';
    var vPlan = planEl ? planEl.value : '';
    groups.forEach(function(g){ hideGroup(g, false); });
    groups.forEach(function(g){
      var hide = false;
      switch(vMain){
        case 'color_rojo':
        case 'color_azul':
        case 'color_verde':
          hide = (colorFromParent(g.parent) !== vMain.split('_')[1]);
          break;
        case 'pendientes':
          hide = (estadoFromParent(g.parent) !== 'pendiente');
          break;
        case 'plan':
          hide = (vPlan && planFromParent(g.parent) !== vPlan);
          break;
      }
      if (hide) hideGroup(g,true);
    });

    // Luego aplica texto rápido
    var q = input ? norm(input.value) : '';
    var qNum = digits(q);
    if (!q && !(qNum && qNum.length >= 3)) return;
    groups.forEach(function(g){
      if (g.parent.classList.contains('d-none')) return;
      var ok = false;
      if (q && correoFromParent(g.parent).indexOf(q) !== -1) ok = true;
      if (!ok && qNum && qNum.length>=3 && waDigitsFromParent(g.parent).indexOf(qNum) !== -1) ok = true;
      if (!ok && q) {
        for (var i=0;i<g.children.length;i++){
          if (childText(g.children[i]).indexOf(q) !== -1){ ok = true; break; }
        }
      }
      if (!ok) hideGroup(g, true);
    });
  }

  function togglePlan(){
    if (!main || !planEl) return;
    if (main.value === 'plan') planEl.style.display = '';
    else { planEl.value=''; planEl.style.display='none'; }
  }

  input && input.addEventListener('input', apply);
  main  && main.addEventListener('change', function(){ togglePlan(); apply(); });
  planEl&& planEl.addEventListener('change', apply);

  togglePlan();
  apply();
})();