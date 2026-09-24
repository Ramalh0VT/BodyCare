<?php

function ensureDatabaseSchema(PDO $connection): void
{
    $tableExists = (bool) $connection->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'usuarios' LIMIT 1")->fetchColumn();

    if (!$tableExists) {
        $schemaPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'bodycare.sql';
        if (!is_file($schemaPath)) {
            throw new RuntimeException('Arquivo de esquema do banco nao encontrado em ' . $schemaPath);
        }

        $schema = file_get_contents($schemaPath);
        if ($schema === false) {
            throw new RuntimeException('Nao foi possivel ler o esquema do banco em ' . $schemaPath);
        }

        foreach (explode(';', $schema) as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }

            $connection->exec($statement . ';');
        }

        return;
    }

    $columns = $connection->query('PRAGMA table_info(usuarios)')->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_map(static fn (array $column): string => (string) $column['name'], $columns);
    $hasEmail = in_array('email', $columnNames, true);
    $hasIdentifier = in_array('identificador', $columnNames, true);

    if ($hasEmail && !$hasIdentifier) {
        return;
    }

    if ($hasIdentifier && !$hasEmail) {
        try {
            $connection->exec('ALTER TABLE usuarios RENAME COLUMN identificador TO email');
            return;
        } catch (Throwable $exception) {
            $connection->exec('PRAGMA foreign_keys = OFF');
            $connection->exec('CREATE TABLE usuarios_migracao_tmp (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, email TEXT NOT NULL UNIQUE, senha_hash TEXT NOT NULL, perfil TEXT NOT NULL CHECK (perfil IN (\'admin\', \'financeiro\', \'medico\', \'enfermeiro\', \'recepcao\', \'cliente\')), status TEXT NOT NULL DEFAULT \'ativo\' CHECK (status IN (\'ativo\', \'inativo\')), criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
            $connection->exec('INSERT INTO usuarios_migracao_tmp (id, nome, email, senha_hash, perfil, status, criado_em) SELECT id, nome, identificador, senha_hash, perfil, status, criado_em FROM usuarios');
            $connection->exec('ALTER TABLE usuarios RENAME TO usuarios_legacy_backup');
            $connection->exec('ALTER TABLE usuarios_migracao_tmp RENAME TO usuarios');
            $connection->exec('DROP TABLE usuarios_legacy_backup');
            $connection->exec('PRAGMA foreign_keys = ON');
            return;
        }
    }

    if ($hasIdentifier && $hasEmail) {
        $connection->exec('PRAGMA foreign_keys = OFF');
        $connection->exec('CREATE TABLE usuarios_migracao_tmp (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, email TEXT NOT NULL UNIQUE, senha_hash TEXT NOT NULL, perfil TEXT NOT NULL CHECK (perfil IN (\'admin\', \'financeiro\', \'medico\', \'enfermeiro\', \'recepcao\', \'cliente\')), status TEXT NOT NULL DEFAULT \'ativo\' CHECK (status IN (\'ativo\', \'inativo\')), criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $connection->exec('INSERT INTO usuarios_migracao_tmp (id, nome, email, senha_hash, perfil, status, criado_em) SELECT id, nome, COALESCE(email, identificador), senha_hash, perfil, status, criado_em FROM usuarios');
        $connection->exec('ALTER TABLE usuarios RENAME TO usuarios_legacy_backup');
        $connection->exec('ALTER TABLE usuarios_migracao_tmp RENAME TO usuarios');
        $connection->exec('DROP TABLE usuarios_legacy_backup');
        $connection->exec('PRAGMA foreign_keys = ON');
    }
}

function db()
{
    static $connection;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $dataDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dataDirectory)) {
        mkdir($dataDirectory, 0775, true);
    }

    $databaseFile = $dataDirectory . DIRECTORY_SEPARATOR . 'bodycare.sqlite';
    $connection = new PDO('sqlite:' . $databaseFile);
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $connection->exec('PRAGMA foreign_keys = ON');
    ensureDatabaseSchema($connection);

    return $connection;
}
