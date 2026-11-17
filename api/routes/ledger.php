<?php
// /api/ledger                    GET (lista com filtros ?from=&to=&type=), POST (novo lançamento)
// /api/ledger/summary            GET ?period=day|week|month|year&date=YYYY-MM-DD
// /api/ledger/ranking            GET ?period=day|week|month|year&date=YYYY-MM-DD  (clientes que mais compraram)
// /api/ledger/{id}                DELETE

require_admin(); // todo o financeiro é área restrita

function ledger_row_to_json(array $r): array {
    return [
        'id' => $r['id'],
        'entryDate' => $r['entry_date'],
        'type' => $r['type'],
        'category' => $r['category'],
        'customerName' => $r['customer_name'],
        'amount' => (float)$r['amount'],
        'description' => $r['description'],
        'createdAt' => str_replace(' ', 'T', $r['created_at']),
    ];
}

// Calcula o intervalo [de, até] (Y-m-d) para um período nomeado, a partir de uma data de referência
function period_bounds(string $period, string $refDate): array {
    $ref = new DateTime($refDate);
    switch ($period) {
        case 'week':
            $dow = (int)$ref->format('N'); // 1 (seg) .. 7 (dom)
            $from = (clone $ref)->modify('-' . ($dow - 1) . ' days');
            $to = (clone $from)->modify('+6 days');
            break;
        case 'month':
            $from = new DateTime($ref->format('Y-m-01'));
            $to = (clone $from)->modify('last day of this month');
            break;
        case 'year':
            $from = new DateTime($ref->format('Y-01-01'));
            $to = new DateTime($ref->format('Y-12-31'));
            break;
        default: // day
            $from = clone $ref;
            $to = clone $ref;
    }
    return [$from->format('Y-m-d'), $to->format('Y-m-d')];
}

// ---- GET /api/ledger/summary ----
if ($method === 'GET' && $id === 'summary') {
    $period = $_GET['period'] ?? 'day';
    $refDate = $_GET['date'] ?? date('Y-m-d');
    [$from, $to] = period_bounds($period, $refDate);

    $stmt = db()->prepare("SELECT type, SUM(amount) AS total FROM ledger_entries WHERE entry_date BETWEEN ? AND ? GROUP BY type");
    $stmt->execute([$from, $to]);
    $totals = ['entrada' => 0.0, 'saida' => 0.0];
    foreach ($stmt->fetchAll() as $row) $totals[$row['type']] = (float)$row['total'];

    json_response([
        'period' => $period, 'from' => $from, 'to' => $to,
        'totalIn' => $totals['entrada'], 'totalOut' => $totals['saida'],
        'saldo' => $totals['entrada'] - $totals['saida'],
    ]);
}

// ---- GET /api/ledger/ranking ----
if ($method === 'GET' && $id === 'ranking') {
    $period = $_GET['period'] ?? 'day';
    $refDate = $_GET['date'] ?? date('Y-m-d');
    [$from, $to] = period_bounds($period, $refDate);

    $stmt = db()->prepare("SELECT customer_name, SUM(amount) AS total, COUNT(*) AS qty
        FROM ledger_entries
        WHERE type = 'entrada' AND customer_name IS NOT NULL AND customer_name != '' AND entry_date BETWEEN ? AND ?
        GROUP BY customer_name ORDER BY total DESC LIMIT 20");
    $stmt->execute([$from, $to]);
    $ranking = array_map(fn($r) => ['customerName' => $r['customer_name'], 'total' => (float)$r['total'], 'qty' => (int)$r['qty']], $stmt->fetchAll());

    json_response(['period' => $period, 'from' => $from, 'to' => $to, 'ranking' => $ranking]);
}

// ---- GET /api/ledger (lista com filtros) ----
if ($method === 'GET' && $id === null) {
    $sql = 'SELECT * FROM ledger_entries WHERE 1=1';
    $params = [];
    if (!empty($_GET['from'])) { $sql .= ' AND entry_date >= ?'; $params[] = $_GET['from']; }
    if (!empty($_GET['to'])) { $sql .= ' AND entry_date <= ?'; $params[] = $_GET['to']; }
    if (!empty($_GET['type'])) { $sql .= ' AND type = ?'; $params[] = $_GET['type']; }
    $sql .= ' ORDER BY entry_date DESC, created_at DESC LIMIT 300';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    json_response(array_map('ledger_row_to_json', $stmt->fetchAll()));
}

// ---- POST /api/ledger (novo lançamento) ----
if ($method === 'POST' && $id === null) {
    $body = get_json_body();
    $entryDate = $body['entryDate'] ?? date('Y-m-d');
    $type = $body['type'] ?? '';
    $amount = (float)($body['amount'] ?? 0);

    if (!in_array($type, ['entrada', 'saida'], true)) json_response(['error' => 'Tipo inválido.'], 400);
    if ($amount <= 0) json_response(['error' => 'Informe um valor maior que zero.'], 400);

    $newId = gen_id();
    $stmt = db()->prepare('INSERT INTO ledger_entries (id, entry_date, type, category, customer_name, amount, description, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $newId, $entryDate, $type,
        trim($body['category'] ?? ''),
        $type === 'entrada' ? (trim($body['customerName'] ?? '') ?: null) : null,
        $amount, trim($body['description'] ?? '')
    ]);

    $row = db()->query('SELECT * FROM ledger_entries WHERE id = ' . db()->quote($newId))->fetch();
    json_response(ledger_row_to_json($row), 201);
}

// ---- DELETE /api/ledger/{id} ----
if ($method === 'DELETE' && $id !== null) {
    $stmt = db()->prepare('SELECT id FROM ledger_entries WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) json_response(['error' => 'Lançamento não encontrado.'], 404);
    db()->prepare('DELETE FROM ledger_entries WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_response(['error' => 'Rota não encontrada.'], 404);
