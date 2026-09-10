<?php
require_once __DIR__ . '/../php/layout.php';
$user = requireProfile(['medico']);
$database = db();
$message = null;

function medicalAttendance(PDO $database, int $attendanceId, int $doctorId): array
{
    $statement = $database->prepare('SELECT * FROM atendimentos WHERE id = ? AND medico_id = ? LIMIT 1');
    $statement->execute([$attendanceId, $doctorId]);
    $attendance = $statement->fetch();
    if (!$attendance) {
        throw new InvalidArgumentException('Atendimento nao encontrado para este medico.');
    }
    return $attendance;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        $action = $_POST['action'] ?? '';
        $attendanceId = (int) ($_POST['atendimento_id'] ?? 0);
        if ($action === 'start') {
            $appointmentId = (int) ($_POST['agendamento_id'] ?? 0);
            $statement = $database->prepare("SELECT * FROM agendamentos WHERE id = ? AND medico_id = ? AND status IN ('agendada','chegou','em_triagem') LIMIT 1");
            $statement->execute([$appointmentId, $user['id']]);
            $appointment = $statement->fetch();
            if (!$appointment) throw new InvalidArgumentException('Agendamento nao encontrado para este medico ou ja iniciado.');
            $database->beginTransaction();
            $database->prepare('INSERT INTO atendimentos (agendamento_id, cliente_id, medico_id) VALUES (?, ?, ?)')->execute([$appointmentId, $appointment['cliente_id'], $user['id']]);
            $database->prepare('UPDATE agendamentos SET status = ? WHERE id = ? AND medico_id = ?')->execute(['em_atendimento', $appointmentId, $user['id']]);
            $database->commit();
            $message = 'Atendimento iniciado.';
        } elseif ($action === 'diagnosis') {
            medicalAttendance($database, $attendanceId, $user['id']);
            $diagnosis = trim($_POST['diagnostico'] ?? '');
            if ($diagnosis === '') throw new InvalidArgumentException('Diagnostico obrigatorio.');
            $database->prepare('UPDATE atendimentos SET diagnostico = ? WHERE id = ? AND medico_id = ?')->execute([$diagnosis, $attendanceId, $user['id']]);
            $message = 'Diagnostico salvo.';
        } elseif ($action === 'exam') {
            medicalAttendance($database, $attendanceId, $user['id']);
            $description = trim($_POST['descricao'] ?? '');
            if ($description === '' || !in_array($_POST['prioridade'] ?? '', ['normal', 'urgente'], true)) throw new InvalidArgumentException('Descricao e prioridade validas sao obrigatorias.');
            $database->prepare('INSERT INTO exames_solicitados (atendimento_id, descricao, prioridade, observacao) VALUES (?, ?, ?, ?)')->execute([$attendanceId, $description, $_POST['prioridade'], trim($_POST['observacao'] ?? '')]);
            $message = 'Exame solicitado.';
        } elseif ($action === 'prescription') {
            medicalAttendance($database, $attendanceId, $user['id']);
            $fields = ['medicamento', 'dose', 'frequencia', 'duracao'];
            foreach ($fields as $field) if (trim($_POST[$field] ?? '') === '') throw new InvalidArgumentException('Todos os campos da prescricao sao obrigatorios.');
            $database->prepare('INSERT INTO prescricoes (atendimento_id, medicamento, dose, frequencia, duracao, instrucoes) VALUES (?, ?, ?, ?, ?, ?)')->execute([$attendanceId, trim($_POST['medicamento']), trim($_POST['dose']), trim($_POST['frequencia']), trim($_POST['duracao']), trim($_POST['instrucoes'] ?? '')]);
            $message = 'Medicamento prescrito.';
        } elseif ($action === 'discharge') {
            $attendance = medicalAttendance($database, $attendanceId, $user['id']);
            $discharge = trim($_POST['alta'] ?? '');
            if (!$attendance['diagnostico'] || $discharge === '') throw new InvalidArgumentException('Diagnostico e orientacoes de alta sao obrigatorios.');
            $database->beginTransaction();
            $database->prepare("UPDATE atendimentos SET alta = ?, fim = CURRENT_TIMESTAMP, status = 'concluido' WHERE id = ? AND medico_id = ? AND status <> 'concluido'")->execute([$discharge, $attendanceId, $user['id']]);
            if ($attendance['agendamento_id']) $database->prepare('UPDATE agendamentos SET status = ? WHERE id = ? AND medico_id = ?')->execute(['concluida', $attendance['agendamento_id'], $user['id']]);
            $database->commit();
            $message = 'Alta registrada e consulta concluida.';
        } elseif ($action === 'admission') {
            medicalAttendance($database, $attendanceId, $user['id']);
            $bed = trim($_POST['leito'] ?? ''); $reason = trim($_POST['motivo'] ?? ''); $cost = (float) ($_POST['custo'] ?? -1);
            if ($bed === '' || $reason === '' || $cost < 0) throw new InvalidArgumentException('Leito, motivo e custo valido sao obrigatorios.');
            $database->prepare('INSERT INTO internacoes (paciente_id, atendimento_id, leito, motivo, custo) SELECT cliente_id, id, ?, ?, ? FROM atendimentos WHERE id = ? AND medico_id = ? AND status <> ?')->execute([$bed, $reason, $cost, $attendanceId, $user['id'], 'concluido']);
            $message = 'Internacao registrada.';
        }
    } catch (Throwable $exception) {
        if ($database->inTransaction()) $database->rollBack();
        $message = 'Erro: ' . $exception->getMessage();
    }
}

$appointments = $database->prepare("SELECT a.*, u.nome AS cliente FROM agendamentos a JOIN clientes c ON c.id = a.cliente_id JOIN usuarios u ON u.id = c.usuario_id WHERE a.medico_id = ? AND a.status IN ('agendada','chegou','em_triagem') ORDER BY a.inicio");
$appointments->execute([$user['id']]);
$appointments = $appointments->fetchAll();
$attendances = $database->prepare('SELECT atd.*, u.nome AS cliente FROM atendimentos atd JOIN clientes c ON c.id = atd.cliente_id JOIN usuarios u ON u.id = c.usuario_id WHERE atd.medico_id = ? ORDER BY atd.inicio DESC');
$attendances->execute([$user['id']]);
$attendances = $attendances->fetchAll();
$historyExams = $database->prepare('SELECT * FROM exames_solicitados WHERE atendimento_id = ? ORDER BY id DESC');
$historyPrescriptions = $database->prepare('SELECT * FROM prescricoes WHERE atendimento_id = ? ORDER BY id DESC');
$admissions = $database->query("SELECT i.*, u.nome AS paciente FROM internacoes i JOIN clientes c ON c.id = i.paciente_id JOIN usuarios u ON u.id = c.usuario_id WHERE i.status = 'aberta' ORDER BY i.entrada_em DESC")->fetchAll();

pageStart('Atendimento medico e internacao', $user);
if ($message) echo '<p role="status">' . e($message) . '</p>';
echo '<h2>Proximos atendimentos</h2><table><tr><th>Cliente</th><th>Inicio</th><th>Especialidade</th><th>Acao</th></tr>';
foreach ($appointments as $item) echo '<tr><td>' . e($item['cliente']) . '</td><td>' . e($item['inicio']) . '</td><td>' . e($item['especialidade']) . '</td><td><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="start"><input type="hidden" name="agendamento_id" value="' . e($item['id']) . '"><button type="submit">Iniciar</button></form></td></tr>';
echo '</table><h2>Prontuarios</h2>';
foreach ($attendances as $item) {
    $historyExams->execute([$item['id']]); $exams = $historyExams->fetchAll();
    $historyPrescriptions->execute([$item['id']]); $prescriptions = $historyPrescriptions->fetchAll();
    echo '<section><h3>ID ' . e($item['id']) . ' - ' . e($item['cliente']) . '</h3><p>Diagnostico: ' . e($item['diagnostico'] ?: 'Nao registrado') . '</p><h4>Exames</h4><ul>'; foreach ($exams as $exam) echo '<li>' . e($exam['descricao']) . ' (' . e($exam['prioridade']) . ')</li>'; echo '</ul><h4>Prescricoes</h4><ul>'; foreach ($prescriptions as $prescription) echo '<li>' . e($prescription['medicamento']) . ': ' . e($prescription['dose']) . ', ' . e($prescription['frequencia']) . ', ' . e($prescription['duracao']) . '</li>'; echo '</ul>';
    echo '<form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="atendimento_id" value="' . e($item['id']) . '"><label>Diagnostico: <textarea name="diagnostico" required></textarea></label><button name="action" value="diagnosis">Salvar</button></form><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="atendimento_id" value="' . e($item['id']) . '"><input name="descricao" placeholder="Exame" required><select name="prioridade"><option>normal</option><option>urgente</option></select><input name="observacao" placeholder="Observacao"><button name="action" value="exam">Solicitar exame</button></form><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="atendimento_id" value="' . e($item['id']) . '"><input name="medicamento" placeholder="Medicamento" required><input name="dose" placeholder="Dose" required><input name="frequencia" placeholder="Frequencia" required><input name="duracao" placeholder="Duracao" required><input name="instrucoes" placeholder="Instrucoes"><button name="action" value="prescription">Prescrever</button></form><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="atendimento_id" value="' . e($item['id']) . '"><textarea name="alta" placeholder="Orientacoes de alta" required></textarea><button name="action" value="discharge">Dar alta e concluir</button></form><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="atendimento_id" value="' . e($item['id']) . '"><input name="leito" placeholder="Leito" required><input name="motivo" placeholder="Motivo" required><input name="custo" type="number" step="0.01" min="0" placeholder="Custo" required><button name="action" value="admission">Encaminhar para internacao</button></form></section>';
}
echo '<h2>Internacoes abertas</h2><table><tr><th>Paciente</th><th>Leito</th><th>Entrada</th><th>Custo</th></tr>'; foreach ($admissions as $item) echo '<tr><td>' . e($item['paciente']) . '</td><td>' . e($item['leito']) . '</td><td>' . e($item['entrada_em']) . '</td><td>' . e($item['custo']) . '</td></tr>'; echo '</table>'; pageEnd();
