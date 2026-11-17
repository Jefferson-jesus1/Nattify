<?php
require_once __DIR__ . '/helpers.php';

$url = trim($_GET['url'] ?? '', '/');
$segments = $url === '' ? [] : explode('/', $url);
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

$resource = $segments[0] ?? '';
$id = $segments[1] ?? null;
$action = $segments[2] ?? null;

try {
    switch ($resource) {
        case 'auth':
            require __DIR__ . '/routes/auth.php';
            break;
        case 'products':
            require __DIR__ . '/routes/products.php';
            break;
        case 'categories':
            require __DIR__ . '/routes/categories.php';
            break;
        case 'orders':
            require __DIR__ . '/routes/orders.php';
            break;
        case 'settings':
            require __DIR__ . '/routes/settings.php';
            break;
        case 'theme':
            require __DIR__ . '/routes/theme.php';
            break;
        case 'ledger':
            require __DIR__ . '/routes/ledger.php';
            break;
        default:
            json_response(['error' => 'Rota não encontrada.'], 404);
    }
} catch (Throwable $e) {
    json_response(['error' => 'Erro interno no servidor.'], 500);
}
