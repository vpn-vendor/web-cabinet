#!/usr/bin/env python3
import logging
import sys

from modules.utils import load_config
from modules.metrics.cpu_monitor import check_cpu_usage
from modules.metrics.memory_monitor import check_memory_usage
from modules.metrics.disk_monitor import check_disk_usage
from modules.metrics.network_monitor import check_network

# Импортируем send_warning_notification, чтобы отправлять "warning" тип
from modules.notifications.warning_notifications import send_warning_notification
# Импортируем login_manager чтобы получить список авторизованных
from modules.security.login_manager import load_bot_config, unify_users_format, parse_user_item

from telegram import Bot

logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO,
    filename='/var/log/telegram_bot.log',
    filemode='a'
)
logger = logging.getLogger(__name__)

def main():
    config = load_config()

    cpu_threshold = 80
    mem_threshold = 80
    disk_threshold = 90
    latency_threshold = 200
    loss_threshold = 5

    # Проверяем метрики
    cpu_res = check_cpu_usage(cpu_threshold)
    mem_res = check_memory_usage(mem_threshold)
    disk_res = check_disk_usage(disk_threshold)
    net_res = check_network(latency_threshold, loss_threshold)

    # Собираем все "alert=True"
    alerts = []
    if cpu_res["alert"]:
        alerts.append(cpu_res["message"])
    if mem_res["alert"]:
        alerts.append(mem_res["message"])
    if disk_res["alert"]:
        alerts.append(disk_res["message"])
    if net_res["alert"]:
        alerts.append(net_res["message"])

    if alerts:
        text = "⚠️ Обнаружены проблемы (cron):\n" + "\n".join(alerts)
        logger.info(f"Отправляем предупреждение через warning_notifications: {text}")

        # Получаем список авторизованных
        cfg = load_bot_config()
        unify_users_format(cfg)
        token = cfg.get("api_key")
        if not token:
            logger.error("Нет API ключа, не можем отправить уведомления.")
            return

        bot = Bot(token=token)
        auth_list = cfg.get("authorized_users", [])

        # Шлём каждому авторизованному
        for user_str in auth_list:
            chat_id = parse_user_item(user_str)
            try:
                bot.send_message(chat_id=chat_id, text=text)
            except Exception as e:
                logger.warning(f"Не удалось отправить сообщение пользователю {user_str}: {e}")
    else:
        logger.info("Все метрики в норме.")

if __name__ == '__main__':
    main()
    sys.exit(0)
