<?php
if (PHP_SAPI !== 'cli' && ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    http_response_code(403); exit;
}
require_once __DIR__.'/config.php';
require_once __DIR__.'/terror.php';

echo date('[Y-m-d H:i:s]')." Terror cron start\n";

$sync = syncTerrorList();
if (isset($sync['error'])) {
    echo "ERROR sync: {$sync['error']}\n"; exit(1);
}
echo "  Sync: total={$sync['total']}\n";
echo date('[Y-m-d H:i:s]')." Done.\n";
