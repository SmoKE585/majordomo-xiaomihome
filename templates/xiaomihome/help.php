<?php
/** Общая инструкция: подключение шлюза и локальный протокол. */
if (!isset($url)) {
    http_response_code(404);
    return;
}
?>
<?= MDJUI::alert('info', 'Модуль не требует облака Xiaomi', 'Шлюз рассылает события в локальную multicast-группу 224.0.0.50:9898, MajorDoMo их принимает. Интернет нужен только приложению, которым вы настраиваете шлюз.') ?>

<?php $card('Как включить локальный протокол', static function () use ($url): void { ?>
    <?= MDJUI::hint(array(
        'title' => 'Порядок действий',
        'steps' => array(
            'Убедитесь, что шлюз и сервер MajorDoMo в одной сети. Шлюз должен быть уже добавлен в Mi Home или Aqara Home.',
            'В Aqara Home откройте шлюз → «Настройки» / «О устройстве» и включите локальный протокол (LAN). Там же показан 16-символьный ключ.',
            'В Mi Home локальный протокол включается в настройках шлюза на старых прошивках. Если ключ не показан, воспользуйтесь инструкцией проекта lumi-gateway-local-api.',
            'Откройте «Устройства»: шлюз появится сам после первого heartbeat. Если нет — проверьте цикл и bind IP в настройках.',
            'Откройте карточку шлюза и вставьте ключ. Без ключа чтение работает, а команды записи шлюз не выполнит.',
            'Нажмите «Опросить» у суб-устройства — шлюз пришлёт текущие значения.',
        ),
        'actions' => MDJUI::button('Настройки модуля', array('href' => $url('settings'), 'variant' => 'secondary', 'size' => 'sm', 'icon' => 'settings')),
        'dismissible' => false,
    )) ?>
<?php }, array('icon' => 'plug-connected')); ?>

<div class="md-xiaomihome-two-col">
    <?php $card('Ключ шлюза', static function (): void { ?>
        <p>Ключ — это AES-128-ключ длиной 16 символов. Им подписывается каждая команда записи, поэтому без ключа нельзя переключить реле, штору или мелодию.</p>
        <ul class="md-xiaomihome-list">
            <li>Ключ хранится в таблице <code>xidevices.GATE_KEY</code> и не выводится в интерфейсе.</li>
            <li>В журналы и ошибки ключ не попадает.</li>
            <li>При смене ключа в приложении обновите его в карточке шлюза.</li>
            <li>Если ключ неверный, шлюз молча игнорирует запись, а чтение продолжает работать.</li>
        </ul>
    <?php }, array('icon' => 'key')); ?>

    <?php $card('Почему устройства появляются сами', static function (): void { ?>
        <p>Протокол шлюза не требует ручного добавления суб-устройств. Шлюз присылает <code>heartbeat</code>, <code>report</code> и <code>read_ack</code> с полями <code>sid</code>, <code>model</code> и <code>data</code>. Модуль создаёт запись в <code>xidevices</code> и строки метрик в <code>xicommands</code>.</p>
        <p class="md-field__hint">Название можно изменить в карточке. Тип и SID — данные протокола, они не редактируются.</p>
    <?php }, array('icon' => 'radar')); ?>
</div>

<?php $card('Ограничения', static function (): void { ?>
    <ul class="md-xiaomihome-list">
        <li>Поддерживаются устройства, которые шлюз отдаёт по локальному протоколу. Камеры, BLE-устройства и облачные сценарии — вне модуля.</li>
        <li>События приходят от шлюза; модуль не опрашивает каждое устройство по расписанию. Команда «Опросить» ставит чтение в очередь.</li>
        <li>UDP 9898 может слушать только один процесс. Если порт занят, цикл напишет ошибку в X-Ray.</li>
        <li>На сервере с несколькими сетевыми интерфейсами multicast может приходить не с той сети. Укажите IP интерфейса в настройках модуля.</li>
        <li>Модуль не заменяет приложение: привязка устройств к шлюзу выполняется в Mi Home или Aqara Home.</li>
    </ul>
    <p class="md-field__hint">
        Документация протокола:
        <a href="https://github.com/louisZL/lumi-gateway-local-api" target="_blank" rel="noopener">lumi-gateway-local-api</a>,
        <a href="https://github.com/aqara/opencloud-docs/blob/master/en/development/gateway-LAN-communication.md" target="_blank" rel="noopener">Aqara LAN communication</a>.
        Исходный модуль: <a href="https://github.com/sergejey/majordomo-xiaomihome" target="_blank" rel="noopener">sergejey/majordomo-xiaomihome</a>.
    </p>
<?php }, array('icon' => 'alert-triangle')); ?>
