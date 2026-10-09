<?php
$enderecoLoja = config_loja('endereco_loja');
$cidadeLoja   = config_loja('cidade');
$latLoja      = (float) config_loja('loja_lat');
$lngLoja      = (float) config_loja('loja_lng');
$linkMapa     = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode("$enderecoLoja, $cidadeLoja");
?>
</main>

<footer class="rodape">
  <div class="container">
    <div class="rodape-grade">
      <div>
        <div class="logo-composto negativa">
          <img src="assets/img/logo-icone-negativa.svg" alt="" width="64" height="64">
          <span><span class="marca-nome">Arte na Cozinha</span><span class="marca-sub">Confeitaria</span></span>
        </div>
        <p style="margin-top:12px">Doces feitos com carinho, entregues direto da nossa cozinha para você — sem aplicativo e sem cadastro.</p>
      </div>
      <div>
        <h3>Onde estamos</h3>
        <address class="rodape-endereco">
          <?= h($enderecoLoja) ?><br>
          <?= h($cidadeLoja) ?>
        </address>
        <ul>
          <li><a href="<?= h($linkMapa) ?>" target="_blank" rel="noopener">📍 Como chegar</a></li>
          <li>Frete: <?= dinheiro(config_loja('valor_km', '3.30')) ?> por km, calculado pela distância</li>
          <li>Entregamos até <?= h(config_loja('raio_maximo_km', '25')) ?> km da confeitaria</li>
        </ul>
      </div>
      <div>
        <h3>Atendimento</h3>
        <ul>
          <li><?= h(config_loja('horario')) ?></li>
          <li><a href="<?= h(link_whatsapp(config_loja('whatsapp_loja'), 'Olá! Gostaria de falar com a Arte na Cozinha.')) ?>" target="_blank" rel="noopener">WhatsApp <?= h(formatar_telefone(config_loja('whatsapp_loja'))) ?></a></li>
          <li><a href="acompanhar.php">Acompanhar pedido</a></li>
          <li><a href="privacidade.php">Aviso de privacidade (LGPD)</a></li>
          <li><a href="admin/">Área da loja</a></li>
        </ul>
      </div>
    </div>

    <?php if ($latLoja && $lngLoja): ?>
    <div class="rodape-mapa">
      <iframe title="Mapa com a localização da Arte na Cozinha" loading="lazy" referrerpolicy="no-referrer"
        src="https://www.openstreetmap.org/export/embed.html?bbox=<?= $lngLoja - 0.008 ?>%2C<?= $latLoja - 0.004 ?>%2C<?= $lngLoja + 0.008 ?>%2C<?= $latLoja + 0.004 ?>&amp;layer=mapnik&amp;marker=<?= $latLoja ?>%2C<?= $lngLoja ?>"></iframe>
    </div>
    <?php endif; ?>

    <p class="rodape-base">
      &copy; <?= date('Y') ?> Arte na Cozinha · <?= h($enderecoLoja) ?>, <?= h($cidadeLoja) ?><br>
      Projeto de TCC — Curso Técnico em Desenvolvimento de Sistemas, Etec Fernando Prestes (Sorocaba).
      Produtos, nomes e preços ilustrativos. Mapa © colaboradores do OpenStreetMap.
    </p>
  </div>
</footer>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script>window.AGORA_SERVIDOR = <?= time() ?>;</script>
<script src="assets/js/carrinho.js?v=1.1"></script>
<script src="assets/js/contagem.js?v=1.1"></script>
<?php foreach ($scriptsPagina ?? [] as $script): ?>
<script src="<?= h($script) ?>?v=1.1"></script>
<?php endforeach; ?>
</body>
</html>
