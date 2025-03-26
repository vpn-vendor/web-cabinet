<?php
// export_bot_logs.php

$log_file = '/var/log/telegram_bot.log';
if (!file_exists($log_file)) {
    die("Лог-файл не найден.");
}

// Считываем весь лог
$all_lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($all_lines === false) {
    die("Не удалось прочитать лог-файл.");
}

// Берем последние 200 строк
$last_lines = array_slice($all_lines, -200);

// Фильтрация: разрешаем только первые 3 сообщения успешного подключения,
// определяем их по наличию указанных подстрок.
$allowed_success_count = 3;
$success_count = 0;
$filtered_lines = [];

foreach ($last_lines as $line) {
    if (strpos($line, "HTTP Request: POST https://api.telegram.org/") !== false ||
        strpos($line, "HTTP/1.1 200 OK") !== false) {
        if ($success_count < $allowed_success_count) {
            $filtered_lines[] = $line;
            $success_count++;
        }
        // Игнорируем все остальные такие строки
    } else {
        $filtered_lines[] = $line;
    }
}

// Переворачиваем массив, чтобы свежие записи были сверху
$filtered_lines = array_reverse($filtered_lines);

// Собираем финальный контент
$log_content = implode("\n", $filtered_lines);

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="telegram_bot_logs.txt"');
echo $log_content;
?>
