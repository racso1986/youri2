


<?php // includes/header.php
if (defined('HEADER_RENDERED')) return;
define('HEADER_RENDERED', true);

$pageTitle = $pageTitle ?? 'Streamings';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Solo gris para filas padre; hijas en blanco -->


<style>
  /* Perfiles: filas hijas sin bordes en col 1 y 2 */
  #perfiles table.table tbody tr:not(.js-parent-row) > td:nth-child(1) {
    border-top: none !important;
    border-bottom: none !important;
    border-right: none !important; /* conserva borde izquierdo exterior */
  }
  #perfiles table.table tbody tr:not(.js-parent-row) > td:nth-child(2) {
    border-top: none !important;
    border-bottom: none !important;
    border-left: none !important;  /* se fusiona con col 1 */
    border-right: none !important; /* el límite con col 3 lo dibuja su border-left */
  }
</style>


</style>

  <link rel="stylesheet" href="assets/css/app.css">

  <style>
    /* Bordes negros para la tabla y todas sus celdas */
    .table-bordered,
    .table-bordered>:not(caption)>*,
    .table-bordered>:not(caption)>*>* {
      border-color: #000 !important;
    }
  </style>
  
  <style>
  /* Asegura collapse (revertimos el cambio previo) */
  #perfiles table.table { border-collapse: collapse !important; }

  /* Quita bordes SOLO en hijas: col 1 y 2 */
  #perfiles table.table tbody tr:not(.js-parent-row) > td:nth-child(1) {
    border-top-color: transparent !important;
    border-bottom-color: transparent !important;
    border-right-color: transparent !important; /* mantenemos el borde izquierdo exterior */
  }
  #perfiles table.table tbody tr:not(.js-parent-row) > td:nth-child(2) {
    border-top-color: transparent !important;
    border-bottom-color: transparent !important;
    border-left-color: transparent !important;
    border-right-color: transparent !important;
  }

  /* Une visualmente padre con su primer hijo en col 1 y 2 */
  #perfiles table.table tbody tr.js-parent-row > td:nth-child(1),
  #perfiles table.table tbody tr.js-parent-row > td:nth-child(2) {
    border-bottom-color: transparent !important;
  }
</style>

<style>
  /* Tabla Perfiles: mantener collapse */
  #perfiles table.table { border-collapse: collapse !important; }

  /* Hijas: sin línea superior y sin bordes verticales en col 1 y 2 */
  #perfiles table.table tbody tr:not(.js-parent-row) > td:nth-child(1),
  #perfiles table.table tbody tr:not(.js-parent-row) > td:nth-child(2) {
    border-top-style: hidden !important;   /* elimina la línea entre filas */
    border-left-color: transparent !important;
    border-right-color: transparent !important;
  }
</style>

<style>
  /* Que el enlace se vea como texto normal (sin cambiar diseño) */
  #perfiles .js-edit-plan {
    text-decoration: none !important;
    color: inherit !important;
  }
  #perfiles .js-edit-plan:focus { outline: none; box-shadow: none; }
</style>

<style>
  /* El enlace debe verse como texto normal */
  #perfiles .js-edit-plan { text-decoration: none !important; color: inherit !important; }
  #perfiles .js-edit-plan:focus { outline: none; box-shadow: none; }
</style>
<style>
  /* Perfiles: estilo de íconos en columna Whatsapp */
  #perfiles .wa-tg a { display:inline-block; margin-right:.5rem; line-height:1; }
  #perfiles .wa-tg svg { width:18px; height:18px; vertical-align:middle; }
</style>

<style>
  .table-pager .pagination { margin-top:.5rem; margin-bottom:0; }
  .table-pager .page-link { cursor:pointer; }
</style>
<style>
  /* Tablas de las pestañas en modo compacto */
  #perfiles table.table,
  #cuentas  table.table,
  #stock    table.table,
  #pausa    table.table,
  .tab-pane table.table {
    font-size: .875rem;
  }
  #perfiles table.table > :not(caption) > * > *,
  #cuentas  table.table > :not(caption) > * > *,
  #stock    table.table > :not(caption) > * > *,
  #pausa    table.table > :not(caption) > * > *,
  .tab-pane table.table > :not(caption) > * > * {
    padding: .15rem .35rem !important; /* menos alto */
    line-height: 1.1;
  }
  /* Controles dentro de celdas (si los hubiera) también compactos */
  #perfiles table.table .btn,
  #cuentas  table.table .btn,
  #stock    table.table .btn,
  #pausa    table.table .btn,
  .tab-pane table.table .btn {
    padding: .125rem .35rem;
    font-size: .80rem;
    line-height: 1.1;
  }
  #perfiles table.table .form-control,
  #perfiles table.table .form-select,
  #cuentas  table.table .form-control,
  #cuentas  table.table .form-select,
  #stock    table.table .form-control,
  #stock    table.table .form-select,
  #pausa    table.table .form-control,
  #pausa    table.table .form-select,
  .tab-pane table.table .form-control,
  .tab-pane table.table .form-select {
    padding: .2rem .35rem;
    height: calc(1.1em + .4rem + 2px);
    font-size: .85rem;
    line-height: 1.1;
  }
</style>
<style>
  /* Cuentas: todas las filas como 'padre' (gris) y sin cursor de botón */
  #cuentas .table > tbody > tr.js-parent-row > * { background-color: #f8f9fa !important; }
  #cuentas .table > tbody > tr.js-parent-row { cursor: default; }
</style>
<style>
  /* Enlace de plan como texto (Cuentas) */
  #cuentas .js-edit-plan { text-decoration: none !important; color: inherit !important; }
  #cuentas .js-edit-plan:focus { outline: none; box-shadow: none; }

  /* Íconos WA/TG (Cuentas) */
  #cuentas .wa-tg a { display:inline-block; margin-right:.5rem; line-height:1; }
  #cuentas .wa-tg svg, #cuentas .wa-tg img { width:18px; height:18px; vertical-align:middle; }
 </style>
<style>
.cobro-cell { min-width: 60px; }
.cobro-cell:hover { background: #fff6d6; }
#tabla-cobros td.cobro-has { font-weight: 600; }
#tabla-cobros td.bg-warning { background-color: #fff3cd !important; }
#tabla-cobros td.bg-info    { background-color: #cff4fc !important; }
#tabla-cobros td.bg-danger  { background-color: #f8d7da !important; }
#tabla-cobros td.bg-success { background-color: #d1e7dd !important; }


/* Cobros: colores solicitados (fondos suaves y textos del mismo color) */
#tabla-cobros td.cobro-has { font-weight: 600; }

#tabla-cobros td.cobro-danger  { background-color: #fde2e7 !important; color: #dc3545 !important; }
#tabla-cobros td.cobro-warning { background-color: #fff3cd !important; color: #b68900 !important; }
#tabla-cobros td.cobro-info    { background-color: #e3f2fd !important; color: #0d6efd !important; }
#tabla-cobros td.cobro-success { background-color: #d1e7dd !important; color: #198754 !important; }

/* Perfiles: solo padres con hijos -> borde inferior negro en Plan y Correo */
#perfiles table.table tbody tr.js-parent-row.has-children > td:nth-child(1),
#perfiles table.table tbody tr.js-parent-row.has-children > td:nth-child(2),
#perfiles table.table tbody tr.js-parent-row.has-children > td.plan-cell-perfil,
#perfiles table.table tbody tr.js-parent-row.has-children > td.correo-cell {
  border-bottom: 1px solid #000 !important;
  box-shadow: inset 0 -1px 0 #000 !important;
  background-clip: padding-box;
}

#modalCambiarPlanStockPausa #spp_plan {
  display: block !important;
  visibility: visible !important;
  opacity: 1 !important;
}


/* Perfiles: borde izquierdo negro en filas hijas */
.perfil-child-row > td:first-child {
  border-left: 2px solid #000 !important;
}



/* Perfiles: borde izquierdo negro para filas hijas */
table tbody tr.perfil-child-row > td:first-child,
table tbody tr.perfil-child-row > th:first-child {
  border-left: 3px solid #000 !important;
}















/* Fallback por si alguna fila hija no trae la clase plan-cell-perfil (seguridad) */
table tbody tr.perfil-child-row > td:first-child {
  /*border-left: 1px solid #000 !important;*/
  position: relative;
}
table tbody tr.perfil-child-row > td:first-child::before {
  content: "";
  position: absolute;
  left: 0;
  top: -1px;
  bottom: -1px;
  padding-left: -5px !important;
  width: 0;
  border-left: 1px solid #000;
  pointer-events: none;
}












/* Perfiles: “borde izquierdo” continuo en filas hijas, SOLO en la celda Plan */
table tbody tr.perfil-child-row td.plan-cell-perfil {
  position: relative;
  border-left: 1px solid #000 !important;  
   left: -1px;
}

/* Rail absoluto que rellena micro cortes y garantiza continuidad */
table tbody tr.perfil-child-row td.plan-cell-perfil .perfil-left-rail {
 position: absolute;
  left: -5px;
  top: -1px;
  bottom: -1px;
  width: 0;
  border-left: 1px solid #000;
  pointer-events: none;
  z-index: 2;
}

/* Fallback (por si una fila hija no trae plan-cell-perfil por alguna razón) */
table tbody tr.perfil-child-row > td:first-child {
  position: relative;
  border-left: 1px solid #000 !important;
  left: -1px;
}
table tbody tr.perfil-child-row > td:first-child .perfil-left-rail {
  position: absolute;
  left: -5px;
  top: -1px;
  bottom: -1px;
  width: 0;
  border-left: 1px solid #000;
  pointer-events: none;
  z-index: 2;
}




























/* Colores de fila (suaves) */
tr.row-color-rojo  > td, tr.row-color-rojo  > th  { background-color: #ffe5e5 !important; }
tr.row-color-azul  > td, tr.row-color-azul  > th  { background-color: #e7f1ff !important; }
tr.row-color-verde > td, tr.row-color-verde > th  { background-color: #ecfbec !important; }
tr.row-color-blanco> td, tr.row-color-blanco> th  { background-color: #ffffff !important; }

/* Mantén el borde izquierdo negro en filas hijas (si aplica) */
tr.perfil-child-row td.plan-cell-perfil {
  position: relative;
  border-left: 2px solid #000 !important;
}
tr.perfil-child-row td.plan-cell-perfil::before {
  content: "";
  position: absolute;
  left: 0; top: -1px; bottom: -1px; width: 0;
  border-left: 2px solid #000;
  pointer-events: none;
}
















/* Colores de fila */
tr.row-color-rojo  > td, tr.row-color-rojo  > th  { background-color: #ffe5e5 !important; }
tr.row-color-azul  > td, tr.row-color-azul  > th  { background-color: #e7f1ff !important; }
tr.row-color-verde > td, tr.row-color-verde > th  { background-color: #ecfbec !important; }
tr.row-color-blanco> td, tr.row-color-blanco> th  { background-color: #ffffff !important; }




















/* Colores de fila (suaves y legibles) */
tr.row-color-rojo  > td, tr.row-color-rojo  > th  { background-color: #ffe5e5 !important; }
tr.row-color-azul  > td, tr.row-color-azul  > th  { background-color: #e7f1ff !important; }
tr.row-color-verde > td, tr.row-color-verde > th  { background-color: #ecfbec !important; }
tr.row-color-blanco> td, tr.row-color-blanco> th  { background-color: #ffffff !important; }

/* Mantén tu borde izquierdo continuo en filas hijas (si aplica en Perfiles) */
tr.perfil-child-row td.plan-cell-perfil {
  position: relative;
  border-left: 2px solid #000 !important;
}
tr.perfil-child-row td.plan-cell-perfil::before {
  content: "";
  position: absolute;
  left: 0; top: -1px; bottom: -1px; width: 0;
  border-left: 2px solid #000;
  pointer-events: none;
}


















/* Colores de fila (Perfiles y Cuenta completa) */
tr.row-color-rojo  > td, tr.row-color-rojo  > th  { background-color: #ffe5e5 !important; }
tr.row-color-azul  > td, tr.row-color-azul  > th  { background-color: #e7f1ff !important; }
tr.row-color-verde > td, tr.row-color-verde > th  { background-color: #ecfbec !important; }
tr.row-color-blanco> td, tr.row-color-blanco> th  { background-color: #ffffff !important; }























/* Colores de fila (Perfiles y Cuenta completa) */
tr.row-color-rojo  > td, tr.row-color-rojo  > th  { background-color: #ffe5e5 !important; }
tr.row-color-azul  > td, tr.row-color-azul  > th  { background-color: #e7f1ff !important; }
tr.row-color-verde > td, tr.row-color-verde > th  { background-color: #ecfbec !important; }
tr.row-color-blanco> td, tr.row-color-blanco> th  { background-color: #ffffff !important; }





















/* Colores de fila (Perfiles y Cuenta completa) */
tr.row-color-rojo  > td, tr.row-color-rojo  > th  { background-color: #ffe5e5 !important; }
tr.row-color-azul  > td, tr.row-color-azul  > th  { background-color: #e7f1ff !important; }
tr.row-color-verde > td, tr.row-color-verde > th  { background-color: #ecfbec !important; }
tr.row-color-blanco> td, tr.row-color-blanco> th  { background-color: #ffffff !important; }













/* Colores de fila (Perfiles y Cuenta completa) */
tr.row-color-rojo  > td, tr.row-color-rojo  > th  { background-color: #ffe5e5 !important; }
tr.row-color-azul  > td, tr.row-color-azul  > th  { background-color: #e7f1ff !important; }
tr.row-color-verde > td, tr.row-color-verde > th  { background-color: #ecfbec !important; }
tr.row-color-blanco> td, tr.row-color-blanco> th  { background-color: #ffffff !important; }















/* Colores de fila (Perfiles y Cuenta completa) */
tr.row-color-rojo  > td, tr.row-color-rojo  > th  { background-color: #ffe5e5 !important; }
tr.row-color-azul  > td, tr.row-color-azul  > th  { background-color: #e7f1ff !important; }
tr.row-color-verde > td, tr.row-color-verde > th  { background-color: #ecfbec !important; }
tr.row-color-blanco> td, tr.row-color-blanco> th  { background-color: #ffffff !important; }
















tr.row-color-rojo  > td, tr.row-color-rojo  > th  { background:#ffe5e5 !important; }
tr.row-color-azul  > td, tr.row-color-azul  > th  { background:#e7f1ff !important; }
tr.row-color-verde > td, tr.row-color-verde > th  { background:#ecfbec !important; }
tr.row-color-blanco> td, tr.row-color-blanco> th  { background:#ffffff !important; }







/* === Perfiles: filas PADRE con texto transparente desde "Perfil" (col 7) hasta la penúltima === */
/* No afecta a las filas HIJAS porque no tienen .js-parent-row */
#perfiles table.table > tbody > tr.js-parent-row > td:nth-child(n+7):not(:last-child) {
  color: transparent !important;      /* “texto transparente” */
  text-shadow: none !important;       /* por si algún estilo le da sombra */
  /* Nota: links/SVG que usan currentColor también quedarán “transparentes” */
}



/* === Perfiles: filas PADRE con texto transparente desde "Perfil" (col 7) hasta la penúltima === */
/* No afecta a las HIJAS porque no tienen .js-parent-row */
#perfiles table.table > tbody > tr.js-parent-row > td:nth-child(n+7):not(:last-child),
#perfiles table.table > tbody > tr.js-parent-row > td:nth-child(n+7):not(:last-child) *,
#perfiles table.table > tbody > tr.js-parent-row > td:nth-child(n+7):not(:last-child) svg,
#perfiles table.table > tbody > tr.js-parent-row > td:nth-child(n+7):not(:last-child) svg * {
  color: transparent !important;      /* texto y enlaces */
  fill: transparent !important;       /* iconos SVG */
  stroke: transparent !important;     /* contornos SVG */
  text-shadow: none !important;
}

/* Estado: evitar que el fondo del badge quede visible */
#perfiles table.table > tbody > tr.js-parent-row > td:nth-child(n+7):not(:last-child) .badge {
  background-color: transparent !important;
  border-color: transparent !important;
}




  /* Evita que cualquier regla previa lo oculte */
  #modalAgregarIptv #iptv-whatsapp-ui {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    height: auto !important;
  }



</style>




<style>
  .table tr.tr-hoy    { --bs-table-bg: red; }
  .table tr.tr-manana { --bs-table-bg: orange; }
  .table tr.tr-pasado { --bs-table-bg: yellow; }
  
  
  
  
/* Cuentas: blanco en odd rows SOLO si NO hay color lógico */
#cuentas .table-striped > tbody > tr:nth-of-type(odd):not(.row-color-rojo):not(.row-color-azul):not(.row-color-verde):not(.row-color-blanco) > * {
  --bs-table-color-type: var(--bs-table-striped-color);
  --bs-table-bg-type: #fff;
}



/* Cuentas: pintar filas con color lógico (gana a cualquier blanco por variables) */
#cuentas .table tbody tr.row-color-rojo > *,
#cuentas .table tbody tr[data-color="rojo"] > * {
  --bs-table-bg-type: #ffd6d6; /* por si usas vars de BS 5.3 */
  --bs-table-bg: #ffd6d6;
  --bs-table-striped-bg: #ffd6d6;
  --bs-table-accent-bg: #ffd6d6;
  background-color: #ffd6d6 !important;
}

#cuentas .table tbody tr.row-color-azul > *,
#cuentas .table tbody tr[data-color="azul"] > * {
  --bs-table-bg-type: #d6e7ff;
  --bs-table-bg: #d6e7ff;
  --bs-table-striped-bg: #d6e7ff;
  --bs-table-accent-bg: #d6e7ff;
  background-color: #d6e7ff !important;
}

#cuentas .table tbody tr.row-color-verde > *,
#cuentas .table tbody tr[data-color="verde"] > * {
  --bs-table-bg-type: #dff5d6;
  --bs-table-bg: #dff5d6;
  --bs-table-striped-bg: #dff5d6;
  --bs-table-accent-bg: #dff5d6;
  background-color: #dff5d6 !important;
}

#cuentas .table tbody tr.row-color-blanco > *,
#cuentas .table tbody tr[data-color="blanco"] > * {
  --bs-table-bg-type: #ffffff;
  --bs-table-bg: #ffffff;
  --bs-table-striped-bg: #ffffff;
  --bs-table-accent-bg: #ffffff;
  background-color: #ffffff !important;
}

  
  #cuentas .table > tbody > tr.js-parent-row > * {
    background-color: #fff !important;
}
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
/* STOCK/PAUSA: blanco por defecto solo si NO hay color lógico */
#stock .table tbody tr:not(.row-color-rojo):not(.row-color-azul):not(.row-color-verde):not(.row-color-blanco) > *,
#pausa .table tbody tr:not(.row-color-rojo):not(.row-color-azul):not(.row-color-verde):not(.row-color-blanco) > * {
  background-color: #fff !important;
  --bs-table-bg: #fff;
  --bs-table-striped-bg: #fff;
  --bs-table-accent-bg: #fff;
}

/* STOCK: pintar fuerte cuando hay color (clase o data-color) */
#stock .table tbody tr.row-color-rojo > *,
#stock .table tbody tr[data-color="rojo"] > * {
  background-color: #ffd6d6 !important;
  --bs-table-bg: #ffd6d6; --bs-table-striped-bg: #ffd6d6; --bs-table-accent-bg: #ffd6d6;
}
#stock .table tbody tr.row-color-azul > *,
#stock .table tbody tr[data-color="azul"] > * {
  background-color: #d6e7ff !important;
  --bs-table-bg: #d6e7ff; --bs-table-striped-bg: #d6e7ff; --bs-table-accent-bg: #d6e7ff;
}
#stock .table tbody tr.row-color-verde > *,
#stock .table tbody tr[data-color="verde"] > * {
  background-color: #dff5d6 !important;
  --bs-table-bg: #dff5d6; --bs-table-striped-bg: #dff5d6; --bs-table-accent-bg: #dff5d6;
}
#stock .table tbody tr.row-color-blanco > *,
#stock .table tbody tr[data-color="blanco"] > * {
  background-color: #ffffff !important;
  --bs-table-bg: #ffffff; --bs-table-striped-bg: #ffffff; --bs-table-accent-bg: #ffffff;
}

/* PAUSA: igual que STOCK */
#pausa .table tbody tr.row-color-rojo > *,
#pausa .table tbody tr[data-color="rojo"] > * {
  background-color: #ffd6d6 !important;
  --bs-table-bg: #ffd6d6; --bs-table-striped-bg: #ffd6d6; --bs-table-accent-bg: #ffd6d6;
}
#pausa .table tbody tr.row-color-azul > *,
#pausa .table tbody tr[data-color="azul"] > * {
  background-color: #d6e7ff !important;
  --bs-table-bg: #d6e7ff; --bs-table-striped-bg: #d6e7ff; --bs-table-accent-bg: #d6e7ff;
}
#pausa .table tbody tr.row-color-verde > *,
#pausa .table tbody tr[data-color="verde"] > * {
  background-color: #dff5d6 !important;
  --bs-table-bg: #dff5d6; --bs-table-striped-bg: #dff5d6; --bs-table-accent-bg: #dff5d6;
}
#pausa .table tbody tr.row-color-blanco > *,
#pausa .table tbody tr[data-color="blanco"] > * {
  background-color: #ffffff !important;
  --bs-table-bg: #ffffff; --bs-table-striped-bg: #ffffff; --bs-table-accent-bg: #ffffff;
}








.__filtersWrap__ {
    display: none !important;
} 




/* PERFILES: los hijos nunca muestran color */
#perfiles tbody tr:not(.js-parent-row).row-color-rojo,
#perfiles tbody tr:not(.js-parent-row).row-color-azul,
#perfiles tbody tr:not(.js-parent-row).row-color-verde,
#perfiles tbody tr:not(.js-parent-row).row-color-blanco {
  background-color: transparent !important;
}









 /*Compactar tabla IPTV */
.table-compact>:not(caption)>*>*{padding:0rem 0rem}
.table-compact th{font-size:.82rem;font-weight:600}
.table-compact td{font-size:.82rem}
.table-compact .badge{font-size:.72rem}
.table-compact .whatsapp svg{width:14px;height:14px}
.table-compact .btn-sm{padding:0rem 0rem; line-height:1.1}


.table>:not(caption)>*>*{
    padding: .1rem .3rem !important;
}










</style>




<style>
/* Señala que estamos en IPTV (si no lo pusiste ya, añade data-page="iptv" al <body>) */
body[data-page="iptv"] {}

/* Oculta solo las barras globales heredadas (streamings), excepto si SON tu filtro local */
body[data-page="iptv"] #filterBar:not(.iptv-local-filter),
body[data-page="iptv"] .filters:not(.iptv-local-filter),
body[data-page="iptv"] .toolbar-filtros:not(.iptv-local-filter),
body[data-page="iptv"] .filters-bar:not(.iptv-local-filter),
body[data-page="iptv"] .js-filters-root:not(.iptv-local-filter) {
  display: none !important;
}

/* Tus filtros locales siempre visibles… */
body[data-page="iptv"] .iptv-local-filter {
  display: block !important;
}

/* …pero SOLO cuando su pestaña está activa */
.tab-pane:not(.active) .iptv-local-filter {
  display: none !important;
}




</style>



<style>
  tr[data-sep] > td {
    background-color: #f8f9fa;
    border-color: #dee2e6;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: capitalize;
  }
</style>




<style>
  tr[data-sep] > td {
    background-color: #f8f9fa;
    border-color: #dee2e6;
    font-weight: 600;
    font-size: 0.92rem;
  }
</style>

<style>
  #perfilModal #modalChildPrecio_display { display:none !important; }
</style>




</head>
<body class="bg-light">
