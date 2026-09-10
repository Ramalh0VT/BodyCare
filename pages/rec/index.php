<?php
require_once __DIR__ . '/../php/layout.php';
$user = requireProfile(['recepcao']);
$database = db(); $message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'schedule') {
            $clientId = (int) ($_POST['cliente_id'] ?? 0); $date = trim($_POST['inicio'] ?? ''); $specialty = trim($_POST['especialidade'] ?? '');
            if (!$clientId || $date === '' || $specialty === '') throw new InvalidArgumentException('Cliente, data e especialidade sao obrigatorios.');
            $check = $database->prepare('SELECT COUNT(*) FROM agendamentos WHERE inicio = ? AND status = ?'); $check->execute([$date, 'agendada']);
            if ((int) $check->fetchColumn() > 0) throw new InvalidArgumentException('Horario indisponivel.');
            $database->prepare('INSERT INTO agendamentos (cliente_id, especialidade, tipo, inicio, observacao) VALUES (?, ?, ?, ?, ?)')->execute([$clientId, $specialty, $_POST['tipo'] ?? 'consulta', $date, trim($_POST['observacao'] ?? '')]); $message = 'Agendamento criado.';
        } elseif ($action === 'arrival') {
            $appointmentId = (int) ($_POST['agendamento_id'] ?? 0); $appointment = $database->prepare('SELECT cliente_id FROM agendamentos WHERE id = ?'); $appointment->execute([$appointmentId]); $appointment = $appointment->fetch();
            if (!$appointment) throw new InvalidArgumentException('Agendamento nao encontrado.');
            $database->prepare('INSERT INTO chegadas (agendamento_id, cliente_id, chegada_em, recepcionista_id, motivo, observacao) VALUES (?, ?, CURRENT_TIMESTAMP, ?, ?, ?)')->execute([$appointmentId, $appointment['cliente_id'], $user['id'], trim($_POST['motivo'] ?? ''), trim($_POST['observacao'] ?? '')]); $database->prepare('UPDATE agendamentos SET status = ? WHERE id = ?')->execute(['chegou', $appointmentId]); $message = 'Chegada registrada e enviada para triagem.';
        } elseif ($action === 'status') {
            $database->prepare('UPDATE agendamentos SET status = ? WHERE id = ?')->execute([$_POST['status'] ?? 'cancelada', (int) $_POST['id']]); $message = 'Status atualizado.';
        }
    } catch (Throwable $exception) { $message = 'Erro: ' . $exception->getMessage(); }
}
$clients = $database->query("SELECT c.id, u.nome FROM clientes c JOIN usuarios u ON u.id = c.usuario_id ORDER BY u.nome")->fetchAll();
$appointments = $database->query("SELECT a.*, u.nome AS cliente FROM agendamentos a JOIN clientes c ON c.id = a.cliente_id JOIN usuarios u ON u.id = c.usuario_id ORDER BY a.inicio")->fetchAll();
pageStart('Recepcao e agenda', $user); if ($message) echo '<p role="status">' . e($message) . '</p>';
echo '<h2>Agenda</h2><table><tr><th>Cliente</th><th>Inicio</th><th>Tipo</th><th>Especialidade</th><th>Status</th><th>Acoes</th></tr>';
foreach ($appointments as $item) echo '<tr><td>' . e($item['cliente']) . '</td><td>' . e($item['inicio']) . '</td><td>' . e($item['tipo']) . '</td><td>' . e($item['especialidade']) . '</td><td>' . e($item['status']) . '</td><td><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="arrival"><input type="hidden" name="agendamento_id" value="' . e($item['id']) . '"><button type="submit">Registrar chegada</button></form><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="' . e($item['id']) . '"><input type="hidden" name="status" value="cancelada"><button type="submit">Cancelar</button></form></td></tr>';
echo '</table><h2>Novo agendamento</h2><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="schedule"><label>Cliente: <select name="cliente_id" required><option value="">Selecione</option>'; foreach ($clients as $client) echo '<option value="' . e($client['id']) . '">' . e($client['nome']) . '</option>'; echo '</select></label><br>'; formField('Inicio', 'inicio', 'datetime-local'); formField('Especialidade', 'especialidade'); echo '<label>Tipo: <select name="tipo"><option>consulta</option><option>retorno</option></select></label><br>'; formField('Observacao', 'observacao', 'text', '', false); echo '<button type="submit">Agendar</button></form>'; pageEnd();
