<?php
session_set_cookie_params(0, "/");
session_start();
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>VPN Panel</title>
    <link rel="icon" href="/favicon.png" type="image/png">
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <?php
    require_once 'config.php';
    include 'inc/header.php';
    include 'inc/navigation.php';
    echo '<div class="main-container">';

    $page = isset($_GET['page']) ? rtrim($_GET['page'], '/') : 'home';
    switch ($page) {
        case 'home':
            include 'inc/home.php';
            break;
        case 'openvpn':
            include 'inc/openvpn.php';
            break;
        case 'wireguard':
            include 'inc/wireguard.php';
            break;
        case 'logs':
            include 'inc/logs.php';
            break;
        case 'console':
            include 'inc/console.php';
            break;
        case 'network':
            include 'inc/network.php';
            break;
        case 'monitoring':
            include 'inc/monitoring.php';
            break;
        case 'filemanager':
            include 'inc/filemanager.php';
            break;
        case 'telegram_bot':
            include 'inc/telegram_bot.php';
            break;
        default:
            echo "<h1>404</h1><p>Страница не найдена.</p>";
            break;
    }
    echo '</div>';
    include 'inc/footer.php';
    ?>
</body>
</html>
