# modules/vpn_setting/check_ip.py

import subprocess
import logging
from telegram import Update
from telegram.ext import ContextTypes

logger = logging.getLogger(__name__)

async def on_check_ip_callback(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query = update.callback_query
    await query.answer()

    try:
        result = subprocess.run(["curl", "-4", "ifconfig.me"], capture_output=True, text=True, timeout=5)
        current_ip = result.stdout.strip()
        if not current_ip:
            current_ip = "Не удалось определить IP (пустой ответ)"
    except Exception as e:
        logger.error("Ошибка при получении IP через curl ifconfig.me: %s", e)
        current_ip = f"Ошибка при curl ifconfig.me: {e}"

    last_ip = context.user_data.get("last_ip")
    if last_ip:
        if last_ip == current_ip:
            comparison = f"IP не изменился: {current_ip}"
        else:
            comparison = f"Старый IP: {last_ip}\nНовый IP: {current_ip}\nОбнаружено изменение айпи."
    else:
        comparison = f"Определён текущий IP: {current_ip}"

    # Сохраняем текущее значение как последнее
    context.user_data["last_ip"] = current_ip

    await query.message.reply_text(comparison)
