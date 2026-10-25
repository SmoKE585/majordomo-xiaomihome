<?php
/**
 * Константы протокола шлюза Xiaomi/Aqara.
 *
 * 2026-10-25 — глобальные define() заменены константами класса.
 */
final class XiaomiHomeConst
{
    /** Порт multicast-группы, на который шлют шлюзы. */
    public const PORT = 9898;

    /** Порт, на который отправляется whois-запрос. */
    public const PEER_PORT = 4321;

    /** Multicast-адрес протокола шлюза. */
    public const ADDRESS = '224.0.0.50';

    /** IV для подписи команд (AES-128-CBC, нулевой padding). */
    public const SIGNATURE_IV = '17996d093d28ddb3ba695a2e6f58562e';
}
