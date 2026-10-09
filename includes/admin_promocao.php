<?php
/**
 * Campos de prazo da promoção, usados em admin/promocoes.php e admin/produto_form.php.
 *
 *  ( ) Manter     — (só ao editar) continua com o prazo que já estava valendo
 *  ( ) Automático — a promoção acaba sozinha depois da duração padrão (ex.: 7 dias)
 *  ( ) Manual     — o administrador escolhe a data e a hora do fim
 */
function campos_prazo_promocao(string $sufixo, ?string $modoAtual = null, ?string $fimAtual = null): string
{
    $horas      = duracao_promocao_horas();
    $fimAuto    = time() + $horas * 3600;
    $manual     = $modoAtual === 'manual';
    $valorFim   = $fimAtual ? date('Y-m-d\TH:i', strtotime($fimAtual)) : date('Y-m-d\TH:i', $fimAuto);
    $minimo     = date('Y-m-d\TH:i', time() + 300);
    $maximo     = date('Y-m-d\TH:i', strtotime('+1 year'));
    $id         = 'prazo-' . preg_replace('/\W/', '', $sufixo);

    $temPrazo   = $fimAtual && strtotime($fimAtual) > time();

    ob_start(); ?>
    <fieldset class="prazo-promocao" data-prazo>
      <legend>Tempo para o fim do desconto</legend>
      <?php if ($temPrazo): ?>
      <label class="opcao-prazo">
        <input type="radio" name="modo_prazo" value="manter" checked>
        <span>
          <strong>Manter o prazo atual</strong>
          <small>Continua terminando em <?= date('d/m', strtotime($fimAtual)) ?> às <?= date('H:i', strtotime($fimAtual)) ?>
            (faltam <?= h(tempo_restante($fimAtual)) ?>)</small>
        </span>
      </label>
      <?php endif; ?>
      <label class="opcao-prazo">
        <input type="radio" name="modo_prazo" value="automatico" <?= $manual || $temPrazo ? '' : 'checked' ?>>
        <span>
          <strong>Automático</strong> — <?= h(descrever_horas($horas)) ?>
          <small>Termina sozinho em <?= date('d/m', $fimAuto) ?> às <?= date('H:i', $fimAuto) ?>
            (contando a partir de quando você salvar)</small>
        </span>
      </label>
      <label class="opcao-prazo">
        <input type="radio" name="modo_prazo" value="manual" <?= $manual && !$temPrazo ? 'checked' : '' ?>>
        <span>
          <strong>Manual</strong> — escolher data e hora
          <input type="datetime-local" name="fim_manual" id="<?= $id ?>" value="<?= h($valorFim) ?>"
                 min="<?= $minimo ?>" max="<?= $maximo ?>" aria-label="Data e hora do fim da promoção">
        </span>
      </label>
    </fieldset>
    <?php return ob_get_clean();
}

/** Texto do prazo de uma promoção ativa, com contador ao vivo */
function texto_prazo_promocao(array $p): string
{
    if (empty($p['promo_fim'])) {
        return '<span class="prazo-texto">Sem prazo definido</span>';
    }
    $rotulo = $p['promo_modo'] === 'manual'
        ? 'Fim definido manualmente: ' . date('d/m', strtotime($p['promo_fim'])) . ' às ' . date('H:i', strtotime($p['promo_fim'])) . ' · faltam'
        : 'Tempo automático para o fim do desconto:';
    return '<span class="prazo-texto"' . atributo_fim($p['promo_fim']) . '>⏳ ' . h($rotulo)
         . ' <strong class="contagem-valor">' . h(tempo_restante($p['promo_fim'])) . '</strong></span>';
}
