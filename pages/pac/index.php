<?php
require_once __DIR__ . '/../php/layout.php';
$user = requireProfile(['cliente']);
$database = db();
$statement = $database->prepare('SELECT c.id FROM clientes c WHERE c.usuario_id = ?'); $statement->execute([$user['id']]); $clientId = (int) $statement->fetchColumn();
$statement = $database->prepare('SELECT a.*, u.nome AS medico FROM agendamentos a LEFT JOIN usuarios u ON u.id = a.medico_id WHERE a.cliente_id = ? ORDER BY a.inicio'); $statement->execute([$clientId]); $appointments = $statement->fetchAll();
pageStart('Area do cliente', $user);
echo '<h2>Meus agendamentos</h2><table><tr><th>Data</th><th>Tipo</th><th>Especialidade</th><th>Profissional</th><th>Status</th></tr>';
foreach ($appointments as $item) echo '<tr><td>' . e($item['inicio']) . '</td><td>' . e($item['tipo']) . '</td><td>' . e($item['especialidade']) . '</td><td>' . e($item['medico'] ?: 'A definir') . '</td><td>' . e($item['status']) . '</td></tr>';
echo '</table>'; pageEnd();
