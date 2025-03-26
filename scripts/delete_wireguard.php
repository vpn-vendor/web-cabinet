<?php
header('Content-Type: text/plain');

$configFile = '/etc/wireguard/tun0.conf';

// Останавливаем службу WireGuard
shell_exec('sudo systemctl stop wg-quick@tun0 2>&1');
shell_exec('sudo systemctl disable wg-quick@tun0 2>&1');

if (file_exists($configFile)) {
    $result = shell_exec('sudo rm -f ' . escapeshellarg($configFile) . ' 2>&1');
    echo "Конфигурация WireGuard удалена.\n" . $result;
} else {
    echo "Файл конфигурации не найден.";
}
