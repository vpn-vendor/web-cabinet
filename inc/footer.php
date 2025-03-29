<!-- /var/www/html/inc/footer.php -->
<footer>
    <div class="footer-content">
        <!-- Левая колонка (если нужно) -->
        <div class="footer-left">
            <!-- Пока пусто -->
        </div>

        <!-- Центральная колонка -->
        <div class="footer-center">
            © <?php echo date('Y'); ?> VPN Panel v<?php echo SITE_VERSION; ?>
            &nbsp;|&nbsp;
            <a href="https://t.me/vpn_vendor" target="_blank" class="footer-link">
                Поддержка в Telegram
            </a>
        </div>

        <?php if ($page !== 'network'): ?>
        <!-- Правая колонка (виджет скорости) -->
        <div class="footer-right">
            <div id="network-speed-footer" style="margin-top:10px; text-align: right; font-size: 14px; background: rgba(255,255,255,0.1); padding: 5px 10px; border-radius: 4px; display: inline-block;">
                <label for="ifaceSelect" style="margin-right:5px;">Интерфейс:</label>
                <select id="ifaceSelect" style="padding:2px 4px; border: 1px solid #ccc; border-radius: 3px;"></select>
                <span id="net-speed-display" style="margin-left:10px; font-weight: bold;">Скорость: -</span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</footer>

<script>
// Функции для работы с cookies
function setCookie(name, value, days) {
  let expires = "";
  if (days) {
    const date = new Date();
    date.setTime(date.getTime() + days*24*60*60*1000);
    expires = "; expires=" + date.toUTCString();
  }
  document.cookie = name + "=" + (value || "")  + expires + "; path=/";
}

function getCookie(name) {
  const nameEQ = name + "=";
  const ca = document.cookie.split(';');
  for(let i=0;i < ca.length;i++) {
    let c = ca[i];
    while (c.charAt(0)==' ') c = c.substring(1,c.length);
    if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length,c.length);
  }
  return null;
}

// При загрузке страницы устанавливаем сохранённое значение (если есть)
document.addEventListener("DOMContentLoaded", function() {
    const selectElem = document.getElementById('ifaceSelect');
    const savedIface = getCookie('selectedIface');
    if (selectElem && savedIface) {
        Array.from(selectElem.options).forEach(option => {
            if (option.value === savedIface) {
                selectElem.value = savedIface;
            }
        });
    }
    if (selectElem) {
        selectElem.addEventListener('change', function() {
            setCookie('selectedIface', selectElem.value, 30);
        });
    }
});

// Глобальные переменные для хранения предыдущих данных
let prevData = null;
let prevTimestamp = null;

// Функция конвертации скорости (байт/с) в удобный формат
function convertSpeed(bytesPerSec) {
    if (bytesPerSec < 1024) {
        return bytesPerSec + " B/s";
    } else if (bytesPerSec < 1024 * 1024) {
        return (bytesPerSec / 1024).toFixed(2) + " KB/s";
    } else if (bytesPerSec < 1024 * 1024 * 1024) {
        return (bytesPerSec / (1024 * 1024)).toFixed(2) + " MB/s";
    } else {
        return (bytesPerSec / (1024 * 1024 * 1024)).toFixed(2) + " GB/s";
    }
}

function updateNetworkSpeed() {
    fetch('/scripts/ajax_network.php')
        .then(response => response.json())
        .then(data => {
            if (!data.interfaces) {
                document.getElementById('net-speed-display').innerHTML = "Скорость: -";
                return;
            }
            
            const selectElem = document.getElementById('ifaceSelect');
            if (selectElem && selectElem.options.length === 0) {
                Object.keys(data.interfaces).forEach(function(iface) {
                    // Автоматически исключаем loopback, хотя сервер уже его исключил
                    if (iface.toLowerCase() === "lo") return;
                    let option = document.createElement('option');
                    option.value = iface;
                    option.text = iface;
                    selectElem.appendChild(option);
                });
                // Если ранее был сохранённый выбор, установим его
                const saved = getCookie('selectedIface');
                if (saved) {
                    selectElem.value = saved;
                }
            }
            
            let selectedIface = selectElem ? (selectElem.value || (selectElem.options[0] ? selectElem.options[0].value : null)) : null;
            
            if (selectedIface && data.interfaces[selectedIface]) {
                let currentData = data.interfaces[selectedIface];
                let currentTimestamp = data.timestamp;
                let speedIn = 0, speedOut = 0;
                if (prevData && prevData[selectedIface] && prevTimestamp !== null) {
                    let deltaTime = currentTimestamp - prevTimestamp;
                    if (deltaTime > 0) {
                        speedIn = (currentData.rx_bytes - prevData[selectedIface].rx_bytes) / deltaTime;
                        speedOut = (currentData.tx_bytes - prevData[selectedIface].tx_bytes) / deltaTime;
                    }
                }
                prevData = data.interfaces;
                prevTimestamp = currentTimestamp;
                
                let formattedIn = convertSpeed(Math.max(0, Math.round(speedIn)));
                let formattedOut = convertSpeed(Math.max(0, Math.round(speedOut)));
                const arrowUp = "↑";
                const arrowDown = "↓";
                let displayHTML =
                    '<span style="color: #4caf50; margin-right:10px;" title="Входящий трафик (расчёт): ' + formattedIn + '">' +
                    formattedIn + " " + arrowUp + '</span>' +
                    '<span style="color: #f44336;" title="Исходящий трафик (расчёт): ' + formattedOut + '">' +
                    formattedOut + " " + arrowDown + '</span>';
                document.getElementById('net-speed-display').innerHTML = displayHTML;
            } else {
                document.getElementById('net-speed-display').innerHTML = "Скорость: -";
            }
        })
        .catch(error => {
            console.error('Ошибка получения данных скорости:', error);
            document.getElementById('net-speed-display').innerHTML = "Скорость: Нет данных";
        });
}

setInterval(updateNetworkSpeed, 1000);
updateNetworkSpeed();
</script>