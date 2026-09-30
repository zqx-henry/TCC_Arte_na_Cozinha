  </main>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="../assets/js/admin.js?v=1.0"></script>
<?php foreach ($scriptsPagina ?? [] as $script): ?>
<script src="<?= h($script) ?>?v=1.0"></script>
<?php endforeach; ?>
</body>
</html>
