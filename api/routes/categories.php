<?php
// /api/categories            GET (público), POST (admin)
// /api/categories/{id}       PUT, DELETE (admin)

if ($method === 'GET' && $id === null) {
    $rows = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
    json_response($rows);
}

if ($method === 'POST' && $id === null) {
    require_admin();
    $body = get_json_body();
    $name = trim($body['name'] ?? '');
    if ($name === '') json_response(['error' => 'Nome da categoria é obrigatório.'], 400);
    $newId = gen_id();
    db()->prepare('INSERT INTO categories (id, name) VALUES (?, ?)')->execute([$newId, $name]);
    json_response(['id' => $newId, 'name' => $name], 201);
}

if ($method === 'PUT' && $id !== null) {
    require_admin();
    $body = get_json_body();
    $stmt = db()->prepare('SELECT id, name FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    $cat = $stmt->fetch();
    if (!$cat) json_response(['error' => 'Categoria não encontrada.'], 404);
    $name = trim($body['name'] ?? $cat['name']);
    db()->prepare('UPDATE categories SET name = ? WHERE id = ?')->execute([$name, $id]);
    json_response(['id' => $id, 'name' => $name]);
}

if ($method === 'DELETE' && $id !== null) {
    require_admin();
    $stmt = db()->prepare('SELECT COUNT(*) AS n FROM products WHERE category_id = ?');
    $stmt->execute([$id]);
    if ((int)$stmt->fetch()['n'] > 0) {
        json_response(['error' => 'Existem produtos nesta categoria. Mova-os antes de excluir.'], 400);
    }
    db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_response(['error' => 'Rota não encontrada.'], 404);
