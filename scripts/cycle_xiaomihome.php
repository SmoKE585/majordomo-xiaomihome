<?php
/**
 * Цикл модуля Xiaomi Home.
 *
 * Скрипт поднимает рантайм и отдаёт работу модулю: приём multicast-сообщений
 * шлюза, очередь команд и переподключение живут в modules/xiaomihome.
 *
 * 2026-10-25 — сообщения обрабатываются в этом же процессе, без HTTP-запроса
 * к ajax-эндпоинту; сокет закрывается при остановке и переподключается сам.
 */

chdir(dirname(__DIR__));

require_once './config.php';
require_once './lib/loader.php';
require_once './load_settings.php';
require_once DIR_MODULES . 'xiaomihome/xiaomihome.class.php';

set_time_limit(0);

$cycle_name = str_replace('.php', '', basename(__FILE__));
$module = new xiaomihome();

echo date('H:i:s') . ' running ' . basename(__FILE__) . PHP_EOL;

$last_beat = 0;
$last_error = '';

while (true) {
    if (time() - $last_beat > 5) {
        $last_beat = time();
        setGlobal($cycle_name . 'Run', time(), 1);
    }

    try {
        $module->cycleTick();
        $last_error = '';
    } catch (Throwable $failure) {
        $message = get_class($failure) . ': ' . $failure->getMessage();
        if ($message !== $last_error) {
            $last_error = $message;
            DebMes('xiaomihome cycle: ' . $message, 'xiaomihome', 'error');
        }
    }

    if (isRebootRequired() || isset($_GET['onetime'])) {
        $module->shutdownCycle();
        echo date('H:i:s') . ' stopping ' . basename(__FILE__)
            . (isset($_GET['onetime']) ? ' (onetime)' : ' (reboot)') . PHP_EOL;
        break;
    }

    usleep(100000);
}
