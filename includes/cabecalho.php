<?php
/**
 * Cabeçalho do site do cliente.
 * Variáveis opcionais: $tituloPagina, $paginaAtual, $descricaoPagina
 */
require_once __DIR__ . '/icones.php';

$tituloPagina    = $tituloPagina ?? 'Arte na Cozinha';
$paginaAtual     = $paginaAtual ?? '';
$descricaoPagina = $descricaoPagina ?? 'Arte na Cozinha — confeitaria artesanal com delivery próprio. Peça bolos, doces, tortas e bebidas direto com a loja, sem cadastro.';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="description" content="<?= h($descricaoPagina) ?>">
  <meta name="theme-color" content="#C2185B">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="Arte na Cozinha">
  <meta property="og:title" content="<?= h($tituloPagina) ?>">
  <meta property="og:description" content="<?= h($descricaoPagina) ?>">
  <meta property="og:image" content="assets/img/logo.svg">
  <title><?= h($tituloPagina) ?></title>

  <link rel="icon" type="image/svg+xml" href="assets/img/logo-icone.svg">
  <link rel="apple-touch-icon" href="assets/img/logo-icone.svg">
  <link rel="manifest" href="manifest.webmanifest">

  <!-- Fontes do sistema (Quadro 20 / Apêndice A) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@1,700&family=Poppins:wght@400;500;600;700&display=swap">

  <link rel="stylesheet" href="assets/css/style.css?v=1.2">

  <!-- Dados estruturados: ajudam o Google a mostrar endereço e horário da confeitaria -->
  <script type="application/ld+json"><?= json_encode([
      '@context'  => 'https://schema.org',
      '@type'     => 'Bakery',
      'name'      => 'Arte na Cozinha',
      'telephone' => '+' . so_digitos(config_loja('whatsapp_loja')),
      'address'   => [
          '@type'           => 'PostalAddress',
          'streetAddress'   => config_loja('endereco_loja'),
          'addressLocality' => 'Sorocaba',
          'addressRegion'   => 'SP',
          'addressCountry'  => 'BR',
      ],
      'geo' => ['@type' => 'GeoCoordinates', 'latitude' => (float) config_loja('loja_lat'), 'longitude' => (float) config_loja('loja_lng')],
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
</head>
<body data-pagina="<?= h($paginaAtual) ?>">

<a class="sr-only" href="#conteudo">Pular para o conteúdo</a>

<header class="topo" id="topo">
  <div class="container topo-conteudo">
    <a href="index.php" class="marca" aria-label="Arte na Cozinha — página inicial">
      <img src="assets/img/logo-icone.svg" alt="" width="48" height="48">
      <span class="marca-nome">Arte na Cozinha</span>
    </a>

    <div class="topo-acoes">
      <nav class="menu-desktop" aria-label="Menu principal">
        <a href="index.php" class="<?= $paginaAtual === 'cardapio' ? 'ativo' : '' ?>">Cardápio</a>
        <a href="acompanhar.php" class="<?= $paginaAtual === 'acompanhar' ? 'ativo' : '' ?>">Acompanhar pedido</a>
      </nav>
      <?php $clienteTopo = cliente_logado(); ?>
      <a href="<?= $clienteTopo ? 'conta.php' : 'entrar.php' ?>" class="botao-conta <?= $paginaAtual === 'conta' ? 'ativo' : '' ?>"
         aria-label="<?= $clienteTopo ? 'Minha conta' : 'Entrar' ?>">
        <?= icone('usuario') ?>
        <span class="botao-conta-texto"><?= $clienteTopo ? 'Olá, ' . h(explode(' ', $clienteTopo['nome'])[0]) : 'Entrar' ?></span>
      </a>
      <a href="carrinho.php" class="botao-icone botao-carrinho" aria-label="Abrir carrinho">
        <?= icone('carrinho') ?>
        <span class="contador" id="contadorCarrinho" hidden>0</span>
      </a>
    </div>
  </div>
</header>

<main id="conteudo">
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="container" style="padding-top:12px"><?= exibir_flash() ?></div>
<?php endif; ?>
