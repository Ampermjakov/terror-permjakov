<?php
// JSON-эндпоинт для программной пробивки (портал lk.permjakov.ru). Отдаёт результат
// checkTerror() как есть: {status: clean|found|error, count, items}.
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/terror.php';

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 3) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Запрос слишком короткий (минимум 3 символа)'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(checkTerror($q), JSON_UNESCAPED_UNICODE);
