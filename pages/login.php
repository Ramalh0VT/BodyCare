<?php
require_once __DIR__ . '/php/layout.php';

if (currentUser()) {
    header('Location: ' . redirectToProfile(currentUser()['perfil']));
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (attemptLogin(trim($_POST['email'] ?? ''), $_POST['senha'] ?? '')) {
        header('Location: ' . redirectToProfile(currentUser()['perfil']));
        exit;
    }
    $error = 'E-mail, senha ou status inválidos.';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login BodyCare</title>
    <link rel="stylesheet" href="../CSS/style.css">
</head>
<body>
    <main class="auth-shell">
        <section class="container auth-card">
            <h1>Login BodyCare</h1>
            <?php if ($error): ?><p role="alert" class="message message-error"><?= e($error) ?></p><?php endif; ?>
            <form method="post" class="auth-form">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <?php formField('E-mail', 'email', 'email'); ?>
                <?php formField('Senha', 'senha', 'password'); ?>
                <button type="submit">Entrar</button>
            </form>
        </section>
    </main>
</body>
</html>
