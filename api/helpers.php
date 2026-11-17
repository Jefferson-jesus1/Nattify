<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function json_response($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function get_json_body(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// Gera um id único (32 caracteres hexadecimais) — não precisa ser UUID "de verdade"
function gen_id(): string {
    return bin2hex(random_bytes(16));
}

function short_code(string $id): string {
    return strtoupper(substr($id, 0, 8));
}

// Extrai o token "Bearer xxx" do cabeçalho Authorization
function bearer_token(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(\S+)/', $header, $m)) return $m[1];
    return null;
}

// Cria uma sessão de admin (token aleatório válido por 12h) — substitui o JWT da versão Node
function create_admin_session(): string {
    $token = bin2hex(random_bytes(32));
    $stmt = db()->prepare('INSERT INTO admin_sessions (token, created_at, expires_at) VALUES (?, NOW(), DATE_ADD(NOW(), INTERVAL 12 HOUR))');
    $stmt->execute([$token]);
    return $token;
}

// Interrompe a requisição com 401 se não houver um token de admin válido
function require_admin(): void {
    $token = bearer_token();
    if (!$token) json_response(['error' => 'Não autenticado.'], 401);
    $stmt = db()->prepare('SELECT token FROM admin_sessions WHERE token = ? AND expires_at > NOW()');
    $stmt->execute([$token]);
    if (!$stmt->fetch()) json_response(['error' => 'Sessão inválida ou expirada. Faça login novamente.'], 401);
}

function bool_from($value): int {
    if (is_bool($value)) return $value ? 1 : 0;
    $v = strtolower((string)$value);
    return in_array($v, ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
}

// Serialização compartilhada de produto (usada por products.php e por orders.php nas estatísticas)
function product_row_to_json(array $r): array {
    return [
        'id' => $r['id'],
        'name' => $r['name'],
        'description' => $r['description'],
        'price' => (float)$r['price'],
        'stock' => (int)$r['stock'],
        'categoryId' => $r['category_id'],
        'imageUrl' => $r['image_url'],
        'active' => (bool)$r['active'],
        'featured' => (bool)$r['featured'],
        'rating' => (float)$r['rating'],
        'ratingCount' => (int)$r['rating_count'],
        'tags' => $r['tags'] === '' ? [] : explode(',', $r['tags']),
    ];
}
