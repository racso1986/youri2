<?php
// includes/navbar.php
if (defined('NAVBAR_RENDERED')) return; // evita que se pinte 2 veces
define('NAVBAR_RENDERED', true);

$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$active  = fn($file) => $current === $file ? 'active fw-semibold' : '';
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="dashboard.php">Streamings</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link <?= $active('dashboard.php') ?>" href="dashboard.php">Dashboard</a>
        </li>
        <?php if ($current === 'streaming.php'): ?>
          <li class="nav-item">
            <span class="nav-link disabled">/ Detalle</span>
          </li>
        <?php endif; ?>
      </ul>

      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link text-danger" href="logout.php">Salir</a></li>
      </ul>
    </div>
  </div>
</nav>
