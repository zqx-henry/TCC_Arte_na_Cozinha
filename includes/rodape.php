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
        <h3>Atendimento</h3>
        <ul>
          <li><?= h(config_loja('horario')) ?></li>
          <li><?= h(config_loja('cidade')) ?></li>
          <li><a href="<?= h(link_whatsapp(config_loja('whatsapp_loja'), 'Olá! Gostaria de falar com a Arte na Cozinha.')) ?>" target="_blank" rel="noopener">WhatsApp <?= h(formatar_telefone(config_loja('whatsapp_loja'))) ?></a></li>
        </ul>
      </div>
      <div>
        <h3>Links</h3>
        <ul>
          <li><a href="index.php">Cardápio</a></li>
          <li><a href="acompanhar.php">Acompanhar pedido</a></li>
          <li><a href="privacidade.php">Aviso de privacidade (LGPD)</a></li>
          <li><a href="admin/">Área da loja</a></li>
        </ul>
      </div>
    </div>
    <p class="rodape-base">
      &copy; <?= date('Y') ?> Arte na Cozinha · Projeto de TCC — Curso Técnico em Desenvolvimento de Sistemas,
      Etec Fernando Prestes (Sorocaba). Produtos, nomes e preços ilustrativos.
    </p>
  </div>
</footer>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script src="assets/js/carrinho.js?v=1.0"></script>
<?php foreach ($scriptsPagina ?? [] as $script): ?>
<script src="<?= h($script) ?>?v=1.0"></script>
<?php endforeach; ?>
</body>
</html>
