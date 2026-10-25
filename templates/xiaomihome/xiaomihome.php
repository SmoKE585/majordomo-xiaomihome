<?php
/**
 * Xiaomi Home: общая оболочка страниц модуля.
 *
 * Шаблон только выводит подготовленные данные. Действия — обычные POST-формы
 * с CSRF-токеном, поэтому страница работает после partLoad и без сборки JS.
 */
if (!isset($view, $csrf) || !class_exists('MDJUI', false)) {
    http_response_code(404);
    return;
}

$e = static fn($value) => MDJUI::e((string)($value ?? ''));
$url = static function (string $page = '', int $id = 0, string $tab = '') use ($view): string {
    if ($page === 'list') return ROOTHTML . 'panel/xiaomihome.html';
    if ($page !== '') return ROOTHTML . 'panel/xiaomihome/0/' . $page . '.html';
    $base = ROOTHTML . 'panel/xiaomihome/' . max(0, $id) . '.html';
    if ($tab !== '') $base = ROOTHTML . 'panel/xiaomihome/' . max(0, $id) . '/' . rawurlencode($tab) . '.html';
    return $base;
};
$hidden = static function (array $fields) use ($e, $csrf): void {
    // _md_csrf проверяет AuthGate, _token — сам модуль.
    echo '<input type="hidden" name="_md_csrf" value="' . $e($csrf) . '">';
    echo '<input type="hidden" name="_token" value="' . $e($csrf) . '">';
    foreach ($fields as $name => $value) {
        echo '<input type="hidden" name="' . $e($name) . '" value="' . $e($value) . '">';
    }
};
$card = static function (string $title, callable $body, array $props = array()): void {
    ob_start();
    $body();
    $slot = (string)ob_get_clean();
    echo MDJUI::card(array_merge(array('title' => $title), $props), $slot);
};
$deviceImage = static function (string $type, bool $large = false) use ($e): void {
    $src = XiaomiHomeCatalog::imageUrl($type);
    ?><span class="md-table__icon md-xiaomihome-image<?= $large ? ' md-xiaomihome-image--large' : '' ?>" data-md-xiaomihome-image>
        <?php if ($src !== null): ?><img src="<?= $e($src) ?>" alt="" width="<?= $large ? 88 : 44 ?>" height="<?= $large ? 88 : 44 ?>" loading="lazy" decoding="async" data-md-xiaomihome-photo><?php endif; ?>
        <?= MDJUI::icon('home-bolt') ?>
    </span><?php
};
$batteryBadge = static function (string $level) use ($e): void {
    if ($level === '' || !is_numeric($level)) return;
    $number = (float)$level;
    $tone = $number <= 20 ? 'danger' : ($number <= 40 ? 'warning' : 'outline');
    echo MDJUI::badge(round($number) . '%', $tone, array('icon' => 'battery'));
};
$statusOf = static function (array $status): string {
    return $status['state'] === 'online' ? 'online' : ($status['state'] === 'offline' ? 'offline' : 'idle');
};

$title = 'Xiaomi Home';
$lede = 'Zigbee-устройства Xiaomi и Aqara через локальный протокол шлюза.';
if ($view === 'settings') {
    $title = 'Настройки Xiaomi Home';
    $lede = 'Сетевой интерфейс multicast-приёма, округление и диагностика.';
} elseif ($view === 'help') {
    $title = 'Как подключить шлюз';
    $lede = 'Локальный протокол, ключ шлюза и почему устройства появляются сами.';
} elseif ($view === 'edit_xidevices' && $device) {
    $title = (string)$device['TITLE'];
    $lede = XiaomiHomeCatalog::title((string)$device['TYPE']) . ' · SID ' . (string)$device['SID'];
}
?>
<link rel="stylesheet" href="<?= $e(ROOTHTML) ?>assets/modules/xiaomihome/xiaomihome.css?v=20261025-1">
<script src="<?= $e(ROOTHTML) ?>assets/modules/xiaomihome/xiaomihome.js?v=20261025-1" defer></script>
<div class="md-ui-page md-ui-page--stack md-xiaomihome-page" data-md-xiaomihome
     data-md-csrf="<?= $e($csrf) ?>"
     data-md-ajax-url="<?= $e(ROOTHTML . 'ajax/xiaomihome.html') ?>">
    <header class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><?= MDJUI::icon('home-bolt') ?> Xiaomi Home</div>
            <h1 class="md-ui-hero__title"><?= $e($title) ?></h1>
            <p class="md-ui-hero__lede"><?= $e($lede) ?></p>
        </div>
        <div class="md-ui-hero__actions">
            <?php if ($view === 'search_xidevices'): ?>
                <?= MDJUI::button('Настройки', array('href' => $url('settings'), 'variant' => 'secondary', 'icon' => 'settings')) ?>
                <?= MDJUI::button('Как подключить', array('href' => $url('help'), 'variant' => 'ghost', 'icon' => 'help-circle')) ?>
            <?php elseif ($view === 'edit_xidevices' && $device): ?>
                <?= MDJUI::button('К устройствам', array('href' => $url('list'), 'variant' => 'secondary', 'icon' => 'arrow-left')) ?>
                <form method="post" class="md-xiaomihome-inline-form">
                    <?php $hidden(array('operation' => 'refresh_device', 'device_id' => $device['ID'])); ?>
                    <?= MDJUI::button('Опросить', array('type' => 'submit', 'icon' => 'refresh')) ?>
                </form>
                <?= MDJUI::button('Настройки', array('href' => $url('settings'), 'variant' => 'ghost', 'icon' => 'settings')) ?>
            <?php else: ?>
                <?= MDJUI::button('К устройствам', array('href' => $url('list'), 'variant' => 'secondary', 'icon' => 'arrow-left')) ?>
                <?php if ($view === 'settings'): ?><?= MDJUI::button('Как подключить', array('href' => $url('help'), 'variant' => 'ghost', 'icon' => 'help-circle')) ?><?php endif; ?>
                <?php if ($view === 'help'): ?><?= MDJUI::button('Настройки', array('href' => $url('settings'), 'variant' => 'ghost', 'icon' => 'settings')) ?><?php endif; ?>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($error !== ''): ?>
        <?= MDJUI::alert('danger', 'Действие не выполнено', $error) ?>
    <?php endif; ?>
    <?php if (is_array($flash) && !empty($flash['title'])): ?>
        <?= MDJUI::alert($flash['type'] ?? 'success', (string)$flash['title'], (string)($flash['text'] ?? '')) ?>
    <?php endif; ?>

    <?php if ($view === 'search_xidevices'): ?>
        <?php require __DIR__ . '/devices.php'; ?>
    <?php elseif ($view === 'edit_xidevices'): ?>
        <?php require __DIR__ . '/device.php'; ?>
    <?php elseif ($view === 'settings'): ?>
        <?php require __DIR__ . '/settings.php'; ?>
    <?php elseif ($view === 'help'): ?>
        <?php require __DIR__ . '/help.php'; ?>
    <?php endif; ?>
</div>
