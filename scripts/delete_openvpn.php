<?php
// /var/www/html/scripts/delete_openvpn.php
header('Content-Type: text/plain');
$openvpnConfigFile = '/etc/openvpn/client1.conf';

// Останавливаем службу OpenVPN перед удалением
shell_exec('sudo systemctl stop openvpn@client1 2>&1');
shell_exec('sudo systemctl disable openvpn@client1 2>&1');

if (file_exists($openvpnConfigFile)) {
    $result = shell_exec('sudo rm -f ' . escapeshellarg($openvpnConfigFile) . ' 2>&1');
    echo "Конфигурация удалена.\n" . $result;
} else {
    echo "Конфигурационный файл не найден.";
}
?>
