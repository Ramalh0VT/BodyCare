<?php

require_once __DIR__ . '/db.php';

function listUsers($database): array
{
    return $database->query('SELECT id, nome, identificador, perfil, status FROM usuarios ORDER BY nome')->fetchAll();
}

function findUser($database, $userId)
{
    $statement = $database->prepare('SELECT * FROM usuarios WHERE id = ?');
    $statement->execute([(int) $userId]);
    return $statement->fetch();
}

function listClients($database): array
{
    return $database->query("SELECT c.id, c.convenio_id, u.nome FROM clientes c JOIN usuarios u ON u.id = c.usuario_id WHERE u.status = 'ativo' ORDER BY u.nome")->fetchAll();
}

function listDoctors($database): array
{
    return $database->query("SELECT id, nome FROM usuarios WHERE perfil = 'medico' AND status = 'ativo' ORDER BY nome")->fetchAll();
}

function moneyToCents($value): int
{
    $value = str_replace(',', '.', trim((string) $value));
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
        throw new InvalidArgumentException('Valor monetario invalido.');
    }
    $parts = explode('.', $value, 2);
    $cents = str_pad($parts[1] ?? '0', 2, '0');
    return ((int) $parts[0] * 100) + (int) $cents;
}

function centsToMoney(int $cents): string
{
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}
