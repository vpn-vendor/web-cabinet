<!-- inc/upgrade.php: страница перехода на новую панель. Только разметка, без логики. -->
<section class="upg">
    <span class="upg-badge">Новая версия</span>
    <h1>Новая версия: VPN Panel</h1>
    <p class="upg-lead">Этот кабинет и скрипт <code>vpn.sh</code> больше не развиваются.
    На смену пришла панель VPN Panel: ставится на этот же сервер, переносит
    настройки сама и управляется из браузера целиком.</p>

    <div class="upg-links">
        <a class="upg-btn" href="#upg-how">Как перейти</a>
        <a class="upg-btn upg-btn-soft" href="https://github.com/vpn-vendor/vpn-panel#переход-со-старого-скрипта" target="_blank" rel="noopener noreferrer">README панели</a>
    </div>

    <div class="upg-shot">
        <img src="/img/vpn-panel.png" alt="Панель VPN Panel, страница «Обзор»" width="1440" height="579">
    </div>

    <div class="upg-card">
        <h2>Что изменилось</h2>
        <ul>
            <li>Мастер первой настройки вместо меню скрипта: карты, провайдер, адрес офиса и подключение VPN он находит сам.</li>
            <li>Сеть, DHCP, DNS, каналы VPN, защита и диагностика в одном окне.</li>
            <li>Обновления приходят сами и проверяются подписью.</li>
            <li>Ничего не грузит из интернета: работает и когда связи нет.</li>
            <li>Код открыт, лицензия GPL-3.0.</li>
        </ul>
    </div>

    <div class="upg-card" id="upg-how">
        <h2>Как перейти</h2>
        <p>Переход занимает около десяти минут. Старый шлюз работает до самого
        конца, интернет в офисе пропадает только на время проверки связи. Если
        новая сеть не поднимется, сервер сам вернёт старый шлюз.</p>
        <ol>
            <li>На сервере наберите две строки, одну за другой:
                <code class="upg-cmd">wget https://vpn-vendor.github.io/vpn-panel/install.sh</code>
                <code class="upg-cmd">sudo bash install.sh</code>
                <span class="upg-note">Команды только ставят пакет, старый шлюз продолжает работать.</span>
            </li>
            <li>Наберите <code>sudo vpn-panel-code</code>. Команда покажет адрес и код входа.
                Откройте адрес в браузере с любого компьютера офиса.</li>
            <li>Мастер первой настройки покажет, что нашёл у старого шлюза и что
                выключит. Проверьте и нажмите «Применить и проверить».</li>
        </ol>
        <p class="upg-more">Подробно, с картинками:
            <a href="https://github.com/vpn-vendor/vpn-panel#переход-со-старого-скрипта" target="_blank" rel="noopener noreferrer">README панели, раздел «Переход со старого скрипта»</a>.</p>
    </div>

    <div class="upg-card">
        <h2>Если сервер можно выключить на час</h2>
        <p class="upg-last">Лучше поставить Ubuntu 26.04 Desktop LTS начисто с флешки панели:
        получите шифрование диска и рабочий стол с панелью на самом шлюзе.
        Как собрать флешку:
        <a href="https://github.com/vpn-vendor/vpn-panel#способ-3-установка-с-флешки-начисто" target="_blank" rel="noopener noreferrer">README панели, «Способ 3»</a>.</p>
    </div>

    <div class="upg-warn">
        <strong>После перехода скрипт <code>vpn.sh</code> не запускайте:</strong>
        его пункт «Удалить всё» снесёт пакеты вместе с панелью и SSH.
    </div>
</section>
