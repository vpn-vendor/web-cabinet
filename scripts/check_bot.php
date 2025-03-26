<?php
header('Content-Type: application/json; charset=utf-8');
$status = trim(shell_exec("systemctl is-active telegram-bot.service"));
if ($status === 'active') {
    echo json_encode(['status' => true, 'message' => 'Бот отвечает']);
} else {
    echo json_encode(['status' => false, 'message' => 'Бот не отвечает']);
}
?>
