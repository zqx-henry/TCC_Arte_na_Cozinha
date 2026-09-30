<?php
/**
 * Aviso de privacidade — LGPD, Lei nº 13.709/2018 (seção 3.1.4 e RNF11)
 */
require_once __DIR__ . '/includes/bootstrap.php';
$tituloPagina = 'Aviso de privacidade | Arte na Cozinha';
require __DIR__ . '/includes/cabecalho.php';
?>
<div class="container" style="max-width:760px">
  <div class="cabecalho-pagina">
    <a href="index.php" class="botao-icone" aria-label="Voltar ao cardápio"><?= icone('voltar') ?></a>
    <h1>Aviso de privacidade</h1>
  </div>

  <article class="cartao texto-longo">
    <p>A Arte na Cozinha respeita a sua privacidade e trata os seus dados pessoais de acordo com a
       Lei Geral de Proteção de Dados Pessoais (LGPD – Lei nº 13.709/2018).</p>

    <h2>Quais dados coletamos</h2>
    <ul>
      <li><strong>Nome</strong> — para identificar o seu pedido;</li>
      <li><strong>WhatsApp</strong> — para confirmar o pedido e avisar sobre a entrega;</li>
      <li><strong>Endereço, bairro e complemento</strong> — para realizar a entrega.</li>
    </ul>
    <p>Não é necessário criar conta nem senha para comprar. Coletamos apenas os dados necessários para a entrega.</p>

    <h2>Para que usamos</h2>
    <p>Os dados são usados exclusivamente para preparar, entregar e dar suporte ao seu pedido,
       e para manter o histórico de vendas da loja. Não vendemos nem compartilhamos seus dados com terceiros
       para fins de publicidade.</p>

    <h2>Pagamentos</h2>
    <p>Os dados de cartão são processados diretamente pela instituição de pagamento e não ficam armazenados no nosso sistema.</p>

    <h2>Segurança</h2>
    <p>O acesso ao painel administrativo é restrito aos responsáveis da loja, com login e senha armazenada de forma
       criptografada, e o site utiliza conexão segura (HTTPS) quando publicado.</p>

    <h2>Seus direitos</h2>
    <p>Você pode pedir, a qualquer momento, a consulta, a correção ou a exclusão dos seus dados pelo nosso WhatsApp
       <a href="<?= h(link_whatsapp(config_loja('whatsapp_loja'), 'Olá! Gostaria de falar sobre meus dados pessoais (LGPD).')) ?>" target="_blank" rel="noopener"><?= h(formatar_telefone(config_loja('whatsapp_loja'))) ?></a>.</p>

    <h2>Dados salvos no seu aparelho</h2>
    <p>Para facilitar os próximos pedidos, o seu navegador guarda o carrinho e os dados de entrega apenas neste aparelho.
       Você pode apagá-los limpando os dados de navegação.</p>
  </article>
</div>
<?php require __DIR__ . '/includes/rodape.php'; ?>
