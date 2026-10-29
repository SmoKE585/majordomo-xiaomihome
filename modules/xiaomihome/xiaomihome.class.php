<?php

use MajorDoMo\Core\Core;

require_once __DIR__ . '/Constants.php';
require_once __DIR__ . '/Catalog.php';
require_once __DIR__ . '/Repository.php';
require_once __DIR__ . '/Engine.php';

/**
 * Xiaomi Home — Zigbee-устройства Xiaomi/Mijia/Aqara через локальный протокол шлюза.
 *
 * Шлюз рассылает сообщения в multicast-группу 224.0.0.50:9898, модуль слушает
 * их в цикле и раскладывает значения по таблице `xicommands`. Команды устройствам
 * подписываются ключом шлюза (`GATE_KEY`) и отправляются на его IP.
 *
 * 2026-10-25 — новый интерфейс на MDJUI, чистые URL, настройки с проверкой,
 * цикл без HTTP-перезапуска, типовая доступность устройств, живые обновления
 * списка и документация. Таблицы и привязки прежней версии сохраняются.
 * 2026-10-29 — неудачная отправка команды больше не удаляет строку очереди:
 * две повторные попытки, затем удаление с записью в журнал ошибок.
 */
class xiaomihome extends module
{
    use XiaomiHomeEngine;

    private $repo = null;
    private $settingsCache = null;
    private $roundModeCache = null;
    private $socket = null;
    private $lastData = 0;
    /** Неудачные попытки отправки по ID строки xiqueue (живут в памяти цикла). */
    private $queueAttempts = array();

    public function __construct()
    {
        $this->name = 'xiaomihome';
        $this->title = 'Xiaomi Home';
        $this->module_category = '<#LANG_SECTION_DEVICES#>';
        $this->checkInstalled();
    }

    // ------------------------------------------------------------------
    // Настройки и параметры
    // ------------------------------------------------------------------

    public function getConfig()
    {
        parent::getConfig();
        if (!is_array($this->config)) $this->config = array();
        $this->config += array(
            'API_BIND' => '',
            'API_ROUND' => 2,
            'API_LOG_DEBMES' => 0,
            'API_LOG_CYCLE' => 0,
            'API_LOG_GW_HEARTBEAT' => 0,
            'ALIVE_MINUTES' => 0,
        );
        return $this->config;
    }

    public function getParams()
    {
        global $id, $view_mode, $tab, $mode, $data_source;
        $this->id = isset($id) && is_scalar($id) ? (int)$id : 0;
        $this->view_mode = isset($view_mode) && is_scalar($view_mode) ? (string)$view_mode : '';
        $this->tab = isset($tab) && is_scalar($tab) ? (string)$tab : '';
        $this->mode = isset($mode) && is_scalar($mode) ? (string)$mode : '';
        $this->data_source = isset($data_source) && is_scalar($data_source) ? (string)$data_source : '';
        if ($this->view_mode === 'update_settings') $this->view_mode = 'settings';
    }

    public function saveParams($data = 0)
    {
        return parent::saveParams(array(
            'id' => (int)$this->id,
            'view_mode' => (string)$this->view_mode,
            'tab' => (string)$this->tab,
        ));
    }

    public function settings(): array
    {
        if (is_array($this->settingsCache)) return $this->settingsCache;
        $this->getConfig();
        $round = (int)($this->config['API_ROUND'] ?? 2);
        if (!in_array($round, array(0, 1, 2), true)) $round = 2;
        $alive = (int)($this->config['ALIVE_MINUTES'] ?? 0);
        return $this->settingsCache = array(
            'bind_ip' => trim((string)($this->config['API_BIND'] ?? '')),
            'round' => $round,
            'alive_minutes' => $alive > 0 ? max(5, min(1440, $alive)) : 0,
            'log_debmes' => !empty($this->config['API_LOG_DEBMES']),
            'log_cycle' => !empty($this->config['API_LOG_CYCLE']),
            'log_heartbeat' => !empty($this->config['API_LOG_GW_HEARTBEAT']),
        );
    }

    /** Режим округления для движка (0/1/2 знака после запятой). */
    public function roundMode(): int
    {
        if ($this->roundModeCache === null) $this->roundModeCache = $this->settings()['round'];
        return $this->roundModeCache;
    }

    /** Сколько минут без сообщений устройство считается доступным. */
    public function aliveMinutes(string $type): int
    {
        $override = $this->settings()['alive_minutes'];
        return $override > 0 ? $override : XiaomiHomeCatalog::aliveMinutes($type);
    }

    public function repo(): XiaomiHomeRepository
    {
        if (!$this->repo) $this->repo = new XiaomiHomeRepository();
        return $this->repo;
    }

    // ------------------------------------------------------------------
    // Маршрутизация
    // ------------------------------------------------------------------

    public function run()
    {
        $this->getParams();
        require_once ROOT . 'lib/ui/MDJUI.php';

        if (gr('ajax')) {
            $this->ajaxAnswer();
        }

        $session = Core::session();
        $csrf = $session->csrfToken();
        $error = '';
        $flash = $session->pull('xiaomihome_flash');

        // Старое удаление через GET больше не выполняется: открываем карточку,
        // где удаление подтверждается отдельной формой.
        if ($this->view_mode === 'delete_xidevices' && $this->id > 0) {
            $this->redirect($this->deviceUrl($this->id));
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            try {
                if (!$session->checkCsrf($_POST['_md_csrf'] ?? $_POST['_token'] ?? null)) {
                    throw new InvalidArgumentException('Форма устарела: страница была открыта слишком давно. Обновите её.');
                }
                $outcome = $this->handlePost();
                $this->flash($outcome['type'] ?? 'success', $outcome['title'] ?? 'Готово', $outcome['text'] ?? '');
                $this->redirect($outcome['url'] ?? $this->listUrl());
            } catch (Throwable $failure) {
                $error = $failure->getMessage();
            }
        }

        $id = (int)$this->id;
        $view = $this->resolveView();
        $tab = (string)$this->tab;
        $bindingCommand = null;
        $linkedControl = '';
        $binding = array();

        if ($view === 'edit_xidevices') {
            if ($id > 0 && preg_match('/^bind-(\d+)$/', $tab, $match)) {
                $bindingCommand = $this->repo()->command((int)$match[1]);
                if (!$bindingCommand || (int)$bindingCommand['DEVICE_ID'] !== $id) {
                    $bindingCommand = null;
                    $tab = '';
                    $error = $error !== '' ? $error : 'Свойство не найдено у этого устройства.';
                } else {
                    $tab = 'bind';
                }
            }
            if (!in_array($tab, array('', 'data', 'bind', 'help'), true)) $tab = '';
        }

        $device = null;
        $isNew = false;
        $commands = array();
        $technical = array();
        $online = array('state' => 'unknown', 'age' => null, 'alive' => 0);
        $queue = array();

        if ($view === 'edit_xidevices') {
            $device = $id > 0 ? $this->repo()->device($id) : null;
            if (!$device) {
                http_response_code(404);
                $error = $error !== '' ? $error : 'Устройство не найдено. Возможно, оно было удалено.';
                $view = 'search_xidevices';
            } else {
                $online = $this->deviceStatus($device);
                $commands = $this->repo()->commands($id);
                $technical = array_values(array_filter($commands, static function ($command) {
                    return in_array($command['TITLE'], array('report', 'heartbeat', 'write_ack', 'read_ack',
                        'write_rsp', 'read_rsp', 'server_ack', 'server_rsp', 'discovery_rsp', 'iam', 'ip'), true);
                }));
                $queue = $this->repo()->queueForIp((string)$device['GATE_IP'], 8);
                if ($tab === 'bind' && $bindingCommand) {
                    $binding = $bindingCommand;
                    $linkedControl = $this->renderLinkedObject($binding);
                }
            }
        }

        // При ошибке формы показываем введённое, а не только значения из базы.
        if ($error !== '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $view === 'edit_xidevices' && $device) {
            foreach (array('TITLE' => 'title', 'GATE_KEY' => 'gate_key') as $column => $field) {
                if (isset($_POST[$field]) && is_scalar($_POST[$field]) && trim((string)$_POST[$field]) !== '') {
                    $device[$column] = trim((string)$_POST[$field]);
                }
            }
        }

        $settings = $this->settings();
        $stats = $this->stats();
        $devices = array();
        $types = array();
        $search = '';
        $typeFilter = '';
        $sort = 'updated';
        $page = 1;
        $pages = 1;
        $count = 0;

        if ($view === 'search_xidevices') {
            $search = mb_substr($this->request('q'), 0, 100);
            $typeFilter = trim($this->request('type'));
            if ($typeFilter !== '' && !preg_match('/^[a-z0-9_.-]{1,100}$/i', $typeFilter)) $typeFilter = '';
            $sort = in_array($this->request('sort'), array('updated', 'title', 'type', 'new'), true) ? $this->request('sort') : 'updated';
            $page = max(1, (int)$this->request('page'));
            $perPage = 40;
            $count = $this->repo()->countDevices($search, $typeFilter);
            $pages = max(1, (int)ceil($count / $perPage));
            $page = min($page, $pages);
            $devices = $this->repo()->devices($search, $typeFilter, $sort, $perPage, ($page - 1) * $perPage);
            foreach ($devices as &$row) {
                $row['STATUS'] = $this->deviceStatus($row);
            }
            unset($row);
            $types = $this->repo()->types();
        }

        $vars = array(
            'view' => $view,
            'tab' => $tab,
            'device' => $device,
            'isNew' => $isNew,
            'devices' => $devices,
            'types' => $types,
            'search' => $search,
            'typeFilter' => $typeFilter,
            'sort' => $sort,
            'page' => $page,
            'pages' => $pages,
            'count' => $count,
            'commands' => $commands,
            'technical' => $technical,
            'online' => $online,
            'binding' => $binding,
            'bindingCommand' => $bindingCommand,
            'linkedControl' => $linkedControl,
            'queue' => $queue,
            'settings' => $settings,
            'stats' => $stats,
            'error' => $error,
            'flash' => $flash,
            'csrf' => $csrf,
        );

        try {
            $this->result = $this->renderTemplate('xiaomihome.php', $vars);
        } catch (Throwable $failure) {
            DebMes('xiaomihome: page render failed: ' . $failure->getMessage(), 'xiaomihome', 'error');
            $this->result = MDJUI::alert('danger', 'Ошибка страницы модуля', $failure->getMessage());
        }
    }

    private function resolveView(): string
    {
        $view = (string)$this->view_mode;
        $tab = (string)$this->tab;
        if ($view === '') {
            if ($this->id > 0) return 'edit_xidevices';
            if (in_array($tab, array('settings', 'help'), true)) return $tab;
            return 'search_xidevices';
        }
        $map = array(
            'search_xidevices' => 'search_xidevices',
            'edit_xidevices' => 'edit_xidevices',
            'settings' => 'settings',
            'help' => 'help',
            'delete_xidevices' => 'edit_xidevices',
        );
        return $map[$view] ?? 'search_xidevices';
    }

    public function listUrl(): string
    {
        return ROOTHTML . 'panel/xiaomihome.html';
    }

    public function pageUrl(string $page): string
    {
        return ROOTHTML . 'panel/xiaomihome/0/' . $page . '.html';
    }

    public function deviceUrl(int $id, string $tab = ''): string
    {
        $base = ROOTHTML . 'panel/xiaomihome/' . max(0, $id) . '.html';
        if ($tab !== '') $base = ROOTHTML . 'panel/xiaomihome/' . max(0, $id) . '/' . rawurlencode($tab) . '.html';
        return $base;
    }

    private function renderTemplate(string $template, array $vars): string
    {
        $file = ROOT . 'templates/' . $this->name . '/' . $template;
        if (!is_file($file)) return MDJUI::alert('danger', 'Шаблон не найден', 'Ожидался файл ' . $file . '.');
        extract($vars, EXTR_SKIP);
        $level = ob_get_level();
        ob_start();
        try {
            require $file;
            return (string)ob_get_clean();
        } catch (Throwable $failure) {
            // Ошибка в шаблоне не должна оставлять «сырой» HTML без оболочки панели.
            while (ob_get_level() > $level) ob_end_clean();
            throw $failure;
        }
    }

    private function renderLinkedObject(array $binding): string
    {
        if (!class_exists('linkedobject', false)) {
            require_once DIR_MODULES . 'linkedobject/linkedobject.class.php';
        }
        $this->data = array(
            'LINKED_OBJECT' => (string)($binding['LINKED_OBJECT'] ?? ''),
            'LINKED_PROPERTY' => (string)($binding['LINKED_PROPERTY'] ?? ''),
            'LINKED_METHOD' => (string)($binding['LINKED_METHOD'] ?? ''),
        );
        $linked = new linkedobject();
        $linked->owner = $this;
        $linked->object_field = 'linked_object';
        $linked->property_field = 'linked_property';
        $linked->method_field = 'linked_method';
        $linked->width = '100%';
        $linked->run();
        return (string)$linked->result;
    }

    // ------------------------------------------------------------------
    // POST-действия
    // ------------------------------------------------------------------

    private function handlePost(): array
    {
        switch ($this->posted('operation')) {
            case 'save_device': return $this->saveDevice();
            case 'delete_device': return $this->deleteDevice();
            case 'refresh_device': return $this->refreshDevice();
            case 'clear_queue': return $this->clearQueue();
            case 'save_settings': return $this->saveSettings();
            case 'save_binding': return $this->saveBinding();
            case 'delete_binding': return $this->deleteBinding();
            case 'delete_command': return $this->deleteCommandRow();
        }
        throw new InvalidArgumentException('Неизвестное действие формы.');
    }

    private function saveDevice(): array
    {
        $id = (int)$this->posted('device_id');
        $device = $this->repo()->device($id);
        if (!$device) throw new InvalidArgumentException('Устройство не найдено.');
        $title = trim($this->posted('title'));
        if ($title === '') throw new InvalidArgumentException('Укажите название устройства.');
        if (mb_strlen($title) > 100) throw new InvalidArgumentException('Название длиннее 100 символов.');

        $record = array('ID' => $id, 'TITLE' => $title);
        if (XiaomiHomeCatalog::isGateway((string)$device['TYPE'])) {
            $key = trim($this->posted('gate_key'));
            if ($key !== '') {
                if (strlen($key) !== 16) {
                    throw new InvalidArgumentException('Ключ шлюза — ровно 16 символов. Скопируйте его из приложения без пробелов.');
                }
                $record['GATE_KEY'] = $key;
            }
        }
        $this->repo()->saveDevice($record);
        return array(
            'title' => 'Устройство сохранено',
            'text' => XiaomiHomeCatalog::isGateway((string)$device['TYPE'])
                ? 'Ключ используется для подписи команд. Если он неверный, шлюз не выполнит запись.'
                : 'Название обновлено. Тип и SID приходят от шлюза и не редактируются.',
            'url' => $this->deviceUrl($id),
        );
    }

    private function deleteDevice(): array
    {
        $id = (int)$this->posted('device_id');
        $device = $this->repo()->device($id);
        if (!$device) throw new InvalidArgumentException('Устройство уже удалено.');
        $commands = $this->repo()->commands($id);
        foreach ($commands as $command) {
            if ((string)$command['LINKED_OBJECT'] !== '' && (string)$command['LINKED_PROPERTY'] !== '') {
                removeLinkedProperty((string)$command['LINKED_OBJECT'], (string)$command['LINKED_PROPERTY'], $this->name);
            }
        }
        $this->repo()->deleteDevice($id);
        return array(
            'title' => 'Устройство удалено',
            'text' => '«' . (string)$device['TITLE'] . '» и его привязки удалены. Объекты и сценарии MajorDoMo не изменялись.',
            'url' => $this->listUrl(),
        );
    }

    private function refreshDevice(): array
    {
        $id = (int)$this->posted('device_id');
        $device = $this->repo()->device($id);
        if (!$device) throw new InvalidArgumentException('Устройство не найдено.');
        $ip = (string)$device['GATE_IP'];
        if ($ip === '') throw new InvalidArgumentException('Шлюз ещё не сообщил свой IP. Дождитесь heartbeat или проверьте ключ и сеть.');
        $data = array('cmd' => 'read', 'sid' => (string)$device['SID']);
        $this->repo()->addQueue($ip, json_encode($data, JSON_UNESCAPED_UNICODE));
        return array(
            'title' => 'Запрос отправлен',
            'text' => 'Шлюз получит команду чтения и пришлёт текущие значения. Ответ появится в свойствах.',
            'url' => $this->returnUrl($this->deviceUrl($id)),
        );
    }

    /** Возврат на страницу, с которой нажали «Опросить», если это наш модуль. */
    private function returnUrl(string $fallback): string
    {
        $value = $this->posted('return_url');
        if ($value === '' || preg_match('/[\r\n]/', $value)) return $fallback;
        return str_starts_with($value, ROOTHTML . 'panel/xiaomihome') ? $value : $fallback;
    }
    private function clearQueue(): array
    {
        $ip = trim($this->posted('gate_ip'));
        $this->repo()->clearQueue($ip);
        return array(
            'title' => 'Очередь очищена',
            'text' => $ip !== '' ? 'Команды для шлюза ' . $ip . ' удалены.' : 'Все команды удалены из очереди.',
            'url' => $this->listUrl(),
        );
    }

    private function saveSettings(): array
    {
        $bind = trim($this->posted('bind_ip'));
        if ($bind !== '' && filter_var($bind, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new InvalidArgumentException('IP привязки должен быть IPv4-адресом или пустым (все интерфейсы).');
        }
        $round = (int)$this->posted('round');
        if (!in_array($round, array(0, 1, 2), true)) throw new InvalidArgumentException('Точность округления — 0, 1 или 2 знака.');
        $alive = (int)$this->posted('alive_minutes');
        if ($alive !== 0 && ($alive < 5 || $alive > 1440)) {
            throw new InvalidArgumentException('Порог доступности — 0 (авто) или от 5 до 1440 минут.');
        }
        $this->config = array(
            'API_BIND' => $bind,
            'API_ROUND' => $round,
            'API_LOG_DEBMES' => $this->posted('log_debmes') === '1' ? 1 : 0,
            'API_LOG_CYCLE' => $this->posted('log_cycle') === '1' ? 1 : 0,
            'API_LOG_GW_HEARTBEAT' => $this->posted('log_heartbeat') === '1' ? 1 : 0,
            'ALIVE_MINUTES' => $alive,
        );
        $this->saveConfig();
        $this->settingsCache = null;
        $this->roundModeCache = null;
        setGlobal('cycle_xiaomihomeControl', 'restart');
        return array(
            'title' => 'Настройки сохранены',
            'text' => 'Цикл перезапущен: новый bind IP, округление и порог доступности применятся сразу.',
            'url' => $this->pageUrl('settings'),
        );
    }

    private function saveBinding(): array
    {
        $commandId = (int)$this->posted('command_id');
        $command = $this->repo()->command($commandId);
        if (!$command) throw new InvalidArgumentException('Свойство не найдено.');
        $objectName = trim($this->posted('linked_object'));
        $propertyName = trim($this->posted('linked_property'));
        $methodName = trim($this->posted('linked_method'));
        foreach (array($objectName, $propertyName, $methodName) as $name) {
            if (mb_strlen($name) > 100) throw new InvalidArgumentException('Имя объекта, свойства или метода длиннее 100 символов.');
        }
        if (($propertyName !== '' || $methodName !== '') && $objectName === '') {
            throw new InvalidArgumentException('Сначала выберите объект MajorDoMo.');
        }
        if ($objectName !== '') {
            $object = getObject($objectName);
            if (!$object) throw new InvalidArgumentException('Объект «' . $objectName . '» не найден.');
            if ($propertyName !== '' && !$object->getPropertyByName($propertyName, $object->class_id, $object->id)) {
                throw new InvalidArgumentException('У объекта «' . $objectName . '» нет свойства «' . $propertyName . '».');
            }
            if ($methodName !== '' && !$object->getMethodByName($methodName, $object->class_id, $object->id)) {
                throw new InvalidArgumentException('У объекта «' . $objectName . '» нет метода «' . $methodName . '».');
            }
        }
        if ($objectName !== '' && $propertyName === '' && $methodName === '') $objectName = '';

        $previousObject = (string)$command['LINKED_OBJECT'];
        $previousProperty = (string)$command['LINKED_PROPERTY'];
        $this->repo()->saveCommand(array(
            'ID' => $commandId,
            'LINKED_OBJECT' => $objectName,
            'LINKED_PROPERTY' => $propertyName,
            'LINKED_METHOD' => $methodName,
        ));
        if ($objectName !== '' && $propertyName !== '' && !addLinkedProperty($objectName, $propertyName, $this->name)) {
            throw new RuntimeException('Не удалось зарегистрировать привязку свойства.');
        }
        if ($previousObject !== '' && $previousProperty !== ''
            && ($previousObject !== $objectName || $previousProperty !== $propertyName)) {
            removeLinkedPropertyIfNotUsed('xicommands', $previousObject, $previousProperty, $this->name);
        }
        return array(
            'title' => 'Привязка сохранена',
            'text' => $objectName === ''
                ? 'Привязка снята: значение больше не передаётся в объект.'
                : '«' . XiaomiHomeCatalog::label((string)$command['TITLE']) . '» будет записываться в ' . $objectName . '.' . $propertyName . '.',
            'url' => $this->deviceUrl((int)$command['DEVICE_ID'], 'bind-' . $commandId),
        );
    }

    private function deleteBinding(): array
    {
        $commandId = (int)$this->posted('command_id');
        $command = $this->repo()->command($commandId);
        if (!$command) throw new InvalidArgumentException('Свойство не найдено.');
        if ((string)$command['LINKED_OBJECT'] !== '' && (string)$command['LINKED_PROPERTY'] !== '') {
            removeLinkedProperty((string)$command['LINKED_OBJECT'], (string)$command['LINKED_PROPERTY'], $this->name);
        }
        $this->repo()->saveCommand(array(
            'ID' => $commandId,
            'LINKED_OBJECT' => '',
            'LINKED_PROPERTY' => '',
            'LINKED_METHOD' => '',
        ));
        return array(
            'title' => 'Привязка снята',
            'text' => 'Свойство осталось у устройства, но больше не связано с объектом.',
            'url' => $this->deviceUrl((int)$command['DEVICE_ID'], 'bind-' . $commandId),
        );
    }

    private function deleteCommandRow(): array
    {
        $commandId = (int)$this->posted('command_id');
        $command = $this->repo()->command($commandId);
        if (!$command) throw new InvalidArgumentException('Свойство не найдено.');
        if ((string)$command['LINKED_OBJECT'] !== '' && (string)$command['LINKED_PROPERTY'] !== '') {
            removeLinkedProperty((string)$command['LINKED_OBJECT'], (string)$command['LINKED_PROPERTY'], $this->name);
        }
        $this->repo()->deleteCommand($commandId);
        return array(
            'title' => 'Свойство удалено',
            'text' => 'Строка удалена. При следующем сообщении шлюза она может появиться снова.',
            'url' => $this->deviceUrl((int)$command['DEVICE_ID'], 'data'),
        );
    }

    // ------------------------------------------------------------------
    // Данные для шаблонов
    // ------------------------------------------------------------------

    private function deviceStatus(array $device): array
    {
        $type = (string)($device['TYPE'] ?? '');
        $alive = $this->aliveMinutes($type);
        $timestamp = !empty($device['UPDATED']) ? strtotime((string)$device['UPDATED']) : false;
        if (!$timestamp) return array('state' => 'unknown', 'age' => null, 'alive' => $alive);
        $age = max(0, time() - $timestamp);
        return array(
            'state' => $age <= $alive * 60 ? 'online' : 'offline',
            'age' => $age,
            'alive' => $alive,
        );
    }

    /** Тон бейджа батареи: danger до 20%, warning до 40%, иначе outline. */
    private function batteryTone(string $level): string
    {
        if ($level === '' || !is_numeric($level)) return 'outline';
        $value = (float)$level;
        return $value <= 20 ? 'danger' : ($value <= 40 ? 'warning' : 'outline');
    }
    private function stats(): array
    {
        $stats = $this->repo()->stats();
        $lastRun = 0;
        try {
            $lastRun = (int)gg('cycle_xiaomihomeRun');
        } catch (Throwable $failure) {
            $lastRun = 0;
        }
        $stats['cycle_age'] = $lastRun > 0 ? max(0, time() - $lastRun) : null;
        $stats['cycle_running'] = $lastRun > 0 && (time() - $lastRun) < 20;

        $online = 0;
        foreach ($this->repo()->select('SELECT TYPE, UPDATED FROM xidevices') as $device) {
            if ($this->deviceStatus($device)['state'] === 'online') $online++;
        }
        $stats['online'] = $online;
        $stats['offline'] = max(0, $stats['devices'] - $online);
        return $stats;
    }

    private function flash(string $type, string $title, string $text = ''): void
    {
        Core::session()->set('xiaomihome_flash', array('type' => $type, 'title' => $title, 'text' => $text));
    }

    private function request(string $name): string
    {
        $value = $_GET[$name] ?? '';
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function posted(string $name): string
    {
        if (!array_key_exists($name, $_POST)) return '';
        $value = $_POST[$name];
        if (!is_scalar($value)) throw new InvalidArgumentException('Некорректное поле формы: ' . $name);
        return trim((string)$value);
    }

    // ------------------------------------------------------------------
    // AJAX для живого списка
    // ------------------------------------------------------------------

    private function ajaxAnswer(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $op = (string)($_GET['op'] ?? $_POST['op'] ?? '');
            if ($op === 'refresh') {
                $id = (int)($_POST['device_id'] ?? $_GET['device_id'] ?? 0);
                $device = $this->repo()->device($id);
                if (!$device) throw new InvalidArgumentException('Устройство не найдено.');
                $ip = (string)$device['GATE_IP'];
                if ($ip === '') throw new InvalidArgumentException('Шлюз ещё не сообщил свой IP.');
                $this->repo()->addQueue($ip, json_encode(array('cmd' => 'read', 'sid' => (string)$device['SID']), JSON_UNESCAPED_UNICODE));
                echo json_encode(array('ok' => true, 'data' => array(
                    'message' => 'Запрос отправлен. Шлюз пришлёт текущие значения.',
                )), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            if ($op !== 'rows') throw new InvalidArgumentException('Неизвестная операция.');
            $items = array();
            foreach ($this->repo()->liveRows(200) as $device) {
                $status = $this->deviceStatus($device);
                $items[] = array(
                    'id' => (int)$device['ID'],
                    'updated' => (string)($device['UPDATED'] ?? ''),
                    'state' => $status['state'],
                    'age' => $status['age'],
                    'alive' => $status['alive'],
                    'battery' => (string)($device['BATTERY'] ?? ''),
                    'battery_tone' => $this->batteryTone((string)($device['BATTERY'] ?? '')),
                    'preview' => (string)($device['PREVIEW'] ?? ''),
                    'lost' => $status['state'] === 'offline',
                );
            }
            echo json_encode(array('ok' => true, 'now' => time(), 'items' => $items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $failure) {
            http_response_code(400);
            echo json_encode(array('ok' => false, 'error' => $failure->getMessage()), JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // ------------------------------------------------------------------
    // Цикл
    // ------------------------------------------------------------------

    public function cycleTick(): void
    {
        $this->getConfig();
        $this->ensureSocket();
        $this->sendDueQueue();
        $this->receiveMessages();
        if (time() - $this->lastData > 60) {
            $this->closeSocket();
            $this->ensureSocket();
        }
    }

    private function ensureSocket(): void
    {
        if ($this->socket !== null) return;
        if (!function_exists('socket_create')) throw new RuntimeException('Расширение PHP sockets не установлено.');
        $settings = $this->settings();
        $bindIp = $settings['bind_ip'] !== '' ? $settings['bind_ip'] : '0.0.0.0';
        $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket === false) {
            throw new RuntimeException('Не удалось создать UDP-сокет: ' . socket_strerror(socket_last_error()));
        }
        @socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        if (!@socket_bind($socket, $bindIp, XiaomiHomeConst::PORT)) {
            $error = socket_strerror(socket_last_error($socket));
            @socket_close($socket);
            throw new RuntimeException('Не удалось занять UDP ' . $bindIp . ':' . XiaomiHomeConst::PORT . ' — ' . $error);
        }
        @socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        @socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 0, 'usec' => 200000));
        @socket_set_option($socket, IPPROTO_IP, IP_MULTICAST_LOOP, 1);
        @socket_set_option($socket, IPPROTO_IP, IP_MULTICAST_TTL, 32);
        if (defined('MCAST_JOIN_GROUP')) {
            @socket_set_option($socket, IPPROTO_IP, MCAST_JOIN_GROUP, array(
                'group' => XiaomiHomeConst::ADDRESS,
                'interface' => 0,
                'source' => 0,
            ));
        }
        $this->socket = $socket;
        $this->lastData = time();
        $whois = '{"cmd":"whois"}';
        @socket_sendto($socket, $whois, strlen($whois), 0, XiaomiHomeConst::ADDRESS, XiaomiHomeConst::PEER_PORT);
        if ($settings['log_cycle']) {
            echo date('H:i:s') . ' socket ready on ' . $bindIp . ':' . XiaomiHomeConst::PORT . PHP_EOL;
        }
    }

    private function closeSocket(): void
    {
        if ($this->socket !== null) {
            @socket_close($this->socket);
            $this->socket = null;
        }
    }

    public function shutdownCycle(): void
    {
        $this->closeSocket();
    }

    private function sendDueQueue(): void
    {
        $settings = $this->settings();
        foreach ($this->repo()->dueQueue(50) as $row) {
            $id = (int)$row['ID'];
            $ip = trim((string)$row['IP']);
            if ($ip === '' || $this->socket === null) {
                $this->failQueueRow($id, 'no destination or socket');
                continue;
            }
            if ($settings['log_cycle']) echo date('H:i:s') . ' queue -> ' . $ip . ': ' . $row['DATA'] . PHP_EOL;
            if ($this->sendMessage((string)$row['DATA'], $ip, $this->socket)) {
                unset($this->queueAttempts[$id]);
                $this->repo()->deleteQueue($id);
                continue;
            }
            $this->failQueueRow($id, 'socket_sendto failed for ' . $ip);
        }
    }

    /**
     * Неудачная отправка: строку не удаляем сразу, чтобы короткий сбой сети
     * не терял команду. После трёх попыток удаляем и пишем ошибку — иначе
     * очередь росла бы бесконечно.
     */
    private function failQueueRow(int $id, string $reason): void
    {
        $attempts = (int)($this->queueAttempts[$id] ?? 0) + 1;
        $this->queueAttempts[$id] = $attempts;
        if ($attempts < 3) {
            DebMes('xiaomihome: queue #' . $id . ' not sent (' . $reason . '), retry ' . $attempts . '/3', 'xiaomi', 'warning');
            return;
        }
        DebMes('xiaomihome: queue #' . $id . ' dropped after 3 attempts (' . $reason . ')', 'xiaomi', 'error');
        unset($this->queueAttempts[$id]);
        $this->repo()->deleteQueue($id);
    }

    private function receiveMessages(): void
    {
        if ($this->socket === null) return;
        $settings = $this->settings();
        $started = microtime(true);
        $count = 0;
        while ($count < 100 && (microtime(true) - $started) < 2.0) {
            $buffer = '';
            $read = @socket_recvfrom($this->socket, $buffer, 65535, 0, $remoteIp, $remotePort);
            if ($read === false || $read === 0 || $buffer === '') break;
            $this->lastData = time();
            $count++;
            try {
                $this->processMessage($buffer, (string)$remoteIp, $settings['log_debmes'], $settings['log_heartbeat']);
            } catch (Throwable $failure) {
                DebMes('xiaomihome: message from ' . $remoteIp . ' failed: ' . $failure->getMessage(), 'xiaomihome', 'error');
            }
        }
    }

    // ------------------------------------------------------------------
    // Установка и удаление
    // ------------------------------------------------------------------

    public function install($parent_name = '')
    {
        parent::install($parent_name);
        try {
            setGlobal('cycle_xiaomihomeControl', 'start');
        } catch (Throwable $failure) {
            DebMes('xiaomihome: cannot start cycle on install: ' . $failure->getMessage(), 'xiaomihome', 'warning');
        }
    }

    public function uninstall()
    {
        try {
            setGlobal('cycle_xiaomihomeControl', 'stop');
        } catch (Throwable $failure) {
            DebMes('xiaomihome: cannot stop cycle on uninstall: ' . $failure->getMessage(), 'xiaomihome', 'warning');
        }
        $this->deleteAllData();
        parent::uninstall();
    }

    public function dbInstall($data = '')
    {
        $data = <<<EOD
 xidevices: ID int(10) unsigned NOT NULL auto_increment
 xidevices: TITLE varchar(100) NOT NULL DEFAULT ''
 xidevices: TYPE varchar(100) NOT NULL DEFAULT ''
 xidevices: SID varchar(255) NOT NULL DEFAULT ''
 xidevices: GATE_KEY varchar(255) NOT NULL DEFAULT ''
 xidevices: GATE_IP varchar(255) NOT NULL DEFAULT ''
 xidevices: TOKEN varchar(255) NOT NULL DEFAULT ''
 xidevices: PARENT_ID int(10) unsigned NOT NULL DEFAULT '0'
 xidevices: UPDATED datetime

 xicommands: ID int(10) unsigned NOT NULL auto_increment
 xicommands: TITLE varchar(100) NOT NULL DEFAULT ''
 xicommands: VALUE varchar(255) NOT NULL DEFAULT ''
 xicommands: DEVICE_ID int(10) NOT NULL DEFAULT '0'
 xicommands: LINKED_OBJECT varchar(100) NOT NULL DEFAULT ''
 xicommands: LINKED_PROPERTY varchar(100) NOT NULL DEFAULT ''
 xicommands: LINKED_METHOD varchar(100) NOT NULL DEFAULT ''
 xicommands: UPDATED datetime

 xiqueue: ID int(10) unsigned NOT NULL auto_increment
 xiqueue: IP varchar(100) NOT NULL DEFAULT ''
 xiqueue: DATA text
 xiqueue: ADDED datetime
EOD;

        parent::dbInstall($data);
    }

    private function deleteAllData(): void
    {
        try {
            $repo = $this->repo();
            foreach ($repo->select('SELECT * FROM xidevices') as $device) {
                foreach ($repo->commands((int)$device['ID']) as $command) {
                    if ((string)$command['LINKED_OBJECT'] !== '' && (string)$command['LINKED_PROPERTY'] !== '') {
                        removeLinkedProperty((string)$command['LINKED_OBJECT'], (string)$command['LINKED_PROPERTY'], $this->name);
                    }
                }
            }
            foreach (array('xiqueue', 'xicommands', 'xidevices') as $table) {
                $repo->db->requireExec('DROP TABLE IF EXISTS ' . $table);
            }
        } catch (Throwable $failure) {
            DebMes('xiaomihome: uninstall cleanup failed: ' . $failure->getMessage(), 'xiaomihome', 'error');
        }
    }
}
