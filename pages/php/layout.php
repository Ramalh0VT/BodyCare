<?php
require_once __DIR__ . '/auth.php';

function pageStart(string $title, array $user): void
{
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . e($title) . '</title></head><body>';
    echo '<header><h1>' . e($title) . '</h1><p>Usuario: ' . e($user['nome']) . ' | Perfil: ' . e($user['perfil']) . '</p><nav><a href="../' . e(redirectToProfile($user['perfil'])) . '">Inicio</a> | <a href="../logout.php">Sair</a></nav></header><main>';
}

function pageEnd(): void
{
    echo '</main></body></html>';
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
