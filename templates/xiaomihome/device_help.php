<?php
/** Справка по конкретному устройству. */
if (!isset($device, $type, $url)) {
    http_response_code(404);
    return;
}
$label = XiaomiHomeCatalog::title((string)$type);
$batteryType = XiaomiHomeCatalog::battery((string)$type);
$alive = XiaomiHomeCatalog::aliveMinutes((string)$type);
$isGateway = XiaomiHomeCatalog::isGateway((string)$type);
$keyHints = array(
    'temperature' => 'Температура приходит от датчиков sensor_ht и weather.',
    'humidity' => 'Влажность приходит от sensor_ht и weather.',
    'pressure_kpa' => 'Давление приходит от weather; вторая метрика pressure_mm — в мм рт. ст.',
    'battery_level' => 'Заряд рассчитывается из напряжения батарейки. Порог низкого заряда — 20%.',
    'motion' => 'Датчик движения присылает 1 при движении и 0 после периода без движения.',
    'no_motion' => 'Время без движения в минутах; удобно для сценариев «нет движения N минут».',
    'status' => 'Для датчика открытия: 1 — закрыто, 0 — открыто.',
    'leak' => 'Для датчика протечки: 1 — протечка, 0 — норма.',
    'channel' => 'Реле: 1 — включено, 0 — выключено. Для двухканальных устройств — channel_0 и channel_1.',
    'curtain_level' => 'Положение шторы в процентах: 0 — закрыто, 100 — открыто.',
    'curtain_status' => 'Команда управления шторой: open, close, stop, auto.',
    'brightness' => 'Яркость подсветки шлюза; связана с цветом rgb.',
    'rgb' => 'Цвет подсветки шлюза в HEX без #. Запись меняет и яркость.',
    'ringtone' => 'Мелодия шлюза: номер и громкость через запятую, например 10,50.',
);
?>
<?php $card('О модели', static function () use ($label, $type, $batteryType, $alive, $isGateway, $device, $e): void { ?>
    <dl class="md-xiaomihome-meta-list">
        <div><dt>Название</dt><dd><?= $e($label) ?></dd></div>
        <div><dt>Тип протокола</dt><dd class="font-monospace"><?= $e($type ?: '—') ?></dd></div>
        <div><dt>Питание</dt><dd><?= $e($batteryType ?: ($isGateway || XiaomiHomeCatalog::isPowered((string)$type) ? 'сеть' : '—')) ?></dd></div>
        <div><dt>Порог доступности</dt><dd><?= (int)$alive ?> мин без сообщений</dd></div>
        <div><dt>Ключ шлюза</dt><dd><?= $e(trim((string)$device['GATE_KEY']) !== '' ? 'задан' : 'не задан') ?></dd></div>
    </dl>
    <?php if ($isGateway): ?>
        <p class="md-field__hint">Шлюз принимает команды только с подписью ключа. Если ключ не задан или неверный, чтение работает, а переключение реле и мелодия — нет.</p>
    <?php endif; ?>
<?php }, array('icon' => 'info-circle')); ?>

<?php $card('Что можно привязать', static function () use ($device, $label, $keyHints, $e, $url, $id): void { ?>
    <p>Свойства устройства открыты на вкладке «Свойства». Значения приходят от шлюза событиями; опрос нужен только для первичного чтения.</p>
    <?php if ($keyHints): ?>
        <dl class="md-xiaomihome-hints">
            <?php foreach ($keyHints as $key => $hint): ?>
                <div><dt><?= $e(XiaomiHomeCatalog::label($key)) ?></dt><dd><?= $e($hint) ?></dd></div>
            <?php endforeach; ?>
        </dl>
    <?php else: ?>
        <p class="md-field__hint">Для этой модели подсказки по отдельным метрикам не заданы. Откройте «Свойства»: названия и значения там уже человекочитаемые.</p>
    <?php endif; ?>
    <div class="md-ui-form-actions"><?= MDJUI::button('Открыть свойства', array('href' => $url('', (int)$id, 'data'), 'variant' => 'secondary', 'icon' => 'link')) ?></div>
<?php }, array('icon' => 'adjustments-horizontal')); ?>

<?php $card('Если значения не приходят', static function (): void { ?>
    <?= MDJUI::hint(array(
        'title' => 'Проверьте по порядку',
        'steps' => array(
            'Цикл cycle_xiaomihome запущен — статус виден в X-Ray и в настройках модуля.',
            'Шлюз и MajorDoMo в одной сети, UDP 9898 не занят другим процессом.',
            'В настройках модуля не указан чужой bind IP; при нескольких сетях укажите IP интерфейса, смотрящего в сеть шлюза.',
            'Устройство привязано к шлюзу в приложении Mi Home или Aqara Home и шлёт события.',
            'Для команд записи задан ключ шлюза (16 символов).',
        ),
        'dismissible' => false,
    )) ?>
<?php }, array('icon' => 'alert-circle')); ?>
