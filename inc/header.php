<?php
// /var/www/html/inc/header.php
$publicIp = trim(shell_exec('curl -s ifconfig.me'));
?>
<!-- Блок, отображающий IP по центру вверху (если нужно) -->
<div style="
  position:absolute;
  top:22px;
  left:50%;
  transform:translateX(-50%);
  background:#333;
  padding:5px 10px;
  border-radius:4px;">
    <span>IP: <?php echo htmlspecialchars($publicIp); ?></span>
</div>

<header>
    <div class="header-logo">
        <a href="/"><img src="/logo.png" alt="Logo" height="40"></a>
        <a href="/" class="header-title" style="text-decoration: none; color: inherit;">VPN Panel</a>
    </div>
    <!-- Кнопка для выхода -->
    <div style="position: absolute; top: 22px; right: 20px;">
        <a href="logout.php" style="color: #fff; text-decoration: none;">Выйти</a>
    </div>
    <!-- Глобальный элемент для пинг-статуса -->
    <div id="ping-status" style="position: absolute; top: 22px; right: 220px; width: 200px; background-color: #333; padding: 8px; border-radius: 6px; text-align: center;">
        Проверка пинга...
    </div>
</header>

<!-- Скрипт для обновления глобального пинга -->
<script src="/js/global_ping.js"></script>
