<?php
/**
 * Layout do painel administrativo (Figura 11) — parte de cima.
 * Variáveis: $tituloPagina, $menuAtivo, $admin
 */
require_once __DIR__ . '/icones.php';

$menu = [
    'pedidos'       => ['index.php',         'pedidos',  'Pedidos'],
    'produtos'      => ['produtos.php',      'produtos', 'Produtos e preços'],
    'promocoes'     => ['promocoes.php',     'promocao', 'Promoções'],
    'configuracoes' => ['configuracoes.php', 'config',   'Configurações'],
];
$menuAtivo = $menuAtivo ?? '';
$novosPendentes = (int) db()->query("SELECT COUNT(*) FROM pedido WHERE status = 'recebido'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="#C2185B">
  <title><?= h($tituloPagina ?? 'Painel') ?> | Painel da loja — Arte na Cozinha</title>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logo-icone.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@1,700&family=Poppins:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="../assets/css/style.css?v=1.0">
  <link rel="stylesheet" href="../assets/css/admin.css?v=1.0">
</head>
<body class="painel" data-csrf="<?= csrf_token() ?>">

<aside class="lateral" id="lateral">
  <div class="lateral-marca">
    <img src="../assets/img/logo-icone-negativa.svg" alt="" width="52" height="52">
    <div>
      <span class="lateral-nome">Arte na Cozinha</span>
      <span class="lateral-sub">Painel da loja</span>
    </div>
  </div>

  <nav class="lateral-menu" aria-label="Menu do painel">
    <?php foreach ($menu as $chave => [$href, $ic, $rotulo]): ?>
      <a href="<?= $href ?>" class="<?= $menuAtivo === $chave ? 'ativo' : '' ?>">
        <?= icone($ic) ?> <span><?= h($rotulo) ?></span>
        <?php if ($chave === 'pedidos' && $novosPendentes > 0): ?>
          <span class="bolinha" id="bolinhaNovos"><?= $novosPendentes ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
    <a href="../index.php" target="_blank" rel="noopener"><?= icone('site') ?> <span>Ver site</span></a>
  </nav>

  <form class="lateral-sair" method="post" action="sair.php">
    <?= csrf_campo() ?>
    <div class="lateral-usuario">Olá, <?= h(explode(' ', $admin['nome'])[0]) ?></div>
    <button type="submit"><?= icone('sair') ?> Sair</button>
  </form>
</aside>
<div class="lateral-fundo" id="lateralFundo" hidden></div>

<div class="painel-conteudo">
  <header class="painel-topo-mobile">
    <button type="button" class="botao-icone" id="abrirMenu" aria-label="Abrir menu" aria-controls="lateral" aria-expanded="false"><?= icone('menu') ?></button>
    <span class="lateral-nome">Arte na Cozinha</span>
    <img src="../assets/img/logo-icone.svg" alt="" width="40" height="40">
  </header>

  <main class="painel-main">
    <?= exibir_flash() ?>
