/* File: public/assets/js/perfiles_filters.js
   Adaptado a partir de cuentas_filters.js, mismo patrón de "grupo padre + hijos".
   Requisitos mínimos en el DOM (pestaña Perfiles):
   - Contenedor/pestaña con id="perfiles"
   - Un wrapper de filtros: .__pfFilter__[data-scope="perfiles"]
     ├─ select.pf-main            (opciones: '', 'plan', 'estado')
     ├─ select.pf-plan            (se muestra solo si pf-main === 'plan')
     ├─ select.pf-estado          (se muestra solo si pf-main === 'estado')
     ├─ input.pf-search           (texto: busca en correo del padre o nombre de perfil de hijos)
     └─ button.pf-clear           (limpia filtros)
   - Una tabla dentro de #perfiles con <tbody>, con filas PADRE e HIJO siguiendo el mismo orden visual:
     • Fila PADRE: class "js-parent-row" O data-parent="1"
       - Debe contener (o exponer como data-*) información de correo/plan/estado
         Ej: data-correo, data-plan, data-estado
     • Filas HIJO: las que siguen hasta el próximo PADRE
       - Ideal: data-perfil (nombre del perfil), data-precio (numérico)
       - Si no hay data-*, se intentará leer del texto de las celdas.
*/

;(function(){
  'use strict';
  if (window.__pfFiltersBound) return;
  window.__pfFiltersBound = true;

  // ---------- utils ----------
  function norm(s){ return String(s||'').toLowerCase().trim(); }
  function toNum(s){
    if (s == null) return NaN;
    var n = String(s).replace(/[^\d.,-]/g,'').replace(',', '.');
    var f = parseFloat(n);
    return isNaN(f) ? NaN : f;
  }
  function show(el, yes){ if (el) el.style.display = yes ? '' : 'none'; }

  // intenta normalizar "plan"
  function planKeyFromText(txt){
    var s = norm(txt);
    if (s.indexOf('premium')  !== -1) return 'premium';
    if (s.indexOf('estándar') !== -1 || s.indexOf('estandar') !== -1 || s.indexOf('standard') !== -1) return 'estandar';
    if (s.indexOf('básico')   !== -1 || s.indexOf('basico')   !== -1 || s.indexOf('individual') !== -1) return 'basico';
    return s || '';
  }

  // ---------- extractores desde filas ----------
  function correoFromParentRow(tr){
    // prioridad: data-correo
    var c = tr.getAttribute('data-correo');
    if (c) return norm(c);
    // fallback por clase común
    var td = tr.querySelector('.correo-cell,.cell-correo,[data-correo-cell]');
    if (td) return norm(td.textContent);
    // fallback por columnas: primera o segunda celda que tenga @
    var tds = tr.querySelectorAll('td');
    for (var i=0;i<tds.length;i++){
      var txt = norm(tds[i].textContent);
      if (txt.indexOf('@') !== -1) return txt;
    }
    // último recurso: texto total
    return norm(tr.textContent);
  }

  function planFromRow(tr){
    // prioridad: data-plan
    var v = tr.getAttribute('data-plan') || '';
    if (v) return planKeyFromText(v);
    // por celda con clase
    var td = tr.querySelector('.plan-cell-perfil,.plan-cell,[data-plan-cell]');
    if (td) return planKeyFromText(td.textContent);
    // por texto de celdas
    var tds = tr.querySelectorAll('td');
    for (var i=0;i<tds.length;i++){
      var k = planKeyFromText(tds[i].textContent);
      if (k) return k;
    }
    return '';
  }

  function estadoFromRow(tr){
    // prioridad data-estado
    var est = norm(tr.getAttribute('data-estado') || '');
    if (est) return est; // ej: activo, pausado, pendiente
    // buscar palabras en celdas
    var tds = tr.querySelectorAll('td');
    for (var i=0;i<tds.length;i++){
      var t = norm(tds[i].textContent);
      if (t === 'pendiente' || t === 'activo' || t === 'pausado' || t === 'inactivo' || t === 'ocupado' || t === 'libre') {
        return t;
      }
    }
    return '';
  }

  function perfilNameFromChild(tr){
    var v = tr.getAttribute('data-perfil') || '';
    if (v) return norm(v);
    var td = tr.querySelector('.perfil-cell,.cell-perfil,[data-perfil-cell]');
    if (td) return norm(td.textContent);
    // fallback: primera celda con algo de texto
    var tds = tr.querySelectorAll('td');
    for (var i=0;i<tds.length;i++){
      var txt = norm(tds[i].textContent);
      if (txt) return txt;
    }
    return '';
  }

  function precioFromChild(tr){
    var v = tr.getAttribute('data-precio');
    if (v != null) return toNum(v);
    var td = tr.querySelector('.precio-cell,.cell-precio,[data-precio-cell]');
    if (td) return toNum(td.textContent);
    // fallback: buscar número con decimales en las celdas
    var tds = tr.querySelectorAll('td');
    for (var i=0;i<tds.length;i++){
      var n = toNum(tds[i].textContent);
      if (!isNaN(n)) return n;
    }
    return NaN;
  }

  // ---------- agrupación padre + hijos ----------
  function buildGroups(tbody){
    var groups = [];
    var rows = Array.from(tbody.querySelectorAll('tr'));
    var curr = null;
    rows.forEach(function(tr){
      var isParent = tr.classList.contains('js-parent-row') || tr.getAttribute('data-parent') === '1';
      if (isParent || !curr) {
        curr = { parent: tr, children: [] };
        groups.push(curr);
      } else {
        curr.children.push(tr);
      }
    });
    return groups;
  }
  function setGroupHidden(group, hide){
    var all = [group.parent].concat(group.children);
    all.forEach(function(tr){ tr.classList.toggle('d-none', !!hide); });
  }

  function ensureIndexGroups(groups){
    var i = 0;
    groups.forEach(function(g){
      g.parent.dataset.idx = g.parent.dataset.idx || String(++i);
    });
  }
  function reappendGroups(tbody, groups){
    groups.forEach(function(g){
      tbody.appendChild(g.parent);
      g.children.forEach(function(ch){ tbody.appendChild(ch); });
    });
  }
  function restoreGroupOrder(groups){
    groups.sort(function(a,b){
      var ai = parseInt(a.parent.dataset.idx||'0',10);
      var bi = parseInt(b.parent.dataset.idx||'0',10);
      return ai - bi;
    });
  }

  // ---------- binding principal ----------
  function bindPerfiles(){
    var pane = document.getElementById('perfiles');
    if (!pane) return; // pestaña no presente

    var wrap   = pane.querySelector('.__pfFilter__[data-scope="perfiles"]');
    if (!wrap || wrap.dataset.bound === '1') return;
    wrap.dataset.bound = '1';

    // Controles del filtro
    var selMain   = wrap.querySelector('.pf-main');     // '', 'plan', 'estado'
    var selPlan   = wrap.querySelector('.pf-plan');     // premium/estandar/basico (o tus valores)
    var selEstado = wrap.querySelector('.pf-estado');   // activo/pausado/pendiente/libre/ocupado...
    var qInput    = wrap.querySelector('.pf-search');   // texto
    var btnClr    = wrap.querySelector('.pf-clear');    // botón limpiar

    var table = pane.querySelector('table');
    var tbody = table ? table.querySelector('tbody') : null;
    if (!tbody) return;

    var groups = buildGroups(tbody);
    ensureIndexGroups(groups);

    function toggleControls(){
      var main = selMain ? selMain.value : '';
      show(selPlan,   main === 'plan');
      show(selEstado, main === 'estado');
    }

    function apply(){
      var vMain   = selMain   ? selMain.value : '';
      var vPlan   = selPlan   ? norm(selPlan.value) : '';
      var vEstado = selEstado ? norm(selEstado.value) : '';
      var q       = qInput    ? norm(qInput.value) : '';

      // restaurar orden original (como cuentas_filters)
      restoreGroupOrder(groups);
      reappendGroups(tbody, groups);

      // mostrar todo inicialmente
      groups.forEach(function(g){ setGroupHidden(g, false); });

      // aplicar filtro principal sobre el PADRE
      groups.forEach(function(g){
        var hide = false;
        var parent = g.parent;

        switch (vMain) {
          case 'plan': {
            if (vPlan) {
              if (planFromRow(parent) !== vPlan) hide = true;
            }
            break;
          }
          case 'estado': {
            if (vEstado) {
              if (estadoFromRow(parent) !== vEstado) hide = true;
            }
            break;
          }
          default: break;
        }

        if (hide) setGroupHidden(g, true);
      });

      // búsqueda por correo (padre) o nombre de perfil (hijos)
      if (q) {
        groups.forEach(function(g){
          if (g.parent.classList.contains('d-none')) return;

          var pass = false;

          // correo del padre
          if (correoFromParentRow(g.parent).indexOf(q) !== -1) pass = true;

          // nombres de perfiles en hijos
          if (!pass) {
            for (var i=0;i<g.children.length;i++){
              if (perfilNameFromChild(g.children[i]).indexOf(q) !== -1) { pass = true; break; }
            }
          }

          if (!pass) setGroupHidden(g, true);
        });
      }
    }

    if (selMain)   selMain.addEventListener('change', function(){ toggleControls(); apply(); });
    if (selPlan)   selPlan.addEventListener('change', apply);
    if (selEstado) selEstado.addEventListener('change', apply);
    if (qInput)    qInput.addEventListener('input', apply);
    if (btnClr)    btnClr.addEventListener('click', function(){
      if (selMain)   selMain.value = '';
      if (selPlan) { selPlan.value = ''; selPlan.style.display = 'none'; }
      if (selEstado){ selEstado.value = ''; selEstado.style.display = 'none'; }
      if (qInput)    qInput.value = '';
      apply();
    });

    toggleControls();
    apply();
  }

  // init ahora y cuando se muestre la pestaña
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindPerfiles);
  } else {
    bindPerfiles();
  }
  try { document.addEventListener('shown.bs.tab', bindPerfiles, false); } catch (_) {}

})();
