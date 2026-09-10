<?php
require_once __DIR__ . '/php/layout.php';

if (currentUser()) {
    header('Location: ' . redirectToProfile(currentUser()['perfil']));
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (attemptLogin(trim($_POST['identificador'] ?? ''), $_POST['senha'] ?? '')) {
        header('Location: ' . redirectToProfile(currentUser()['perfil']));
        exit;
    }
    $error = 'Identificador, senha ou status invalidos.';
}
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Login BodyCare</title></head>
<body>
<main>
<h1>Login BodyCare</h1>
<?php if ($error): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
<?php formField('Identificador', 'identificador', 'email'); ?>
<?php formField('Senha', 'senha', 'password'); ?>
<button type="submit">Entrar</button>
</form>
<p>Usuarios de demonstracao usam a senha <strong>123456</strong>.</p>
</main>
</body>
</html>
