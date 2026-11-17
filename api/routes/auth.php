<?php
// Disponível no index.php: $method, $id (aqui usado como sub-ação), $action

$sub = $id; // /api/auth/login -> $id = 'login'; /api/auth/change-password -> $id = 'change-password'

if ($method === 'POST' && $sub === 'login') {
    $body = get_json_body();
    $username = $body['username'] ?? '';
    $password = $body['password'] ?? '';
    if ($username === '' || $password === '') {
        json_response(['error' => 'Informe usuário e senha.'], 400);
    }
    $stmt = db()->prepare('SELECT username, password_hash FROM admin_users WHERE id = 1');
    $stmt->execute();
    $admin = $stmt->fetch();
    if (!$admin || $username !== $admin['username'] || !password_verify($password, $admin['password_hash'])) {
        json_response(['error' => 'Usuário ou senha incorretos.'], 401);
    }
    $token = create_admin_session();
    json_response(['token' => $token, 'username' => $admin['username']]);
}

if ($method === 'POST' && $sub === 'change-password') {
    require_admin();
    $body = get_json_body();
    $current = $body['currentPassword'] ?? '';
    $new = $body['newPassword'] ?? '';
    $stmt = db()->prepare('SELECT password_hash FROM admin_users WHERE id = 1');
    $stmt->execute();
    $admin = $stmt->fetch();
    if (!$admin || !password_verify($current, $admin['password_hash'])) {
        json_response(['error' => 'Senha atual incorreta.'], 401);
    }
    if (strlen($new) < 4) {
        json_response(['error' => 'A nova senha deve ter pelo menos 4 caracteres.'], 400);
    }
    $hash = password_hash($new, PASSWORD_BCRYPT);
    db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = 1')->execute([$hash]);
    json_response(['ok' => true]);
}

json_response(['error' => 'Rota não encontrada.'], 404);
