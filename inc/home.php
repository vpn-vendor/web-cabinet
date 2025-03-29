<?php
// inc/home.php

// Определяем ОС и uptime
$osInfo = trim(shell_exec('lsb_release -d 2>/dev/null'));
if (!$osInfo) {
    $osRelease = @file_get_contents('/etc/os-release');
    if ($osRelease && preg_match('/^PRETTY_NAME="([^"]+)"/m', $osRelease, $m)) {
        $osInfo = "OS: " . $m[1];
    } else {
        $osInfo = "OS: неизвестная Linux";
    }
} else {
    $osInfo = str_replace('Description:', 'OS:', $osInfo);
}
$uptimeStr = trim(shell_exec('uptime -p 2>/dev/null'));
if (!$uptimeStr) {
    $uptimeStr = "Не удалось получить uptime.";
}

// VPN-сервисы
$ovpnStatus = trim(shell_exec('systemctl is-active openvpn@client1 2>/dev/null'));
$wgStatus   = trim(shell_exec('systemctl is-active wg-quick@tun0 2>/dev/null'));
function formatStatus($status) {
    return ($status === 'active')
        ? "<span style='color:lime;'>Активен</span>"
        : "<span style='color:red;'>Выключен</span>";
}
?>

<!-- Основное содержимое страницы -->
<div class="system-info-container">
    <div class="os-info">
        <strong><?php echo htmlspecialchars($osInfo); ?></strong><br>
        <em><?php echo htmlspecialchars($uptimeStr); ?></em>
    </div>
</div>

<div style="margin-bottom:20px; text-align:center;">
    <h2>VPN-сервисы:</h2>
    <div style="display:flex; justify-content:center; gap:40px;">
        <div>
            <a href="/openvpn">OpenVPN</a>:
            <?php echo formatStatus($ovpnStatus); ?>
        </div>
        <div>
            <a href="/wireguard">WireGuard</a>:
            <?php echo formatStatus($wgStatus); ?>
        </div>
    </div>
</div>

<!-- Новый блок системных показателей -->
<div id="system-stats-new" class="system-stats-new">
    <h2>Системные показатели</h2>
    <div class="stat-item">
        <span>CPU:</span>
        <div class="progress-bar">
            <div id="cpu-progress" class="progress-fill" style="width:0%;">0%</div>
        </div>
    </div>
    <div class="stat-item">
        <span>RAM:</span>
        <div class="progress-bar">
            <div id="ram-progress" class="progress-fill" style="width:0%;">0%</div>
        </div>
    </div>
    <div class="stat-item">
        <span>Disk:</span>
        <div class="progress-bar">
            <div id="disk-progress" class="progress-fill" style="width:0%;">0 / 0</div>
        </div>
    </div>
</div>

<!-- Заголовок для Highcharts -->
<h2 style="text-align:center; margin-top:40px;">Мониторинг системы</h2>

<!-- Блок фильтра по времени и сброса зума -->
<div style="text-align:center; margin-bottom:10px;">
  <label for="selectRange">Период:</label>
  <select id="selectRange">
    <option value="300">5 минут</option>
    <option value="1800">30 минут</option>
    <option value="3600" selected>1 час</option>
    <option value="21600">6 часов</option>
    <option value="86400">24 часа</option>
  </select>
  <button id="btnApplyRange">Применить</button>
</div>

<!-- Сам график -->
<div id="chartCombined" style="max-width:900px; height:400px; margin: 0 auto;"></div>

<!-- Подключаем библиотеки Highcharts и другие скрипты -->
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script>
<script src="/js/home_highcharts.js"></script>
<script src="/js/home.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>