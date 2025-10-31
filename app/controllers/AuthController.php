<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../models/UserModel.php';

$action = $_POST['action'] ?? null;
if (!$action) {
    set_flash('error', 'Acción inválida.');
    redirect('../../public/index.php');
}

if ($action === 'register') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if (!$email || !$password) {
        set_flash('warning', 'Completa email y password.');
        redirect('../../public/index.php');
    }
    if (UserModel::findByEmail($email)) {
        set_flash('error', 'El email ya está registrado.');
        redirect('../../public/index.php');
    }
    $user_id = UserModel::create($email, $password);
    $_SESSION['user_id'] = $user_id;
    $_SESSION['is_admin'] = true;
    set_flash('success', 'Registro exitoso. ¡Bienvenido!');
    redirect('../../public/dashboard.php');
}

if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if (!$email || !$password) {
        set_flash('warning', 'Completa email y password.');
        redirect('../../public/index.php');
    }
    $user = UserModel::findByEmail($email);
    if (!$user || $user['password_plain'] !== $password) {
        set_flash('error', 'Credenciales inválidas.');
        redirect('../../public/index.php');
    }
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['is_admin'] = true;
    set_flash('success', 'Ingreso exitoso.');
    redirect('../../public/dashboard.php');
}

set_flash('error', 'Acción no soportada.');
redirect('../../public/index.php');
?>
