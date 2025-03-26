<?php
// inc/telegram_bot.php

require_once(__DIR__ . '/../config.php');
session_start();
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: login.php");
    exit();
}

// Путь к конфигурационному файлу бота
$config_file = __DIR__ . '/../data/telegram_bot_config.json';
if (file_exists($config_file)) {
    $config = json_decode(file_get_contents($config_file), true);
} else {
    // Инициализация дефолтных значений (без поля bot_status)
    $config = [
        'api_key' => '',
        'credentials' => [
            'login' => '',
            'password' => '',
        ],
        'authorized_users' => [],
    ];
}

// Путь к лог-файлу бота
$log_file = '/var/log/telegram_bot.log';

// Функция для сохранения конфигурации и редиректа с параметром "saved"
function saveConfigAndRedirect(array $cfg, $config_file) {
    file_put_contents($config_file, json_encode($cfg, JSON_PRETTY_PRINT));
    header("Location: telegram_bot?saved=1");
    exit();
}

// Обработка POST-запросов
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_POST['clear_api'])) {
        $config['api_key'] = '';
    }

    if (isset($_POST['update_api_key'])) {
        $config['api_key'] = trim($_POST['api_key']);
    }

    if (isset($_POST['regenerate_credentials'])) {
        $config['credentials']['login'] = bin2hex(random_bytes(4));    // 8 символов
        $config['credentials']['password'] = bin2hex(random_bytes(8)); // 16 символов
        $config['authorized_users'] = [];
    }

    if (isset($_POST['start_bot'])) {
        shell_exec("sudo systemctl enable telegram_bot.service 2>&1");
        shell_exec("sudo systemctl start telegram_bot.service 2>&1");
    }

    if (isset($_POST['stop_bot'])) {
        shell_exec("sudo systemctl stop telegram_bot.service 2>&1");
        shell_exec("sudo systemctl disable telegram_bot.service 2>&1");
    }

    // Новый обработчик для перезагрузки службы
    if (isset($_POST['restart_bot'])) {
        shell_exec("sudo systemctl restart telegram_bot.service 2>&1");
    }

    // Определяем статус службы, но не сохраняем в config
    $service_status_cmd = trim(shell_exec("sudo systemctl is-active telegram_bot.service"));

    saveConfigAndRedirect($config, $config_file);
}

// Перед выводом страницы определяем статус службы (через sudo)
$service_status_cmd = trim(shell_exec("sudo systemctl is-active telegram_bot.service"));
$service_status = ($service_status_cmd === 'active') ? 'active' : 'inactive';

// Читаем лог-файл (если существует) и переворачиваем строки, чтобы свежие записи были сверху
$log_content = "Лог не найден";
if (file_exists($log_file)) {
    $log_lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($log_lines !== false) {
        $log_lines = array_reverse($log_lines);
        $log_content = implode("\n", array_slice($log_lines, 0, 200));
    } else {
        $log_content = "Не удалось прочитать лог-файл.";
    }
}

// Генерируем команду /login, если данные имеются
$login_command = "";
if (!empty($config['credentials']['login']) && !empty($config['credentials']['password'])) {
    $login_command = "/login " . $config['credentials']['login'] . " " . $config['credentials']['password'];
}
?>
<!-- НЕТ <html>, <head>, <body>, footer.php – всё это подключается через index.php -->

<style>
/* Стили, сохранённые с прежней версии */

.toast-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.toast-message {
    position: relative;
    background-color: #333;
    color: #fff;
    padding: 10px 15px;
    border-radius: 4px;
    min-width: 200px;
    max-width: 300px;
    font-size: 0.95em;
    box-shadow: 0 4px 6px rgba(0,0,0,0.3);
    animation: fadeIn 0.3s ease;
}
.toast-message.success { background-color: #4caf50; }
.toast-message.error { background-color: #f44336; }
.toast-message .close-btn {
    position: absolute;
    top: 5px;
    right: 8px;
    background: none;
    border: none;
    color: #fff;
    font-size: 16px;
    cursor: pointer;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.page-header {
    text-align: center;
    margin-top: 20px;
    margin-bottom: 10px;
}

.api-key-input {
    width: 50%;
    display: block;
    margin: 0 auto;
    padding: 8px;
    background-color: rgba(240,240,240,0.9);
    border: 1px solid #ccc;
    border-radius: 4px;
}
.api-key-input[disabled] {
    background-color: #ddd;
    color: #666;
}

.apply-btn {
    display: block;
    margin: 15px auto;
    padding: 10px 20px;
    background-color: #fff;
    color: #333;
    border: 1px solid #ccc;
    border-radius: 6px;
    box-shadow: 0 3px 6px rgba(0,0,0,0.3);
    font-size: 1em;
    cursor: pointer;
    transition: transform 0.1s ease, box-shadow 0.2s ease;
}
.apply-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.4);
}

.bot-status {
    text-align: center;
    font-size: 1.2em;
    margin: 10px 0;
}
.bot-status .inactive { color: red; }
.bot-status .active { color: green; }

.info-container {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    margin-top: 20px;
}
.info-box {
    flex: 1;
    min-width: 360px;
    margin: 10px;
    padding: 20px;
    background-color: #444;
    border: 1px solid #555;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    color: #fff;
}
.info-box h2 {
    margin-top: 0;
    text-align: center;
    font-size: 1.2em;
}
.info-box p {
    margin: 10px 0;
    font-size: 1.1em;
}
.info-box label {
    font-weight: bold;
}

.start-btn, .stop-btn {
    display: inline-block;
    padding: 10px 20px;
    font-size: 0.95em;
    font-weight: bold;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    box-shadow: 0 4px 6px rgba(0,0,0,0.3);
    transition: transform 0.1s ease, background 0.3s ease;
}
.start-btn {
    background: linear-gradient(180deg, #4caf50 0%, #43a047 100%);
}
.start-btn:hover {
    transform: translateY(-2px);
    background: linear-gradient(180deg, #66bb6a 0%, #5a9216 100%);
}
.stop-btn {
    background: linear-gradient(180deg, #f44336 0%, #e53935 100%);
}
.stop-btn:hover {
    transform: translateY(-2px);
    background: linear-gradient(180deg, #ef5350 0%, #e53935 100%);
}

/* Новая кнопка для перезагрузки */
.restart-btn {
    display: inline-block;
    padding: 10px 20px;
    font-size: 0.95em;
    font-weight: bold;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    background: linear-gradient(180deg, #ffa000 0%, #ff8f00 100%);
    box-shadow: 0 4px 6px rgba(0,0,0,0.3);
    transition: transform 0.1s ease, background 0.3s ease;
    margin-top: 10px;
}
.restart-btn:hover {
    transform: translateY(-2px);
    background: linear-gradient(180deg, #ffc107 0%, #ffa000 100%);
}

/* Кнопки копирования */
.copy-btn {
    margin-left: 5px;
    padding: 4px 8px;
    background-color: #333;
    border: 1px solid #666;
    border-radius: 4px;
    color: #fff;
    font-size: 0.9em;
}
.copy-btn:hover {
    background-color: #555;
}

.readonly-field {
    width: 100%;
    padding: 8px;
    background-color: #3f3f3f;
    border: 1px solid #666;
    color: #fff;
    border-radius: 4px;
    margin-bottom: 10px;
}
.readonly-field:focus {
    outline: none;
}

.login-command {
    width: 100%;
    padding: 8px;
    border: 1px solid #666;
    border-radius: 4px;
    margin-top: 10px;
    text-align: center;
    font-weight: bold;
}
.login-command.filled {
    background-color: #4caf50;
    color: #fff;
}
.login-command.empty {
    background-color: #777;
    color: #ccc;
}

.info-box ul {
    list-style: none;
    padding: 0;
    margin: 0;
}
.info-box ul li {
    padding: 8px;
    border-bottom: 1px solid #555;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.info-box ul li:last-child {
    border-bottom: none;
}
.info-box ul li button {
    padding: 4px 8px;
    background-color: #c00;
    border: none;
    color: #fff;
    border-radius: 4px;
}

.log-box {
    background-color: #333;
    color: #ccc;
    max-height: 300px;
    overflow-y: auto;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #555;
    font-family: monospace;
    font-size: 0.9em;
    white-space: pre-wrap;
}

.export-logs-btn {
    display: inline-block;
    margin: 10px 0;
    padding: 8px 16px;
    background-color: #2196F3;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    box-shadow: 0 3px 6px rgba(0,0,0,0.3);
    transition: transform 0.1s ease, background 0.3s ease;
}
.export-logs-btn:hover {
    transform: translateY(-2px);
    background-color: #1976D2;
}

/* Модальное окно для очистки API ключа */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}
.modal {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    max-width: 400px;
    width: 90%;
    text-align: center;
    color: #333;
}
.modal button {
    margin-top: 15px;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
.modal .confirm-btn {
    background-color: #f44336;
    color: #fff;
}
.modal .cancel-btn {
    background-color: #ccc;
    color: #333;
    margin-left: 10px;
}

/* Новые стили для модального окна перезагрузки */
.modal .confirm-btn-green {
    background-color: #4caf50; /* зеленая кнопка для подтверждения */
    color: #fff;
}
.modal .cancel-btn-red {
    background-color: #f44336; /* красная кнопка для отмены */
    color: #fff;
}

/* Новые стили для макета уведомлений */
.notification-item {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
    justify-content: space-between;
}
.notification-item span {
    flex: 1;
}
.toggle-btn {
    padding: 6px 12px;
    background-color: #666;
    border: none;
    border-radius: 4px;
    color: #fff;
    cursor: not-allowed;
}
</style>

<div class="toast-container" id="toast-container"></div>

<!-- Модальное окно для очистки API ключа -->
<div class="modal-overlay" id="modal-clear-api">
    <div class="modal">
        <p>Вы уверены, что хотите очистить API ключ?</p>
        <form id="clear_api_form" method="post" action="telegram_bot">
            <input type="hidden" name="clear_api" value="1">
            <button type="button" class="confirm-btn" onclick="confirmClearApi()">Очистить</button>
            <button type="button" class="cancel-btn" onclick="hideClearApiModal()">Отмена</button>
        </form>
    </div>
</div>

<!-- Новое модальное окно для перезагрузки бота -->
<div class="modal-overlay" id="modal-restart-bot">
    <div class="modal">
        <p>Внимание! Вы действительно хотите перезагрузить службу бота?</p>
        <form id="restart_bot_form" method="post" action="telegram_bot">
            <input type="hidden" name="restart_bot" value="1">
            <button type="button" class="confirm-btn-green" onclick="confirmRestartBot()">Да</button>
            <button type="button" class="cancel-btn-red" onclick="hideRestartBotModal()">Нет</button>
        </form>
    </div>
</div>

<div class="main-container">
    <h1 class="page-header">Настройки Telegram Bot</h1>

    <!-- Статус бота -->
    <div class="bot-status">
        Статус бота:
        <span class="<?php echo ($service_status === 'active') ? 'active' : 'inactive'; ?>">
            <?php echo $service_status; ?>
        </span>
    </div>

    <!-- Кнопки управления службой -->
    <div style="text-align:center; margin-bottom:20px;">
        <form method="post" action="telegram_bot" style="display:inline-block;">
            <?php if ($service_status === 'active'): ?>
                <button type="submit" name="stop_bot" class="stop-btn">Отключить бота</button>
            <?php else: ?>
                <button type="submit" name="start_bot" class="start-btn">Запустить бота</button>
            <?php endif; ?>
        </form>
        <!-- Новая кнопка для перезагрузки -->
        <button type="button" class="restart-btn" onclick="showRestartBotModal()">Перезагрузить бота</button>
    </div>

<!-- Форма для объяснения получения API -->
<div id="apiInstructions" style="margin: 20px auto; max-width: 600px; background: #e0e0e0; padding: 15px; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.2); font-size: 16px; line-height: 1.4; color: #333;">
  <div style="display: flex; justify-content: space-between; align-items: center;">
    <span style="font-weight: bold;">Инструкции по получению API-ключа</span>
    <button id="toggleApiInstructions" style="background: none; border: none; cursor: pointer; font-size: 20px; line-height: 1; color: #333;">&#x2715;</button>
  </div>
  <div id="apiContent" style="margin-top: 10px;">
    <p>
      Чтобы получить API-ключ для вашего Telegram-бота, перейдите в 
      <a href="https://t.me/BotFather" target="_blank" style="color: #007bff; text-decoration: none; font-weight: bold;">BotFather</a>
      и следуйте инструкциям. Рекомендуется выбрать случайное имя бота, известное только вам, чтобы повысить безопасность.
    </p>
  </div>
</div>
<button id="showApiInstructions" style="display: none; margin: 20px auto; padding: 10px 20px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
  Показать инструкции по API
</button>

<script>
(function() {
  const instructions = document.getElementById('apiInstructions');
  const toggleBtn = document.getElementById('toggleApiInstructions');
  const showBtn = document.getElementById('showApiInstructions');

  toggleBtn.addEventListener('click', function() {
    instructions.style.display = 'none';
    showBtn.style.display = 'block';
    localStorage.setItem('apiInstructionsCollapsed', 'true');
  });

  showBtn.addEventListener('click', function() {
    instructions.style.display = 'block';
    showBtn.style.display = 'none';
    localStorage.setItem('apiInstructionsCollapsed', 'false');
  });

  // При загрузке страницы проверяем сохранённое состояние
  if (localStorage.getItem('apiInstructionsCollapsed') === 'true') {
    instructions.style.display = 'none';
    showBtn.style.display = 'block';
  }
})();
</script>


    <!-- Форма для ввода API ключа -->
    <form class="api-key-form" method="post" action="telegram_bot">
        <label for="api_key">Telegram API Key:</label><br>
        <input class="api-key-input" type="text" name="api_key" id="api_key"
               value="<?= htmlspecialchars($config['api_key']) ?>"
               placeholder="Вставь сюда свой Telegram API Key полученный из @BotFather"
               <?php echo (!empty($config['api_key']) ? "disabled" : ""); ?> required>
        <br><br>
        <?php if (!empty($config['api_key'])): ?>
            <button type="button" onclick="showClearApiModal()" class="apply-btn">Очистить API ключ</button>
        <?php else: ?>
            <button type="submit" name="update_api_key" class="apply-btn">Применить</button>
        <?php endif; ?>
    </form>

    <!-- Контейнер для учетных данных и команды /login -->
    <div class="info-container">
        <!-- Учётные данные -->
        <div class="info-box">
            <h2>Учётные данные для доступа к боту</h2>
            <label for="bot_login">Логин:</label>
            <div style="display: flex; align-items: center;">
                <input type="text" id="bot_login" class="readonly-field" readonly
                       value="<?= htmlspecialchars($config['credentials']['login']) ?>">
                <button type="button" class="copy-btn" onclick="copyToClipboard('bot_login')">Копировать</button>
            </div>
            <label for="bot_password">Пароль:</label>
            <div style="display: flex; align-items: center;">
                <input type="text" id="bot_password" class="readonly-field" readonly
                       value="<?= htmlspecialchars($config['credentials']['password']) ?>">
                <button type="button" class="copy-btn" onclick="copyToClipboard('bot_password')">Копировать</button>
            </div>
            <p style="font-size: 0.9em; color: #ddd; text-align:center;">
                При смене логина/пароля все ранее авторизованные пользователи будут сброшены.
            </p>
            <p style="font-size: 0.9em; color: #ccc; text-align:center;">
                Эти данные нужно ввести в вашего телеграмм бота и авторизоваться.
            </p>
            <form method="post" action="telegram_bot">
                <button type="submit" name="regenerate_credentials">Сгенерировать новые логин/пароль</button>
            </form>
            <!-- Команда /login -->
            <label for="login_command" style="margin-top:10px; display:block; font-weight:bold;">
                Нажмите для копирования команды /login:
            </label>
            <input type="text" id="login_command" class="login-command <?= ($login_command ? 'filled' : 'empty'); ?>" readonly
                   value="<?= htmlspecialchars($login_command) ?>">
            <button type="button" class="copy-btn" onclick="copyToClipboard('login_command')">Копировать команду</button>
        </div>

        <!-- Контейнер для авторизованных пользователей -->
        <div class="info-box">
            <h2>Авторизованные пользователи</h2>
            <?php if (!empty($config['authorized_users'])): ?>
                <ul>
                    <?php foreach ($config['authorized_users'] as $user): ?>
                        <li>
                            <?php
                            if (preg_match('/^(\d+)\s*-\s*@(.+)$/', $user, $m)) {
                                $chatId = $m[1];
                                $usernamePart = $m[2];
                                $usernameFull = '@' . $usernamePart;
                                echo '<a href="https://t.me/' . htmlspecialchars($usernamePart) . '" target="_blank">'
                                     . htmlspecialchars($usernameFull) . '</a> - ' . htmlspecialchars($chatId);
                            } else {
                                echo htmlspecialchars($user);
                            }
                            ?>
                            <button onclick="if(confirm('Отключить данного пользователя?')) { location.href='remove_user.php?user=<?= urlencode($user) ?>'; }">
                                Отключить
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>Нет авторизованных пользователей.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Контейнер для логирования действий -->
    <div class="info-container">
        <div class="info-box" style="position: relative;">
            <h2>Логирование действий</h2>
            <button type="button" class="export-logs-btn" style="position: absolute; right: 10px; top: 10px;" onclick="exportLogs()">
                Экспортировать логи
            </button>
            <div class="log-box">
                <?= htmlspecialchars($log_content) ?>
            </div>
        </div>

        <!-- Настройки уведомлений (в разработке) -->
        <div class="info-box">
            <h2>Настройки уведомлений (в разработке)</h2>
            <p style="font-size:0.9em; color:#ccc;">
                Этот раздел находится в разработке. Ниже отображаются только переключатели для демонстрации.
            </p>
            <div class="notification-item">
                <span>Уведомлять о перезагрузке сервера:</span>
                <button class="toggle-btn" disabled>Выкл</button>
            </div>
            <div class="notification-item">
                <span>Уведомлять о высоком использовании CPU:</span>
                <button class="toggle-btn" disabled>Выкл</button>
            </div>
            <div class="notification-item">
                <span>Уведомлять о нехватке места на диске:</span>
                <button class="toggle-btn" disabled>Выкл</button>
            </div>
            <div class="notification-item">
                <span>Уведомлять о сетевых проблемах:</span>
                <button class="toggle-btn" disabled>Выкл</button>
            </div>
        </div>
    </div>
</div>

<script>
// TOAST функция
function showToast(message, type='success') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const toast = document.createElement('div');
    toast.classList.add('toast-message', type);
    const closeBtn = document.createElement('button');
    closeBtn.classList.add('close-btn');
    closeBtn.innerHTML = '×';
    closeBtn.onclick = () => container.removeChild(toast);
    toast.innerText = message;
    toast.appendChild(closeBtn);
    container.appendChild(toast);
    setTimeout(() => {
        if (toast.parentNode === container) {
            container.removeChild(toast);
        }
    }, 5000);
}

// COPY TO CLIPBOARD
function copyToClipboard(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.select();
    input.setSelectionRange(0, 99999);
    document.execCommand("copy");
    showToast("Скопировано: " + input.value, "success");
}

// Обновление команды /login
function updateLoginCommand() {
    const login = document.getElementById('bot_login').value;
    const password = document.getElementById('bot_password').value;
    const commandField = document.getElementById('login_command');
    if (login && password) {
        commandField.value = "/login " + login + " " + password;
        commandField.classList.remove('empty');
        commandField.classList.add('filled');
    } else {
        commandField.value = "";
        commandField.classList.remove('filled');
        commandField.classList.add('empty');
    }
}

// Модальное окно для очистки API
function showClearApiModal() {
    document.getElementById('modal-clear-api').style.display = 'flex';
}
function hideClearApiModal() {
    document.getElementById('modal-clear-api').style.display = 'none';
}
function confirmClearApi() {
    document.getElementById('clear_api_form').submit();
}

// Модальное окно для перезагрузки бота
function showRestartBotModal() {
    document.getElementById('modal-restart-bot').style.display = 'flex';
}
function hideRestartBotModal() {
    document.getElementById('modal-restart-bot').style.display = 'none';
}
function confirmRestartBot() {
    document.getElementById('restart_bot_form').submit();
}

// Экспорт логов
function exportLogs() {
    window.location.href = '/export_bot_logs.php';
}

document.addEventListener('DOMContentLoaded', () => {
    <?php if (isset($_GET['saved']) && $_GET['saved'] == 1): ?>
        showToast("Настройки сохранены!", "success");
    <?php endif; ?>
    updateLoginCommand();
});
</script>
