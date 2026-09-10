<?php
require_once __DIR__ . '/../php/layout.php';
$user = requireProfile(['enfermeiro']);
$database = db();
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $arrivalId = (int) ($_POST['chegada_id'] ?? 0);
    $level = $_POST['nivel'] ?? '';
    $specialty = trim($_POST['especialidade'] ?? '');
    $allowedLevels = ['emergencia', 'urgente', 'prioritario', 'eletivo'];
    $arrival = $database->prepare("SELECT c.id FROM chegadas c JOIN agendamentos a ON a.id = c.agendamento_id WHERE c.id = ? AND a.status IN ('chegou','em_triagem')");
    $arrival->execute([$arrivalId]);
    if (!$arrival->fetch() || !in_array($level, $allowedLevels, true) || $specialty === '') {
        $message = 'Chegada valida, nivel e especialidade sao obrigatorios.';
    } else {
        $statement = $database->prepare('INSERT INTO triagens (chegada_id, enfermeiro_id, nivel, especialidade_encaminhada, dados_clinicos, observacao) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT(chegada_id) DO UPDATE SET enfermeiro_id = excluded.enfermeiro_id, nivel = excluded.nivel, especialidade_encaminhada = excluded.especialidade_encaminhada, dados_clinicos = excluded.dados_clinicos, observacao = excluded.observacao, classificada_em = CURRENT_TIMESTAMP');
        $statement->execute([$arrivalId, $user['id'], $level, $specialty, trim($_POST['dados_clinicos'] ?? ''), trim($_POST['observacao'] ?? '')]);
        $database->prepare('UPDATE agendamentos SET status = ? WHERE id = (SELECT agendamento_id FROM chegadas WHERE id = ?)')->execute(['em_triagem', $arrivalId]);
        $message = 'Triagem registrada e fila reordenada.';
    }
}

$queue = $database->query("SELECT c.id, c.chegada_em, c.motivo, u.nome AS paciente, COALESCE(t.nivel, 'sem_triagem') AS nivel, COALESCE(t.especialidade_encaminhada, '') AS especialidade FROM chegadas c JOIN clientes cl ON cl.id = c.cliente_id JOIN usuarios u ON u.id = cl.usuario_id JOIN agendamentos a ON a.id = c.agendamento_id LEFT JOIN triagens t ON t.chegada_id = c.id WHERE a.status IN ('chegou','em_triagem') ORDER BY CASE nivel WHEN 'emergencia' THEN 1 WHEN 'urgente' THEN 2 WHEN 'prioritario' THEN 3 WHEN 'eletivo' THEN 4 ELSE 5 END, c.chegada_em")->fetchAll();
pageStart('Triagem e fila clinica', $user);
messageFromQuery();
if ($message) echo '<p role="status">' . e($message) . '</p>';
echo '<h2>Fila</h2><table><tr><th>Paciente</th><th>Chegada</th><th>Motivo</th><th>Nivel</th><th>Especialidade</th></tr>';
foreach ($queue as $item) echo '<tr><td>' . e($item['paciente']) . '</td><td>' . e($item['chegada_em']) . '</td><td>' . e($item['motivo']) . '</td><td>' . e($item['nivel']) . '</td><td>' . e($item['especialidade'] ?: 'Pendente') . '</td></tr>';
echo '</table><h2>Classificar</h2><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
formField('ID da chegada', 'chegada_id', 'number');
echo '<label>Nivel: <select name="nivel" required><option value="">Selecione</option><option>emergencia</option><option>urgente</option><option>prioritario</option><option>eletivo</option></select></label><br>';
formField('Especialidade encaminhada', 'especialidade');
formField('Dados clinicos', 'dados_clinicos', 'text', '', false); formField('Observacao', 'observacao', 'text', '', false); echo '<button type="submit">Salvar triagem</button></form>'; pageEnd();
