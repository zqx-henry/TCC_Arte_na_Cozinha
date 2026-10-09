<?php
/**
 * Login do painel administrativo (RF09 / UC09)
 */
require_once __DIR__ . '/../includes/auth.php';

if (admin_logado()) {
    redirecionar('index.php');
}

$erro = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = (string) ($_POST['email'] ?? '');
    if (!csrf_valido($_POST['csrf'] ?? null)) {
        $erro = 'Sessão expirada. Tente novamente.';
    } else {
        $erro = fazer_login($email, (string) ($_POST['senha'] ?? '')) ?? '';
        if ($erro === '') {
            redirecionar('index.php');
        }
    }
}
header('X-Frame-Options: DENY');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="#C2185B">
  <title>Entrar | Painel da loja — Arte na Cozinha</title>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logo-icone.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@1,700&family=Poppins:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="../assets/css/style.css?v=1.1">
  <link rel="stylesheet" href="../assets/css/admin.css?v=1.1">
</head>
<body class="pagina-login">
  <main class="login">
    <div class="login-marca">
      <div class="logo-composto negativa">
        <img src="../assets/img/logo-icone-negativa.svg" alt="" width="72" height="72">
        <span><span class="marca-nome">Arte na Cozinha</span><span class="marca-sub">Confeitaria</span></span>
      </div>
      <p>Painel da loja</p>
    </div>

    <form class="login-caixa" method="post" novalidate>
      <h1>Entrar no painel</h1>
      <p class="dica" style="margin-top:4px">Acesso restrito aos responsáveis da Arte na Cozinha.</p>

      <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?= h($erro) ?></div><?php endif; ?>

      <?= csrf_campo() ?>
      <div class="campos">
        <div class="campo">
          <label for="email">E-mail</label>
          <input id="email" name="email" type="email" autocomplete="username" required value="<?= h($email) ?>" autofocus>
        </div>
        <div class="campo">
          <label for="senha">Senha</label>
          <input id="senha" name="senha" type="password" autocomplete="current-password" required>
        </div>
        <button class="botao botao-bloco botao-grande" type="submit">Entrar</button>
      </div>
      <p class="nota"><a href="../index.php">← Voltar para o site</a></p>
    </form>
  </main>
</body>
</html>
