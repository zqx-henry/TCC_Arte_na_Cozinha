<?php
/**
 * Autenticação do administrador (RF09 / UC09)
 * Senhas guardadas com password_hash() (bcrypt) — seção 3.1.4 e RNF10.
 */
require_once __DIR__ . '/bootstrap.php';

const TEMPO_SESSAO_ADMIN = 4 * 60 * 60; // 4 horas sem uso encerra a sessão

function admin_logado(): ?array
{
    if (empty($_SESSION['admin']['id'])) {
        return null;
    }
    if (time() - ($_SESSION['admin']['ultimo_acesso'] ?? 0) > TEMPO_SESSAO_ADMIN) {
        unset($_SESSION['admin']);
        return null;
    }
    $_SESSION['admin']['ultimo_acesso'] = time();
    return $_SESSION['admin'];
}

/** Coloque no topo de toda página do painel */
function exigir_login(): array
{
    $admin = admin_logado();
    if (!$admin) {
        redirecionar('login.php');
    }
    header('X-Frame-Options: DENY');
    header('Cache-Control: no-store');
    return $admin;
}

/** Tenta autenticar; devolve mensagem de erro ou null em caso de sucesso */
function fazer_login(string $email, string $senha): ?string
{
    // Limite de tentativas: 5 erros bloqueiam por 5 minutos (proteção contra força bruta)
    $tentativas = $_SESSION['login_tentativas'] ?? ['qtd' => 0, 'desde' => time()];
    if (time() - $tentativas['desde'] > 300) {
        $tentativas = ['qtd' => 0, 'desde' => time()];
    }
    if ($tentativas['qtd'] >= 5) {
        return 'Muitas tentativas. Aguarde alguns minutos e tente novamente.';
    }

    $st = db()->prepare('SELECT id_admin, nome, email, senha_hash FROM administrador WHERE email = ?');
    $st->execute([mb_strtolower(trim($email))]);
    $admin = $st->fetch();

    if (!$admin || !password_verify($senha, $admin['senha_hash'])) {
        $tentativas['qtd']++;
        $_SESSION['login_tentativas'] = $tentativas;
        usleep(400000);
        return 'E-mail ou senha incorretos.';
    }

    // Atualiza o hash se o PHP passar a usar um algoritmo mais forte
    if (password_needs_rehash($admin['senha_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE administrador SET senha_hash = ? WHERE id_admin = ?')
            ->execute([password_hash($senha, PASSWORD_DEFAULT), $admin['id_admin']]);
    }

    session_regenerate_id(true);
    unset($_SESSION['login_tentativas']);
    $_SESSION['admin'] = [
        'id'            => (int) $admin['id_admin'],
        'nome'          => $admin['nome'],
        'email'         => $admin['email'],
        'ultimo_acesso' => time(),
    ];
    return null;
}

/** Valida o token CSRF de um formulário POST do painel */
function exigir_post_valido(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valido($_POST['csrf'] ?? null)) {
        flash('erro', 'Ação não autorizada ou sessão expirada. Tente novamente.');
        redirecionar('index.php');
    }
}
