<?php
// remove_user.php

session_start();
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: login.php");
    exit();
}

// Проверяем наличие GET-параметра user
if (!isset($_GET['user'])) {
    header("Location: telegram_bot.php");
    exit();
}

$user_to_remove = $_GET['user'];

// Путь к конфигурационному файлу
$config_file = __DIR__ . '/../data/telegram_bot_config.json';

if (!file_exists($config_file)) {
    header("Location: telegram_bot.php?error=config_not_found");
    exit();
}

$config = json_decode(file_get_contents($config_file), true);
if (!isset($config['authorized_users']) || !is_array($config['authorized_users'])) {
    header("Location: telegram_bot.php?error=no_authorized_users");
    exit();
}

// Ищем и удаляем пользователя
$found = false;
$new_authorized = [];
foreach ($config['authorized_users'] as $authorized_user) {
    // Сравниваем как строки, чтобы быть уверенными в совпадении формата
    if ((string)$authorized_user === (string)$user_to_remove) {
        $found = true;
        continue; // пропускаем данного пользователя
    }
    $new_authorized[] = $authorized_user;
}

if (!$found) {
    header("Location: telegram_bot.php?error=user_not_found");
    exit();
}

// Обновляем конфигурацию
$config['authorized_users'] = $new_authorized;
if (file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT)) === false) {
    header("Location: telegram_bot.php?error=save_failed");
    exit();
}

// Перенаправляем с параметром успешного удаления
header("Location: telegram_bot.php?removed=1");
exit();
?>
