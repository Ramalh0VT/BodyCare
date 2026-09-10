<?php
require_once __DIR__ . '/../php/layout.php';
$user = requireProfile(['medico', 'enfermeiro']);
$database = db(); $message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'evolution') {
            $database->prepare('INSERT INTO evolucoes (internacao_id, profissional_id, texto) VALUES (?, ?, ?)')->execute([(int) $_POST['internacao_id'], $user['id'], trim($_POST['texto'])]); $message = 'Evolucao registrada.';
        } elseif ($action === 'discharge') {
            $database->prepare("UPDATE internacoes SET alta_em = CURRENT_TIMESTAMP, status = 'alta' WHERE id = ? AND status = 'aberta'")->execute([(int) $_POST['internacao_id']]); $message = 'Alta da internacao registrada.';
        }
    } catch (Throwable $exception) { $message = 'Erro: ' . $exception->getMessage(); }
}
$admissions = $database->query("SELECT i.*, u.nome AS paciente FROM internacoes i JOIN clientes c ON c.id = i.paciente_id JOIN usuarios u ON u.id = c.usuario_id WHERE i.status = 'aberta' ORDER BY i.entrada_em DESC")->fetchAll();
pageStart('Acompanhamento de internacoes', $user); if ($message) echo '<p role="status">' . e($message) . '</p>';
echo '<table><tr><th>Paciente</th><th>Leito</th><th>Entrada</th><th>Motivo</th><th>Custo</th><th>Acoes</th></tr>';
foreach ($admissions as $item) echo '<tr><td>' . e($item['paciente']) . '</td><td>' . e($item['leito']) . '</td><td>' . e($item['entrada_em']) . '</td><td>' . e($item['motivo']) . '</td><td>' . e($item['custo']) . '</td><td><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="internacao_id" value="' . e($item['id']) . '"><textarea name="texto" required></textarea><button name="action" value="evolution">Registrar evolucao</button></form><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="internacao_id" value="' . e($item['id']) . '"><button name="action" value="discharge">Registrar alta</button></form></td></tr>';
echo '</table>'; pageEnd();
