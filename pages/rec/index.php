<?php
require_once __DIR__ . '/../php/layout.php';
require_once __DIR__ . '/../php/crud.php';

$user = requireProfile(['recepcao']);
$database = db();
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    try {
        $action = $_POST['action'] ?? '';
        $appointmentId = (int) ($_POST['agendamento_id'] ?? 0);

        if ($action === 'schedule' || $action === 'reschedule') {
            $clientId = (int) ($_POST['cliente_id'] ?? 0);
            $doctorId = (int) ($_POST['medico_id'] ?? 0);
            $start = trim($_POST['inicio'] ?? '');
            $specialty = trim($_POST['especialidade'] ?? '');
            $type = $_POST['tipo'] ?? '';

            if ($clientId <= 0 || $doctorId <= 0 || $start === '' || $specialty === '' || !in_array($type, ['consulta', 'retorno'], true)) {
                throw new InvalidArgumentException('Cliente, medico, data, especialidade e tipo validos sao obrigatorios.');
            }

            $clientCheck = $database->prepare('SELECT id FROM clientes WHERE id = ?');
            $clientCheck->execute([$clientId]);
            $doctorCheck = $database->prepare("SELECT id FROM usuarios WHERE id = ? AND perfil = 'medico' AND status = 'ativo'");
            $doctorCheck->execute([$doctorId]);
            if (!$clientCheck->fetch() || !$doctorCheck->fetch()) {
                throw new InvalidArgumentException('Cliente ou medico invalido.');
            }

            $conflict = $database->prepare("SELECT COUNT(*) FROM agendamentos WHERE medico_id = ? AND inicio = ? AND id <> ? AND status NOT IN ('cancelada', 'concluida')");
            $conflict->execute([$doctorId, $start, $action === 'reschedule' ? $appointmentId : 0]);
            if ((int) $conflict->fetchColumn() > 0) {
                throw new InvalidArgumentException('Horario indisponivel para este medico.');
            }

            if ($action === 'reschedule') {
                $statement = $database->prepare("UPDATE agendamentos SET cliente_id = ?, medico_id = ?, especialidade = ?, tipo = ?, inicio = ?, observacao = ? WHERE id = ? AND status NOT IN ('concluida', 'cancelada')");
                $statement->execute([$clientId, $doctorId, $specialty, $type, $start, trim($_POST['observacao'] ?? ''), $appointmentId]);
                $message = $statement->rowCount() ? 'Agendamento remarcado.' : 'Agendamento nao encontrado ou encerrado.';
            } else {
                $statement = $database->prepare('INSERT INTO agendamentos (cliente_id, medico_id, especialidade, tipo, inicio, observacao) VALUES (?, ?, ?, ?, ?, ?)');
                $statement->execute([$clientId, $doctorId, $specialty, $type, $start, trim($_POST['observacao'] ?? '')]);
                $message = 'Agendamento criado.';
            }
        } elseif ($action === 'arrival') {
            $reason = trim($_POST['motivo'] ?? '');
            if ($reason === '') {
                throw new InvalidArgumentException('O motivo da chegada e obrigatorio.');
            }
            $statement = $database->prepare("SELECT cliente_id FROM agendamentos WHERE id = ? AND status = 'agendada'");
            $statement->execute([$appointmentId]);
            $appointment = $statement->fetch();
            if (!$appointment) {
                throw new InvalidArgumentException('Agendamento nao encontrado ou indisponivel.');
            }

            $database->beginTransaction();
            $database->prepare('INSERT INTO chegadas (agendamento_id, cliente_id, recepcionista_id, motivo, observacao) VALUES (?, ?, ?, ?, ?)')->execute([$appointmentId, $appointment['cliente_id'], $user['id'], $reason, trim($_POST['observacao'] ?? '')]);
            $database->prepare("UPDATE agendamentos SET status = 'chegou' WHERE id = ?")->execute([$appointmentId]);
            $database->commit();
            $message = 'Chegada registrada e enviada para triagem.';
        } elseif ($action === 'cancel') {
            $reason = trim($_POST['motivo_cancelamento'] ?? '');
            if ($reason === '') {
                throw new InvalidArgumentException('Motivo do cancelamento obrigatorio.');
            }
            $statement = $database->prepare("UPDATE agendamentos SET status = 'cancelada', observacao = ? WHERE id = ? AND status NOT IN ('concluida', 'cancelada')");
            $statement->execute([$reason, $appointmentId]);
            $message = $statement->rowCount() ? 'Consulta cancelada.' : 'Consulta nao encontrada ou ja encerrada.';
        }
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        $message = 'Erro: ' . $exception->getMessage();
    }
}

$clients = listClients($database);
$doctors = listDoctors($database);
$appointments = $database->query("SELECT a.*, client.nome AS cliente, doctor.nome AS medico FROM agendamentos a JOIN clientes c ON c.id = a.cliente_id JOIN usuarios client ON client.id = c.usuario_id JOIN usuarios doctor ON doctor.id = a.medico_id ORDER BY a.inicio")->fetchAll();

pageStart('Recepcao e agenda', $user);
if ($message) {
    echo '<p role="status">' . e($message) . '</p>';
}
echo '<h2>Agenda</h2><table><tr><th>Cliente</th><th>Medico</th><th>Inicio</th><th>Tipo</th><th>Especialidade</th><th>Status</th><th>Acoes</th></tr>';
foreach ($appointments as $item) {
    echo '<tr><td>' . e($item['cliente']) . '</td><td>' . e($item['medico']) . '</td><td>' . e($item['inicio']) . '</td><td>' . e($item['tipo']) . '</td><td>' . e($item['especialidade']) . '</td><td>' . e($item['status']) . '</td><td>';
    if ($item['status'] === 'agendada') {
        echo '<form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="arrival"><input type="hidden" name="agendamento_id" value="' . e($item['id']) . '"><input name="motivo" placeholder="Motivo da chegada" required><input name="observacao" placeholder="Observacao"><button type="submit">Registrar chegada</button></form>';
        echo '<form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="reschedule"><input type="hidden" name="agendamento_id" value="' . e($item['id']) . '"><input type="hidden" name="cliente_id" value="' . e($item['cliente_id']) . '"><input type="hidden" name="tipo" value="' . e($item['tipo']) . '"><input name="medico_id" type="number" value="' . e($item['medico_id']) . '" required><input name="inicio" type="datetime-local" value="' . e(str_replace(' ', 'T', $item['inicio'])) . '" required><input name="especialidade" value="' . e($item['especialidade']) . '" required><input name="observacao" placeholder="Observacao"><button type="submit">Remarcar</button></form>';
        echo '<form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="cancel"><input type="hidden" name="agendamento_id" value="' . e($item['id']) . '"><input name="motivo_cancelamento" placeholder="Motivo" required><button type="submit">Cancelar</button></form>';
    }
    echo '</td></tr>';
}
echo '</table><h2>Novo agendamento</h2><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="schedule"><label>Cliente: <select name="cliente_id" required><option value="">Selecione</option>';
foreach ($clients as $client) {
    echo '<option value="' . e($client['id']) . '">' . e($client['nome']) . '</option>';
}
echo '</select></label><br><label>Medico: <select name="medico_id" required><option value="">Selecione</option>';
foreach ($doctors as $doctor) {
    echo '<option value="' . e($doctor['id']) . '">' . e($doctor['nome']) . '</option>';
}
echo '</select></label><br>';
formField('Inicio', 'inicio', 'datetime-local');
formField('Especialidade', 'especialidade');
echo '<label>Tipo: <select name="tipo" required><option value="">Selecione</option><option value="consulta">consulta</option><option value="retorno">retorno</option></select></label><br>';
formField('Observacao', 'observacao', 'text', '', false);
echo '<button type="submit">Agendar</button></form>';
pageEnd();
