<?php
$config_file = '/etc/wireguard/tun0.conf';

// Проверяем доступность файла
if (!is_readable($config_file)) {
    echo "Ошибка: не удалось прочитать файл конфигурации.";
    exit();
}

// Читаем и обрабатываем
$lines = file($config_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$new_lines = [];
$have_keepalive = false;

foreach ($lines as $line) {
    if (preg_match('/^\s*(Address|AllowedIPs)\s*=/i', $line)) {
        list($key, $value) = explode('=', $line, 2);
        $ips = array_filter(array_map('trim', explode(',', $value)));
        $ipv4_only = [];
        foreach ($ips as $ip) {
            if (strpos($ip, ':') === false) {
                $ipv4_only[] = $ip;
            }
        }
        $new_lines[] = trim($key) . ' = ' . implode(',', $ipv4_only);
    }
    elseif (preg_match('/^\s*PersistentKeepalive\s*=/i', $line)) {
        $have_keepalive = true;
        $new_lines[] = $line;
    }
    else {
        $new_lines[] = $line;
    }
}

if (!$have_keepalive) {
    $new_lines[] = 'PersistentKeepalive = 25';
}

file_put_contents($config_file, implode(PHP_EOL, $new_lines) . PHP_EOL);

// Перезапуск WireGuard
shell_exec('sudo systemctl restart wg-quick@tun0');
echo "WireGuard успешно перезапущен.";
?>
