<?php
// Tema visual do cardápio público (padrão / Natal).
// A escolha é global e fica em api/data/theme.json (pasta bloqueada por .htaccess).
// Sem alteração no banco: se o arquivo não existir ou estiver inválido, vale o tema padrão.

const THEME_ALLOWED = ['padrao', 'natal'];

function theme_dir(): string { return dirname(__DIR__) . '/data'; }
function theme_file(): string { return theme_dir() . '/theme.json'; }

function theme_read(): string {
    $file = theme_file();
    if (!is_file($file)) return 'padrao';
    $raw = @file_get_contents($file);
    if ($raw === false) {
        error_log('[naffity] theme: falha ao ler theme.json');
        return 'padrao';
    }
    $data = json_decode($raw, true);
    $theme = is_array($data) ? ($data['theme'] ?? null) : null;
    if (!in_array($theme, THEME_ALLOWED, true)) {
        error_log('[naffity] theme: valor inválido em theme.json, usando padrão');
        return 'padrao';
    }
    return $theme;
}

// Gravação atômica: bloqueio exclusivo + arquivo temporário na mesma pasta + rename.
function theme_write(string $theme): bool {
    $dir = theme_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;
    $lock = @fopen($dir . '/theme.lock', 'c');
    if (!$lock) return false;
    $ok = false;
    if (flock($lock, LOCK_EX)) {
        $tmp = @tempnam($dir, 'theme_');
        if ($tmp !== false) {
            $json = json_encode(['theme' => $theme, 'updatedAt' => date('c')]);
            if (@file_put_contents($tmp, $json) === strlen($json)) {
                @chmod($tmp, 0644);
                $ok = @rename($tmp, theme_file());
            }
            if (!$ok) @unlink($tmp);
        }
        flock($lock, LOCK_UN);
    }
    fclose($lock);
    return $ok && theme_read() === $theme;
}

// GET /api/theme/script — script minúsculo carregado no <head> do cardápio,
// para aplicar o tema antes da primeira renderização (sem "flash").
if ($method === 'GET' && $id === 'script') {
    try { $theme = theme_read(); } catch (Throwable $e) { $theme = 'padrao'; }
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    if ($theme === 'natal') {
        echo "document.documentElement.setAttribute('data-theme','natal');";
    }
    exit;
}

if ($method === 'GET' && $id === null) {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    json_response(['theme' => theme_read()]);
}

if ($method === 'POST' && $id === null) {
    require_admin();
    $body = get_json_body();
    $theme = $body['theme'] ?? null;
    if (!is_string($theme) || !in_array($theme, THEME_ALLOWED, true)) {
        json_response(['error' => 'Tema inválido.'], 422);
    }
    if (!theme_write($theme)) {
        error_log('[naffity] theme: falha ao gravar theme.json');
        json_response(['error' => 'Não foi possível salvar o tema. Verifique as permissões da pasta api/data e tente novamente.'], 500);
    }
    json_response(['theme' => $theme]);
}

json_response(['error' => 'Método não suportado.'], 405);
