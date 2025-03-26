<?php
$logFile = '/var/log/syslog';
$maxLines = 1000;
$linesOut = [];
$seen = [];

if (file_exists($logFile)) {
    $allLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($allLines as $line) {
        // Ищем "wireguard" или "wg-quick" (без учёта регистра)
        if (stripos($line, 'wireguard') !== false || stripos($line, 'wg-quick') !== false) {
            // Убираем дубли
            if (!isset($seen[$line])) {
                $seen[$line] = true;
                $linesOut[] = $line;
                if (count($linesOut) >= $maxLines) {
                    break;
                }
            }
        }
    }
} else {
    $linesOut[] = "Лог-файл не найден: $logFile";
}

$exportContent = implode("\n", $linesOut);
$filename = "wireguard_logs_" . date('Ymd_His') . ".txt";

header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $exportContent;
exit;
