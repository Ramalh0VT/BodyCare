<?php
require_once __DIR__ . '/../php/layout.php';
$user = requireProfile(['admin']);
$database = db(); $message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0); $name = trim($_POST['nome'] ?? ''); $email = trim($_POST['email'] ?? ''); $profile = $_POST['perfil'] ?? '';
            if ($name === '' || $email === '' || !in_array($profile, ['admin', 'financeiro', 'medico', 'enfermeiro', 'recepcao', 'cliente'], true)) throw new InvalidArgumentException('Nome, e-mail e perfil são obrigatórios.');
            if ($id) {
                $current = $database->prepare('SELECT perfil, status FROM usuarios WHERE id = ?'); $current->execute([$id]); $currentUser = $current->fetch();
                if (!$currentUser) throw new InvalidArgumentException('Usuário não encontrado.');
                if ($currentUser['perfil'] === 'admin' && $currentUser['status'] === 'ativo' && $profile !== 'admin' && (int) $database->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'admin' AND status = 'ativo'")->fetchColumn() <= 1) throw new InvalidArgumentException('O último administrador ativo não pode perder o perfil.');
                $database->prepare('UPDATE usuarios SET nome = ?, email = ?, perfil = ? WHERE id = ?')->execute([$name, $email, $profile, $id]);
                if (strlen($_POST['senha'] ?? '') >= 6) $database->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')->execute([password_hash($_POST['senha'], PASSWORD_DEFAULT), $id]);
                $message = 'Usuário atualizado.';
            } else {
                if (strlen($_POST['senha'] ?? '') < 6) throw new InvalidArgumentException('A senha inicial deve ter ao menos seis caracteres.');
                $database->prepare('INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES (?, ?, ?, ?)')->execute([$name, $email, password_hash($_POST['senha'], PASSWORD_DEFAULT), $profile]);
                $newUserId = (int) $database->lastInsertId();
                if ($profile === 'cliente') $database->prepare('INSERT INTO clientes (usuario_id) VALUES (?)')->execute([$newUserId]);
                $message = 'Usuário criado.';
            }
        } elseif ($action === 'toggle') {
            $id = (int) $_POST['id']; $target = $database->prepare('SELECT * FROM usuarios WHERE id = ?'); $target->execute([$id]); $targetUser = $target->fetch();
            if (!$targetUser) throw new InvalidArgumentException('Usuário não encontrado.');
            if ($targetUser['perfil'] === 'admin' && $targetUser['status'] === 'ativo' && (int) $database->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'admin' AND status = 'ativo'")->fetchColumn() <= 1) throw new InvalidArgumentException('O último administrador ativo não pode ser desativado.');
            $database->prepare('UPDATE usuarios SET status = CASE status WHEN ? THEN ? ELSE ? END WHERE id = ?')->execute(['ativo', 'inativo', 'ativo', $id]); $message = 'Status atualizado.';
        } elseif ($action === 'delete') {
            $id = (int) $_POST['id']; if ($id === (int) $user['id']) throw new InvalidArgumentException('O usuário atual não pode ser excluído.');
            $target = $database->prepare('SELECT perfil, status FROM usuarios WHERE id = ?'); $target->execute([$id]); $targetUser = $target->fetch();
            if (!$targetUser) throw new InvalidArgumentException('Usuário não encontrado.');
            if ($targetUser['perfil'] === 'admin' && $targetUser['status'] === 'ativo' && (int) $database->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'admin' AND status = 'ativo'")->fetchColumn() <= 1) throw new InvalidArgumentException('O último administrador ativo não pode ser excluído.');
            $references = 0;
            foreach (['agendamentos' => 'medico_id', 'chegadas' => 'recepcionista_id', 'atendimentos' => 'medico_id', 'evolucoes' => 'profissional_id', 'pagamentos' => 'responsavel_id'] as $table => $column) {
                $reference = $database->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' = ?'); $reference->execute([$id]); $references += (int) $reference->fetchColumn();
            }
            if ($references > 0) throw new InvalidArgumentException('Usuário possui registros vinculados e não pode ser excluído.');
            $database->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]); $message = 'Usuário excluído quando não havia dependência ativa.';
        }
    } catch (Throwable $exception) { $message = 'Erro: ' . $exception->getMessage(); }
}
$users = $database->query('SELECT id, nome, email, perfil, status FROM usuarios ORDER BY nome')->fetchAll();
$editId = (int) ($_GET['editar'] ?? 0); $editUser = null;
if ($editId) { $editStatement = $database->prepare('SELECT * FROM usuarios WHERE id = ?'); $editStatement->execute([$editId]); $editUser = $editStatement->fetch(); }
pageStart('Administração de usuários', $user); if ($message) echo '<p role="status">' . e($message) . '</p>';
echo '<h2>Usuários</h2><table><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th><th>Ações</th></tr>';
foreach ($users as $item) echo '<tr><td>' . e($item['nome']) . '</td><td>' . e($item['email']) . '</td><td>' . e($item['perfil']) . '</td><td>' . e($item['status']) . '</td><td><a href="?editar=' . e($item['id']) . '">Editar</a><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="' . e($item['id']) . '"><button>Alterar status</button></form><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . e($item['id']) . '"><button>Excluir</button></form></td></tr>';
echo '</table><h2>' . ($editUser ? 'Editar usuário' : 'Novo usuário') . '</h2><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="' . e($editUser['id'] ?? 0) . '">'; formField('Nome', 'nome', 'text', $editUser['nome'] ?? ''); formField('E-mail', 'email', 'email', $editUser['email'] ?? ''); formField('Senha nova', 'senha', 'password', '', !$editUser); echo '<label>Perfil: <select name="perfil" required>'; foreach (['admin', 'financeiro', 'medico', 'enfermeiro', 'recepcao', 'cliente'] as $profile) echo '<option ' . (($editUser['perfil'] ?? '') === $profile ? 'selected' : '') . '>' . e($profile) . '</option>'; echo '</select></label><br><button type="submit">Salvar usuário</button></form>'; pageEnd();
