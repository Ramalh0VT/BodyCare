<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function startApplicationSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function attemptLogin(string $identifier, string $password): bool
{
    startApplicationSession();
    $statement = db()->prepare('SELECT * FROM usuarios WHERE identificador = ? AND status = ? LIMIT 1');
    $statement->execute([$identifier, 'ativo']);
    $user = $statement->fetch();
    if (!$user || !password_verify($password, $user['senha_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'nome' => $user['nome'],
        'perfil' => $user['perfil'],
        'status' => $user['status'],
    ];
    return true;
}

function logout(): void
{
    startApplicationSession();
    $_SESSION = [];
    session_destroy();
}

function currentUser(): ?array
{
    startApplicationSession();
    if (empty($_SESSION['user']['id'])) {
        return null;
    }
    $statement = db()->prepare('SELECT id, nome, perfil, status FROM usuarios WHERE id = ? AND status = ? LIMIT 1');
    $statement->execute([(int) $_SESSION['user']['id'], 'ativo']);
    $user = $statement->fetch();
    if (!$user) {
        unset($_SESSION['user']);
        return null;
    }
    $_SESSION['user'] = $user;
    return $user;
}

function requireLogin(): array
{
    $user = currentUser();
    if (!$user) {
        header('Location: ../login.php');
        exit;
    }
    return $user;
}

function requireProfile(array $profiles): array
{
    $user = requireLogin();
    if (!in_array($user['perfil'], $profiles, true)) {
        http_response_code(403);
        exit('Acesso negado para este perfil.');
    }
    return $user;
}

function redirectToProfile(string $profile): string
{
    $routes = [
        'admin' => 'adm/index.php',
        'financeiro' => 'fin/index.php',
        'medico' => 'med/index.php',
        'enfermeiro' => 'tri/index.php',
        'recepcao' => 'rec/index.php',
        'cliente' => 'pac/index.php',
    ];
    return $routes[$profile] ?? 'login.php';
}

function csrfToken(): string
{
    startApplicationSession();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function verifyCsrf(): void
{
    startApplicationSession();
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Formulario expirado. Recarregue a pagina e tente novamente.');
    }
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
