  </main>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script>window.AGORA_SERVIDOR = <?= time() ?>;</script>
<script src="../assets/js/admin.js?v=1.2"></script>
<script src="../assets/js/contagem.js?v=1.2"></script>
<?php foreach ($scriptsPagina ?? [] as $script): ?>
<script src="<?= h($script) ?>?v=1.2"></script>
<?php endforeach; ?>
</body>
</html>
