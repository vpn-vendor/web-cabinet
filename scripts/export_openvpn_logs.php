<?php
$logFile = '/var/log/syslog';

$maxLines = 1000;    // Максимум строк в итоговом файле
$linesOut = [];      // Сюда будем складывать результат
$seenLines = [];     // Ассоциативный массив для проверки дублей

if (file_exists($logFile)) {
    // Читаем лог-файл в массив (каждая строка — элемент массива)
    $allLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($allLines as $line) {
        // Ищем "openvpn" (или openvpn@client1), без учёта регистра:
        if (stripos($line, 'openvpn') !== false || stripos($line, 'openvpn@client1') !== false) {
            // Проверяем, не видели ли мы эту строку раньше (чтобы убрать дубликаты)
            if (!isset($seenLines[$line])) {
                $seenLines[$line] = true;
                $linesOut[] = $line;
                // Ограничиваем длину
                if (count($linesOut) >= $maxLines) {
                    break;
                }
            }
        }
    }
} else {
    // Лог-файл не найден
    $linesOut[] = "Лог-файл не найден: $logFile";
}

// Формируем содержимое
$exportContent = implode("\n", $linesOut);
// Генерируем имя файла
$filename = "openvpn_logs_" . date('Ymd_His') . ".txt";

// Отправляем заголовки, чтобы пользователь получил файл .txt
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $exportContent;
exit;
