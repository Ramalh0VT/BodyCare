<?php
require_once __DIR__ . '/../php/layout.php';
$user = requireProfile(['admin']);
$database = db(); $message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0); $name = trim($_POST['nome'] ?? ''); $identifier = trim($_POST['identificador'] ?? ''); $profile = $_POST['perfil'] ?? '';
            if ($name === '' || $identifier === '' || !in_array($profile, ['admin', 'financeiro', 'medico', 'enfermeiro', 'recepcao', 'cliente'], true)) throw new InvalidArgumentException('Nome, identificador e perfil sao obrigatorios.');
            if ($id) {
                $current = $database->prepare('SELECT perfil, status FROM usuarios WHERE id = ?'); $current->execute([$id]); $currentUser = $current->fetch();
                if (!$currentUser) throw new InvalidArgumentException('Usuario nao encontrado.');
                if ($currentUser['perfil'] === 'admin' && $currentUser['status'] === 'ativo' && $profile !== 'admin' && (int) $database->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'admin' AND status = 'ativo'")->fetchColumn() <= 1) throw new InvalidArgumentException('O ultimo administrador ativo nao pode perder o perfil.');
                $database->prepare('UPDATE usuarios SET nome = ?, identificador = ?, perfil = ? WHERE id = ?')->execute([$name, $identifier, $profile, $id]);
                if (strlen($_POST['senha'] ?? '') >= 6) $database->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')->execute([password_hash($_POST['senha'], PASSWORD_DEFAULT), $id]);
                $message = 'Usuario atualizado.';
            } else {
                if (strlen($_POST['senha'] ?? '') < 6) throw new InvalidArgumentException('A senha inicial deve ter ao menos seis caracteres.');
                $database->prepare('INSERT INTO usuarios (nome, identificador, senha_hash, perfil) VALUES (?, ?, ?, ?)')->execute([$name, $identifier, password_hash($_POST['senha'], PASSWORD_DEFAULT), $profile]);
                $newUserId = (int) $database->lastInsertId();
                if ($profile === 'cliente') $database->prepare('INSERT INTO clientes (usuario_id) VALUES (?)')->execute([$newUserId]);
                $message = 'Usuario criado.';
            }
        } elseif ($action === 'toggle') {
            $id = (int) $_POST['id']; $target = $database->prepare('SELECT * FROM usuarios WHERE id = ?'); $target->execute([$id]); $targetUser = $target->fetch();
            if (!$targetUser) throw new InvalidArgumentException('Usuario nao encontrado.');
            if ($targetUser['perfil'] === 'admin' && $targetUser['status'] === 'ativo' && (int) $database->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'admin' AND status = 'ativo'")->fetchColumn() <= 1) throw new InvalidArgumentException('O ultimo administrador ativo nao pode ser desativado.');
            $database->prepare('UPDATE usuarios SET status = CASE status WHEN ? THEN ? ELSE ? END WHERE id = ?')->execute(['ativo', 'inativo', 'ativo', $id]); $message = 'Status atualizado.';
        } elseif ($action === 'delete') {
            $id = (int) $_POST['id']; if ($id === (int) $user['id']) throw new InvalidArgumentException('O usuario atual nao pode ser excluido.');
            $target = $database->prepare('SELECT perfil, status FROM usuarios WHERE id = ?'); $target->execute([$id]); $targetUser = $target->fetch();
            if (!$targetUser) throw new InvalidArgumentException('Usuario nao encontrado.');
            if ($targetUser['perfil'] === 'admin' && $targetUser['status'] === 'ativo' && (int) $database->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'admin' AND status = 'ativo'")->fetchColumn() <= 1) throw new InvalidArgumentException('O ultimo administrador ativo nao pode ser excluido.');
            $references = 0;
            foreach (['agendamentos' => 'medico_id', 'chegadas' => 'recepcionista_id', 'triagens' => 'enfermeiro_id', 'atendimentos' => 'medico_id', 'evolucoes' => 'profissional_id', 'pagamentos' => 'responsavel_id'] as $table => $column) {
                $reference = $database->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' = ?'); $reference->execute([$id]); $references += (int) $reference->fetchColumn();
            }
            if ($references > 0) throw new InvalidArgumentException('Usuario possui registros vinculados e nao pode ser excluido.');
            $database->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]); $message = 'Usuario excluido quando nao havia dependencia ativa.';
        }
    } catch (Throwable $exception) { $message = 'Erro: ' . $exception->getMessage(); }
}
$users = $database->query('SELECT id, nome, identificador, perfil, status FROM usuarios ORDER BY nome')->fetchAll();
$editId = (int) ($_GET['editar'] ?? 0); $editUser = null;
if ($editId) { $editStatement = $database->prepare('SELECT * FROM usuarios WHERE id = ?'); $editStatement->execute([$editId]); $editUser = $editStatement->fetch(); }
pageStart('Administracao de usuarios', $user); if ($message) echo '<p role="status">' . e($message) . '</p>';
echo '<h2>Usuarios</h2><table><tr><th>Nome</th><th>Identificador</th><th>Perfil</th><th>Status</th><th>Acoes</th></tr>';
foreach ($users as $item) echo '<tr><td>' . e($item['nome']) . '</td><td>' . e($item['identificador']) . '</td><td>' . e($item['perfil']) . '</td><td>' . e($item['status']) . '</td><td><a href="?editar=' . e($item['id']) . '">Editar</a><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="' . e($item['id']) . '"><button>Alterar status</button></form><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . e($item['id']) . '"><button>Excluir</button></form></td></tr>';
echo '</table><h2>' . ($editUser ? 'Editar usuario' : 'Novo usuario') . '</h2><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="' . e($editUser['id'] ?? 0) . '">'; formField('Nome', 'nome', 'text', $editUser['nome'] ?? ''); formField('Identificador', 'identificador', 'email', $editUser['identificador'] ?? ''); formField('Senha nova', 'senha', 'password', '', !$editUser); echo '<label>Perfil: <select name="perfil" required>'; foreach (['admin', 'financeiro', 'medico', 'enfermeiro', 'recepcao', 'cliente'] as $profile) echo '<option ' . (($editUser['perfil'] ?? '') === $profile ? 'selected' : '') . '>' . e($profile) . '</option>'; echo '</select></label><br><button type="submit">Salvar usuario</button></form>'; pageEnd();
