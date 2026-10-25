# Xiaomi Home для MajorDoMo

Модуль для Zigbee-устройств Xiaomi, Mijia и Aqara через локальный протокол
шлюза: multicast **224.0.0.50**, UDP **9898**. Облако Xiaomi не нужно —
шлюз присылает события сам, MajorDoMo раскладывает их по свойствам и вызывает
привязки объектов.

Версия **4.0** — новый интерфейс и цикл поверх проверенного протокольного
движка. Схема БД не меняется, данные и привязки сохраняются при обновлении.

## Возможности

- автоматическое появление суб-устройств из сообщений шлюза;
- список с поиском, фильтром по типу, статусом, батареей и живым обновлением;
- карточка устройства: тип, SID, IP шлюза, ключ, батарейка, технические сообщения;
- свойства и привязки к объектам/методам MajorDoMo;
- команды реле, штор, подсветки и мелодии с подписью ключа шлюза;
- тип-aware доступность: шлюз 15 минут, сетевые 60, motion/cube 65, батарейные 170;
- настройки bind IP, округления датчиков, порога доступности и журналов;
- инструкция по включению локального протокола и получению ключа;
- документация для «Базы знаний» MajorDoMo.

## Совместимость

- MajorDoMo с новой админкой (`MDJUI`, `md-*`, чистые `/panel/...`), PHP 8.1+;
- расширение PHP `sockets`;
- шлюзы Mi Home и Aqara, отдающие локальный протокол;
- обновление с версии 2018 года: таблицы `xidevices`, `xicommands`, `xiqueue`
  и привязки не пересоздаются.

## Установка

1. Скопируйте содержимое репозитория в корень MajorDoMo, сохранив пути:
   `modules/`, `templates/`, `public/assets/`, `scripts/`.
2. Откройте `/panel/xiaomihome.html` — модуль зарегистрируется и создаст таблицы.
3. Включите локальный протокол шлюза в Mi Home или Aqara Home.
4. Дождитесь появления шлюза в списке и вставьте 16-символьный ключ в его карточке.
5. Проверьте цикл `cycle_xiaomihome` в X-Ray.

Подробная инструкция — в [modules/xiaomihome/docs/gateway.md](modules/xiaomihome/docs/gateway.md).

## Структура

```
modules/xiaomihome/
  xiaomihome.class.php   контроллер: страницы, действия, цикл
  Engine.php             trait: разбор сообщений шлюза и propertySetHandle
  Constants.php          порт, multicast-адрес, IV подписи
  Catalog.php            названия типов, батарейки, подписи и единицы
  Repository.php         доступ к таблицам через Core::database()
  docs/                  документация для «Базы знаний»
templates/xiaomihome/    PHP-шаблоны (MDJUI)
public/assets/modules/xiaomihome/  CSS, JS и изображения типов
public/assets/img/modules/xiaomihome.png  иконка модуля
scripts/cycle_xiaomihome.php       тонкий запускатор цикла
```

## Документация

- [Обзор](modules/xiaomihome/docs/overview.md)
- [Шлюз и ключ](modules/xiaomihome/docs/gateway.md)
- [Устройства и статусы](modules/xiaomihome/docs/devices.md)
- [Свойства и привязки](modules/xiaomihome/docs/bindings.md)
- [Обновление](modules/xiaomihome/docs/upgrade.md)
- [Что берём из Home Assistant](modules/xiaomihome/docs/roadmap.md)

## Благодарности

Протокол и исходный модуль — SergeJey и участники
[sergejey/majordomo-xiaomihome](https://github.com/sergejey/majordomo-xiaomihome).
Документация протокола —
[lumi-gateway-local-api](https://github.com/louisZL/lumi-gateway-local-api) и
[Aqara LAN communication](https://github.com/aqara/opencloud-docs/blob/master/en/development/gateway-LAN-communication.md).
Сравнение с Home Assistant —
[`xiaomi_aqara`](https://www.home-assistant.io/integrations/xiaomi_aqara/) и
[`XiaomiGateway3`](https://github.com/AlexxIT/XiaomiGateway3).
