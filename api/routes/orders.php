<?php
// /api/orders                      POST (checkout, público)
// /api/orders/track                GET  ?name=&phone= (público)
// /api/orders                      GET  (admin, lista tudo)
// /api/orders/{id}/status          PATCH (admin)
// /api/orders/{id}/payment-status  PATCH (admin)
// /api/orders/stats/summary        GET  (admin)

function order_row_to_json(array $r): array {
    return [
        'id' => $r['id'],
        'code' => $r['code'],
        'customerName' => $r['customer_name'],
        'phone' => $r['phone'],
        'address' => $r['address'],
        'items' => json_decode($r['items'], true),
        'subtotal' => (float)$r['subtotal'],
        'deliveryFee' => (float)$r['delivery_fee'],
        'total' => (float)$r['total'],
        'paymentMethod' => $r['payment_method'],
        'paymentStatus' => $r['payment_status'],
        'status' => $r['status'],
        'notes' => $r['notes'],
        'createdAt' => str_replace(' ', 'T', $r['created_at']),
    ];
}

// ---- POST /api/orders (checkout) ----
if ($method === 'POST' && $id === null) {
    $body = get_json_body();
    $customerName = trim($body['customerName'] ?? '');
    $phone = trim($body['phone'] ?? '');
    $items = $body['items'] ?? [];
    $paymentMethod = $body['paymentMethod'] ?? '';

    if ($customerName === '' || $phone === '' || !is_array($items) || count($items) === 0) {
        json_response(['error' => 'Preencha seu nome, telefone e adicione itens ao carrinho.'], 400);
    }
    if (!in_array($paymentMethod, ['pix', 'cartao', 'dinheiro'], true)) {
        json_response(['error' => 'Forma de pagamento inválida.'], 400);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $orderItems = [];
        $subtotal = 0.0;
        foreach ($items as $it) {
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? FOR UPDATE');
            $stmt->execute([$it['productId'] ?? '']);
            $product = $stmt->fetch();
            if (!$product || !$product['active']) {
                throw new Exception('Produto indisponível: ' . ($it['productId'] ?? ''));
            }
            $qty = max(1, (int)($it['qty'] ?? 1));
            if ((int)$product['stock'] < $qty) {
                throw new Exception('"' . $product['name'] . '" tem apenas ' . $product['stock'] . ' unidade(s) em estoque.');
            }
            $orderItems[] = [
                'productId' => $product['id'], 'name' => $product['name'],
                'price' => (float)$product['price'], 'qty' => $qty, 'imageUrl' => $product['image_url'],
            ];
            $subtotal += (float)$product['price'] * $qty;
            $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?')->execute([$qty, $product['id']]);
        }

        $settings = $pdo->query('SELECT delivery_fee FROM settings WHERE id = 1')->fetch();
        $deliveryFee = (float)$settings['delivery_fee'];
        $total = $subtotal + $deliveryFee;

        $newId = gen_id();
        $code = short_code($newId);
        $paymentStatus = $paymentMethod === 'dinheiro' ? 'pendente_na_entrega' : 'aguardando_pagamento';

        $stmt = $pdo->prepare('INSERT INTO orders (id, code, customer_name, phone, address, items, subtotal, delivery_fee, total, payment_method, payment_status, status, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            $newId, $code, $customerName, $phone, $body['address'] ?? '',
            json_encode($orderItems, JSON_UNESCAPED_UNICODE), $subtotal, $deliveryFee, $total,
            $paymentMethod, $paymentStatus, 'recebido', $body['notes'] ?? ''
        ]);

        $pdo->commit();
        $row = $pdo->query('SELECT * FROM orders WHERE id = ' . $pdo->quote($newId))->fetch();
        json_response(order_row_to_json($row), 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        json_response(['error' => $e->getMessage()], 400);
    }
}

// ---- GET /api/orders/track?name=&phone= (público) ----
if ($method === 'GET' && $id === 'track') {
    $name = trim($_GET['name'] ?? '');
    $phone = trim($_GET['phone'] ?? '');
    if ($name === '' || $phone === '') json_response(['error' => 'Informe seu nome e telefone.'], 400);

    $normPhone = preg_replace('/\D/', '', $phone);
    $stmt = db()->query('SELECT * FROM orders ORDER BY created_at DESC');
    $matches = [];
    foreach ($stmt->fetchAll() as $row) {
        $rowPhone = preg_replace('/\D/', '', $row['phone']);
        if ($rowPhone === $normPhone && mb_strtolower(trim($row['customer_name'])) === mb_strtolower($name)) {
            $matches[] = order_row_to_json($row);
        }
    }
    if (count($matches) === 0) json_response(['error' => 'Nenhum pedido encontrado com esse nome e telefone.'], 404);
    json_response($matches);
}

// ---- GET /api/orders/stats/summary (admin) ----
if ($method === 'GET' && $id === 'stats' && $action === 'summary') {
    require_admin();
    $orders = db()->query('SELECT * FROM orders')->fetchAll();
    $valid = array_filter($orders, fn($o) => $o['status'] !== 'cancelado');

    $revenue = array_sum(array_map(fn($o) => (float)$o['total'], $valid));
    $countByProduct = [];
    foreach ($valid as $o) {
        foreach (json_decode($o['items'], true) as $it) {
            $countByProduct[$it['name']] = ($countByProduct[$it['name']] ?? 0) + $it['qty'];
        }
    }
    arsort($countByProduct);
    $topProducts = [];
    foreach (array_slice($countByProduct, 0, 5, true) as $name => $qty) {
        $topProducts[] = ['name' => $name, 'qty' => $qty];
    }

    $lowStockRows = db()->query('SELECT * FROM products WHERE active = 1 AND stock <= 3')->fetchAll();

    json_response([
        'totalOrders' => count($orders),
        'pendingOrders' => count(array_filter($orders, fn($o) => in_array($o['status'], ['recebido', 'em_preparo', 'saiu_para_entrega'], true))),
        'revenue' => $revenue,
        'topProducts' => $topProducts,
        'lowStock' => array_map('product_row_to_json', $lowStockRows),
    ]);
}

// ---- GET /api/orders (admin, lista tudo) ----
if ($method === 'GET' && $id === null) {
    require_admin();
    $rows = db()->query('SELECT * FROM orders ORDER BY created_at DESC')->fetchAll();
    json_response(array_map('order_row_to_json', $rows));
}

// ---- PATCH /api/orders/{id}/status (admin) ----
if ($method === 'PATCH' && $id !== null && $action === 'status') {
    require_admin();
    $allowed = ['recebido', 'em_preparo', 'saiu_para_entrega', 'entregue', 'cancelado'];
    $body = get_json_body();
    $newStatus = $body['status'] ?? '';
    if (!in_array($newStatus, $allowed, true)) json_response(['error' => 'Status inválido.'], 400);

    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) json_response(['error' => 'Pedido não encontrado.'], 404);

    if ($newStatus === 'cancelado' && $order['status'] !== 'cancelado') {
        foreach (json_decode($order['items'], true) as $it) {
            db()->prepare('UPDATE products SET stock = stock + ? WHERE id = ?')->execute([$it['qty'], $it['productId']]);
        }
    }
    db()->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
    $row = db()->query('SELECT * FROM orders WHERE id = ' . db()->quote($id))->fetch();
    json_response(order_row_to_json($row));
}

// ---- PATCH /api/orders/{id}/payment-status (admin) ----
if ($method === 'PATCH' && $id !== null && $action === 'payment-status') {
    require_admin();
    $body = get_json_body();
    $stmt = db()->prepare('SELECT id FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) json_response(['error' => 'Pedido não encontrado.'], 404);
    db()->prepare('UPDATE orders SET payment_status = ? WHERE id = ?')->execute([$body['paymentStatus'] ?? '', $id]);
    $row = db()->query('SELECT * FROM orders WHERE id = ' . db()->quote($id))->fetch();
    json_response(order_row_to_json($row));
}

json_response(['error' => 'Rota não encontrada.'], 404);
