<?php
require_once __DIR__ . '/../php/layout.php';
$user = requireProfile(['enfermeiro']);
$database = db();
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $arrivalId = (int) ($_POST['chegada_id'] ?? 0);
    $level = $_POST['nivel'] ?? '';
    $allowedLevels = ['emergencia', 'urgente', 'prioritario', 'eletivo'];
    if (!$arrivalId || !in_array($level, $allowedLevels, true)) {
        $message = 'Chegada e nivel sao obrigatorios.';
    } else {
        $statement = $database->prepare('INSERT INTO triagens (chegada_id, enfermeiro_id, nivel, dados_clinicos, observacao) VALUES (?, ?, ?, ?, ?) ON CONFLICT(chegada_id) DO UPDATE SET enfermeiro_id = excluded.enfermeiro_id, nivel = excluded.nivel, dados_clinicos = excluded.dados_clinicos, observacao = excluded.observacao, classificada_em = CURRENT_TIMESTAMP');
        $statement->execute([$arrivalId, $user['id'], $level, trim($_POST['dados_clinicos'] ?? ''), trim($_POST['observacao'] ?? '')]);
        $message = 'Triagem registrada e fila reordenada.';
    }
}

$queue = $database->query("SELECT c.id, c.chegada_em, c.motivo, u.nome AS paciente, COALESCE(t.nivel, 'sem_triagem') AS nivel FROM chegadas c JOIN clientes cl ON cl.id = c.cliente_id JOIN usuarios u ON u.id = cl.usuario_id LEFT JOIN triagens t ON t.chegada_id = c.id LEFT JOIN agendamentos a ON a.id = c.agendamento_id WHERE NOT EXISTS (SELECT 1 FROM atendimentos atd WHERE atd.cliente_id = c.cliente_id AND atd.status = 'concluido' AND atd.inicio >= c.chegada_em) ORDER BY CASE nivel WHEN 'emergencia' THEN 1 WHEN 'urgente' THEN 2 WHEN 'prioritario' THEN 3 WHEN 'eletivo' THEN 4 ELSE 5 END, c.chegada_em")->fetchAll();
pageStart('Triagem e fila clinica', $user);
messageFromQuery();
if ($message) echo '<p role="status">' . e($message) . '</p>';
echo '<h2>Fila</h2><table><tr><th>Paciente</th><th>Chegada</th><th>Motivo</th><th>Nivel</th></tr>';
foreach ($queue as $item) echo '<tr><td>' . e($item['paciente']) . '</td><td>' . e($item['chegada_em']) . '</td><td>' . e($item['motivo']) . '</td><td>' . e($item['nivel']) . '</td></tr>';
echo '</table><h2>Classificar</h2><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
formField('ID da chegada', 'chegada_id', 'number');
echo '<label>Nivel: <select name="nivel" required><option value="">Selecione</option><option>emergencia</option><option>urgente</option><option>prioritario</option><option>eletivo</option></select></label><br>';
formField('Dados clinicos', 'dados_clinicos', 'text', '', false); formField('Observacao', 'observacao', 'text', '', false); echo '<button type="submit">Salvar triagem</button></form>'; pageEnd();
