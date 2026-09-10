<?php
require_once __DIR__ . '/../php/layout.php';
$user = requireProfile(['financeiro']);
$database = db(); $message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'charge') {
            $amount = (float) ($_POST['valor_total'] ?? 0); $clientId = (int) ($_POST['cliente_id'] ?? 0); $type = trim($_POST['tipo'] ?? '');
            if ($amount <= 0 || !$clientId || $type === '') throw new InvalidArgumentException('Cliente, tipo e valor valido sao obrigatorios.');
            $client = $database->prepare('SELECT id FROM clientes WHERE id = ?'); $client->execute([$clientId]); if (!$client->fetch()) throw new InvalidArgumentException('Cliente invalido.');
            $database->prepare('INSERT INTO cobrancas (cliente_id, tipo, referencia_id, valor_total) VALUES (?, ?, ?, ?)')->execute([$clientId, $type, (int) ($_POST['referencia_id'] ?? 0), $amount]); $message = 'Cobranca registrada.';
        } elseif ($action === 'payment') {
            $chargeId = (int) ($_POST['cobranca_id'] ?? 0); $amount = (float) ($_POST['valor'] ?? 0); $form = trim($_POST['forma'] ?? '');
            if ($chargeId <= 0 || $amount <= 0 || $form === '') throw new InvalidArgumentException('Cobranca, valor e forma de pagamento sao obrigatorios.');
            $charge = $database->prepare('SELECT valor_total, valor_pago, status FROM cobrancas WHERE id = ?'); $charge->execute([$chargeId]); $charge = $charge->fetch();
            if (!$charge || $charge['status'] === 'paga') throw new InvalidArgumentException('Cobranca inexistente ou ja paga.');
            $remaining = (float) $charge['valor_total'] - (float) $charge['valor_pago']; if ($amount > $remaining) throw new InvalidArgumentException('Pagamento maior que o saldo da cobranca.');
            $database->beginTransaction(); $database->prepare('INSERT INTO pagamentos (cobranca_id, valor, forma, responsavel_id) VALUES (?, ?, ?, ?)')->execute([$chargeId, $amount, $form, $user['id']]); $database->prepare("UPDATE cobrancas SET valor_pago = valor_pago + ?, status = CASE WHEN valor_pago + ? >= valor_total THEN 'paga' ELSE 'parcial' END WHERE id = ?")->execute([$amount, $amount, $chargeId]); $database->commit(); $message = 'Pagamento registrado.';
        } elseif ($action === 'convenio') {
            $coverage = (float) ($_POST['cobertura'] ?? -1); if (trim($_POST['nome'] ?? '') === '' || trim($_POST['registro'] ?? '') === '' || $coverage < 0 || $coverage > 100) throw new InvalidArgumentException('Nome, registro e cobertura entre 0 e 100 sao obrigatorios.');
            $database->prepare('INSERT INTO convenios (nome, registro, cobertura) VALUES (?, ?, ?)')->execute([trim($_POST['nome']), trim($_POST['registro']), $coverage]); $message = 'Convenio cadastrado.';
        } elseif ($action === 'link_convenio') {
            $clientId = (int) ($_POST['cliente_id'] ?? 0); $convenioId = (int) ($_POST['convenio_id'] ?? 0); $check = $database->prepare('SELECT COUNT(*) FROM convenios WHERE id = ? AND status = ?'); $check->execute([$convenioId, 'ativo']);
            if (!$clientId || !$convenioId || !(int) $check->fetchColumn()) throw new InvalidArgumentException('Cliente ou convenio invalido.');
            $database->prepare('UPDATE clientes SET convenio_id = ? WHERE id = ?')->execute([$convenioId, $clientId]); $message = 'Cliente vinculado ao convenio.';
        }
    } catch (Throwable $exception) { if ($database->inTransaction()) $database->rollBack(); $message = 'Erro: ' . $exception->getMessage(); }
}
$clients = $database->query("SELECT c.id, c.convenio_id, u.nome FROM clientes c JOIN usuarios u ON u.id = c.usuario_id ORDER BY u.nome")->fetchAll();
$convenios = $database->query('SELECT * FROM convenios ORDER BY nome')->fetchAll();
$charges = $database->query("SELECT co.*, u.nome AS cliente FROM cobrancas co JOIN clientes c ON c.id = co.cliente_id JOIN usuarios u ON u.id = c.usuario_id ORDER BY co.id DESC")->fetchAll();
pageStart('Financeiro', $user); if ($message) echo '<p role="status">' . e($message) . '</p>';
echo '<h2>Cobrancas</h2><table><tr><th>Cliente</th><th>Tipo</th><th>Total</th><th>Pago</th><th>Status</th><th>Pagamento</th></tr>';
foreach ($charges as $charge) echo '<tr><td>' . e($charge['cliente']) . '</td><td>' . e($charge['tipo']) . '</td><td>' . e($charge['valor_total']) . '</td><td>' . e($charge['valor_pago']) . '</td><td>' . e($charge['status']) . '</td><td><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="payment"><input type="hidden" name="cobranca_id" value="' . e($charge['id']) . '"><input name="valor" type="number" step="0.01" min="0.01" required><input name="forma" placeholder="Forma" required><button type="submit">Pagar</button></form></td></tr>';
echo '</table><h2>Nova cobranca</h2><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="charge"><label>Cliente: <select name="cliente_id" required><option value="">Selecione</option>'; foreach ($clients as $client) echo '<option value="' . e($client['id']) . '">' . e($client['nome']) . '</option>'; echo '</select></label><br>'; formField('Tipo', 'tipo'); formField('Referencia', 'referencia_id', 'number', '0', false); formField('Valor total', 'valor_total', 'number'); echo '<button type="submit">Registrar cobranca</button></form><h2>Novo convenio</h2><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="convenio">'; formField('Nome', 'nome'); formField('Registro', 'registro'); formField('Cobertura percentual', 'cobertura', 'number', '0'); echo '<button type="submit">Cadastrar convenio</button></form><h2>Vincular conveniado</h2><form method="post"><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="link_convenio"><label>Cliente: <select name="cliente_id" required>'; foreach ($clients as $client) echo '<option value="' . e($client['id']) . '">' . e($client['nome']) . '</option>'; echo '</select></label><br><label>Convenio: <select name="convenio_id" required>'; foreach ($convenios as $convenio) echo '<option value="' . e($convenio['id']) . '">' . e($convenio['nome']) . '</option>'; echo '</select></label><br><button type="submit">Vincular</button></form>'; pageEnd();
