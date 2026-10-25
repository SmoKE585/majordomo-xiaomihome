<?php
/** Настройки Xiaomi Home и состояние цикла. */
if (!isset($settings, $stats, $url)) {
    http_response_code(404);
    return;
}
?>
<?php $card('Приём сообщений и округление', static function () use ($settings, $hidden, $e, $url): void { ?>
    <form method="post" class="md-ui-form-stack">
        <?php $hidden(array('operation' => 'save_settings')); ?>
        <div class="md-ui-form-grid">
            <?= MDJUI::input(array('name' => 'bind_ip', 'label' => 'Bind IP', 'value' => $settings['bind_ip'], 'placeholder' => '0.0.0.0', 'hint' => 'Пусто — все интерфейсы. Если multicast не приходит, укажите локальный IP сервера из сети шлюза.')) ?>
            <?= MDJUI::select(array('name' => 'round', 'label' => 'Округление датчиков', 'selected' => $settings['round'], 'options' => array(0 => 'Целые', 1 => 'Десятые', 2 => 'Сотые'), 'hint' => 'Температура, влажность, давление и заряд батареи.')) ?>
        </div>
        <?= MDJUI::input(array('name' => 'alive_minutes', 'label' => 'Порог доступности, минут', 'value' => $settings['alive_minutes'], 'type' => 'number', 'attrs' => array('min' => 0, 'max' => 1440), 'hint' => '0 — автоматически по типу устройства: шлюз 15 минут, батарейные датчики до 170, motion и cube — 65. Ручное значение применяется ко всем.')) ?>
        <?= MDJUI::checkbox(array('name' => 'log_debmes', 'label' => 'Писать сообщения шлюза в DebMes', 'value' => '1', 'checked' => $settings['log_debmes'], 'description' => 'Подробный журнал обмена. Включайте для разбора, затем выключайте.')) ?>
        <?= MDJUI::checkbox(array('name' => 'log_cycle', 'label' => 'Писать шаги цикла', 'value' => '1', 'checked' => $settings['log_cycle'], 'description' => 'Подключение сокета, очередь и переподключение.')) ?>
        <?= MDJUI::checkbox(array('name' => 'log_heartbeat', 'label' => 'Включать heartbeat шлюзов в журнал', 'value' => '1', 'checked' => $settings['log_heartbeat'], 'description' => 'Heartbeat приходит каждые несколько секунд и быстро раздувает журнал.')) ?>
        <div class="md-ui-form-actions">
            <?= MDJUI::button('Сохранить настройки', array('type' => 'submit', 'icon' => 'check')) ?>
            <?= MDJUI::button('Отмена', array('href' => $url('list'), 'variant' => 'ghost')) ?>
        </div>
    </form>
<?php }, array('icon' => 'settings')); ?>

<?php $card('Состояние цикла', static function () use ($stats, $url, $hidden): void { ?>
    <div class="md-xiaomihome-state-grid">
        <?= MDJUI::status($stats['cycle_running'] ? 'online' : 'offline', $stats['cycle_running'] ? 'Цикл работает' : 'Цикл не отвечает') ?>
        <div>
            <?php if ($stats['cycle_running']): ?>
                Последний отклик <?= (int)$stats['cycle_age'] ?> с назад. Приём multicast и очередь работают.
            <?php elseif ($stats['cycle_age'] === null): ?>
                След цикла не найден. Запустите <code>php majordomo cycles:start xiaomihome</code> или включите его в X-Ray.
            <?php else: ?>
                Последний отклик <?= (int)$stats['cycle_age'] ?> с назад. Проверьте процесс в X-Ray: возможно, порт 9898 занят.
            <?php endif; ?>
        </div>
    </div>
    <div class="md-ui-kpi-grid md-xiaomihome-kpi-tight">
        <?= MDJUI::kpi(array('label' => 'Устройства', 'value' => $stats['devices'], 'icon' => 'home-bolt')) ?>
        <?= MDJUI::kpi(array('label' => 'Онлайн', 'value' => $stats['online'], 'icon' => 'wifi', 'tone' => 'success')) ?>
        <?= MDJUI::kpi(array('label' => 'Шлюзы', 'value' => $stats['gateways'], 'icon' => 'router')) ?>
        <?= MDJUI::kpi(array('label' => 'Очередь', 'value' => $stats['queue'], 'icon' => 'list-check')) ?>
    </div>
    <div class="md-ui-form-actions">
        <?= MDJUI::button('X-Ray: циклы и журнал', array('href' => ROOTHTML . 'panel/xray.html', 'variant' => 'secondary', 'icon' => 'activity')) ?>
        <?= MDJUI::button('Как подключить', array('href' => $url('help'), 'variant' => 'ghost', 'icon' => 'help-circle')) ?>
        <form method="post" class="md-xiaomihome-inline-form">
            <?php $hidden(array('operation' => 'clear_queue')); ?>
            <?= MDJUI::button('Очистить всю очередь', array('type' => 'submit', 'variant' => 'ghost', 'size' => 'sm', 'icon' => 'eraser', 'attrs' => array('data-md-confirm' => 'Удалить все команды из очереди?'))) ?>
        </form>
    </div>
<?php }, array('icon' => 'refresh')); ?>

<?= MDJUI::hint(array(
    'title' => 'Что меняется при сохранении',
    'points' => array(
        'Настройки хранятся в записи модуля (project_modules.DATA), как и в версии 2018 года.',
        'После сохранения цикл перезапускается, чтобы новый bind IP и округление применились сразу.',
        'Порог доступности влияет только на отображение статуса; сообщения и привязки не зависят от него.',
        'Ключи шлюзов задаются в карточках шлюзов, а не здесь.',
    ),
    'dismissible' => false,
)) ?>
