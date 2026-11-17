<?php
// ATENÇÃO: preencha com os dados do banco MySQL criado no hPanel da Hostinger
// (hPanel → Bancos de Dados → MySQL). Local nunca deixe esse arquivo público em outro lugar.

define('DB_HOST', getenv('DOCERIA_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DOCERIA_DB_NAME') ?: 'SEU_BANCO');   // ex: u123456789_naffity
define('DB_USER', getenv('DOCERIA_DB_USER') ?: 'SEU_USUARIO');          // ex: u123456789_naffity
define('DB_PASS', getenv('DOCERIA_DB_PASS') ?: 'SUA_SENHA');
define('JWT_ISH_SECRET', 'TROQUE_POR_UM_TEXTO_ALEATORIO'); // usado só como semente extra, pode deixar

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
