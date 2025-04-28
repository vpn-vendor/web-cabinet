<?php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["config_file"])) {
    $upload_dir = '/etc/wireguard/';
    $file = $_FILES["config_file"];
    // Проверка расширения
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

    if ($file_extension != "conf") {
        echo "Ошибка: Загрузите файл с расширением .conf";
        exit();
    }

    $config_file = $upload_dir . 'tun0.conf';

    // Перемещение файла
    if (move_uploaded_file($file["tmp_name"], $config_file)) {
        // Чтение конфига
        $lines = file($config_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $new_lines = [];
        $have_keepalive = false;

        foreach ($lines as $line) {
            // Обработка Address и AllowedIPs
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
            // Проверка PersistentKeepalive
            elseif (preg_match('/^\s*PersistentKeepalive\s*=/i', $line)) {
                $have_keepalive = true;
                $new_lines[] = $line;
            }
            else {
                $new_lines[] = $line;
            }
        }
        // Добавляем PersistentKeepalive, если не найден
        if (!$have_keepalive) {
            $new_lines[] = 'PersistentKeepalive = 25';
        }
        // Запись обратно в файл
        file_put_contents($config_file, implode(PHP_EOL, $new_lines) . PHP_EOL);

        // Отключаем OpenVPN
        shell_exec('sudo systemctl stop openvpn@client1.service');
        shell_exec('sudo systemctl disable openvpn@client1.service');
        shell_exec('sudo rm /etc/openvpn/client1.conf');

        // Включаем и перезапускаем WireGuard
        shell_exec('sudo systemctl enable wg-quick@tun0');
        shell_exec('sudo systemctl restart wg-quick@tun0');

        echo "Файл конфигурации WireGuard успешно загружен и применен.";
        header("Location: index.php");
        exit();
    } else {
        echo "Ошибка при загрузке файла.";
    }
} else {
    echo "Ошибка: Неверный запрос.";
}
?>
