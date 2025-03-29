<?php
// inc/network.php - вывод метрик с динамическим обновлением через AJAX polling
?>
<div class="container" style="text-align:center; margin: 0 auto; max-width: 1000px;">
  <h1>Сеть</h1>
  <p>Обновлено: <span id="updateTime">Загрузка...</span></p>

  <!-- Селектор сетевого интерфейса -->
  <div style="margin: 10px auto; text-align: center;">
    <label for="ifaceSelect">Выберите сетевой интерфейс:</label>
    <select id="ifaceSelect"></select>
  </div>

  <!-- Кнопка для перезагрузки служб сбора статистики -->
  <div style="text-align:center; margin: 20px 0;">
    <button id="restartServicesButton" class="refresh-button">
      Перезагрузить службы сбора статистики
    </button>
  </div>

  <!-- Блок для отображения результата перезагрузки (модальное окно) -->
  <div id="restartResult" style="display:none; position:fixed; top:20%; left:50%; transform:translateX(-50%); width:80%; max-width:600px; background:#fff; color:#000; border:2px solid #4caf50; border-radius:8px; padding:20px; z-index:1000;">
    <h3 style="margin-top:0;">Результат перезагрузки</h3>
    <pre id="restartLog" style="max-height:300px; overflow:auto; background:#eee; padding:10px; border-radius:4px;"></pre>
    <div style="text-align:right; margin-top:10px;">
      <button onclick="closeRestartResult();" style="padding:5px 15px; font-size:14px;">Закрыть</button>
    </div>
  </div>

  <!-- CSS для кнопки (можно вынести в отдельный CSS файл) -->
  <style>
    #restartServicesButton {
      background-color: #4caf50;
      color: #000;
      font-size: 16px;
      padding: 10px 20px;
      border: none;
      border-radius: 5px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.3);
      cursor: pointer;
      transition: background-color 0.3s ease, box-shadow 0.3s ease;
    }
    #restartServicesButton:hover {
      background-color: #45a049;
      box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    }
    #restartServicesButton:disabled {
      background-color: #aaa;
      box-shadow: none;
      cursor: default;
    }
  </style>

  <script>
    const restartButton = document.getElementById("restartServicesButton");
    const restartResult = document.getElementById("restartResult");
    const restartLog = document.getElementById("restartLog");

    restartButton.addEventListener("click", function() {
      restartButton.disabled = true;
      restartButton.style.backgroundColor = "#aaa";
      restartButton.textContent = "Перезагрузка...";

      fetch("/api/restart_services.php")
        .then(response => {
          if (!response.ok) {
            throw new Error("Ошибка " + response.status);
          }
          return response.json();
        })
        .then(data => {
          let logText = "";
          if (data.status === "success") {
            data.results.forEach(result => {
              logText += `Команда: ${result.command}\nВывод: ${result.output}\n\n`;
            });
          } else {
            logText = "Ошибка при перезагрузке служб: " + (data.message || "");
          }
          restartLog.textContent = logText;
          restartResult.style.display = "block";
        })
        .catch(error => {
          restartLog.textContent = "Ошибка: " + error;
          restartResult.style.display = "block";
        });
    });

    function closeRestartResult() {
      restartResult.style.display = "none";
      restartButton.disabled = false;
      restartButton.style.backgroundColor = "#4caf50";
      restartButton.textContent = "Перезагрузить службы сбора статистики";
    }
  </script>

  <!-- График нагрузки сети (данные из network_load.json, за 1 час) -->
  <h2>График нагрузки сети (за 1 час)</h2>
  <canvas id="networkLoadChart" style="max-width: 800px; height: 400px; margin: 0 auto;"></canvas>

  <!-- График загрузки CPU -->
  <h2>График загрузки CPU</h2>
  <canvas id="cpuChart" style="max-width: 800px; height: 400px; margin: 0 auto;"></canvas>

  <h2>Тест скорости интернета</h2>
  <div style="font-size: 0.8em; color: #ccc; margin-bottom: 10px;">
    Что такое Jitter?
    <span style="cursor: help; color: #4caf50; font-weight: bold; border: 1px solid #4caf50; border-radius: 50%; padding: 2px 5px; margin-left: 5px;" title="Jitter – это показатель нестабильности задержки пакетов в сети. Он измеряет, насколько варьируется время, за которое пакеты достигают получателя. Чем ниже значение Jitter, тем стабильнее соединение. Например, при пинге 60-70 мс приемлемым считается Jitter ниже 5 мс.">?</span>
  </div>
  <iframe src="/speedtest/" width="900" height="380" frameborder="0" style="margin: 0 auto; display: block;"></iframe>

  <!-- Таблица сетевых I/O метрик -->
  <h2>Сетевые I/O метрики</h2>
  <table id="networkTable" border="1" cellpadding="5" cellspacing="0" style="width:100%; margin: 0 auto; border-collapse: collapse;">
    <thead>
      <tr style="background: #444; color:#fff;">
        <th>Интерфейс</th>
        <th>Передано байт</th>
        <th>Принято байт</th>
        <th>Пакетов отправлено</th>
        <th>Пакетов получено</th>
        <th title="Ошибки (входящие): количество ошибок при получении пакетов. Нормальное значение – 0.">Ошибки (входящие)</th>
        <th title="Ошибки (исходящие): количество ошибок при отправке пакетов. Нормальное значение – 0.">Ошибки (исходящие)</th>
        <th title="Пакетов дропнуто (входящие). Зеленый: 0, красный: >1%">Пакетов дропнуто (входящие)</th>
        <th title="Пакетов дропнуто (исходящие). Зеленый: 0, красный: >1%">Пакетов дропнуто (исходящие)</th>
      </tr>
    </thead>
    <tbody>
      <!-- Данные будут заполнены динамически -->
    </tbody>
  </table>

  <!-- Таблица статуса сетевых интерфейсов -->
  <h2>Статус сетевых интерфейсов</h2>
  <table id="networkStatusTable" border="1" cellpadding="5" cellspacing="0" style="width:60%; margin: 0 auto; border-collapse: collapse;">
    <thead>
      <tr style="background: #444; color:#fff;">
        <th>Интерфейс</th>
        <th>Состояние</th>
        <th>Пропускная способность (Мбит/с)</th>
        <th>MTU</th>
      </tr>
    </thead>
    <tbody>
      <!-- Данные будут заполнены динамически -->
    </tbody>
  </table>

  <!-- Раздел для отображения локальной сети с ограничением по количеству устройств -->
  <h2>Локальная сеть</h2>
  <p>
    Количество устройств:
    <span id="localNetworkCount" title="Это приблизительное число. Фактическое количество может варьироваться из-за особенностей сканирования, временных задержек и особенностей сети.">0</span>
  </p>
  <!-- Поле для фильтрации -->
  <div style="text-align:center; margin: 10px 0;">
    <label for="localFilterInput" style="font-size: 0.9em; color: #ccc;">Поиск по IP, MAC, Производитель:</label>
    <input type="text" id="localFilterInput" style="padding:5px; font-size: 0.9em;">
    <p style="font-size: 0.8em; color: #ccc; margin-top: 5px;">Нажми на "Показать все", чтобы фильтровать по столбикам.</p>
  </div>
  <div style="text-align:center; margin-top:10px;">
    <button id="forceScanButton" style="padding: 5px 10px; font-size: 14px;">Запустить сканирование локальной сети</button>
  </div>
  <!-- Таблица локальной сети (видимые устройства) -->
  <div style="width:80%; margin: 0 auto; border: 1px solid #444; border-collapse: collapse; position: relative;">
    <table id="localNetworkTable" border="1" cellpadding="5" cellspacing="0" style="width:100%; border-collapse: collapse;">
      <thead>
        <tr style="background: #444; color: #fff;">
          <th>Устройство</th>
          <th>IP-адрес</th>
          <th>MAC-адрес</th>
          <th>Производитель</th>
        </tr>
      </thead>
      <tbody>
        <!-- Данные заполняются JavaScript -->
      </tbody>
    </table>
    <div id="showAllContainer" style="margin-top: 10px; text-align: right; display: none;">
      <button id="showAllButton" style="padding: 5px 10px; font-size: 14px;">Показать все</button>
    </div>
  </div>
  <!-- Модальное окно для полного списка устройств -->
  <div id="fullLocalDevicesModal" style="display: none; position: fixed; top: 10%; left: 50%; transform: translateX(-50%); width: 80%; max-width: 800px; max-height: 80vh; background: #fff; color: #000; border: 2px solid #4caf50; border-radius: 8px; padding: 20px; overflow-y: auto; z-index: 1000;">
    <h3 style="margin-top:0;">Полный список локальных устройств</h3>
    <table id="fullLocalNetworkTable" border="1" cellpadding="5" cellspacing="0" style="width:100%; border-collapse: collapse;">
      <thead>
        <tr style="background: #444; color: #fff;">
          <th>Устройство</th>
          <th>IP-адрес</th>
          <th>MAC-адрес</th>
          <th>Производитель</th>
        </tr>
      </thead>
      <tbody>
        <!-- Данные заполняются JavaScript -->
      </tbody>
    </table>
    <div style="text-align: right; margin-top: 10px;">
      <button onclick="document.getElementById('fullLocalDevicesModal').style.display='none';" style="padding: 5px 10px; font-size: 14px;">Закрыть</button>
    </div>
  </div>

<!-- Подключаем Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Подключаем наш файл с дополнительным кодом для работы с локальной сетью -->
<script src="/js/local_network.js"></script>

<!-- Остальной JS код для обновления метрик и графиков -->
<script>
/* Функция для форматирования байтов с сокращением и подсказкой */
function formatBytesValue(bytes) {
    let display, unit;
    if (bytes >= 1e12) {
        display = (bytes / 1e12).toFixed(2);
        unit = "ТБ";
    } else if (bytes >= 1e9) {
        display = (bytes / 1e9).toFixed(2);
        unit = "ГБ";
    } else if (bytes >= 1e6) {
        display = (bytes / 1e6).toFixed(2);
        unit = "МБ";
    } else {
        display = bytes;
        unit = "B";
    }
    return `<span title="${bytes} B">${display} ${unit}</span>`;
}

/* Функция для форматирования количества пакетов с сокращением и подсказкой */
function formatPacketsValue(value) {
    let short;
    if (value >= 1e9) {
        short = (value / 1e9).toFixed(2) + "B";
    } else if (value >= 1e6) {
        short = (value / 1e6).toFixed(2) + "M";
    } else if (value >= 1e3) {
        short = (value / 1e3).toFixed(2) + "K";
    } else {
        short = value;
    }
    return `<span title="${value}">${short}</span>`;
}

function formatError(value, total, label) {
    let percent = total > 0 ? (value / total * 100) : 0;
    let color = "green";
    if (value > 0) {
        color = (percent > 1) ? "red" : "orange";
    }
    let tooltip = `${label}: ${value}. Это ${percent.toFixed(2)}% от общего количества.`;
    return `<span style="white-space: nowrap; color:${color};" title="${tooltip}">${value}</span>`;
}

function formatPacketError(value, total, label, direction) {
    let percent = total > 0 ? (value / total * 100) : 0;
    let color = "green";
    if (value > 0) {
        color = (percent > 1) ? "red" : "orange";
    }
    let arrow = direction === "in" ? "↓" : (direction === "out" ? "↑" : "");
    let tooltip = `${label}: ${value}${arrow}. Это ${percent.toFixed(2)}% от общего количества. Обычно нормальное значение – 0.`;
    return `<span style="white-space: nowrap; color:${color};" title="${tooltip}">${value}${arrow}</span>`;
}

function formatSpeed(speed) {
    let color = "green";
    let tooltip = "Хороший показатель. Данная сетевая карта/материнская плата идеально подходит для больших нагрузок.";
    if (speed <= 10) {
        color = "red";
        tooltip = "ВНИМАНИЕ! Низкая пропускная способность, следует сменить сетевую карту или материнскую плату на более быструю.";
    } else if (speed <= 100) {
        color = "orange";
        tooltip = "Среднее значение. Стоит подумать над обновлением сетевой карты/материнской платы. Пропускной способности достаточно для примерно 20-30 компьютеров в локальной сети.";
    }
    return `<span style="white-space: nowrap; color:${color};" title="${tooltip}">${speed}</span>`;
}

/* Функция для определения иконки устройства по строке производителя */
function getDeviceIcon(vendor) {
    var vendorLower = vendor.toLowerCase();
    if (vendorLower.includes("router") || vendorLower.includes("tplink") || vendorLower.includes("netgear") ||
        vendorLower.includes("d-link") || vendorLower.includes("asus") || vendorLower.includes("ubiquiti")) {
        return '<img src="/img/devices/router.png" alt="Router" style="width:24px; height:24px;">';
    } else if (vendorLower.includes("windows")) {
        return '<img src="/img/devices/windows.png" alt="Windows" style="width:24px; height:24px;">';
    } else if (vendorLower.includes("linux") || vendorLower.includes("ubuntu") || vendorLower.includes("debian") ||
               vendorLower.includes("fedora") || vendorLower.includes("centos")) {
        return '<img src="/img/devices/linux.png" alt="Linux" style="width:24px; height:24px;">';
    } else if (vendorLower === "(unknown)" || vendorLower.trim() === "") {
        return '<img src="/img/devices/unknown.png" alt="Unknown" style="width:24px; height:24px;">';
    } else {
        return '<img src="/img/devices/computer.png" alt="Computer" style="width:24px; height:24px;">';
    }
}

/* Функция для обновления локальной сети через AJAX */
function fetchLocalNetwork() {
    fetch('/data/local_network.json')
    .then(response => response.json())
    .then(data => {
        const tbody = document.getElementById('localNetworkTable').querySelector('tbody');
        tbody.innerHTML = "";
        if (data.devices && data.devices.length > 0) {
            data.devices.forEach(function(device) {
                const icon = getDeviceIcon(device.vendor);
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${icon}</td>
                    <td>${device.ip}</td>
                    <td>${device.mac}</td>
                    <td>${device.vendor}</td>
                `;
                tbody.appendChild(tr);
            });
            document.getElementById('localNetworkCount').textContent = data.devices.length;
        } else {
            tbody.innerHTML = "<tr><td colspan='4'>Нет данных</td></tr>";
            document.getElementById('localNetworkCount').textContent = "0";
        }
    })
    .catch(error => console.error("Ошибка при получении данных локальной сети:", error));
}

/* Функция для обновления системных метрик через AJAX */
function fetchMetrics() {
    fetch('/data/system_metrics.json')
    .then(response => response.json())
    .then(data => {
        if (data.timestamp) {
            const currentTime = new Date(data.timestamp * 1000).toLocaleTimeString();
            document.getElementById('updateTime').textContent = currentTime;
        }
        if (data.cpu) {
            const currentTime = new Date(data.timestamp * 1000).toLocaleTimeString();
            cpuChart.data.labels.push(currentTime);
            cpuChart.data.datasets[0].data.push(data.cpu.cpu_percent);
            // Ограничиваем историю до 1 часа (180 точек при 2-секундном интервале)
            if (cpuChart.data.labels.length > 180) {
                cpuChart.data.labels.shift();
                cpuChart.data.datasets[0].data.shift();
            }
            cpuChart.update();
        }
        if (data.network_io) {
            const tbody = document.querySelector('#networkTable tbody');
            tbody.innerHTML = "";
            Object.keys(data.network_io).forEach(function(iface) {
                const counters = data.network_io[iface];
                const bytesSent = `<span style="white-space: nowrap;" title="${counters.bytes_sent} B">${formatBytesValue(counters.bytes_sent)} ↑</span>`;
                const bytesRecv = `<span style="white-space: nowrap;" title="${counters.bytes_recv} B">${formatBytesValue(counters.bytes_recv)} ↓</span>`;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${iface}</td>
                    <td>${bytesSent}</td>
                    <td>${bytesRecv}</td>
                    <td>${formatPacketsValue(counters.packets_sent)}</td>
                    <td>${formatPacketsValue(counters.packets_recv)}</td>
                    <td>${formatPacketError(counters.errin, counters.packets_recv, "Ошибки (входящие)", "in")}</td>
                    <td>${formatPacketError(counters.errout, counters.packets_sent, "Ошибки (исходящие)", "out")}</td>
                    <td>${formatError(counters.dropin, counters.packets_recv, "Дроп входящих")}</td>
                    <td>${formatError(counters.dropout, counters.packets_sent, "Дроп исходящих")}</td>
                `;
                tbody.appendChild(tr);
            });
        }
        if (data.network_status) {
            const tbody = document.querySelector('#networkStatusTable tbody');
            tbody.innerHTML = "";
            Object.keys(data.network_status).forEach(function(iface) {
                const status = data.network_status[iface];
                let stateText = status.isup ? "Работает" : "Не работает";
                let stateColor = status.isup ? "green" : "red";
                let speedFormatted = "";
                if (iface === "lo") {
                    speedFormatted = `<span style="white-space: nowrap; color:green;" title="Локальная петля (lo) всегда имеет скорость 0 – это нормально">0</span>`;
                } else {
                    speedFormatted = formatSpeed(status.speed);
                }
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${iface}</td>
                    <td><span style="white-space: nowrap; color:${stateColor};" title="Состояние: ${stateText}">${stateText}</span></td>
                    <td>${speedFormatted}</td>
                    <td>${status.mtu}</td>
                `;
                tbody.appendChild(tr);
            });
        }
    })
    .catch(error => console.error("Ошибка при получении метрик:", error));
}

// ====================
// Раздел: График нагрузки сети (данные из network_load.json)
// Накопление истории за 1 час и отображение в мегабайтах (MB/s)
var networkHistory = []; // Каждый элемент: { timestamp, speed_sent, speed_recv }
function fetchNetworkLoad() {
    fetch('/data/network_load.json')
    .then(response => response.json())
    .then(data => {
        if (data.timestamp && data.network_speed) {
            var iface = selectedIface; // используем выбранный интерфейс
            if (data.network_speed[iface]) {
                var snapshot = {
                    timestamp: data.timestamp,
                    speed_sent: data.network_speed[iface].speed_sent,
                    speed_recv: data.network_speed[iface].speed_recv
                };
                networkHistory.push(snapshot);
                var cutoff = data.timestamp - 3600; // последние 1 час
                networkHistory = networkHistory.filter(function(point) {
                    return point.timestamp >= cutoff;
                });
                updateNetworkLoadChart();
            }
        }
    })
    .catch(error => console.error("Ошибка при получении данных network_load:", error));
}

function updateNetworkLoadChart() {
    var labels = [];
    var incomingData = [];
    var outgoingData = [];
    networkHistory.forEach(function(point) {
        labels.push(new Date(point.timestamp * 1000).toLocaleTimeString());
        incomingData.push(point.speed_recv / 1048576); // перевод в MB/s
        outgoingData.push(point.speed_sent / 1048576);
    });
    networkLoadChart.data.labels = labels;
    networkLoadChart.data.datasets[0].data = incomingData;
    networkLoadChart.data.datasets[1].data = outgoingData;
    networkLoadChart.update();
}

// Инициализация графика нагрузки сети (Chart.js)
var ctxLoad = document.getElementById('networkLoadChart').getContext('2d');
var networkLoadChart = new Chart(ctxLoad, {
    type: 'line',
    data: {
        labels: [],
        datasets: [
            {
                label: 'Входящий трафик (MB/s)',
                data: [],
                borderColor: 'rgba(255, 159, 64, 1)',
                fill: false,
                tension: 0.1
            },
            {
                label: 'Исходящий трафик (MB/s)',
                data: [],
                borderColor: 'rgba(153, 102, 255, 1)',
                fill: false,
                tension: 0.1
            }
        ]
    },
    options: {
        animation: false,
        scales: {
            x: { title: { display: true, text: 'Время' } },
            y: { title: { display: true, text: 'Скорость (MB/s)' } }
        }
    }
});

// Обновляем график нагрузки сети каждые 2 секунды
setInterval(fetchNetworkLoad, 2000);

// Глобальная переменная для выбранного интерфейса (по умолчанию пустая)
var selectedIface = "";

// Функция заполнения выпадающего списка интерфейсов
function populateIfaceSelect() {
    fetch('/data/system_metrics.json')
    .then(response => response.json())
    .then(data => {
        var select = document.getElementById('ifaceSelect');
        select.innerHTML = "";
        if (data.network_status) {
            Object.keys(data.network_status).forEach(function(iface) {
                if (iface.toLowerCase() === "lo") return;
                var option = document.createElement("option");
                option.value = iface;
                option.text = iface;
                select.appendChild(option);
            });
            if (select.options.length > 0) {
                selectedIface = select.options[0].value;
            }
        }
    })
    .catch(error => console.error("Ошибка при получении интерфейсов:", error));
}

// Обработчик изменения выбора интерфейса
document.addEventListener('DOMContentLoaded', function() {
    var ifaceSelect = document.getElementById('ifaceSelect');
    ifaceSelect.addEventListener('change', function() {
        selectedIface = this.value;
    });
    populateIfaceSelect();
});

// Инициализация графиков и запуск опроса метрик
document.addEventListener('DOMContentLoaded', function() {
    // Инициализация графика CPU (Chart.js)
    const ctxCpu = document.getElementById('cpuChart').getContext('2d');
    window.cpuChart = new Chart(ctxCpu, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'CPU (%)',
                data: [],
                borderColor: 'rgba(75, 192, 192, 1)',
                fill: false,
                tension: 0.1
            }]
        },
        options: {
            animation: false,
            scales: {
                x: { title: { display: true, text: 'Время' } },
                y: { title: { display: true, text: 'Загрузка (%)' }, min: 0, max: 100 }
            }
        }
    });

    // Запускаем опрос системных метрик каждые 2 секунды
    setInterval(fetchMetrics, 2000);
    fetchMetrics();
});
</script>
</div>