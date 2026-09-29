<?php
require_once __DIR__.'/config.php';

// Каждая запись рендерится Росфинмониторингом как один <li> внутри
// <div id="{russian|international}{FL|UL}"><ol class="terrorist-list">.
// FL = физические лица, UL = организации. Формат текста не всегда строго
// единообразен, поэтому парсим по возможности (номер/ФИО/дата рождения),
// но полный исходный текст сохраняем всегда — поиск ведётся и по нему.
function fetchTerrorHtml(): array {
    $outFile = sys_get_temp_dir() . '/terror_fetch_' . bin2hex(random_bytes(8)) . '.html';

    $cmd = escapeshellarg(NODE_BIN) . ' ' . escapeshellarg(TERROR_FETCH_SCRIPT) . ' '
         . escapeshellarg(TERROR_API_URL) . ' ' . escapeshellarg($outFile);

    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = @proc_open($cmd, $descriptors, $pipes, __DIR__);
    if (!is_resource($process)) return ['error' => 'Не удалось запустить обработчик Росфинмониторинга'];

    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0 || !is_file($outFile)) {
        @unlink($outFile);
        error_log('Terror fetch-terror.js failed (exit '.$exitCode.'): '.$stderr);
        return ['error' => 'Не удалось получить данные с портала Росфинмониторинга'];
    }

    $html = file_get_contents($outFile);
    @unlink($outFile);
    if (!$html) return ['error' => 'Не удалось получить данные с портала Росфинмониторинга'];

    return ['html' => $html];
}

function fetchTerrorSections(): array {
    $fetch = fetchTerrorHtml();
    if (isset($fetch['error'])) return $fetch;
    $html = $fetch['html'];

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8">' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);

    $sections = [];
    foreach ($xpath->query('//ol[contains(@class,"terrorist-list")]') as $ol) {
        $container = $ol;
        $sectionId = null;
        while ($container = $container->parentNode) {
            if ($container instanceof DOMElement && $container->hasAttribute('id')) {
                $sectionId = $container->getAttribute('id');
                break;
            }
        }
        if (!$sectionId) continue;

        $items = [];
        foreach ($xpath->query('.//li', $ol) as $li) {
            $text = trim(preg_replace('/\s+/u', ' ', $li->textContent));
            if ($text !== '') $items[] = $text;
        }
        if ($items) $sections[$sectionId] = array_merge($sections[$sectionId] ?? [], $items);
    }

    if (!$sections) return ['error' => 'Не удалось разобрать список Росфинмониторинга'];
    return ['sections' => $sections];
}

function parseTerrorEntry(string $text, string $entryType): array {
    $listNum = null;
    if (preg_match('/^(\d+)\.\s*(.*)$/us', $text, $m)) {
        $listNum = (int)$m[1];
        $body    = trim($m[2]);
    } else {
        $body = $text;
    }

    $dob = null;
    if ($entryType === 'person' && preg_match('/(\d{2})\.(\d{2})\.(\d{4})\s*г\.р\./u', $body, $m)) {
        $dob = "{$m[3]}-{$m[2]}-{$m[1]}";
    }

    $commaPos = mb_strpos($body, ',');
    $name = $commaPos !== false ? mb_substr($body, 0, $commaPos) : $body;
    $name = trim($name, " \t\n\r\0\x0B*");

    return [$listNum, $name, $dob];
}

function syncTerrorList(): array {
    $fetch = fetchTerrorSections();
    if (isset($fetch['error'])) return $fetch;

    $db    = getDB();
    $total = 0;

    $db->beginTransaction();
    try {
        $db->exec("DELETE FROM terror_list");
        $stmt = $db->prepare("
            INSERT INTO terror_list (section, list_num, entry_type, name, dob, raw_text, date_update)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($fetch['sections'] as $sectionId => $items) {
            $entryType = str_ends_with($sectionId, 'UL') ? 'org' : 'person';
            foreach ($items as $text) {
                [$listNum, $name, $dob] = parseTerrorEntry($text, $entryType);
                $stmt->execute([$sectionId, $listNum, $entryType, $name, $dob, $text]);
                $total++;
            }
        }

        $db->prepare("
            INSERT INTO terror_sync (id, total, synced_at) VALUES (1, ?, NOW())
            ON DUPLICATE KEY UPDATE total = ?, synced_at = NOW()
        ")->execute([$total, $total]);

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        return ['error' => $e->getMessage()];
    }

    return ['total' => $total];
}

function checkTerror(string $query): array {
    $needle = trim($query);
    if ($needle === '') return ['status' => 'clean'];

    try {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT * FROM terror_list
            WHERE name LIKE ? OR raw_text LIKE ?
            ORDER BY id DESC
            LIMIT 20
        ");
        $like = '%'.$needle.'%';
        $stmt->execute([$like, $like]);
        $found = $stmt->fetchAll();
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'Ошибка базы данных'];
    }

    if ($found) {
        return ['status' => 'found', 'count' => count($found), 'items' => $found];
    }
    return ['status' => 'clean'];
}

function getCacheAge(): ?string {
    try {
        $db  = getDB();
        $row = $db->query("SELECT synced_at FROM terror_sync WHERE id = 1")->fetch();
        if (!$row) return null;
        $sec = time() - strtotime($row['synced_at']);
        if ($sec < 60)    return 'только что';
        if ($sec < 3600)  return floor($sec / 60).' мин назад';
        if ($sec < 86400) return floor($sec / 3600).' ч назад';
        return floor($sec / 86400).' дн назад';
    } catch (Exception $e) { return null; }
}

function getTerrorCount(): int {
    try {
        $db  = getDB();
        $row = $db->query("SELECT total FROM terror_sync WHERE id = 1")->fetch();
        return $row ? (int)$row['total'] : 0;
    } catch (Exception $e) { return 0; }
}

function terrorSectionLabel(string $sectionId): string {
    $scope = str_starts_with($sectionId, 'international') ? 'международная часть' : 'национальная часть';
    $kind  = str_ends_with($sectionId, 'UL') ? 'организация' : 'физическое лицо';
    return ucfirst($kind).', '.$scope;
}
