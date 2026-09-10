<?php

declare(strict_types=1);

function db(): PDO
{
    static $connection;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $databaseDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($databaseDirectory)) {
        mkdir($databaseDirectory, 0775, true);
    }

    $connection = new PDO('sqlite:' . $databaseDirectory . DIRECTORY_SEPARATOR . 'bodycare.sqlite');
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $connection->exec('PRAGMA foreign_keys = ON');
    initializeDatabase($connection);

    return $connection;
}

function initializeDatabase(PDO $connection): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $connection->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    identificador TEXT NOT NULL UNIQUE,
    senha_hash TEXT NOT NULL,
    perfil TEXT NOT NULL CHECK (perfil IN ('admin','financeiro','medico','enfermeiro','recepcao','cliente')),
    status TEXT NOT NULL DEFAULT 'ativo' CHECK (status IN ('ativo','inativo')),
    criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS convenios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    registro TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'ativo',
    cobertura REAL NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS clientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER UNIQUE,
    telefone TEXT,
    convenio_id INTEGER,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (convenio_id) REFERENCES convenios(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS agendamentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cliente_id INTEGER NOT NULL,
    medico_id INTEGER,
    especialidade TEXT NOT NULL,
    tipo TEXT NOT NULL CHECK (tipo IN ('consulta','retorno')),
    inicio TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'agendada',
    observacao TEXT,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (medico_id) REFERENCES usuarios(id)
);
CREATE TABLE IF NOT EXISTS chegadas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    agendamento_id INTEGER,
    cliente_id INTEGER NOT NULL,
    chegada_em TEXT NOT NULL,
    recepcionista_id INTEGER NOT NULL,
    motivo TEXT,
    observacao TEXT,
    FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (recepcionista_id) REFERENCES usuarios(id)
);
CREATE TABLE IF NOT EXISTS triagens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    chegada_id INTEGER NOT NULL UNIQUE,
    enfermeiro_id INTEGER NOT NULL,
    nivel TEXT NOT NULL CHECK (nivel IN ('emergencia','urgente','prioritario','eletivo')),
    dados_clinicos TEXT,
    classificada_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observacao TEXT,
    FOREIGN KEY (chegada_id) REFERENCES chegadas(id),
    FOREIGN KEY (enfermeiro_id) REFERENCES usuarios(id)
);
CREATE TABLE IF NOT EXISTS atendimentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    agendamento_id INTEGER,
    cliente_id INTEGER NOT NULL,
    medico_id INTEGER NOT NULL,
    inicio TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fim TEXT,
    status TEXT NOT NULL DEFAULT 'em_atendimento',
    diagnostico TEXT,
    alta TEXT,
    FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (medico_id) REFERENCES usuarios(id)
);
CREATE TABLE IF NOT EXISTS exames_solicitados (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    atendimento_id INTEGER NOT NULL,
    descricao TEXT NOT NULL,
    prioridade TEXT NOT NULL DEFAULT 'normal',
    status TEXT NOT NULL DEFAULT 'solicitado',
    observacao TEXT,
    FOREIGN KEY (atendimento_id) REFERENCES atendimentos(id)
);
CREATE TABLE IF NOT EXISTS prescricoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    atendimento_id INTEGER NOT NULL,
    medicamento TEXT NOT NULL,
    dose TEXT NOT NULL,
    frequencia TEXT NOT NULL,
    duracao TEXT NOT NULL,
    instrucoes TEXT,
    FOREIGN KEY (atendimento_id) REFERENCES atendimentos(id)
);
CREATE TABLE IF NOT EXISTS internacoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    paciente_id INTEGER NOT NULL,
    atendimento_id INTEGER,
    leito TEXT NOT NULL,
    entrada_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    alta_em TEXT,
    status TEXT NOT NULL DEFAULT 'aberta',
    motivo TEXT NOT NULL,
    custo REAL NOT NULL DEFAULT 0,
    FOREIGN KEY (paciente_id) REFERENCES clientes(id),
    FOREIGN KEY (atendimento_id) REFERENCES atendimentos(id)
);
CREATE TABLE IF NOT EXISTS evolucoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    internacao_id INTEGER NOT NULL,
    profissional_id INTEGER NOT NULL,
    registrada_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    texto TEXT NOT NULL,
    FOREIGN KEY (internacao_id) REFERENCES internacoes(id),
    FOREIGN KEY (profissional_id) REFERENCES usuarios(id)
);
CREATE TABLE IF NOT EXISTS cobrancas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cliente_id INTEGER NOT NULL,
    tipo TEXT NOT NULL,
    referencia_id INTEGER,
    valor_total REAL NOT NULL,
    valor_pago REAL NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'aberta',
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);
CREATE TABLE IF NOT EXISTS pagamentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cobranca_id INTEGER NOT NULL,
    valor REAL NOT NULL,
    pago_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    forma TEXT NOT NULL,
    responsavel_id INTEGER NOT NULL,
    FOREIGN KEY (cobranca_id) REFERENCES cobrancas(id),
    FOREIGN KEY (responsavel_id) REFERENCES usuarios(id)
);
SQL);

    seedDatabase($connection);
    $initialized = true;
}

function seedDatabase(PDO $connection): void
{
    $count = (int) $connection->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $users = [
        ['Administrador', 'admin@bodycare.local', 'admin'],
        ['Financeiro', 'financeiro@bodycare.local', 'financeiro'],
        ['Medico', 'medico@bodycare.local', 'medico'],
        ['Enfermeiro', 'enfermeiro@bodycare.local', 'enfermeiro'],
        ['Recepcao', 'recepcao@bodycare.local', 'recepcao'],
        ['Cliente demonstracao', 'cliente@bodycare.local', 'cliente'],
    ];
    $statement = $connection->prepare('INSERT INTO usuarios (nome, identificador, senha_hash, perfil) VALUES (?, ?, ?, ?)');
    foreach ($users as $user) {
        $statement->execute([$user[0], $user[1], password_hash('123456', PASSWORD_DEFAULT), $user[2]]);
    }

    $clientUserId = (int) $connection->query("SELECT id FROM usuarios WHERE perfil = 'cliente' LIMIT 1")->fetchColumn();
    $connection->prepare('INSERT INTO clientes (usuario_id, telefone) VALUES (?, ?)')->execute([$clientUserId, '0000-0000']);
}
