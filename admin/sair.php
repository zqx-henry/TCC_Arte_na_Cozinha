<?php
require_once __DIR__ . '/../includes/auth.php';

if (csrf_valido($_POST['csrf'] ?? null)) {
    $_SESSION = [];
    session_regenerate_id(true);
}
redirecionar('login.php');
