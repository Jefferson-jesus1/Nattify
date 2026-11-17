<?php
// /api/products                    GET (público, filtros ?category= &search=)
// /api/products/admin/all          GET (admin)
// /api/products/{id}               GET (público), PUT (admin), DELETE (admin)
// /api/products/{id}/stock         PATCH (admin)
// /api/products                    POST (admin, multipart com "image")

$UPLOAD_DIR = __DIR__ . '/../../uploads';

function save_uploaded_image(string $uploadDir): ?string {
    if (empty($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) json_response(['error' => 'Falha ao enviar a imagem.'], 400);
    if (strpos($_FILES['image']['type'], 'image/') !== 0) json_response(['error' => 'Envie apenas arquivos de imagem.'], 400);
    if ($_FILES['image']['size'] > 5 * 1024 * 1024) json_response(['error' => 'A imagem deve ter no máximo 5MB.'], 400);

    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION)) ?: 'jpg';
    $filename = time() . '-' . bin2hex(random_bytes(6)) . '.' . preg_replace('/[^a-z0-9]/', '', $ext);
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
    move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . '/' . $filename);
    return '/uploads/' . $filename;
}

function delete_image_file(string $uploadDir, ?string $imageUrl): void {
    if (!$imageUrl || strpos($imageUrl, '/uploads/') !== 0) return;
    $path = $uploadDir . '/' . basename($imageUrl);
    if (is_file($path)) @unlink($path);
}

// ---- GET /api/products/admin/all ----
if ($method === 'GET' && $id === 'admin' && $action === 'all') {
    require_admin();
    $rows = db()->query('SELECT * FROM products ORDER BY name')->fetchAll();
    json_response(array_map('product_row_to_json', $rows));
}

// ---- GET /api/products (público) ----
if ($method === 'GET' && $id === null) {
    $sql = 'SELECT * FROM products WHERE active = 1';
    $params = [];
    if (!empty($_GET['category'])) { $sql .= ' AND category_id = ?'; $params[] = $_GET['category']; }
    if (!empty($_GET['search'])) {
        $sql .= ' AND (name LIKE ? OR description LIKE ?)';
        $like = '%' . $_GET['search'] . '%';
        $params[] = $like; $params[] = $like;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    json_response(array_map('product_row_to_json', $stmt->fetchAll()));
}

// ---- GET /api/products/{id} (público) ----
if ($method === 'GET' && $id !== null && $action === null) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_response(['error' => 'Produto não encontrado.'], 404);
    json_response(product_row_to_json($row));
}

// ---- POST /api/products (admin, cria) ----
if ($method === 'POST' && $id === null) {
    require_admin();
    $name = trim($_POST['name'] ?? '');
    $price = $_POST['price'] ?? null;
    if ($name === '' || $price === null) json_response(['error' => 'Nome e preço são obrigatórios.'], 400);

    $newId = gen_id();
    $imageUrl = save_uploaded_image($UPLOAD_DIR) ?? ($_POST['imageUrl'] ?? '');
    $tags = isset($_POST['tags']) ? implode(',', array_filter(array_map('trim', explode(',', $_POST['tags'])))) : '';

    $stmt = db()->prepare('INSERT INTO products (id, name, description, price, stock, category_id, image_url, active, featured, rating, rating_count, tags)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 5, 0, ?)');
    $stmt->execute([
        $newId, $name, $_POST['description'] ?? '', (float)$price, (int)($_POST['stock'] ?? 0),
        $_POST['categoryId'] ?: null, $imageUrl,
        bool_from($_POST['active'] ?? true), bool_from($_POST['featured'] ?? false), $tags
    ]);

    $row = db()->query('SELECT * FROM products WHERE id = ' . db()->quote($newId))->fetch();
    json_response(product_row_to_json($row), 201);
}

// ---- PUT /api/products/{id} (admin, edita) ----
if ($method === 'PUT' && $id !== null && $action === null) {
    require_admin();
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) json_response(['error' => 'Produto não encontrado.'], 404);

    $newImage = save_uploaded_image($UPLOAD_DIR);
    $imageUrl = $existing['image_url'];
    if ($newImage) {
        delete_image_file($UPLOAD_DIR, $existing['image_url']);
        $imageUrl = $newImage;
    }

    $name = $_POST['name'] ?? $existing['name'];
    $description = $_POST['description'] ?? $existing['description'];
    $price = isset($_POST['price']) ? (float)$_POST['price'] : $existing['price'];
    $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : $existing['stock'];
    $categoryId = $_POST['categoryId'] ?? $existing['category_id'];
    $active = isset($_POST['active']) ? bool_from($_POST['active']) : $existing['active'];
    $featured = isset($_POST['featured']) ? bool_from($_POST['featured']) : $existing['featured'];
    $tags = isset($_POST['tags']) ? implode(',', array_filter(array_map('trim', explode(',', $_POST['tags'])))) : $existing['tags'];

    $stmt = db()->prepare('UPDATE products SET name=?, description=?, price=?, stock=?, category_id=?, image_url=?, active=?, featured=?, tags=? WHERE id=?');
    $stmt->execute([$name, $description, $price, $stock, $categoryId ?: null, $imageUrl, $active, $featured, $tags, $id]);

    $row = db()->query('SELECT * FROM products WHERE id = ' . db()->quote($id))->fetch();
    json_response(product_row_to_json($row));
}

// ---- PATCH /api/products/{id}/stock (admin) ----
if ($method === 'PATCH' && $id !== null && $action === 'stock') {
    require_admin();
    $stmt = db()->prepare('SELECT stock FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) json_response(['error' => 'Produto não encontrado.'], 404);

    $body = get_json_body();
    $newStock = (int)$existing['stock'];
    if (isset($body['set'])) $newStock = max(0, (int)$body['set']);
    elseif (isset($body['delta'])) $newStock = max(0, $newStock + (int)$body['delta']);

    db()->prepare('UPDATE products SET stock = ? WHERE id = ?')->execute([$newStock, $id]);
    $row = db()->query('SELECT * FROM products WHERE id = ' . db()->quote($id))->fetch();
    json_response(product_row_to_json($row));
}

// ---- DELETE /api/products/{id} (admin) ----
if ($method === 'DELETE' && $id !== null) {
    require_admin();
    $stmt = db()->prepare('SELECT image_url FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) json_response(['error' => 'Produto não encontrado.'], 404);
    delete_image_file($UPLOAD_DIR, $existing['image_url']);
    db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_response(['error' => 'Rota não encontrada.'], 404);
