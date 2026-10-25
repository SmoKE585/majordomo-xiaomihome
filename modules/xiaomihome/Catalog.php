<?php
/**
 * Каталог моделей шлюза Xiaomi/Aqara и подписи свойств.
 *
 * Модуль получает от шлюза технические `TYPE` (`sensor_motion.aq2`) и ключи
 * метрик (`no_motion`, `pressure_kpa`). Здесь собраны человеческие названия,
 * типы батареек, время жизни без сообщений и единицы измерения. Это только
 * представление: протокол и привязки работают с исходными ключами.
 *
 * 2026-10-25 — каталог добавлен при переводе модуля на новый интерфейс.
 */
final class XiaomiHomeCatalog
{
    public const TITLES = array(
        'gateway' => 'Шлюз Xiaomi (Mi Home)',
        'acpartner.v3' => 'Aqara AC Companion (шлюз)',
        'plug' => 'Умная розетка Mi',
        '86plug' => 'Розетка Mi (86 мм)',
        'ctrl_86plug' => 'Розетка Aqara (86 мм)',
        'ctrl_86plug.aq1' => 'Розетка Aqara (86 мм, aq1)',
        'ctrl_ln1' => 'Aqara Relay 1 канал',
        'ctrl_ln1.aq1' => 'Aqara Relay 1 канал (aq1)',
        'ctrl_ln2' => 'Aqara Relay 2 канала',
        'ctrl_ln2.aq1' => 'Aqara Relay 2 канала (aq1)',
        'ctrl_neutral1' => 'Aqara выключатель 1 клавиша',
        'ctrl_neutral1.aq1' => 'Aqara выключатель 1 клавиша (aq1)',
        'ctrl_neutral2' => 'Aqara выключатель 2 клавиши',
        'ctrl_neutral2.aq1' => 'Aqara выключатель 2 клавиши (aq1)',
        'curtain' => 'Aqara Curtain Controller',
        'switch' => 'Кнопка Mi Wireless',
        '86sw1' => 'Настенная кнопка Mi 1 клавиша',
        '86sw2' => 'Настенная кнопка Mi 2 клавиши',
        'sensor_switch' => 'Кнопка Aqara Wireless',
        'sensor_switch.aq2' => 'Кнопка Aqara Wireless (aq2)',
        'sensor_switch.aq3' => 'Кнопка Aqara Wireless (aq3)',
        'sensor_86sw1' => 'Кнопка Aqara 1 клавиша',
        'sensor_86sw2' => 'Кнопка Aqara 2 клавиши',
        'remote.b1acn01' => 'Пульт Aqara 1 кнопка',
        'remote.b186acn01' => 'Пульт Aqara 2 кнопки',
        'remote.b286acn01' => 'Пульт Aqara 2 кнопки (b286)',
        'motion' => 'Датчик движения Mi',
        'sensor_motion' => 'Датчик движения Aqara',
        'sensor_motion.aq2' => 'Датчик движения Aqara (aq2)',
        'magnet' => 'Датчик открытия Mi',
        'sensor_magnet' => 'Датчик открытия Aqara',
        'sensor_magnet.aq2' => 'Датчик открытия Aqara (aq2)',
        'sensor_ht' => 'Датчик температуры и влажности',
        'weather' => 'Погодный датчик Aqara',
        'weather.v1' => 'Погодный датчик Aqara (v1)',
        'sensor_wleak.aq1' => 'Датчик протечки Aqara',
        'smoke' => 'Датчик дыма Mi',
        'sensor_smoke' => 'Датчик дыма Aqara',
        'natgas' => 'Датчик газа Mi',
        'sensor_natgas' => 'Датчик газа Aqara',
        'lock.aq1' => 'Замок Aqara',
        'cube' => 'Куб Mi',
        'sensor_cube' => 'Куб Aqara',
        'sensor_cube.aqgl01' => 'Куб Aqara (aqgl01)',
        'vibration' => 'Датчик вибрации Mi',
        'vibration.aq1' => 'Датчик вибрации Aqara',
    );

    /** Тип батарейки для карточки устройства. */
    private const BATTERIES = array(
        'switch' => 'CR2032', 'sensor_switch' => 'CR2032', 'sensor_switch.aq2' => 'CR2032',
        'sensor_switch.aq3' => 'CR2032', '86sw1' => 'CR2032', '86sw2' => 'CR2032',
        'sensor_86sw1' => 'CR2032', 'sensor_86sw2' => 'CR2032',
        'remote.b1acn01' => 'CR2032', 'remote.b186acn01' => 'CR2032', 'remote.b286acn01' => 'CR2032',
        'sensor_ht' => 'CR2032', 'weather' => 'CR2032', 'weather.v1' => 'CR2032',
        'vibration' => 'CR2032', 'vibration.aq1' => 'CR2032', 'sensor_wleak.aq1' => 'CR2032',
        'motion' => 'CR2450', 'sensor_motion' => 'CR2450', 'sensor_motion.aq2' => 'CR2450',
        'cube' => 'CR2450', 'sensor_cube.aqgl01' => 'CR2450',
        'magnet' => 'CR1632', 'sensor_magnet' => 'CR1632', 'sensor_magnet.aq2' => 'CR1632',
        'smoke' => 'CR123A', 'sensor_smoke' => 'CR123A',
        'lock.aq1' => '8 × AA',
    );

    private const LABELS = array(
        'command' => 'Команда',
        'ip' => 'IP шлюза',
        'channel' => 'Канал',
        'channel_0' => 'Канал 1',
        'channel_1' => 'Канал 2',
        'temperature' => 'Температура',
        'humidity' => 'Влажность',
        'pressure_kpa' => 'Давление',
        'pressure_mm' => 'Давление',
        'battery_level' => 'Заряд батареи',
        'voltage' => 'Напряжение батареи',
        'status' => 'Открыто / закрыто',
        'motion' => 'Движение',
        'no_motion' => 'Нет движения',
        'lux' => 'Освещённость',
        'illumination' => 'Освещённость',
        'load_power' => 'Мощность',
        'load_voltage' => 'Напряжение',
        'energy_consumed' => 'Расход энергии',
        'leak' => 'Протечка',
        'alarm' => 'Тревога',
        'density' => 'Концентрация газа',
        'no_close' => 'Долго открыто',
        'rgb' => 'Цвет',
        'brightness' => 'Яркость',
        'curtain_level' => 'Положение шторы',
        'curtain_status' => 'Управление шторой',
        'ringtone' => 'Мелодия',
        'error' => 'Ошибка',
        'iam' => 'Регистрация',
        'click' => 'Нажатие',
        'double_click' => 'Двойное нажатие',
        'long_click' => 'Долгое нажатие',
        'long_click_press' => 'Долгое нажатие (нажато)',
        'long_click_release' => 'Долгое нажатие (отпущено)',
        'left_click' => 'Левая клавиша',
        'right_click' => 'Правая клавиша',
        'both_click' => 'Обе клавиши',
        'left_double_click' => 'Левая: двойное',
        'right_double_click' => 'Правая: двойное',
        'both_double_click' => 'Обе: двойное',
        'left_long_click' => 'Левая: долгое',
        'right_long_click' => 'Правая: долгое',
        'both_long_click' => 'Обе: долгое',
        'long_both_click' => 'Обе: долгое',
        'double_both_click' => 'Обе: двойное',
        'flip90' => 'Переворот 90°',
        'flip180' => 'Переворот 180°',
        'move' => 'Перемещение',
        'tap_twice' => 'Двойной стук',
        'shake_air' => 'Встряхивание',
        'swing' => 'Качание',
        'alert' => 'Сигнал',
        'free_fall' => 'Падение',
        'rotate' => 'Поворот',
        'rotate_time' => 'Длительность поворота',
        'coordination' => 'Координаты',
        'bed_activity' => 'Активность на кровати',
        'final_tilt_angle' => 'Угол наклона',
        'report' => 'Отчёт шлюза',
        'heartbeat' => 'Heartbeat шлюза',
        'write_ack' => 'Подтверждение записи',
        'read_ack' => 'Ответ на чтение',
        'write_rsp' => 'Ответ записи',
        'read_rsp' => 'Ответ чтения',
        'server_ack' => 'Ответ сервера',
        'server_rsp' => 'Ответ сервера (rsp)',
        'discovery_rsp' => 'Ответ обнаружения',
    );

    private const UNITS = array(
        'temperature' => '°C',
        'humidity' => ' %',
        'pressure_kpa' => ' кПа',
        'pressure_mm' => ' мм рт. ст.',
        'battery_level' => ' %',
        'voltage' => ' В',
        'lux' => ' лк',
        'illumination' => ' лк',
        'load_power' => ' Вт',
        'load_voltage' => ' В',
        'energy_consumed' => ' кВт·ч',
        'density' => ' ppm',
        'curtain_level' => ' %',
        'rotate' => '°',
        'no_motion' => ' мин',
    );

    private const GATEWAYS = array('gateway', 'acpartner.v3');
    private const POWERED = array('plug', '86plug', 'ctrl_86plug', 'ctrl_86plug.aq1',
        'ctrl_ln1', 'ctrl_ln1.aq1', 'ctrl_ln2', 'ctrl_ln2.aq1',
        'ctrl_neutral1', 'ctrl_neutral1.aq1', 'ctrl_neutral2', 'ctrl_neutral2.aq1', 'curtain');
    private const FAST_BATTERY = array('motion', 'sensor_motion', 'sensor_motion.aq2', 'cube', 'sensor_cube', 'sensor_cube.aqgl01');

    public static function models(): array
    {
        return self::TITLES;
    }

    public static function title(string $type): string
    {
        $type = trim($type);
        if ($type === '') return 'Тип не определён';
        return self::TITLES[$type] ?? $type;
    }

    public static function isKnown(string $type): bool
    {
        return isset(self::TITLES[trim($type)]);
    }

    public static function imageUrl(string $type): ?string
    {
        $type = trim($type);
        if ($type === '' || !preg_match('/^[a-z0-9_.-]+$/i', $type)) return null;
        $file = ROOT . 'public/assets/modules/xiaomihome/img/' . $type . '.png';
        if (!is_file($file)) return null;
        return ROOTHTML . 'assets/modules/xiaomihome/img/' . rawurlencode($type) . '.png';
    }

    public static function battery(string $type): string
    {
        return self::BATTERIES[trim($type)] ?? '';
    }

    public static function isGateway(string $type): bool
    {
        return in_array(trim($type), self::GATEWAYS, true);
    }

    public static function isPowered(string $type): bool
    {
        return in_array(trim($type), self::POWERED, true);
    }

    /**
     * Сколько минут без сообщений считать устройство доступным.
     * Значения близки к XiaomiGateway3: шлюз — минуты, батарейные датчики — часы,
     * motion и cube шлют события чаще и считаются потерянными быстрее.
     */
    public static function aliveMinutes(string $type): int
    {
        $type = trim($type);
        if (self::isGateway($type)) return 15;
        if (in_array($type, self::FAST_BATTERY, true)) return 65;
        if (self::isPowered($type)) return 60;
        if ($type === '') return 60;
        return 170;
    }

    public static function label(string $key): string
    {
        if (isset(self::LABELS[$key])) return self::LABELS[$key];
        $readable = str_replace(array('_', '-'), ' ', $key);
        return mb_convert_case(trim($readable), MB_CASE_TITLE, 'UTF-8');
    }

    public static function unit(string $key): string
    {
        return self::UNITS[$key] ?? '';
    }

    /** Понятное значение для таблиц и карточек. */
    public static function display(string $key, $value): string
    {
        if ($value === null || $value === '') return '—';
        $text = trim((string)$value);

        $binary = array(
            'channel' => array('1' => 'Вкл', '0' => 'Выкл'),
            'channel_0' => array('1' => 'Вкл', '0' => 'Выкл'),
            'channel_1' => array('1' => 'Вкл', '0' => 'Выкл'),
            'status' => array('1' => 'Закрыто', '0' => 'Открыто'),
            'motion' => array('1' => 'Движение', '0' => 'Нет'),
            'leak' => array('1' => 'Протечка', '0' => 'Норма'),
            'alarm' => array('1' => 'Тревога', '0' => 'Норма'),
            'no_close' => array('1' => 'Да', '0' => 'Нет'),
            'error' => array('0' => 'Нет'),
            'curtain_status' => array('open' => 'Открыть', 'close' => 'Закрыть', 'stop' => 'Стоп', 'auto' => 'Авто'),
        );
        if (isset($binary[$key][$text])) return $binary[$key][$text];

        if (is_numeric($text)) {
            $unit = self::unit($key);
            $number = $text;
            if ($key === 'battery_level' || $key === 'voltage') $number = rtrim(rtrim(number_format((float)$text, 2, '.', ''), '0'), '.');
            return $number . $unit;
        }
        return $text;
    }

    /** Короткая строка для списка: несколько значимых метрик. */
    public static function preview(array $commands, int $limit = 4): string
    {
        $skip = array('report', 'heartbeat', 'write_ack', 'read_ack', 'write_rsp', 'read_rsp',
            'server_ack', 'server_rsp', 'discovery_rsp', 'iam', 'ip', 'command');
        $parts = array();
        foreach ($commands as $command) {
            $key = (string)($command['TITLE'] ?? '');
            if ($key === '' || in_array($key, $skip, true)) continue;
            $parts[] = self::label($key) . ': ' . self::display($key, $command['VALUE'] ?? '');
            if (count($parts) >= $limit) break;
        }
        return implode(' · ', $parts);
    }
}
