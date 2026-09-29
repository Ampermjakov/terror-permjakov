<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/terror.php';

$checkResult  = null;
$checkedQuery = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['q'])) {
    $checkedQuery = trim($_POST['q']);
    $checkResult  = checkTerror($checkedQuery);
}

$cacheAge    = getCacheAge();
$terrorCount = getTerrorCount();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список террористов и экстремистов — проверка | permjakov.ru</title>
    <meta name="description" content="Проверка по перечню лиц и организаций, причастных к экстремизму или терроризму (Росфинмониторинг).">
    <link rel="icon" href="https://permjakov.ru/amper.svg" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <nav class="topnav">
        <div class="topnav-inner">
            <a href="https://permjakov.ru" class="brand"><span>//</span> permjakov.ru</a>
            <div class="nav-tools">
                <a href="https://pd.permjakov.ru/" class="nav-tool">РНК ПД</a>
                <a href="https://chek.permjakov.ru/" class="nav-tool">РКН Аудит</a>
                <a href="https://rkn.permjakov.ru" class="nav-tool">РКН Блок</a>
                <a href="https://cbr.permjakov.ru" class="nav-tool">ЦБ Стоп-лист</a>
                <a href="https://terror.permjakov.ru" class="nav-tool active">Терроризм</a>
                <a href="https://agents.permjakov.ru" class="nav-tool">Иноагенты</a>
                <a href="https://whois.permjakov.ru" class="nav-tool">Whois</a>
            </div>
            <button class="nav-burger" id="navBurger" aria-label="Меню">
                <span></span><span></span><span></span>
            </button>
        </div>
    </nav>

    <div class="container">

        <header class="header">
            <div class="header-badge">
                <span class="badge-dot"></span>
                Официальный перечень Росфинмониторинга<?php if ($terrorCount): ?> · <?= number_format($terrorCount) ?> записей<?php endif; ?>
            </div>
            <h1>Список террористов и экстремистов</h1>
            <p>Проверка по перечню организаций и физических лиц, в отношении которых имеются сведения об их причастности к экстремистской деятельности или терроризму.<br>Введите ФИО или название организации.</p>
        </header>

        <div class="card ad-1">
            <form method="POST" class="search-form" id="checkForm">
                <input type="text" name="q" class="search-input"
                    placeholder="ФИО или название организации"
                    value="<?= htmlspecialchars($_POST['q'] ?? '') ?>"
                    autocomplete="off" spellcheck="false" required>
                <button type="submit" class="search-btn" id="checkBtn">Проверить →</button>
            </form>
            <?php if ($cacheAge): ?>
                <div class="cache-info">Данные Росфинмониторинга обновлены: <?= htmlspecialchars($cacheAge) ?></div>
            <?php endif; ?>
        </div>

        <?php if ($checkResult): ?>
            <?php if ($checkResult['status'] === 'error'): ?>
                <div class="card result-card ad-2">
                    <div class="result-state result-error">
                        <div class="result-icon">⚠️</div>
                        <div>
                            <div class="result-title">Ошибка проверки</div>
                            <div class="result-sub"><?= htmlspecialchars($checkResult['message']) ?></div>
                        </div>
                    </div>
                </div>

            <?php elseif ($checkResult['status'] === 'found'): ?>
                <div class="card result-card ad-2">
                    <div class="result-state result-danger">
                        <div class="result-icon">🚨</div>
                        <div>
                            <div class="result-title">Найден в перечне террористов/экстремистов</div>
                            <div class="result-sub"><?= htmlspecialchars($checkedQuery) ?> · <?= $checkResult['count'] ?> совпадение(й)</div>
                        </div>
                    </div>
                    <?php foreach (array_slice($checkResult['items'], 0, 5) as $item): ?>
                        <div class="cbr-item cbr-item-danger">
                            <div class="cbr-row"><span class="cbr-key">Запись</span><span class="cbr-val"><?= htmlspecialchars($item['raw_text']) ?></span></div>
                            <div class="cbr-row"><span class="cbr-key">Категория</span><span class="cbr-val"><?= htmlspecialchars(terrorSectionLabel($item['section'])) ?></span></div>
                        </div>
                    <?php endforeach; ?>
                    <div class="result-actions">
                        <a href="https://www.fedsfm.ru/documents/terr-list" target="_blank" rel="noopener" class="btn-secondary">Официальная страница Росфинмониторинга ↗</a>
                    </div>
                </div>

            <?php else: ?>
                <div class="card result-card ad-2">
                    <div class="result-state result-clean">
                        <div class="result-icon">✅</div>
                        <div>
                            <div class="result-title">Не найден в перечне</div>
                            <div class="result-sub"><?= htmlspecialchars($checkedQuery) ?> отсутствует в перечне террористов и экстремистов</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🔍</div>
                <h3>Актуальные данные</h3>
                <p>Проверка по официальному перечню Росфинмониторинга. Кэш обновляется каждый час.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">👤</div>
                <h3>Физлица и организации</h3>
                <p>Национальная часть перечня — и физические лица, и организации, в одном поиске.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📄</div>
                <h3>Полная запись</h3>
                <p>Показывается исходная формулировка Росфинмониторинга — без искажений при разборе.</p>
            </div>
        </div>

        <footer class="footer">
            <p>© <?= date('Y') ?> <a href="https://permjakov.ru">permjakov.ru</a> · Данные: <a href="https://www.fedsfm.ru/documents/terr-list" target="_blank" rel="noopener">fedsfm.ru</a></p>
            <div class="footer-links">
                <a href="https://pd.permjakov.ru/" class="nav-tool">РНК ПД</a>
                <a href="https://chek.permjakov.ru/" class="nav-tool">РКН Аудит</a>
                <a href="https://rkn.permjakov.ru" class="nav-tool">РКН Блок</a>
                <a href="https://cbr.permjakov.ru" class="nav-tool">ЦБ Стоп-лист</a>
                <a href="https://terror.permjakov.ru" class="nav-tool active">Терроризм</a>
                <a href="https://agents.permjakov.ru" class="nav-tool">Иноагенты</a>
                <a href="https://whois.permjakov.ru" class="nav-tool">Whois</a>
            </div>
        </footer>
    </div>

    <div class="nav-mobile" id="navMobile">
        <button class="nav-mobile-close" id="navMobileClose" aria-label="Закрыть">✕</button>
        <a href="https://pd.permjakov.ru/" class="nav-tool">РНК ПД</a>
        <a href="https://chek.permjakov.ru/" class="nav-tool">РКН Аудит</a>
        <a href="https://rkn.permjakov.ru" class="nav-tool">РКН Блок</a>
        <a href="https://cbr.permjakov.ru" class="nav-tool">ЦБ Стоп-лист</a>
        <a href="https://terror.permjakov.ru" class="nav-tool active">Терроризм</a>
        <a href="https://agents.permjakov.ru" class="nav-tool">Иноагенты</a>
        <a href="https://whois.permjakov.ru" class="nav-tool">Whois</a>
    </div>

    <script>
        document.getElementById('checkForm').addEventListener('submit', function () {
            document.getElementById('checkBtn').disabled = true;
        });
        document.getElementById('navBurger').addEventListener('click', function () {
            document.getElementById('navMobile').classList.add('open');
        });
        document.getElementById('navMobileClose').addEventListener('click', function () {
            document.getElementById('navMobile').classList.remove('open');
        });
    </script>
</body>

</html>
