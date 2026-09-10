<?php
require_once __DIR__ . '/db.php';

function create(PDO $pdo, string $table, array $data): int
{
    $allowedTables = ['usuarios', 'clientes', 'convenios', 'agendamentos', 'chegadas', 'triagens', 'atendimentos', 'exames_solicitados', 'prescricoes', 'internacoes', 'evolucoes', 'cobrancas', 'pagamentos'];
    if (!in_array($table, $allowedTables, true)) throw new InvalidArgumentException('Tabela nao permitida.');
    $columns = array_keys($data);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
    $statement = $pdo->prepare($sql);
    $statement->execute(array_values($data));
    return (int) $pdo->lastInsertId();
}

function readAll(PDO $pdo, string $table, string $where = '', array $parameters = []): array
{
    $allowedTables = ['usuarios', 'clientes', 'convenios', 'agendamentos', 'chegadas', 'triagens', 'atendimentos', 'exames_solicitados', 'prescricoes', 'internacoes', 'evolucoes', 'cobrancas', 'pagamentos'];
    if (!in_array($table, $allowedTables, true)) throw new InvalidArgumentException('Tabela nao permitida.');
    $statement = $pdo->prepare('SELECT * FROM ' . $table . ($where ? ' WHERE ' . $where : ''));
    $statement->execute($parameters);
    return $statement->fetchAll();
}
