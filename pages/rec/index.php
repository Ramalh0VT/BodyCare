<?php
require_once __DIR__ . '/../php/layout.php';
$user = requireProfile(['recepcao']);
$database = db(); $message = null;
$validStatuses = ['agendada', 'chegou', 'em_triagem', 'em_atendimento', 'concluida', 'cancelada'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'schedule') {
            $clientId = (int) ($_POST['cliente_id'] ?? 0); $doctorId = (int) ($_POST['medico_id'] ?? 0); $date = trim($_POST['inicio'] ?? ''); $specialty = trim($_POST['especialidade'] ?? ''); $type = $_POST['tipo'] ?? '';
            if (!$clientId || !$doctorId || $date === '' || $specialty === '' || !in_array($type, ['consulta', 'retorno'], true)) throw new InvalidArgumentException('Cliente, medico, data, especialidade e tipo validos sao obrigatorios.');
            $doctor = $database->prepare("SELECT id FROM usuarios WHERE id = ? AND perfil = 'medico' AND status = 'ativo'"); $doctor->execute([$doctorId]); if (!$doctor->fetch()) throw new InvalidArgumentException('Medico invalido.');
            $check = $database->prepare("SELECT COUNT(*) FROM agendamentos WHERE medico_id = ? AND inicio = ? AND status NOT IN ('cancelada','concluida')"); $check->execute([$doctorId, $date]);
            if ((int) $check->fetchColumn() > 0) throw new InvalidArgumentException('Horario indisponivel para este medico.');
            $database->prepare('INSERT INTO agendamentos (cliente_id, medico_id, especialidade, tipo, inicio, observacao) VALUES (?, ?, ?, ?, ?, ?)')->execute([$clientId, $doctorId, $specialty, $type, $date, trim($_POST['observacao'] ?? '')]); $message = 'Agendamento criado.';
        } elseif ($action === 'arrival') {
            $appointmentId = (int) ($_POST['agendamento_id'] ?? 0); $appointment = $database->prepare("SELECT a.cliente_id FROM agendamentos a WHERE a.id = ? AND a.status = 'agendada'"); $appointment->execute([$appointmentId]); $appointment = $appointment->fetch();
            if (!$appointment) throw new InvalidArgumentException('Agendamento nao encontrado ou indisponivel.');
            $duplicate = $database->prepare('SELECT COUNT(*) FROM chegadas WHERE agendamento_id = ?'); $duplicate->execute([$appointmentId]); if ((int) $duplicate->fetchColumn() > 0) throw new InvalidArgumentException('A chegada deste agendamento ja foi registrada.');
            $database->beginTransaction(); $database->prepare('INSERT INTO chegadas (agendamento_id, cliente_id, chegada_em, recepcionista_id, motivo, observacao) VALUES (?, ?, CURRENT_TIMESTAMP, ?, ?, ?)')->execute([$appointmentId, $appointment['cliente_id'], $user['id'], trim($_POST['motivo'] ?? ''), trim($_POST['observacao'] ?? '')]); $database->prepare('UPDATE agendamentos SET status = ? WHERE id = ?')->execute(['chegou', $appointmentId]); $database->commit(); $message = 'Chegada registrada e enviada para triagem.';
        } elseif ($action === 'cancel') {
            $reason = trim($_POST['motivo_cancelamento'] ?? ''); if ($reason === '') throw new InvalidArgumentException('Motivo do cancelamento obrigatorio.');
            $database->prepare("UPDATE agendamentos SET status = 'cancelada', observacao = ? WHERE id = ? AND status NOT IN ('concluida','cancelada')")->execute([$reason, (int) $_POST['id']]); $message = 'Consulta cancelada.';
        }
    } catch (Throwable $exception) { if ($database->inTransaction()) $database->rollBack(); $message = 'Erro: ' . $exception->getMessage(); }
}
$clients = $database->query("SELECT c.id, u.nome FROM clientes c JOIN usuarios u ON u.id = c.usuario_id ORDER BY u.nome")->fetchAll();
$doctors = $database->query("SELECT id, nome FROM usuarios WHERE perfil = 'medico' AND status = 'ativo' ORDER BY nome")->fetchAll();
$appointments = $database->query("SELECT a.*, client.nome AS cliente, doctor.nome AS medico FROM agendamentos a JOIN clientes c ON c.id = a.cliente_id JOIN usuarios client ON client.id = c.usuario_id LEFT JOIN usuarios doctor ON doctor.id = a.medico_id ORDER BY a.inicio")->fetchAll();
pageStart('Recepcao e agenda', $user); if ($message) echo '<p role="status">' . e($message) . '</p>';
echo '<h2>Agenda</h2><table><tr><th>Cliente</th><th>Medico</th><th>Inicio</th><th>Tipo</th><th>Especialidade</th><th>Status</th><th>Acoes</th></tr>';
foreach ($appointments as $item) echo '<tr><td>' . e($item['cliente']) . '</td><td>' . e($item['medico'] ?: 'Nao definido') . '</td><td>' . e($item['inicio']) . '</td><td>' . e($item['tipo']) . '</td><td>' . e($item['especialidade']) . '</td><td>' . e($item['status']) . '</td><td><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="arrival"><input type="hidden" name="agendamento_id" value="' . e($item['id']) . '"><button type="submit">Registrar chegada</button></form><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="' . e($item['id']) . '"><input name="motivo_cancelamento" required placeholder="Motivo"><button type="submit">Cancelar</button></form></td></tr>';
echo '</table><h2>Novo agendamento</h2><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="schedule"><label>Cliente: <select name="cliente_id" required><option value="">Selecione</option>'; foreach ($clients as $client) echo '<option value="' . e($client['id']) . '">' . e($client['nome']) . '</option>'; echo '</select></label><br><label>Medico: <select name="medico_id" required><option value="">Selecione</option>'; foreach ($doctors as $doctor) echo '<option value="' . e($doctor['id']) . '">' . e($doctor['nome']) . '</option>'; echo '</select></label><br>'; formField('Inicio', 'inicio', 'datetime-local'); formField('Especialidade', 'especialidade'); echo '<label>Tipo: <select name="tipo" required><option value="">Selecione</option><option>consulta</option><option>retorno</option></select></label><br>'; formField('Observacao', 'observacao', 'text', '', false); echo '<button type="submit">Agendar</button></form>'; pageEnd();
