<?php
/** Список устройств Xiaomi Home. */
if (!isset($devices, $stats, $url)) {
    http_response_code(404);
    return;
}
$unit = static fn($n, $one, $few, $many) => MDJUI::plural((int)$n, $one, $few, $many);
$listReturn = $url('list');
$listQuery = http_build_query(array_filter(array(
    'q' => $search,
    'type' => $typeFilter,
    'sort' => $sort !== 'updated' ? $sort : '',
    'page' => $page > 1 ? $page : '',
), static fn($value) => $value !== '' && $value !== null));
if ($listQuery !== '') $listReturn .= '?' . $listQuery;
$ageText = static function (array $status): string {
    if ($status['age'] === null) return 'ещё не отвечало';
    if ($status['age'] < 60) return $status['age'] . ' с назад';
    return round($status['age'] / 60) . ' мин назад';
};
?>
<div class="md-ui-kpi-grid">
    <?= MDJUI::kpi(array('label' => 'Устройства', 'value' => $stats['devices'], 'icon' => 'home-bolt', 'meta' => 'найдено шлюзом')) ?>
    <?= MDJUI::kpi(array('label' => 'Онлайн', 'value' => $stats['online'], 'icon' => 'wifi', 'tone' => 'success', 'meta' => 'в пределах порога доступности')) ?>
    <?= MDJUI::kpi(array('label' => 'Шлюзы', 'value' => $stats['gateways'], 'icon' => 'router', 'meta' => 'принимают сообщения')) ?>
    <?= MDJUI::kpi(array('label' => 'Низкий заряд', 'value' => $stats['low_battery'], 'icon' => 'battery', 'tone' => $stats['low_battery'] ? 'warning' : 'neutral', 'meta' => 'меньше 20%')) ?>
    <?= MDJUI::kpi(array('label' => 'Очередь', 'value' => $stats['queue'], 'icon' => 'list-check', 'meta' => $stats['queue'] ? 'команд ждут отправки' : 'пусто')) ?>
</div>

<?php if (!$stats['cycle_running']): ?>
    <?php
    $hint = 'Приём сообщений шлюза и очередь работают только при запущенном цикле cycle_xiaomihome. ';
    $hint .= $stats['cycle_age'] === null ? 'След цикла ещё не найден. ' : 'Последний отклик ' . (int)$stats['cycle_age'] . ' с назад. ';
    $hint .= 'Проверьте его в X-Ray или запустите командой php majordomo cycles:start xiaomihome.';
    echo MDJUI::alert('warning', 'Цикл модуля не запущен', $hint, array(
        'actions' => MDJUI::button('Открыть X-Ray', array('href' => ROOTHTML . 'panel/xray.html', 'variant' => 'secondary', 'size' => 'sm', 'icon' => 'activity')),
    ));
    ?>
<?php endif; ?>

<section class="md-table-wrap" aria-label="Устройства Xiaomi Home" data-md-xiaomihome-list>
    <form method="get" action="<?= $e($url('list')) ?>" class="md-table__toolbar">
        <div class="md-table__search">
            <?= MDJUI::icon('search') ?>
            <input type="search" name="q" value="<?= $e($search) ?>" placeholder="Название, тип или SID" aria-label="Поиск устройств" autocomplete="off">
        </div>
        <select class="md-table__filter" name="type" aria-label="Фильтр по типу">
            <option value="">Все типы</option>
            <?php foreach ($types as $typeRow): ?>
                <option value="<?= $e($typeRow['TYPE']) ?>" <?= $typeFilter === $typeRow['TYPE'] ? 'selected' : '' ?>>
                    <?= $e(XiaomiHomeCatalog::title((string)$typeRow['TYPE'])) ?> (<?= (int)$typeRow['TOTAL'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <select class="md-table__filter" name="sort" aria-label="Сортировка">
            <?php foreach (array('updated' => 'По активности', 'title' => 'По названию', 'type' => 'По типу', 'new' => 'Сначала новые') as $key => $label): ?>
                <option value="<?= $e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= $e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <?= MDJUI::button('Найти', array('type' => 'submit', 'variant' => 'secondary', 'size' => 'sm', 'icon' => 'search')) ?>
        <?php if ($search !== '' || $typeFilter !== ''): ?><?= MDJUI::button('Сбросить', array('href' => $url('list'), 'variant' => 'ghost', 'size' => 'sm')) ?><?php endif; ?>
        <span class="md-table__meta">Найдено: <?= (int)$count ?></span>
    </form>

    <?php if (!$devices): ?>
        <?= MDJUI::emptyState(array(
            'icon' => 'home-bolt',
            'title' => $search !== '' || $typeFilter !== '' ? 'Устройства не найдены' : 'Шлюз ещё не прислал устройства',
            'text' => $search !== '' || $typeFilter !== ''
                ? 'Измените запрос или сбросьте фильтр.'
                : 'Включите локальный протокол шлюза в приложении, укажите ключ в карточке шлюза и запустите цикл. Устройства появятся сами после первого сообщения.',
            'action' => MDJUI::button('Как подключить', array('href' => $url('help'), 'icon' => 'help-circle'))
                . MDJUI::button('Настройки', array('href' => $url('settings'), 'variant' => 'secondary', 'icon' => 'settings')),
        )) ?>
    <?php else: ?>
        <div class="md-table__scroll">
            <table class="md-table">
                <thead>
                <tr>
                    <th scope="col">Устройство</th>
                    <th scope="col">Тип</th>
                    <th scope="col">Доступность</th>
                    <th scope="col">Батарея</th>
                    <th scope="col">Последние значения</th>
                    <th scope="col">Обновлено</th>
                    <th scope="col" class="text-end" aria-label="Действия"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($devices as $row):
                    $status = $row['STATUS'] ?? array('state' => 'unknown', 'age' => null, 'alive' => 60);
                    $state = $statusOf($status);
                    $isGateway = XiaomiHomeCatalog::isGateway((string)$row['TYPE']);
                    ?>
                    <tr data-md-device-id="<?= (int)$row['ID'] ?>" class="<?= $state === 'offline' ? 'is-lost' : '' ?>">
                        <td>
                            <div class="md-table__name">
                                <?php $deviceImage((string)$row['TYPE']); ?>
                                <div>
                                    <a href="<?= $e($url('', (int)$row['ID'])) ?>"><strong><?= $e($row['TITLE'] ?: 'Без названия') ?></strong></a>
                                    <small class="font-monospace"><?= $e($row['SID']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="md-table__name">
                                <div>
                                    <strong><?= $e(XiaomiHomeCatalog::title((string)$row['TYPE'])) ?></strong>
                                    <?php if ((string)$row['TYPE'] !== ''): ?><small><?= $e($row['TYPE']) ?></small><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span data-md-role="status"><?= MDJUI::status($state, $state === 'online' ? 'Онлайн' : ($state === 'offline' ? 'Офлайн' : 'Нет данных')) ?></span>
                            <div class="md-xiaomihome-age" data-md-role="age"><?= $e($ageText($status)) ?></div>
                        </td>
                        <td><span data-md-role="battery"><?php $batteryBadge((string)($row['BATTERY'] ?? '')); ?></span></td>
                        <td class="md-xiaomihome-preview" data-md-role="preview"><?= $e($row['PREVIEW'] ?: '—') ?></td>
                        <td><span class="md-table__signal" data-md-role="updated"><?= $e($row['UPDATED'] ?: '—') ?></span></td>
                        <td>
                            <div class="md-table__actions">
                                <form method="post" class="md-xiaomihome-inline-form">
                                    <?php $hidden(array('operation' => 'refresh_device', 'device_id' => $row['ID'], 'return_url' => $listReturn)); ?>
                                    <?= MDJUI::button('', array('type' => 'submit', 'variant' => 'ghost', 'size' => 'sm', 'icon' => 'refresh', 'attrs' => array('title' => 'Запросить состояние', 'aria-label' => 'Запросить состояние ' . $row['TITLE']))) ?>
                                </form>
                                <?= MDJUI::button('', array('href' => $url('', (int)$row['ID']), 'variant' => 'ghost', 'size' => 'sm', 'icon' => 'arrow-right', 'attrs' => array('title' => 'Открыть устройство', 'aria-label' => 'Открыть ' . $row['TITLE']))) ?>
                                <form method="post" class="md-xiaomihome-inline-form">
                                    <?php $hidden(array('operation' => 'delete_device', 'device_id' => $row['ID'])); ?>
                                    <?= MDJUI::button('', array('type' => 'submit', 'variant' => 'ghost', 'size' => 'sm', 'icon' => 'trash', 'attrs' => array('title' => 'Удалить устройство', 'aria-label' => 'Удалить ' . $row['TITLE'], 'data-md-confirm' => 'Удалить «' . $row['TITLE'] . '» вместе с привязками? Объекты и сценарии останутся без изменений.'))) ?>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="md-table__footer">
            <span>Показано <?= $count ? (($page - 1) * 40 + 1) : 0 ?>–<?= min($count, $page * 40) ?> из <?= (int)$count ?></span>
            <?php if ($pages > 1): ?>
                <nav aria-label="Страницы устройств">
                    <ul class="pagination md-pagination mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <?php $prev = $url('list') . '?' . http_build_query(array('q' => $search, 'type' => $typeFilter, 'sort' => $sort, 'page' => max(1, $page - 1))); ?>
                            <?php if ($page > 1): ?><a class="page-link" href="<?= $e($prev) ?>" aria-label="Предыдущая страница"><?= MDJUI::icon('chevron-left') ?></a><?php else: ?><span class="page-link"><?= MDJUI::icon('chevron-left') ?></span><?php endif; ?>
                        </li>
                        <li class="page-item active"><span class="page-link" aria-current="page"><?= (int)$page ?> из <?= (int)$pages ?></span></li>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                            <?php $next = $url('list') . '?' . http_build_query(array('q' => $search, 'type' => $typeFilter, 'sort' => $sort, 'page' => min($pages, $page + 1))); ?>
                            <?php if ($page < $pages): ?><a class="page-link" href="<?= $e($next) ?>" aria-label="Следующая страница"><?= MDJUI::icon('chevron-right') ?></a><?php else: ?><span class="page-link"><?= MDJUI::icon('chevron-right') ?></span><?php endif; ?>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?= MDJUI::hint(array(
    'title' => 'Откуда берутся устройства',
    'points' => array(
        'Шлюз сам присылает сообщения о суб-устройствах в multicast-группу 224.0.0.50:9898.',
        'Новые устройства появляются в списке автоматически; тип и SID приходят от шлюза.',
        'Для команд нужен ключ шлюза: без него чтение работает, а запись не подписывается.',
        'Список обновляется каждые 8 секунд без перезагрузки страницы.',
    ),
    'dismissible' => false,
)) ?>
