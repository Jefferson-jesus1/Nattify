<?php
function row_to_settings(array $r): array {
    return [
        'siteName' => $r['site_name'],
        'tagline' => $r['tagline'],
        'primaryColor' => $r['primary_color'],
        'bgColor' => $r['bg_color'],
        'whatsapp' => $r['whatsapp'],
        'instagram' => $r['instagram'],
        'deliveryFee' => (float)$r['delivery_fee'],
        'minOrder' => (float)$r['min_order'],
    ];
}

if ($method === 'GET') {
    $stmt = db()->query('SELECT * FROM settings WHERE id = 1');
    $row = $stmt->fetch();
    json_response(row_to_settings($row));
}

if ($method === 'PUT') {
    require_admin();
    $body = get_json_body();
    $current = db()->query('SELECT * FROM settings WHERE id = 1')->fetch();

    $siteName = $body['siteName'] ?? $current['site_name'];
    $tagline = $body['tagline'] ?? $current['tagline'];
    $whatsapp = $body['whatsapp'] ?? $current['whatsapp'];
    $instagram = $body['instagram'] ?? $current['instagram'];
    $deliveryFee = isset($body['deliveryFee']) ? (float)$body['deliveryFee'] : (float)$current['delivery_fee'];

    $stmt = db()->prepare('UPDATE settings SET site_name=?, tagline=?, whatsapp=?, instagram=?, delivery_fee=? WHERE id = 1');
    $stmt->execute([$siteName, $tagline, $whatsapp, $instagram, $deliveryFee]);

    $row = db()->query('SELECT * FROM settings WHERE id = 1')->fetch();
    json_response(row_to_settings($row));
}

json_response(['error' => 'Método não suportado.'], 405);
