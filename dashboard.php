<?php
// public/dashboard.php
session_start();
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

require_once __DIR__ . '/../config/db.php';
$pdo = get_pdo();

function row_json_attr(array $row): string {
  $json = json_encode($row, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
  return htmlspecialchars($json, ENT_QUOTES, 'UTF-8');
}

$streamings = $pdo->query("SELECT * FROM streamings ORDER BY created_at DESC, id DESC")
                  ->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Streamings</h3>
    <a href="iptv.php" class="btn btn-outline-primary">IPTV</a>
    <a href="cobros.php" class="btn btn-outline-warning">Cobros</a>

    <button type="button" class="btn btn-sm btn-success btn-add-streaming">
  Agregar Streaming
</button>
 

  </div>

  <?php if (!empty($_SESSION['flash_text'])): ?>
    <div id="flash"
         data-type="<?= htmlspecialchars($_SESSION['flash_type'] ?? 'success') ?>"
         data-text="<?= htmlspecialchars($_SESSION['flash_text']) ?>"></div>
    <?php unset($_SESSION['flash_text'], $_SESSION['flash_type']); ?>
  <?php endif; ?>

  <div class="row g-3">
    <?php foreach ($streamings as $s):
      // ====== LOGO: usar SIEMPRE ruta RELATIVA al directorio /public ======
      // En DB guarda sólo el filename (p.ej., logo_1234.jpg). Si viniera con "uploads/..." lo normalizamos.
      $filename = basename((string)($s['logo'] ?? ''));
      $logoRel  = $filename ? 'uploads/' . $filename : ''; // <-- sin "/" inicial, RELATIVO
      // =========================================

      // Datos para el modal de edición
      $row = [
        'id'     => (int)$s['id'],
        'nombre' => (string)$s['nombre'],
        'plan'   => (string)$s['plan'],
        'precio' => (string)$s['precio'],
        'logo'   => (string)$s['logo'],
      ];
      $json = row_json_attr($row);
    ?>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
      <div class="card h-100 shadow-sm">
        <div class="ratio ratio-16x9 bg-light">
          <?php if ($logoRel): ?>
            <img
              src="<?= htmlspecialchars($logoRel) ?>"
              class="img-fluid w-100 h-100 p-2"
              style="object-fit:contain"
              alt="logo"
              onerror="this.onerror=null; this.replaceWith(Object.assign(document.createElement('div'),{className:'d-flex align-items-center justify-content-center text-muted',innerText:'Sin logo'}));">
          <?php else: ?>
            <div class="d-flex align-items-center justify-content-center text-muted">Sin logo</div>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <h5 class="card-title mb-1"><?= htmlspecialchars($s['nombre']) ?></h5>
          <p class="card-text mb-2"><small class="text-muted"><?= htmlspecialchars($s['plan']) ?></small></p>
          <div class="fw-semibold mb-3">S/<?= number_format((float)$s['precio'], 2) ?></div>

          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-primary" href="streaming.php?id=<?= (int)$s['id'] ?>">Abrir</a>

            <?php
$row = [
  'id'     => (int)$s['id'],
  'nombre' => (string)$s['nombre'],
  'plan'   => (string)$s['plan'],
  'precio' => (string)$s['precio'],
  'logo'   => (string)$s['logo'],
];
$json = htmlspecialchars(json_encode($row, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
?>
<button type="button"
  class="btn btn-sm btn-primary btn-edit-streaming"
  data-row='<?= $json ?>'
  data-id="<?= (int)$s['id'] ?>"
  data-nombre="<?= htmlspecialchars($s['nombre'], ENT_QUOTES) ?>"
  data-plan="<?= htmlspecialchars($s['plan'], ENT_QUOTES) ?>"
  data-precio="<?= htmlspecialchars($s['precio'], ENT_QUOTES) ?>"
  data-logo="<?= htmlspecialchars($s['logo'], ENT_QUOTES) ?>">
  Editar
</button>


            <form action="../app/controllers/StreamingController.php" method="post"
                  class="d-inline form-delete-streaming">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger">Borrar</button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($streamings)): ?>
      <div class="col-12">
        <div class="alert alert-info">Aún no hay streamings. Crea el primero con “Agregar Streaming”.</div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
// Debe existir #streamingModal en includes/modals.php
include __DIR__ . '/../includes/modals.php';
include __DIR__ . '/../includes/footer.php';
