<?php
// public/actions/perfil_create.php
declare(strict_types=1);

// Asegura rutas correctas desde /public
$controller = __DIR__ . '/../../app/controllers/PerfilController.php';

if (!is_file($controller)) {
  http_response_code(500);
  echo 'PerfilController.php no encontrado en: ' . htmlspecialchars($controller);
  exit;
}

// Carga el controlador. La mayoría de controladores de tu app leen $_POST['action']
// y despachan (create/update/delete). No hacemos nada más: lo incluimos y dejamos que haga su trabajo.
require $controller;
