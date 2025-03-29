<?php
// /scripts/ajax_network.php
header('Content-Type: application/json');

// Читаем содержимое файла /proc/net/dev
$lines = file('/proc/net/dev', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$interfaces = [];

// Пропускаем первые две строки (заголовки)
array_shift($lines);
array_shift($lines);

foreach ($lines as $line) {
    // Формат строки: "  eth0:  123456789  ...  987654321 ..."
    $parts = explode(":", $line);
    if (count($parts) != 2) {
        continue;
    }
    $iface = trim($parts[0]);
    if (strtolower($iface) === "lo") continue; // исключаем loopback

    // Разбиваем вторую часть по пробелам
    $data = preg_split('/\s+/', trim($parts[1]));
    if (count($data) >= 9) {
        $rx = (int)$data[0];
        $tx = (int)$data[8];
        $interfaces[$iface] = [
            'rx_bytes' => $rx,
            'tx_bytes' => $tx
        ];
    }
}

echo json_encode([
    'interfaces' => $interfaces,
    'timestamp' => time()
]);
?>
