<?php
/** Карточка устройства Xiaomi Home. */
if (!isset($device, $url, $tab)) {
    http_response_code(404);
    return;
}
$id = (int)$device['ID'];
$type = (string)$device['TYPE'];
$isGateway = XiaomiHomeCatalog::isGateway($type);
$state = $statusOf($online);
$battery = (string)($device['BATTERY'] ?? '');
$batteryType = XiaomiHomeCatalog::battery($type);
$gateKeySet = trim((string)$device['GATE_KEY']) !== '';
$tabTitles = array('' => 'Обзор', 'data' => 'Свойства', 'help' => 'Справка');
$technicalKeys = array('report', 'heartbeat', 'write_ack', 'read_ack', 'write_rsp', 'read_rsp',
    'server_ack', 'server_rsp', 'discovery_rsp', 'iam', 'ip');
$dataCount = 0;
foreach ($commands as $command) {
    if (!in_array($command['TITLE'], $technicalKeys, true)) $dataCount++;
}
?>
<nav class="nav md-tabs" aria-label="Разделы устройства">
    <?php foreach ($tabTitles as $key => $label):
        $href = $key === '' ? $url('', $id) : $url('', $id, $key);
        $active = $tab === $key || ($tab === 'bind' && $key === 'data');
        ?>
        <a class="nav-link <?= $active ? 'active' : '' ?>" href="<?= $e($href) ?>" <?= $active ? 'aria-current="page"' : '' ?>>
            <?= $e($label) ?><?= $key === 'data' ? ' <span class="md-tabs__count">' . (int)$dataCount . '</span>' : '' ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === ''): ?>
    <div class="md-ui-kpi-grid">
        <?= MDJUI::kpi(array('label' => 'Доступность', 'value' => $state === 'online' ? 'Онлайн' : ($state === 'offline' ? 'Офлайн' : 'Неизвестно'), 'icon' => 'wifi', 'tone' => $state === 'online' ? 'success' : ($state === 'offline' ? 'warning' : 'neutral'), 'meta' => $online['age'] === null ? 'сообщений ещё не было' : ($online['age'] < 60 ? $online['age'] . ' с назад' : round($online['age'] / 60) . ' мин назад'))) ?>
        <?= MDJUI::kpi(array('label' => 'Батарея', 'value' => $battery !== '' ? round((float)$battery) . '%' : '—', 'icon' => 'battery', 'tone' => $battery !== '' && (float)$battery <= 20 ? 'danger' : 'neutral', 'meta' => $batteryType !== '' ? $batteryType . ', порог ' . $online['alive'] . ' мин' : 'порог ' . $online['alive'] . ' мин')) ?>
        <?= MDJUI::kpi(array('label' => 'Свойства', 'value' => $dataCount, 'icon' => 'adjustments-horizontal', 'meta' => 'доступно для привязок')) ?>
        <?= MDJUI::kpi(array('label' => 'Очередь шлюза', 'value' => count($queue), 'icon' => 'list-check', 'meta' => (string)$device['GATE_IP'] ?: 'IP шлюза неизвестен')) ?>
    </div>

    <div class="md-xiaomihome-two-col">
        <?php $card('Устройство', static function () use ($deviceImage, $type, $device, $state, $id, $e, $url, $battery, $batteryType): void { ?>
            <div class="md-xiaomihome-device-head">
                <?php $deviceImage($type, true); ?>
                <div class="md-xiaomihome-device-meta">
                    <div class="md-xiaomihome-device-title"><?= $e(XiaomiHomeCatalog::title($type)) ?></div>
                    <?php if ($type !== ''): ?><div class="md-xiaomihome-device-model font-monospace"><?= $e($type) ?></div><?php endif; ?>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <?= MDJUI::status($state, $state === 'online' ? 'Онлайн' : ($state === 'offline' ? 'Офлайн' : 'Нет данных')) ?>
                        <?php if ($battery !== ''): ?><?= MDJUI::badge(round((float)$battery) . '%', (float)$battery <= 20 ? 'danger' : ((float)$battery <= 40 ? 'warning' : 'outline'), array('icon' => 'battery')) ?><?php endif; ?>
                    </div>
                </div>
            </div>
            <dl class="md-xiaomihome-meta-list">
                <div><dt>SID</dt><dd class="font-monospace"><?= $e($device['SID'] ?: '—') ?></dd></div>
                <div><dt>IP шлюза</dt><dd class="font-monospace"><?= $e($device['GATE_IP'] ?: '—') ?></dd></div>
                <div><dt>Тип батарейки</dt><dd><?= $e($batteryType ?: '—') ?></dd></div>
                <div><dt>Последнее сообщение</dt><dd><?= $e($device['UPDATED'] ?: '—') ?></dd></div>
                <?php if ($isGateway): ?><div><dt>Ключ шлюза</dt><dd><?= $e($gateKeySet ? 'задан' : 'не задан') ?></dd></div><?php endif; ?>
                <div><dt>В системе</dt><dd>#<?= (int)$device['ID'] ?></dd></div>
            </dl>
            <div class="md-ui-form-actions">
                <?= MDJUI::button('Свойства и привязки', array('href' => $url('', $id, 'data'), 'variant' => 'secondary', 'icon' => 'link')) ?>
                <?= MDJUI::button('Справка', array('href' => $url('', $id, 'help'), 'variant' => 'ghost', 'icon' => 'book')) ?>
            </div>
        <?php }, array('icon' => 'home-bolt')); ?>

        <?php $card('Настройки устройства', static function () use ($device, $isGateway, $hidden, $e): void { ?>
            <form method="post" class="md-ui-form-stack">
                <?php $hidden(array('operation' => 'save_device', 'device_id' => $device['ID'])); ?>
                <?= MDJUI::input(array('name' => 'title', 'label' => 'Название', 'value' => $device['TITLE'], 'required' => true, 'attrs' => array('maxlength' => 100))) ?>
                <?php if ($isGateway): ?>
                    <?= MDJUI::input(array('name' => 'gate_key', 'label' => 'Ключ шлюза', 'value' => '', 'type' => 'password', 'attrs' => array('autocomplete' => 'off', 'spellcheck' => 'false', 'placeholder' => trim((string)$device['GATE_KEY']) !== '' ? 'оставьте пустым, чтобы не менять' : '16 символов из приложения'), 'hint' => 'Ключ нужен для подписи команд записи. Он не отображается и не пишется в журналы.')) ?>
                <?php endif; ?>
                <p class="md-field__hint">Тип и SID приходят от шлюза. Переименование меняет только название в MajorDoMo.</p>
                <div class="md-ui-form-actions">
                    <?= MDJUI::button('Сохранить', array('type' => 'submit', 'icon' => 'check')) ?>
                </div>
            </form>
            <form method="post" class="md-xiaomihome-danger-zone">
                <?php $hidden(array('operation' => 'delete_device', 'device_id' => $device['ID'])); ?>
                <?= MDJUI::button('Удалить устройство', array('type' => 'submit', 'variant' => 'danger', 'size' => 'sm', 'icon' => 'trash', 'attrs' => array('data-md-confirm' => 'Удалить «' . $device['TITLE'] . '» и его привязки? Объекты и сценарии останутся.'))) ?>
                <span class="md-field__hint">Удаляются запись устройства и строки свойств. Объекты MajorDoMo не изменяются.</span>
            </form>
        <?php }, array('icon' => 'settings')); ?>
    </div>

    <?php $card('Диагностика', static function () use ($device, $hidden, $queue, $technical, $e, $id, $url, $state): void { ?>
        <div class="md-xiaomihome-tools">
            <div class="md-xiaomihome-tool-row">
                <form method="post" class="md-xiaomihome-inline-form">
                    <?php $hidden(array('operation' => 'refresh_device', 'device_id' => $device['ID'])); ?>
                    <?= MDJUI::button('Запросить состояние', array('type' => 'submit', 'variant' => 'secondary', 'size' => 'sm', 'icon' => 'refresh')) ?>
                </form>
                <?php if ((string)$device['GATE_IP'] !== ''): ?>
                    <form method="post" class="md-xiaomihome-inline-form">
                        <?php $hidden(array('operation' => 'clear_queue', 'gate_ip' => $device['GATE_IP'])); ?>
                        <?= MDJUI::button('Очистить очередь шлюза', array('type' => 'submit', 'variant' => 'ghost', 'size' => 'sm', 'icon' => 'eraser', 'attrs' => array('data-md-confirm' => 'Удалить все команды для шлюза ' . $device['GATE_IP'] . '?'))) ?>
                    </form>
                <?php endif; ?>
                <?= MDJUI::button('Все свойства', array('href' => $url('', $id, 'data'), 'variant' => 'ghost', 'size' => 'sm', 'icon' => 'list')) ?>
            </div>
            <?php if ($queue): ?>
                <details class="md-xiaomihome-details">
                    <summary>Последние команды шлюзу (<?= count($queue) ?>)</summary>
                    <div class="md-table__scroll">
                        <table class="md-table md-table--compact">
                            <thead><tr><th scope="col">Добавлена</th><th scope="col">Сообщение</th></tr></thead>
                            <tbody>
                            <?php foreach ($queue as $row): ?>
                                <tr><td><span class="md-table__signal"><?= $e($row['ADDED']) ?></span></td><td class="md-xiaomihome-value"><?= $e(mb_substr((string)$row['DATA'], 0, 200)) ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            <?php endif; ?>
            <?php if ($technical): ?>
                <details class="md-xiaomihome-details">
                    <summary>Последние технические сообщения (<?= count($technical) ?>)</summary>
                    <div class="md-xiaomihome-log-list">
                        <?php foreach (array_slice($technical, 0, 8) as $command): ?>
                            <div class="md-xiaomihome-log-item">
                                <div><strong><?= $e($command['TITLE']) ?></strong> <span class="md-table__signal"><?= $e($command['UPDATED']) ?></span></div>
                                <pre class="md-xiaomihome-json"><?= htmlspecialchars((string)$command['VALUE'], ENT_QUOTES, 'UTF-8') ?></pre>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
            <p class="md-field__hint">Если шлюз не отвечает: проверьте ключ, IP шлюза и что UDP 9898 не занят другим процессом. Для приёма multicast на сервере с несколькими сетями укажите IP интерфейса в настройках.</p>
        </div>
    <?php }, array('icon' => 'stethoscope')); ?>

<?php elseif ($tab === 'data'): ?>
    <section class="md-table-wrap" aria-label="Свойства устройства"
             data-md-table data-md-table-unit-one="свойство" data-md-table-unit-few="свойства" data-md-table-unit-many="свойств">
        <div class="md-table__toolbar">
            <div class="md-table__search">
                <?= MDJUI::icon('search') ?>
                <input type="search" data-md-table-search placeholder="Свойство, ключ или привязка" aria-label="Поиск свойств" autocomplete="off">
            </div>
            <span class="md-table__meta" data-md-table-meta>Свойств: <?= count($commands) ?></span>
        </div>
        <?php if (!$commands): ?>
            <?= MDJUI::emptyState(array('icon' => 'list', 'title' => 'Свойств пока нет', 'text' => 'Значения появятся после первого сообщения шлюза об этом устройстве.', 'compact' => true)) ?>
        <?php else: ?>
            <div class="md-table__scroll">
                <table class="md-table">
                    <thead>
                    <tr>
                        <th scope="col"><button type="button" class="md-table__sort" data-md-table-sort="name">Свойство <?= MDJUI::icon('arrows-up-down') ?></button></th>
                        <th scope="col">Значение</th>
                        <th scope="col">Обновлено</th>
                        <th scope="col">Привязка</th>
                        <th scope="col" class="text-end" aria-label="Действия"></th>
                    </tr>
                    </thead>
                    <tbody data-md-table-body>
                    <?php foreach ($commands as $row):
                        $key = (string)$row['TITLE'];
                        $label = XiaomiHomeCatalog::label($key);
                        $bound = trim((string)$row['LINKED_OBJECT']) !== '' && trim((string)$row['LINKED_PROPERTY']) !== '';
                        $technicalRow = in_array($key, $technicalKeys, true);
                        ?>
                        <tr data-md-row data-sort-name="<?= $e($label) ?>" data-search="<?= $e($label . ' ' . $key . ' ' . ($row['VALUE'] ?? '') . ' ' . ($row['LINKED_OBJECT'] ?? '') . ' ' . ($row['LINKED_PROPERTY'] ?? '')) ?>">
                            <td>
                                <div class="md-table__name">
                                    <span class="md-table__icon"><?= MDJUI::icon($key === 'battery_level' ? 'battery' : ($key === 'temperature' ? 'temperature' : ($key === 'humidity' ? 'droplet' : 'adjustments-horizontal'))) ?></span>
                                    <div>
                                        <a href="<?= $e($url('', $id, 'bind-' . (int)$row['ID'])) ?>"><strong><?= $e($label) ?></strong></a>
                                        <small class="font-monospace"><?= $e($key) ?></small>
                                        <?php if ($technicalRow): ?><?= MDJUI::badge('техническое', 'neutral') ?><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="md-xiaomihome-value"><?= $e(XiaomiHomeCatalog::display($key, $row['VALUE'])) ?></td>
                            <td><span class="md-table__signal"><?= $e($row['UPDATED'] ?: '—') ?></span></td>
                            <td>
                                <?php if ($bound): ?>
                                    <?= MDJUI::badge($row['LINKED_OBJECT'] . '.' . $row['LINKED_PROPERTY'], 'outline', array('icon' => 'link')) ?>
                                <?php else: ?>
                                    <span class="md-table__signal">не привязано</span>
                                <?php endif; ?>
                                <?php if (trim((string)$row['LINKED_METHOD']) !== ''): ?><?= MDJUI::badge('+' . $row['LINKED_METHOD'], 'neutral', array('icon' => 'bolt')) ?><?php endif; ?>
                            </td>
                            <td><div class="md-table__actions"><?= MDJUI::button('', array('href' => $url('', $id, 'bind-' . (int)$row['ID']), 'variant' => 'ghost', 'size' => 'sm', 'icon' => 'pencil', 'attrs' => array('title' => 'Настроить привязку', 'aria-label' => 'Настроить ' . $label))) ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="md-table__empty" data-md-table-empty><?= MDJUI::emptyState(array('title' => 'Свойства не найдены', 'text' => 'Измените поиск.', 'compact' => true)) ?></div>
            <div class="md-table__footer"><span data-md-table-count>Свойств: <?= count($commands) ?></span></div>
        <?php endif; ?>
    </section>

<?php elseif ($tab === 'bind' && $bindingCommand): ?>
    <?php
    $key = (string)$bindingCommand['TITLE'];
    $label = XiaomiHomeCatalog::label($key);
    $bound = trim((string)$bindingCommand['LINKED_OBJECT']) !== '' && trim((string)$bindingCommand['LINKED_PROPERTY']) !== '';
    ?>
    <p class="md-xiaomihome-context">
        <a href="<?= $e($url('', $id, 'data')) ?>">Свойства устройства</a> ·
        <strong><?= $e($label) ?></strong> · <code><?= $e($key) ?></code>
    </p>
    <?php $card('Привязка к объекту MajorDoMo', static function () use ($bindingCommand, $linkedControl, $hidden, $url, $e, $bound, $key, $label): void { ?>
        <form method="post" class="md-ui-form-stack">
            <?php $hidden(array('operation' => 'save_binding', 'command_id' => $bindingCommand['ID'])); ?>
            <div class="md-ui-form-grid">
                <div class="md-field">
                    <span class="md-field__label">Последнее значение</span>
                    <span class="md-xiaomihome-value"><?= $e(XiaomiHomeCatalog::display($key, $bindingCommand['VALUE'])) ?></span>
                </div>
                <div class="md-field">
                    <span class="md-field__label">Обновлено</span>
                    <span><?= $e($bindingCommand['UPDATED'] ?: 'ещё не приходило') ?></span>
                </div>
            </div>
            <p class="md-field__hint">Когда шлюз присылает событие, модуль записывает значение в свойство объекта. Когда свойство объекта меняется из сценария или панели, модуль ставит команду шлюзу в очередь. Повтор того же значения команду не отправляет.</p>
            <?= $linkedControl ?>
            <div class="md-ui-form-actions">
                <?= MDJUI::button('Сохранить привязку', array('type' => 'submit', 'icon' => 'check')) ?>
                <?= MDJUI::button('К свойствам', array('href' => $url('', (int)$bindingCommand['DEVICE_ID'], 'data'), 'variant' => 'ghost')) ?>
                <?php if ($bound): ?>
                    <?= MDJUI::button('Снять привязку', array('type' => 'submit', 'variant' => 'secondary', 'icon' => 'link-off', 'attrs' => array('name' => 'operation', 'value' => 'delete_binding', 'data-md-confirm' => 'Снять привязку? Свойство объекта останется, но перестанет обновляться.'))) ?>
                <?php endif; ?>
            </div>
        </form>
        <hr class="md-xiaomihome-divider">
        <form method="post" class="md-xiaomihome-inline-form">
            <?php $hidden(array('operation' => 'delete_command', 'command_id' => $bindingCommand['ID'])); ?>
            <?= MDJUI::button('Удалить свойство из модуля', array('type' => 'submit', 'variant' => 'danger', 'size' => 'sm', 'icon' => 'trash', 'attrs' => array('data-md-confirm' => 'Удалить строку свойства? При следующем сообщении шлюза она может появиться снова.'))) ?>
        </form>
    <?php }, array('icon' => 'link')); ?>

<?php elseif ($tab === 'help'): ?>
    <?php require __DIR__ . '/device_help.php'; ?>
<?php endif; ?>
