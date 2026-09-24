<?php
require_once __DIR__ . '/../php/layout.php';
require_once __DIR__ . '/../php/crud.php';
$user = requireProfile(['medico', 'enfermeiro']);
$database = db();
$message = null;

function medicalAttendance(PDO $database, int $attendanceId, int $doctorId): array
{
    $statement = $database->prepare('SELECT * FROM atendimentos WHERE id = ? AND medico_id = ? LIMIT 1');
    $statement->execute([$attendanceId, $doctorId]);
    $attendance = $statement->fetch();
    if (!$attendance) {
        throw new InvalidArgumentException('Atendimento não encontrado para este médico.');
    }
    return $attendance;
}

function editableMedicalAttendance(array $attendance): void
{
    if ($attendance['status'] !== 'em_atendimento') {
        throw new InvalidArgumentException('Este atendimento já foi concluído.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        $action = $_POST['action'] ?? '';
        $attendanceId = (int) ($_POST['atendimento_id'] ?? 0);
        if ($action === 'start') {
            $appointmentId = (int) ($_POST['agendamento_id'] ?? 0);
            $statement = $database->prepare("SELECT * FROM agendamentos WHERE id = ? AND medico_id = ? AND status IN ('agendada','chegou') LIMIT 1");
            $statement->execute([$appointmentId, $user['id']]);
            $appointment = $statement->fetch();
            if (!$appointment) throw new InvalidArgumentException('Agendamento não encontrado para este médico ou já iniciado.');
            $database->beginTransaction();
            $database->prepare('INSERT INTO atendimentos (agendamento_id, cliente_id, medico_id, especialidade) VALUES (?, ?, ?, ?)')->execute([$appointmentId, $appointment['cliente_id'], $user['id'], $appointment['especialidade']]);
            $database->prepare('UPDATE agendamentos SET status = ? WHERE id = ? AND medico_id = ?')->execute(['em_atendimento', $appointmentId, $user['id']]);
            $database->commit();
            $message = 'Atendimento iniciado.';
        } elseif ($action === 'diagnosis') {
            $attendance = medicalAttendance($database, $attendanceId, $user['id']); editableMedicalAttendance($attendance);
            $diagnosis = trim($_POST['diagnostico'] ?? '');
            if ($diagnosis === '') throw new InvalidArgumentException('Diagnóstico obrigatório.');
            $database->prepare('UPDATE atendimentos SET diagnostico = ? WHERE id = ? AND medico_id = ?')->execute([$diagnosis, $attendanceId, $user['id']]);
            $message = 'Diagnóstico salvo.';
        } elseif ($action === 'exam') {
            $attendance = medicalAttendance($database, $attendanceId, $user['id']); editableMedicalAttendance($attendance);
            $description = trim($_POST['descricao'] ?? '');
            if ($description === '' || !in_array($_POST['prioridade'] ?? '', ['normal', 'urgente'], true)) throw new InvalidArgumentException('Descrição e prioridade válidas são obrigatórias.');
            $database->prepare('INSERT INTO exames_solicitados (atendimento_id, descricao, prioridade, observacao) VALUES (?, ?, ?, ?)')->execute([$attendanceId, $description, $_POST['prioridade'], trim($_POST['observacao'] ?? '')]);
            $message = 'Exame solicitado.';
        } elseif ($action === 'prescription') {
            $attendance = medicalAttendance($database, $attendanceId, $user['id']); editableMedicalAttendance($attendance);
            $fields = ['medicamento', 'dose', 'frequencia', 'duracao'];
            foreach ($fields as $field) if (trim($_POST[$field] ?? '') === '') throw new InvalidArgumentException('Todos os campos da prescrição são obrigatórios.');
            $database->prepare('INSERT INTO prescricoes (atendimento_id, medicamento, dose, frequencia, duracao, instrucoes) VALUES (?, ?, ?, ?, ?, ?)')->execute([$attendanceId, trim($_POST['medicamento']), trim($_POST['dose']), trim($_POST['frequencia']), trim($_POST['duracao']), trim($_POST['instrucoes'] ?? '')]);
            $message = 'Medicamento prescrito.';
        } elseif ($action === 'discharge') {
            $attendance = medicalAttendance($database, $attendanceId, $user['id']);
            editableMedicalAttendance($attendance);
            $discharge = trim($_POST['alta'] ?? '');
            if (!$attendance['diagnostico'] || $discharge === '') throw new InvalidArgumentException('Diagnóstico e orientações de alta são obrigatórios.');
            $database->beginTransaction();
            $database->prepare("UPDATE atendimentos SET alta = ?, fim = CURRENT_TIMESTAMP, status = 'concluido' WHERE id = ? AND medico_id = ? AND status <> 'concluido'")->execute([$discharge, $attendanceId, $user['id']]);
            if ($attendance['agendamento_id']) $database->prepare('UPDATE agendamentos SET status = ? WHERE id = ? AND medico_id = ?')->execute(['concluida', $attendance['agendamento_id'], $user['id']]);
            $database->commit();
            $message = 'Alta registrada e consulta concluída.';
        } elseif ($action === 'admission') {
            $attendance = medicalAttendance($database, $attendanceId, $user['id']); editableMedicalAttendance($attendance);
            $bed = trim($_POST['leito'] ?? ''); $reason = trim($_POST['motivo'] ?? ''); $costCents = moneyToCents($_POST['custo'] ?? '0');
            if ($bed === '' || $reason === '' || $costCents < 0) throw new InvalidArgumentException('Leito, motivo e custo válidos são obrigatórios.');
            $cost = centsToMoney($costCents);
            $database->prepare('INSERT INTO internacoes (paciente_id, atendimento_id, leito, motivo, custo) SELECT cliente_id, id, ?, ?, ? FROM atendimentos WHERE id = ? AND medico_id = ? AND status <> ?')->execute([$bed, $reason, $cost, $attendanceId, $user['id'], 'concluido']);
            $message = 'Internação registrada.';
        }
    } catch (Throwable $exception) {
        if ($database->inTransaction()) $database->rollBack();
        $message = 'Erro: ' . $exception->getMessage();
    }
}

$appointments = $database->prepare("SELECT a.*, u.nome AS cliente FROM agendamentos a JOIN clientes c ON c.id = a.cliente_id JOIN usuarios u ON u.id = c.usuario_id WHERE a.medico_id = ? AND a.status IN ('agendada', 'chegou') ORDER BY a.inicio");
$appointments->execute([$user['id']]);
$appointments = $appointments->fetchAll();
$attendances = $database->prepare('SELECT atd.*, u.nome AS cliente FROM atendimentos atd JOIN clientes c ON c.id = atd.cliente_id JOIN usuarios u ON u.id = c.usuario_id WHERE atd.medico_id = ? ORDER BY atd.inicio DESC');
$attendances->execute([$user['id']]);
$attendances = $attendances->fetchAll();
$historyExams = $database->prepare('SELECT * FROM exames_solicitados WHERE atendimento_id = ? ORDER BY id DESC');
$historyPrescriptions = $database->prepare('SELECT * FROM prescricoes WHERE atendimento_id = ? ORDER BY id DESC');
$admissions = $database->query("SELECT i.*, u.nome AS paciente FROM internacoes i JOIN clientes c ON c.id = i.paciente_id JOIN usuarios u ON u.id = c.usuario_id WHERE i.status = 'aberta' ORDER BY i.entrada_em DESC")->fetchAll();

pageStart('Atendimento médico e internação', $user);
if ($message) echo '<p role="status">' . e($message) . '</p>';
echo '<h2>Próximos atendimentos</h2><table><tr><th>Cliente</th><th>Início</th><th>Especialidade</th><th>Ação</th></tr>';
foreach ($appointments as $item) echo '<tr><td>' . e($item['cliente']) . '</td><td>' . e($item['inicio']) . '</td><td>' . e($item['especialidade']) . '</td><td><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="start"><input type="hidden" name="agendamento_id" value="' . e($item['id']) . '"><button type="submit">Iniciar</button></form></td></tr>';
echo '</table><h2>Prontuários</h2>';
foreach ($attendances as $item) {
    $historyExams->execute([$item['id']]); $exams = $historyExams->fetchAll();
    $historyPrescriptions->execute([$item['id']]); $prescriptions = $historyPrescriptions->fetchAll();
    echo '<section><h3>ID ' . e($item['id']) . ' - ' . e($item['cliente']) . '</h3><p>Diagnóstico: ' . e($item['diagnostico'] ?: 'Não registrado') . '</p><h4>Exames</h4><ul>'; foreach ($exams as $exam) echo '<li>' . e($exam['descricao']) . ' (' . e($exam['prioridade']) . ')</li>'; echo '</ul><h4>Prescrições</h4><ul>'; foreach ($prescriptions as $prescription) echo '<li>' . e($prescription['medicamento']) . ': ' . e($prescription['dose']) . ', ' . e($prescription['frequencia']) . ', ' . e($prescription['duracao']) . '</li>'; echo '</ul>';
    echo '<form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="atendimento_id" value="' . e($item['id']) . '"><label>Diagnóstico: <textarea name="diagnostico" required></textarea></label><button name="action" value="diagnosis">Salvar</button></form><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="atendimento_id" value="' . e($item['id']) . '"><input name="descricao" placeholder="Exame" required><select name="prioridade"><option>normal</option><option>urgente</option></select><input name="observacao" placeholder="Observação"><button name="action" value="exam">Solicitar exame</button></form><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="atendimento_id" value="' . e($item['id']) . '"><input name="medicamento" placeholder="Medicamento" required><input name="dose" placeholder="Dose" required><input name="frequencia" placeholder="Frequência" required><input name="duracao" placeholder="Duração" required><input name="instrucoes" placeholder="Instruções"><button name="action" value="prescription">Prescrever</button></form><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="atendimento_id" value="' . e($item['id']) . '"><textarea name="alta" placeholder="Orientações de alta" required></textarea><button name="action" value="discharge">Dar alta e concluir</button></form><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="atendimento_id" value="' . e($item['id']) . '"><input name="leito" placeholder="Leito" required><input name="motivo" placeholder="Motivo" required><input name="custo" type="number" step="0.01" min="0" placeholder="Custo" required><button name="action" value="admission">Encaminhar para internação</button></form></section>';
}
echo '<h2>Internações abertas</h2><table><tr><th>Paciente</th><th>Leito</th><th>Entrada</th><th>Custo</th></tr>'; foreach ($admissions as $item) echo '<tr><td>' . e($item['paciente']) . '</td><td>' . e($item['leito']) . '</td><td>' . e($item['entrada_em']) . '</td><td>' . e($item['custo']) . '</td></tr>'; echo '</table>'; pageEnd();
