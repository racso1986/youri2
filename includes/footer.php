</main>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


<script src="assets/js/app.js?v=3089"></script>
<script src="assets/js/cuentas_override.js?v=29.0.0"></script>
<script src="assets/js/stock_override.js?v=168.0.0"></script>
<script src="assets/js/ajax_guard.js?v=26.0.0"></script>
<script src="assets/js/stock_pausa_filters.js?v=8.0.0"></script>
<!--<script src="assets/js/perfiles_cuentas_filters.js?v=17.0.0"></script>-->
<script src="assets/js/cuentas_filters.js?v=9.0.0"></script>
<!--<script src="/public/assets/js/perfiles-precio-hijos.js?v=30"></script>-->
<!-- En tu footer, carga después de cuentas_filters.js -->
<!--<script src="assets/js/perfiles_filters.js?v=15"></script>-->
<script src="assets/js/perfiles_filters.fixed.js"></script>
<script src="assets/js/app_perfiles_modal_fix.js"></script>
<script src="assets/js/app_familiar_modal_fix.js?v=6"></script>








<script>
<?php
  require_once __DIR__ . '/../config/db.php';
  if ($flash = get_flash()) {
      $type = htmlspecialchars($flash['type']);
      $msg  = htmlspecialchars($flash['message']);
      echo "Swal.fire({icon: '{$type}', text: '{$msg}'});";
  }
?>
</script>
</body>
</html>




