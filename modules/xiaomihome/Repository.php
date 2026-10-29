<?php

use MajorDoMo\Core\Core;

/**
 * Доступ к таблицам модуля Xiaomi Home.
 *
 * Собственные таблицы `xidevices`, `xicommands`, `xiqueue` остаются прежними:
 * обновление модуля не пересоздаёт их и не трогает привязки. Новый код читает
 * и пишет через Core::database(), имена таблиц и полей не собираются из ввода.
 *
 * 2026-10-25 — слой добавлен при переводе модуля на новый интерфейс.
 * 2026-10-29 — готовность строк очереди проверяется в PHP, а не через NOW():
 * в цикле alpha-ядра часовой пояс MySQL-сессии может не совпадать с PHP.
 */
final class XiaomiHomeRepository
{
    public $db;

    public function __construct()
    {
        $this->db = Core::database();
    }

    public function select(string $sql): array
    {
        return $this->db->select($sql);
    }

    public function one(string $sql): ?array
    {
        return $this->db->one($sql);
    }

    public function quote($value): string
    {
        return "'" . $this->db->escape($value) . "'";
    }

    public function quoteLike(string $value): string
    {
        // escapeLike уже экранирует % и _ обратным слешем для LIKE.
        return "'" . $this->db->escapeLike($value) . "'";
    }

    private function searchWhere(string $search): string
    {
        $search = trim($search);
        if ($search === '') return '1';
        $like = "'%" . $this->db->escapeLike($search) . "%'";
        return '(d.TITLE LIKE ' . $like . ""
            . ' OR d.TYPE LIKE ' . $like . ""
            . ' OR d.SID LIKE ' . $like . ")";
    }

    public function devices(string $search, string $type, string $sort, int $limit, int $offset): array
    {
        $order = 'd.UPDATED DESC';
        if ($sort === 'title') $order = 'd.TITLE ASC';
        if ($sort === 'type') $order = 'd.TYPE ASC, d.TITLE ASC';
        if ($sort === 'new') $order = 'd.ID DESC';
        $where = $this->searchWhere($search);
        if ($type !== '') $where .= ' AND d.TYPE=' . $this->quote($type);
        $devices = $this->db->select('SELECT d.* FROM xidevices d WHERE ' . $where
            . ' ORDER BY ' . $order . ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset));
        return $this->withCommands($devices);
    }

    public function countDevices(string $search, string $type = ''): int
    {
        $where = 'SELECT COUNT(*) AS CNT FROM xidevices d WHERE ' . $this->searchWhere($search);
        if ($type !== '') $where .= ' AND d.TYPE=' . $this->quote($type);
        $row = $this->db->one($where);
        return (int)($row['CNT'] ?? 0);
    }

    public function device(int $id): ?array
    {
        if ($id <= 0) return null;
        $device = $this->db->one('SELECT * FROM xidevices WHERE ID=' . $id);
        if (!$device) return null;
        $device = $this->withCommands(array($device))[0];
        return $device;
    }

    /** Дополняет записи метриками: батарея, число свойств, значения для списка. */
    public function withCommands(array $devices): array
    {
        if (!$devices) return array();
        $ids = array();
        foreach ($devices as $device) {
            $id = (int)($device['ID'] ?? 0);
            if ($id > 0) $ids[] = $id;
        }
        if (!$ids) return $devices;
        $commands = $this->db->select('SELECT * FROM xicommands WHERE DEVICE_ID IN (' . implode(',', $ids) . ') ORDER BY TITLE');
        $grouped = array();
        foreach ($commands as $command) {
            $grouped[(int)$command['DEVICE_ID']][] = $command;
        }
        foreach ($devices as &$device) {
            $list = $grouped[(int)$device['ID']] ?? array();
            $device['COMMANDS_LIST'] = $list;
            $device['COMMANDS'] = count($list);
            $device['PREVIEW'] = XiaomiHomeCatalog::preview($list);
            $device['BATTERY'] = '';
            foreach ($list as $command) {
                if ($command['TITLE'] === 'battery_level') $device['BATTERY'] = (string)$command['VALUE'];
            }
        }
        unset($device);
        return $devices;
    }

    public function saveDevice(array $data): int
    {
        $record = array();
        $id = (int)($data['ID'] ?? 0);
        foreach (array('TITLE', 'GATE_KEY', 'UPDATED') as $field) {
            if (array_key_exists($field, $data)) $record[$field] = $data[$field];
        }
        if ($id > 0) {
            $record['ID'] = $id;
            $this->db->update('xidevices', $record);
            return $id;
        }
        return $this->db->insert('xidevices', $record);
    }

    public function deleteDevice(int $id): void
    {
        $device = $this->device($id);
        if (!$device) return;
        // Очередь принадлежит шлюзу: при удалении суб-устройства её не трогаем,
        // иначе пропадут команды других устройств за тем же шлюзом.
        if (XiaomiHomeCatalog::isGateway((string)$device['TYPE']) && (string)$device['GATE_IP'] !== '') {
            $this->db->requireExec('DELETE FROM xiqueue WHERE IP=' . $this->quote((string)$device['GATE_IP']));
        }
        $this->db->requireExec('DELETE FROM xicommands WHERE DEVICE_ID=' . $id);
        $this->db->requireExec('DELETE FROM xidevices WHERE ID=' . $id);
    }

    public function commands(int $deviceId): array
    {
        if ($deviceId <= 0) return array();
        return $this->db->select('SELECT * FROM xicommands WHERE DEVICE_ID=' . $deviceId . ' ORDER BY TITLE');
    }

    public function command(int $id): ?array
    {
        if ($id <= 0) return null;
        return $this->db->one('SELECT * FROM xicommands WHERE ID=' . $id);
    }

    public function commandByTitle(int $deviceId, string $title): ?array
    {
        return $this->db->one('SELECT * FROM xicommands WHERE DEVICE_ID=' . max(0, $deviceId)
            . ' AND TITLE=' . $this->quote($title) . ' LIMIT 1');
    }

    public function ensureCommand(int $deviceId, string $title): void
    {
        if ($deviceId <= 0 || $title === '') return;
        if ($this->commandByTitle($deviceId, $title)) return;
        $record = array('DEVICE_ID' => $deviceId, 'TITLE' => $title, 'VALUE' => '', 'UPDATED' => date('Y-m-d H:i:s'));
        $this->db->insert('xicommands', $record);
    }

    public function saveCommand(array $data): int
    {
        $record = array();
        $id = (int)($data['ID'] ?? 0);
        foreach (array('TITLE', 'VALUE', 'DEVICE_ID', 'LINKED_OBJECT', 'LINKED_PROPERTY', 'LINKED_METHOD', 'UPDATED') as $field) {
            if (array_key_exists($field, $data)) $record[$field] = $data[$field];
        }
        if ($id > 0) {
            $record['ID'] = $id;
            $this->db->update('xicommands', $record);
            return $id;
        }
        return $this->db->insert('xicommands', $record);
    }

    public function deleteCommand(int $id): void
    {
        if ($id > 0) $this->db->requireExec('DELETE FROM xicommands WHERE ID=' . $id);
    }

    public function types(): array
    {
        return $this->db->select('SELECT TYPE, COUNT(*) AS TOTAL FROM xidevices GROUP BY TYPE ORDER BY TYPE');
    }

    public function queueCount(): int
    {
        $row = $this->db->one('SELECT COUNT(*) AS CNT FROM xiqueue');
        return (int)($row['CNT'] ?? 0);
    }

    public function queueForIp(string $ip, int $limit = 10): array
    {
        if ($ip === '') return array();
        return $this->db->select('SELECT * FROM xiqueue WHERE IP=' . $this->quote($ip) . ' ORDER BY ID DESC LIMIT ' . max(1, $limit));
    }

    public function dueQueue(int $limit = 50): array
    {
        // Сравниваем время в PHP, а не через NOW(): в alpha-ядре цикл
        // подключается к БД до чтения настроек, поэтому часовой пояс сессии
        // MySQL может остаться серверным. Тогда ADDED (время PHP) оказывается
        // «в будущем» и команды висят в очереди часами, хотя цикл живой.
        $limit = max(1, $limit);
        $rows = $this->db->select('SELECT * FROM xiqueue ORDER BY ID LIMIT ' . ($limit * 5));
        $now = time();
        $due = array();
        foreach ($rows as $row) {
            $added = strtotime((string)($row['ADDED'] ?? ''));
            if ($added === false || $added <= $now) $due[] = $row;
            if (count($due) >= $limit) break;
        }
        return $due;
    }

    public function addQueue(string $ip, string $data, int $shift = 0): int
    {
        $record = array(
            'IP' => $ip,
            'DATA' => $data,
            'ADDED' => date('Y-m-d H:i:s', time() + max(0, $shift)),
        );
        return $this->db->insert('xiqueue', $record);
    }

    public function deleteQueue(int $id): void
    {
        if ($id > 0) $this->db->requireExec('DELETE FROM xiqueue WHERE ID=' . $id);
    }

    public function clearQueue(string $ip = ''): void
    {
        $this->db->requireExec($ip === ''
            ? 'DELETE FROM xiqueue'
            : 'DELETE FROM xiqueue WHERE IP=' . $this->quote($ip));
    }

    public function stats(): array
    {
        $devices = (int)($this->db->one('SELECT COUNT(*) AS CNT FROM xidevices')['CNT'] ?? 0);
        $gateways = (int)($this->db->one("SELECT COUNT(*) AS CNT FROM xidevices WHERE TYPE IN ('gateway','acpartner.v3')")['CNT'] ?? 0);
        $bindings = (int)($this->db->one("SELECT COUNT(*) AS CNT FROM xicommands WHERE LINKED_OBJECT<>'' AND LINKED_PROPERTY<>''")['CNT'] ?? 0);
        $lowBattery = (int)($this->db->one("SELECT COUNT(*) AS CNT FROM xicommands WHERE TITLE='battery_level' AND VALUE<>'' AND CAST(VALUE AS DECIMAL(10,2))<20")['CNT'] ?? 0);
        return array(
            'devices' => $devices,
            'gateways' => $gateways,
            'bindings' => $bindings,
            'queue' => $this->queueCount(),
            'low_battery' => $lowBattery,
        );
    }

    /** Снимок для живого обновления списка. */
    public function liveRows(int $limit = 200): array
    {
        $devices = $this->db->select('SELECT * FROM xidevices ORDER BY UPDATED DESC LIMIT ' . max(1, $limit));
        return $this->withCommands($devices);
    }
}
