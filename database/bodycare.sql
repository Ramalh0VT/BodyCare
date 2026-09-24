PRAGMA foreign_keys = ON;

-- Esse esquema deve ser usado com a migração idempotente em pages/php/db.php,
-- que converte bancos legados com a coluna identificador para email antes da aplicação iniciar.

CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    senha_hash TEXT NOT NULL,
    perfil TEXT NOT NULL CHECK (perfil IN ('admin', 'financeiro', 'medico', 'enfermeiro', 'recepcao', 'cliente')),
    status TEXT NOT NULL DEFAULT 'ativo' CHECK (status IN ('ativo', 'inativo')),
    criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS convenios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    registro TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'ativo' CHECK (status IN ('ativo', 'inativo')),
    cobertura DECIMAL(5,2) NOT NULL DEFAULT 0 CHECK (cobertura >= 0 AND cobertura <= 100)
);

CREATE TABLE IF NOT EXISTS clientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL UNIQUE,
    telefone TEXT,
    convenio_id INTEGER,
    numero_convenio TEXT,
    convenio_inicio TEXT,
    convenio_fim TEXT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (convenio_id) REFERENCES convenios(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS agendamentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cliente_id INTEGER NOT NULL,
    medico_id INTEGER NOT NULL,
    especialidade TEXT NOT NULL,
    tipo TEXT NOT NULL CHECK (tipo IN ('consulta', 'retorno')),
    inicio TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'agendada' CHECK (status IN ('agendada', 'chegou', 'em_atendimento', 'concluida', 'cancelada')),
    observacao TEXT,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (medico_id) REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS chegadas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    agendamento_id INTEGER NOT NULL UNIQUE,
    cliente_id INTEGER NOT NULL,
    chegada_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    recepcionista_id INTEGER NOT NULL,
    motivo TEXT NOT NULL,
    observacao TEXT,
    FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (recepcionista_id) REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS atendimentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    agendamento_id INTEGER NOT NULL UNIQUE,
    cliente_id INTEGER NOT NULL,
    medico_id INTEGER NOT NULL,
    especialidade TEXT NOT NULL,
    inicio TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fim TEXT,
    status TEXT NOT NULL DEFAULT 'em_atendimento' CHECK (status IN ('em_atendimento', 'concluido')),
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
    prioridade TEXT NOT NULL DEFAULT 'normal' CHECK (prioridade IN ('normal', 'urgente')),
    status TEXT NOT NULL DEFAULT 'solicitado',
    observacao TEXT,
    valor DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK (valor >= 0),
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
    status TEXT NOT NULL DEFAULT 'aberta' CHECK (status IN ('aberta', 'alta')),
    motivo TEXT NOT NULL,
    custo DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK (custo >= 0),
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

CREATE TABLE IF NOT EXISTS historico_internacoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    internacao_id INTEGER NOT NULL,
    profissional_id INTEGER NOT NULL,
    leito TEXT NOT NULL,
    status TEXT NOT NULL,
    registrado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (internacao_id) REFERENCES internacoes(id),
    FOREIGN KEY (profissional_id) REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS cobrancas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cliente_id INTEGER NOT NULL,
    tipo TEXT NOT NULL CHECK (tipo IN ('consulta', 'retorno', 'exame', 'internacao', 'convenio')),
    referencia_id INTEGER,
    valor_total DECIMAL(10,2) NOT NULL CHECK (valor_total >= 0),
    valor_pago DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK (valor_pago >= 0),
    valor_convenio DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK (valor_convenio >= 0),
    status TEXT NOT NULL DEFAULT 'aberta' CHECK (status IN ('aberta', 'parcial', 'paga')),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);

CREATE TABLE IF NOT EXISTS pagamentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cobranca_id INTEGER NOT NULL,
    valor DECIMAL(10,2) NOT NULL CHECK (valor > 0),
    pago_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    forma TEXT NOT NULL,
    responsavel_id INTEGER NOT NULL,
    FOREIGN KEY (cobranca_id) REFERENCES cobrancas(id),
    FOREIGN KEY (responsavel_id) REFERENCES usuarios(id)
);

CREATE INDEX IF NOT EXISTS idx_agendamentos_medico_inicio ON agendamentos (medico_id, inicio, status);
CREATE INDEX IF NOT EXISTS idx_agendamentos_cliente_inicio ON agendamentos (cliente_id, inicio);
CREATE INDEX IF NOT EXISTS idx_chegadas_status ON chegadas (chegada_em);
CREATE INDEX IF NOT EXISTS idx_cobrancas_status ON cobrancas (status, cliente_id);
CREATE INDEX IF NOT EXISTS idx_evolucoes_internacao ON evolucoes (internacao_id, registrada_em);

INSERT OR IGNORE INTO usuarios (nome, email, senha_hash, perfil, status)
VALUES ('Administrador', 'admin@bodycare.local', '$2y$10$aqGVGRC5mdtHect8HV6lwu.jKOdXG.mKfMlVXgyEnxwXU/1MbuekG', 'admin', 'ativo');
INSERT OR IGNORE INTO usuarios (nome, email, senha_hash, perfil, status)
VALUES ('Financeiro', 'financeiro@bodycare.local', '$2y$10$aqGVGRC5mdtHect8HV6lwu.jKOdXG.mKfMlVXgyEnxwXU/1MbuekG', 'financeiro', 'ativo');
INSERT OR IGNORE INTO usuarios (nome, email, senha_hash, perfil, status)
VALUES ('Medico', 'medico@bodycare.local', '$2y$10$aqGVGRC5mdtHect8HV6lwu.jKOdXG.mKfMlVXgyEnxwXU/1MbuekG', 'medico', 'ativo');
INSERT OR IGNORE INTO usuarios (nome, email, senha_hash, perfil, status)
VALUES ('Enfermeiro', 'enfermeiro@bodycare.local', '$2y$10$aqGVGRC5mdtHect8HV6lwu.jKOdXG.mKfMlVXgyEnxwXU/1MbuekG', 'enfermeiro', 'ativo');
INSERT OR IGNORE INTO usuarios (nome, email, senha_hash, perfil, status)
VALUES ('Recepcao', 'recepcao@bodycare.local', '$2y$10$aqGVGRC5mdtHect8HV6lwu.jKOdXG.mKfMlVXgyEnxwXU/1MbuekG', 'recepcao', 'ativo');
INSERT OR IGNORE INTO usuarios (nome, email, senha_hash, perfil, status)
VALUES ('Cliente demonstracao', 'cliente@bodycare.local', '$2y$10$aqGVGRC5mdtHect8HV6lwu.jKOdXG.mKfMlVXgyEnxwXU/1MbuekG', 'cliente', 'ativo');

INSERT OR IGNORE INTO clientes (usuario_id, telefone)
SELECT id, '0000-0000' FROM usuarios WHERE email = 'cliente@bodycare.local';
