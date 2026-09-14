<?php
require_once __DIR__ . '/../php/layout.php';
$user = requireProfile(['medico', 'enfermeiro']);
$database = db(); $message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        $action = $_POST['action'] ?? '';
        $admissionId = (int) ($_POST['internacao_id'] ?? 0);
        if ($action === 'evolution') {
            $text = trim($_POST['texto'] ?? '');
            if ($text === '') throw new InvalidArgumentException('Texto da evolucao obrigatorio.');
            $statement = $database->prepare("INSERT INTO evolucoes (internacao_id, profissional_id, texto) SELECT id, ?, ? FROM internacoes WHERE id = ? AND status = 'aberta'");
            $statement->execute([$user['id'], $text, $admissionId]);
            if (!$statement->rowCount()) throw new InvalidArgumentException('Internacao inexistente ou ja recebeu alta.');
            $message = 'Evolucao registrada.';
        } elseif ($action === 'discharge') {
            $current = $database->prepare("SELECT leito, status FROM internacoes WHERE id = ? AND status = 'aberta'");
            $current->execute([$admissionId]);
            $admission = $current->fetch();
            if (!$admission) throw new InvalidArgumentException('Internacao inexistente ou ja recebeu alta.');
            $database->beginTransaction();
            $database->prepare("UPDATE internacoes SET alta_em = CURRENT_TIMESTAMP, status = 'alta' WHERE id = ? AND status = 'aberta'")->execute([$admissionId]);
            $database->prepare("INSERT INTO historico_internacoes (internacao_id, profissional_id, leito, status) VALUES (?, ?, ?, 'alta')")->execute([$admissionId, $user['id'], $admission['leito']]);
            $database->commit();
            $message = 'Alta da internacao registrada.';
        } elseif ($action === 'update') {
            $bed = trim($_POST['leito'] ?? '');
            $status = $_POST['status'] ?? '';
            if ($bed === '' || !in_array($status, ['aberta', 'alta'], true)) throw new InvalidArgumentException('Leito e status validos sao obrigatorios.');
            $current = $database->prepare('SELECT leito, status FROM internacoes WHERE id = ?');
            $current->execute([$admissionId]);
            $oldAdmission = $current->fetch();
            if (!$oldAdmission) throw new InvalidArgumentException('Internacao nao encontrada.');
            if ($oldAdmission['status'] === 'alta' && $status === 'aberta') throw new InvalidArgumentException('Internacao em alta nao pode ser reaberta.');
            $database->beginTransaction();
            $database->prepare("UPDATE internacoes SET leito = ?, status = ?, alta_em = CASE WHEN ? = 'alta' THEN COALESCE(alta_em, CURRENT_TIMESTAMP) ELSE NULL END WHERE id = ?")->execute([$bed, $status, $status, $admissionId]);
            $database->prepare('INSERT INTO historico_internacoes (internacao_id, profissional_id, leito, status) VALUES (?, ?, ?, ?)')->execute([$admissionId, $user['id'], $bed, $status]);
            $database->commit();
            $message = 'Internacao atualizada e historico registrado.';
        }
    } catch (Throwable $exception) { $message = 'Erro: ' . $exception->getMessage(); }
}
$admissions = $database->query("SELECT i.*, u.nome AS paciente FROM internacoes i JOIN clientes c ON c.id = i.paciente_id JOIN usuarios u ON u.id = c.usuario_id ORDER BY CASE i.status WHEN 'aberta' THEN 1 ELSE 2 END, i.entrada_em DESC")->fetchAll();
pageStart('Acompanhamento de internacoes', $user); if ($message) echo '<p role="status">' . e($message) . '</p>';
echo '<table><tr><th>Paciente</th><th>Leito</th><th>Entrada</th><th>Status</th><th>Motivo</th><th>Custo</th><th>Acoes</th></tr>';
foreach ($admissions as $item) {
    echo '<tr><td>' . e($item['paciente']) . '</td><td>' . e($item['leito']) . '</td><td>' . e($item['entrada_em']) . '</td><td>' . e($item['status']) . '</td><td>' . e($item['motivo']) . '</td><td>' . e($item['custo']) . '</td><td>';
    if ($item['status'] === 'aberta') {
        echo '<form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="internacao_id" value="' . e($item['id']) . '"><textarea name="texto" required></textarea><button name="action" value="evolution">Registrar evolucao</button></form><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="internacao_id" value="' . e($item['id']) . '"><button name="action" value="discharge">Registrar alta</button></form>';
    }
    echo '<form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="internacao_id" value="' . e($item['id']) . '"><input name="leito" value="' . e($item['leito']) . '" required><select name="status"><option value="aberta"' . ($item['status'] === 'aberta' ? ' selected' : '') . '>aberta</option><option value="alta"' . ($item['status'] === 'alta' ? ' selected' : '') . '>alta</option></select><button name="action" value="update">Atualizar</button></form></td></tr>';
}
echo '</table>'; pageEnd();
