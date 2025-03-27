<?php
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление WireGuard - VPN Panel</title>
    <link rel="icon" href="/favicon.png" type="image/png">
    <link rel="stylesheet" href="/css/styles.css">
    <style>
        .main-container {
            max-width: 1100px;
            margin: 20px auto;
            padding: 20px;
            background: #2c2c2c;
            color: #fff;
            border-radius: 8px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.3);
        }
        .header-title-custom {
            text-align: center;
            margin: 20px 0;
            font-size: 1.8em;
            font-weight: bold;
        }
        .status {
            font-size: 1.2em;
            font-weight: bold;
            text-align: center;
            margin: 10px 0;
        }
        .status span.active { color: lime; }
        .status span.inactive { color: red; }
        /* Консоль */
        .console {
            background-color: #000;
            color: #0f0;
            font-family: "Courier New", monospace;
            padding: 10px;
            margin-top: 20px;
            border-radius: 4px;
            max-height: 300px;
            overflow-y: auto;
            position: relative;
        }
        /* Кнопки управления */
        .yellow-button {
            background: linear-gradient(180deg, #ffca28 0%, #ffc107 100%);
            color: #333;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
            transition: transform 0.1s ease, box-shadow 0.2s ease;
        }
        .yellow-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0,0,0,0.4);
        }
        .red-button {
            background: linear-gradient(180deg, #d32f2f 0%, #c62828 100%);
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
            transition: transform 0.1s ease, box-shadow 0.2s ease;
        }
        .red-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0,0,0,0.4);
        }
        .blue-button {
            background: linear-gradient(180deg, #2196F3 0%, #1E88E5 100%);
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
            transition: transform 0.1s ease, box-shadow 0.2s ease;
        }
        .blue-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0,0,0,0.4);
        }
        /* Область загрузки файла с drag&amp;drop */
        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 6px;
            padding: 30px;
            text-align: center;
            color: #666;
            margin: 20px auto;
            max-width: 500px;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }
        .upload-area.dragover {
            background-color: #f0f8ff;
            border-color: #66afe9;
            color: #333;
        }
        /* Модальные окна */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
        .modal {
            background: #fff;
            color: #000;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.4);
            max-width: 400px;
            width: 90%;
            text-align: center;
        }
        .modal p {
            margin-bottom: 20px;
            font-size: 16px;
        }
        .modal button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 0 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
            transition: transform 0.1s ease, box-shadow 0.2s ease;
        }
        .modal .confirm-btn {
            background-color: #4caf50;
            color: #fff;
        }
        .modal .confirm-btn:hover {
            transform: translateY(-2px);
        }
        .modal .cancel-btn {
            background-color: #f44336;
            color: #fff;
        }
        .modal .cancel-btn:hover {
            transform: translateY(-2px);
        }
        /* Toast-уведомления (как ранее) */
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
    </style>
</head>
<body>
<div class="main-container">
    <h2 class="header-title-custom">Управление WireGuard</h2>

    <?php
    // Получаем статус службы WireGuard
    $wireguardStatus = trim(shell_exec('systemctl is-active wg-quick@tun0 2>/dev/null'));
    $statusText = ($wireguardStatus === 'active') ? "Активен" : "Выключен";
    $statusColor = ($wireguardStatus === 'active') ? "lime" : "red";
    ?>
    <div class="status">
        Статус WireGuard:
        <span style="color: <?php echo $statusColor; ?>;"><?php echo $statusText; ?></span>
    </div>

    <!-- Область загрузки файла конфигурации (drag & drop) -->
    <div class="container" style="margin: 20px auto; text-align: center;">
        <form id="uploadForm" method="post" enctype="multipart/form-data" action="/scripts/upload_wireguard.php">
            <div id="uploadArea" class="upload-area">
                Перетащите сюда файл конфигурации (.conf) или кликните для выбора
                <input type="file" id="wireguard_file" name="config_file" accept=".conf" style="display:none;">
            </div>
            <button type="submit" class="green-button" style="margin-top: 10px;">Загрузить конфиг</button>
        </form>
        <!-- Кнопки управления службой WireGuard -->
        <div style="margin-top: 20px;">
            <button id="btnRestartWireguard" class="yellow-button">Перезагрузить службу WireGuard</button>
            <button id="btnDeleteConfig" class="red-button">Удалить конфиг</button>
        </div>
    </div>

    <?php
    // Проверяем наличие конфигурационного файла WireGuard
    $wireguardConfigFile = '/etc/wireguard/tun0.conf';
    if (file_exists($wireguardConfigFile)) {
        $content = file_get_contents($wireguardConfigFile);
        if (preg_match('/Endpoint\s*=\s*([^\s]+)/', $content, $matches)) {
            $endpoint = $matches[1];
            echo "<p style='text-align:center;'>Активный конфиг: tun0.conf (Endpoint: $endpoint)</p>";
        } else {
            echo "<p style='text-align:center;'>Активный конфиг: tun0.conf (Endpoint не найден)</p>";
        }
    } else {
        echo "<p style='text-align:center; color:red;'>Конфиг WireGuard не найден</p>";
    }
    ?>

    <!-- Консоль для статуса службы WireGuard -->
    <div class="console">
        <pre><?php echo shell_exec('systemctl status wg-quick@tun0 --no-pager 2>&1'); ?></pre>
        <!-- Кнопка "Выгрузить логи" (синяя) -->
        <button id="btnExportLogs" class="blue-button" style="position:absolute; top:10px; right:10px;">
            Выгрузить логи
        </button>
    </div>
</div>

<!-- Модальное окно: перезагрузка WireGuard -->
<div id="modalRestart" class="modal-overlay">
    <div class="modal">
        <p>Внимание! Перезагрузка службы WireGuard прервет соединение. Вы уверены?</p>
        <button class="confirm-btn" onclick="confirmRestart()">Да</button>
        <button class="cancel-btn" onclick="hideModalRestart()">Нет</button>
    </div>
</div>

<!-- Модальное окно: удаление конфига -->
<div id="modalDelete" class="modal-overlay">
    <div class="modal">
        <p>Вы действительно хотите удалить конфигурационный файл WireGuard и остановить службу?</p>
        <button class="confirm-btn" onclick="confirmDelete()">Да</button>
        <button class="cancel-btn" onclick="hideModalDelete()">Нет</button>
    </div>
</div>

<!-- Контейнер для toast-уведомлений -->
<div class="toast-container" id="toast-container"></div>

<script>
// Drag-and-drop
window.selectedFile = null;
const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('wireguard_file');

uploadArea.addEventListener('click', () => {
    fileInput.click();
});

fileInput.addEventListener('change', () => {
    if (fileInput.files.length) {
        window.selectedFile = fileInput.files[0];
        uploadArea.innerText = "Файл выбран: " + window.selectedFile.name;
    }
});

uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
});

uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    if(e.dataTransfer.files.length) {
        window.selectedFile = e.dataTransfer.files[0];
        uploadArea.innerText = "Файл выбран: " + window.selectedFile.name;
    }
});

// Перехват отправки формы и отправка через fetch
document.getElementById('uploadForm').addEventListener('submit', (e) => {
    e.preventDefault();

    if (!window.selectedFile) {
        alert("Файл не выбран!");
        return;
    }

    // Проверка расширения файла (ожидается .conf)
    const ext = window.selectedFile.name.split('.').pop().toLowerCase();
    if(ext !== 'conf') {
        alert("Ошибка: Загрузите файл с расширением .conf");
        return;
    }

    const formData = new FormData();
    formData.append('config_file', window.selectedFile);

    fetch('/scripts/upload_wireguard.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(result => {
         // Если получен полный HTML (редирект), считаем, что загрузка успешна
         if(result.includes("<!DOCTYPE html>")) {
            showToast("Конфиг успешно загружен!", "success");
            setTimeout(() => { location.reload(); }, 1500);
         } else if(result.includes("Ошибка")) {
            showToast(result, "error");
         } else {
            showToast("Конфиг успешно загружен!", "success");
            setTimeout(() => { location.reload(); }, 1500);
         }
    })
    .catch(error => {
         showToast("Ошибка: " + error, "error");
    });
});

// Модальное окно: перезагрузка
const modalRestart = document.getElementById('modalRestart');
function showModalRestart() {
    modalRestart.style.display = 'flex';
}
function hideModalRestart() {
    modalRestart.style.display = 'none';
}
function confirmRestart() {
    hideModalRestart();
    fetch('/scripts/restart_wireguard.php', { method: 'POST' })
        .then(response => response.text())
        .then(result => {
            showToast(result, "success");
            setTimeout(() => { location.reload(); }, 1500);
        })
        .catch(error => {
            showToast("Ошибка при перезагрузке: " + error, "error");
        });
}
document.getElementById('btnRestartWireguard').addEventListener('click', showModalRestart);

// Модальное окно: удаление
const modalDelete = document.getElementById('modalDelete');
function showModalDelete() {
    modalDelete.style.display = 'flex';
}
function hideModalDelete() {
    modalDelete.style.display = 'none';
}
function confirmDelete() {
    hideModalDelete();
    fetch('/scripts/delete_wireguard.php', { method: 'POST' })
        .then(response => response.text())
        .then(result => {
            showToast(result, "success");
            setTimeout(() => { location.reload(); }, 1500);
        })
        .catch(error => {
            showToast("Ошибка при удалении: " + error, "error");
        });
}
document.getElementById('btnDeleteConfig').addEventListener('click', showModalDelete);

// Кнопка "Выгрузить логи"
document.getElementById('btnExportLogs').addEventListener('click', function() {
    window.location.href = '/scripts/export_wireguard_logs.php';
});

// Toast-уведомления
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.classList.add('toast-message', type);
    
    const textSpan = document.createElement('span');
    textSpan.innerText = message;
    
    const closeBtn = document.createElement('button');
    closeBtn.classList.add('close-btn');
    closeBtn.innerHTML = '×';
    closeBtn.onclick = () => {
         if (toast.parentNode) {
             toast.parentNode.removeChild(toast);
         }
    };
    
    toast.appendChild(textSpan);
    toast.appendChild(closeBtn);
    container.appendChild(toast);
    
    setTimeout(() => {
         if (toast.parentNode) {
             toast.parentNode.removeChild(toast);
         }
    }, 5000);
}
</script>
</body>
</html>
