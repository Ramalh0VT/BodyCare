<?php
require_once __DIR__ . '/db.php';

function create(PDO $pdo, string $table, array $data): int
{
    $allowedColumns = [
        'usuarios' => ['nome', 'identificador', 'senha_hash', 'perfil', 'status'],
        'clientes' => ['usuario_id', 'telefone', 'convenio_id'],
        'convenios' => ['nome', 'registro', 'status', 'cobertura'],
        'agendamentos' => ['cliente_id', 'medico_id', 'especialidade', 'tipo', 'inicio', 'status', 'observacao'],
        'chegadas' => ['agendamento_id', 'cliente_id', 'chegada_em', 'recepcionista_id', 'motivo', 'observacao'],
        'triagens' => ['chegada_id', 'enfermeiro_id', 'nivel', 'especialidade_encaminhada', 'dados_clinicos', 'observacao'],
        'atendimentos' => ['agendamento_id', 'cliente_id', 'medico_id', 'inicio', 'fim', 'status', 'diagnostico', 'alta'],
        'exames_solicitados' => ['atendimento_id', 'descricao', 'prioridade', 'status', 'observacao'],
        'prescricoes' => ['atendimento_id', 'medicamento', 'dose', 'frequencia', 'duracao', 'instrucoes'],
        'internacoes' => ['paciente_id', 'atendimento_id', 'leito', 'entrada_em', 'alta_em', 'status', 'motivo', 'custo'],
        'evolucoes' => ['internacao_id', 'profissional_id', 'registrada_em', 'texto'],
        'cobrancas' => ['cliente_id', 'tipo', 'referencia_id', 'valor_total', 'valor_pago', 'status'],
        'pagamentos' => ['cobranca_id', 'valor', 'pago_em', 'forma', 'responsavel_id'],
    ];
    if (!isset($allowedColumns[$table]) || array_diff(array_keys($data), $allowedColumns[$table])) throw new InvalidArgumentException('Tabela ou coluna nao permitida.');
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
