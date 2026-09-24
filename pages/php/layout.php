<?php
require_once __DIR__ . '/auth.php';

function pageStart(string $title, array $user): void
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $segments = array_values(array_filter(explode('/', trim($scriptName, '/')), static fn (string $segment): bool => $segment !== ''));
    $relativePrefix = str_repeat('../', max(0, count($segments) - 1));
    $cssPath = $relativePrefix . 'CSS/style.css';

    echo '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . e($title) . '</title><link rel="stylesheet" href="' . e($cssPath) . '"></head><body class="app-body">';
    $extraLink = '';
    if (in_array($user['perfil'], ['medico', 'enfermeiro'], true)) {
        $extraLink = ' <a href="../int/index.php">Internacoes</a>';
    }
    echo '<div class="app-shell"><header class="app-header"><div class="app-header__title"><h1>' . e($title) . '</h1><p>Usuario: ' . e($user['nome']) . ' | Perfil: ' . e($user['perfil']) . '</p></div><nav class="app-nav"><a href="../' . e(redirectToProfile($user['perfil'])) . '">Inicio</a>' . $extraLink . ' <a href="../logout.php">Sair</a></nav></header><main class="app-main">';
}

function pageEnd(): void
{
    echo '</main></div></body></html>';
}

function messageFromQuery(): void
{
    if (!empty($_GET['ok'])) {
        echo '<p role="status">' . e($_GET['ok']) . '</p>';
    }
    if (!empty($_GET['erro'])) {
        echo '<p role="alert">' . e($_GET['erro']) . '</p>';
    }
}

function formField(string $label, string $name, string $type = 'text', string $value = '', bool $required = true): void
{
    echo '<label>' . e($label) . ': <input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '"' . ($required ? ' required' : '') . '></label><br>';
}
