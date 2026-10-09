<?php
/**
 * Arquivo carregado no início de todas as páginas.
 * Reúne conexão com o banco, sessão e funções auxiliares.
 */

require_once __DIR__ . '/config.php';

// ---------------------------------------------------------------------
// Sessão (cookies protegidos — RNF10)
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('ARTESESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---------------------------------------------------------------------
// Banco de dados (PDO + prepared statements)
// ---------------------------------------------------------------------
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = APP_CONFIG;
        $dsn = "mysql:host={$c['db_host']};port={$c['db_porta']};dbname={$c['db_nome']};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $c['db_usuario'], $c['db_senha'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Mantém o horário do banco igual ao do PHP (America/Sao_Paulo)
            $pdo->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $e) {
            http_response_code(500);
            $msg = $c['debug'] ? $e->getMessage() : 'Tente novamente em alguns minutos.';
            exit('<div style="font-family:sans-serif;padding:2rem;color:#4A2A2F">'
               . '<h2>Não foi possível conectar ao banco de dados</h2><p>'
               . htmlspecialchars($msg) . '</p><p>Verifique se o MySQL está ligado e se o banco '
               . '<code>arte_na_cozinha</code> foi importado (database/arte_na_cozinha.sql).</p></div>');
        }
    }
    return $pdo;
}

// ---------------------------------------------------------------------
// Constantes do domínio
// ---------------------------------------------------------------------
const CATEGORIAS = ['Bolos', 'Doces', 'Tortas', 'Bebidas'];

/** Etapas do pedido (RF03) — ordem da linha do tempo */
const STATUS_PEDIDO = [
    'recebido'     => ['rotulo' => 'Pedido recebido',   'admin' => 'Novo',           'classe' => 'novo',     'texto' => 'a confeitaria recebeu seu pedido'],
    'em_preparo'   => ['rotulo' => 'Em preparo',        'admin' => 'Em preparo',     'classe' => 'preparo',  'texto' => 'a confeitaria está preparando seu pedido'],
    'saiu_entrega' => ['rotulo' => 'Saiu para entrega', 'admin' => 'Saiu p/ entrega','classe' => 'entrega',  'texto' => 'o entregador está a caminho'],
    'entregue'     => ['rotulo' => 'Entregue',          'admin' => 'Entregue',       'classe' => 'entregue', 'texto' => 'pedido entregue. Bom apetite!'],
    'cancelado'    => ['rotulo' => 'Cancelado',         'admin' => 'Cancelado',      'classe' => 'cancelado','texto' => 'pedido cancelado pela loja'],
];

/** Próxima ação disponível para o administrador em cada status */
const PROXIMO_STATUS = [
    'recebido'     => ['status' => 'em_preparo',   'botao' => 'Aceitar'],
    'em_preparo'   => ['status' => 'saiu_entrega', 'botao' => 'Saiu p/ entrega'],
    'saiu_entrega' => ['status' => 'entregue',     'botao' => 'Entregue'],
];

/** Formas de pagamento (RF04 e RF05) */
const FORMAS_PAGAMENTO = [
    'pix'      => 'PIX',
    'cartao'   => 'Cartão',
    'dinheiro' => 'Dinheiro',
];

// ---------------------------------------------------------------------
// Funções auxiliares
// ---------------------------------------------------------------------

/** Escapa texto para exibir em HTML (evita XSS) */
function h(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** 79.9 -> "R$ 79,90" */
function dinheiro($valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

/** Preço que vale agora (promocional, se houver e ainda não tiver vencido) */
function preco_atual(array $produto): float
{
    $promo = $produto['preco_promocional'];
    $vencida = !empty($produto['promo_fim']) && strtotime($produto['promo_fim']) <= time();
    return (!$vencida && $promo !== null && (float) $promo > 0 && (float) $promo < (float) $produto['preco'])
        ? (float) $promo
        : (float) $produto['preco'];
}

function em_promocao(array $produto): bool
{
    return preco_atual($produto) < (float) $produto['preco'];
}

// ---------------------------------------------------------------------
// Promoções com tempo para acabar
// ---------------------------------------------------------------------

/** Trecho de SQL que traz o fim da promoção junto com o produto */
const SQL_PRODUTO_COM_PROMO =
    'SELECT p.*, pr.data_fim AS promo_fim, pr.modo AS promo_modo
       FROM produto p LEFT JOIN promocao pr ON pr.id_produto = p.id_produto';

/**
 * Encerra as promoções cujo prazo acabou: o produto volta ao preço normal.
 * Roda no início de cada página, então nenhum cliente compra com desconto vencido.
 */
function expirar_promocoes(): void
{
    $pdo = db();
    $pdo->exec(
        'UPDATE produto p JOIN promocao pr ON pr.id_produto = p.id_produto
            SET p.preco_promocional = NULL
          WHERE pr.data_fim <= NOW()'
    );
    $pdo->exec('DELETE FROM promocao WHERE data_fim <= NOW()');
}

/** Duração automática configurada no painel, em horas (padrão: 7 dias) */
function duracao_promocao_horas(): int
{
    return max(1, (int) config_loja('promo_duracao_horas', '168'));
}

/** "168" -> "7 dias", "36" -> "1 dia e 12 h" */
function descrever_horas(int $horas): string
{
    $dias = intdiv($horas, 24);
    $resto = $horas % 24;
    $partes = [];
    if ($dias) $partes[] = $dias . ($dias === 1 ? ' dia' : ' dias');
    if ($resto) $partes[] = $resto . ' h';
    return implode(' e ', $partes) ?: '0 h';
}

/** Tempo restante legível: "2d 04h 12min" ou "45min" */
function tempo_restante(string $dataFim): string
{
    $seg = max(0, strtotime($dataFim) - time());
    $d = intdiv($seg, 86400);
    $h = intdiv($seg % 86400, 3600);
    $m = intdiv($seg % 3600, 60);
    if ($d > 0) return sprintf('%dd %02dh %02dmin', $d, $h, $m);
    if ($h > 0) return sprintf('%dh %02dmin', $h, $m);
    return sprintf('%dmin', max(1, $m));
}

/**
 * Cria ou atualiza a promoção de um produto.
 * $modo: 'automatico' (agora + duração padrão) ou 'manual' ($fimManual escolhido no painel).
 * Retorna null em caso de sucesso ou a mensagem de erro.
 */
function salvar_promocao(int $idProduto, ?float $precoPromocional, string $modo = 'automatico', string $fimManual = ''): ?string
{
    $pdo = db();
    if ($precoPromocional === null) {
        $pdo->prepare('UPDATE produto SET preco_promocional = NULL WHERE id_produto = ?')->execute([$idProduto]);
        $pdo->prepare('DELETE FROM promocao WHERE id_produto = ?')->execute([$idProduto]);
        return null;
    }

    if ($erro = validar_prazo_promocao($modo, $fimManual)) {
        return $erro;
    }

    // "manter": só troca o preço e continua com o prazo que já estava valendo
    if ($modo === 'manter') {
        $st = $pdo->prepare('SELECT COUNT(*) FROM promocao WHERE id_produto = ?');
        $st->execute([$idProduto]);
        if ((int) $st->fetchColumn() > 0) {
            $pdo->prepare('UPDATE produto SET preco_promocional = ? WHERE id_produto = ?')->execute([$precoPromocional, $idProduto]);
            return null;
        }
        $modo = 'automatico'; // não havia prazo: começa a contar agora
    }

    if ($modo === 'manual') {
        $fim = strtotime($fimManual);
    } else {
        $modo = 'automatico';
        $fim = time() + duracao_promocao_horas() * 3600;
    }

    $pdo->prepare('UPDATE produto SET preco_promocional = ? WHERE id_produto = ?')->execute([$precoPromocional, $idProduto]);
    $pdo->prepare(
        'INSERT INTO promocao (id_produto, data_inicio, data_fim, modo) VALUES (?, NOW(), ?, ?)
         ON DUPLICATE KEY UPDATE data_inicio = NOW(), data_fim = VALUES(data_fim), modo = VALUES(modo)'
    )->execute([$idProduto, date('Y-m-d H:i:s', $fim), $modo]);
    return null;
}

/** Confere o prazo escolhido no painel; devolve a mensagem de erro ou null */
function validar_prazo_promocao(string $modo, string $fimManual): ?string
{
    if ($modo !== 'manual') {
        return null;
    }
    $fim = strtotime($fimManual);
    if (!$fim) {
        return 'Escolha a data e a hora em que a promoção deve acabar.';
    }
    if ($fim <= time() + 60) {
        return 'O fim da promoção precisa ser uma data e hora futura.';
    }
    if ($fim > strtotime('+1 year')) {
        return 'Escolha um fim de promoção em até 1 ano.';
    }
    return null;
}

/** Atributo com o fim da promoção em segundos (para o contador em JavaScript) */
function atributo_fim(?string $dataFim): string
{
    return $dataFim ? ' data-fim="' . strtotime($dataFim) . '"' : '';
}

/** Lê uma configuração da loja (tabela configuracao) */
function config_loja(string $chave, string $padrao = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = db()->query('SELECT chave, valor FROM configuracao')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $cache[$chave] ?? $padrao;
}

function loja_aberta(): bool
{
    return config_loja('loja_aberta', '1') === '1';
}

/** Mantém só os dígitos de um telefone */
function so_digitos(string $texto): string
{
    return preg_replace('/\D+/', '', $texto);
}

/** "15999990000" -> "(15) 99999-0000" */
function formatar_telefone(string $digitos): string
{
    $d = so_digitos($digitos);
    if (strlen($d) > 11 && str_starts_with($d, '55')) {
        $d = substr($d, 2);
    }
    if (strlen($d) === 11) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
    }
    if (strlen($d) === 10) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
    }
    return $d;
}

/** Link de conversa do WhatsApp com texto preenchido (RF08) */
function link_whatsapp(string $telefone, string $mensagem = ''): string
{
    $d = so_digitos($telefone);
    if (strlen($d) <= 11) {
        $d = '55' . $d; // código do Brasil
    }
    return 'https://wa.me/' . $d . ($mensagem !== '' ? '?text=' . rawurlencode($mensagem) : '');
}

/** Token do link de acompanhamento: impede ver pedidos de outras pessoas (LGPD) */
function token_pedido(int $idPedido): string
{
    return substr(hash_hmac('sha256', 'pedido:' . $idPedido, APP_CONFIG['chave_app']), 0, 16);
}

/** Endereço base do site (ex.: https://meusite.com.br/ ou http://localhost/artes-na-cozinha/) */
function url_base(): string
{
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host   = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $dir    = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if (in_array(basename($dir), ['admin', 'api'], true)) {
        $dir = dirname($dir);
    }
    $dir = rtrim($dir, '/') . '/';
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

function link_acompanhamento(int $idPedido): string
{
    return url_base() . 'acompanhar.php?pedido=' . $idPedido . '&t=' . token_pedido($idPedido);
}

/** Datas em português */
function data_extenso(?int $timestamp = null): string
{
    $timestamp ??= time();
    $dias  = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    return ucfirst($dias[(int) date('w', $timestamp)]) . ', ' . (int) date('j', $timestamp)
         . ' de ' . $meses[(int) date('n', $timestamp) - 1];
}

function hora(string $dataHora): string
{
    return date('H:i', strtotime($dataHora));
}

// ---------------------------------------------------------------------
// Proteção CSRF (formulários e requisições do site)
// ---------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_valido(?string $token): bool
{
    return is_string($token) && hash_equals(csrf_token(), $token);
}

// ---------------------------------------------------------------------
// Mensagens rápidas (aparecem uma vez após redirecionar)
// ---------------------------------------------------------------------
function flash(string $tipo, string $mensagem, ?array $link = null): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensagem' => $mensagem, 'link' => $link];
}

function exibir_flash(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $botao = '';
        if (!empty($f['link'])) {
            $botao = ' <a class="alerta-link" href="' . h($f['link']['url']) . '" target="_blank" rel="noopener">'
                   . h($f['link']['texto']) . '</a>';
        }
        $html .= '<div class="alerta alerta-' . h($f['tipo']) . '" role="status">' . h($f['mensagem']) . $botao . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

function redirecionar(string $destino): never
{
    header('Location: ' . $destino);
    exit;
}

/** Resposta JSON para a API */
function responder_json(array $dados, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Busca um pedido completo com cliente e itens */
function buscar_pedido(int $id): ?array
{
    $st = db()->prepare(
        'SELECT p.*, c.nome, c.telefone, c.endereco, c.bairro, c.complemento,
                e.distancia_km, e.tempo_min, e.tempo_max, e.metodo AS metodo_frete
           FROM pedido p
           JOIN cliente c ON c.id_cliente = p.id_cliente
           LEFT JOIN entrega e ON e.id_pedido = p.id_pedido
          WHERE p.id_pedido = ?'
    );
    $st->execute([$id]);
    $pedido = $st->fetch();
    if (!$pedido) {
        return null;
    }

    $st = db()->prepare(
        'SELECT i.*, pr.nome AS produto
           FROM item_pedido i JOIN produto pr ON pr.id_produto = i.id_produto
          WHERE i.id_pedido = ? ORDER BY i.id_item'
    );
    $st->execute([$id]);
    $pedido['itens'] = $st->fetchAll();

    $st = db()->prepare('SELECT status, data_hora FROM historico_status WHERE id_pedido = ? ORDER BY data_hora, id_historico');
    $st->execute([$id]);
    $pedido['historico'] = $st->fetchAll();

    return $pedido;
}

/** Texto do resumo do pedido para o WhatsApp */
function mensagem_resumo_pedido(array $pedido): string
{
    $linhas   = [];
    $linhas[] = '*Arte na Cozinha — Pedido nº ' . $pedido['id_pedido'] . '*';
    $linhas[] = '';
    foreach ($pedido['itens'] as $item) {
        $linhas[] = $item['quantidade'] . '× ' . $item['produto'] . ' — ' . dinheiro($item['subtotal']);
    }
    $linhas[] = 'Taxa de entrega' . ($pedido['distancia_km'] !== null ? ' (' . number_format((float) $pedido['distancia_km'], 1, ',', '') . ' km)' : '')
              . ' — ' . dinheiro($pedido['taxa_entrega']);
    $linhas[] = '*Total: ' . dinheiro($pedido['valor_total']) . '*';
    $linhas[] = '';
    $linhas[] = 'Pagamento: ' . FORMAS_PAGAMENTO[$pedido['forma_pagamento']]
              . ($pedido['pago_no_site'] ? ' (pago pelo site)' : ' (na entrega)');
    $linhas[] = 'Cliente: ' . $pedido['nome'] . ' · ' . formatar_telefone($pedido['telefone']);
    $linhas[] = 'Entrega: ' . $pedido['endereco'] . ' – ' . $pedido['bairro']
              . ($pedido['complemento'] ? ' (' . $pedido['complemento'] . ')' : '');
    if (!empty($pedido['observacao'])) {
        $linhas[] = 'Obs.: ' . $pedido['observacao'];
    }
    $linhas[] = '';
    $linhas[] = 'Acompanhe: ' . link_acompanhamento((int) $pedido['id_pedido']);
    return implode("\n", $linhas);
}

/** Previsão de entrega do pedido: [minutos mínimos, minutos máximos] */
function previsao_pedido(array $pedido): array
{
    if (!empty($pedido['tempo_min'])) {
        return [(int) $pedido['tempo_min'], (int) $pedido['tempo_max']];
    }
    preg_match_all('/\d+/', config_loja('tempo_entrega', '40-60'), $m);
    return [(int) ($m[0][0] ?? 40), (int) (end($m[0]) ?: 60)];
}

// Encerra promoções vencidas antes de montar qualquer página
expirar_promocoes();
