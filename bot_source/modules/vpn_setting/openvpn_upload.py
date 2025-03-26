# modules/vpn_setting/openvpn_upload.py

import logging
import os
import time
import subprocess
from telegram import Update
from telegram.ext import ContextTypes

logger = logging.getLogger(__name__)

OPENVPN_PATH = "/etc/openvpn/client1.conf"
OPENVPN_SERVICE = "openvpn@client1.service"
WIREGUARD_SERVICE = "wg-quick@tun0"

async def handle_openvpn_file(update: Update, context: ContextTypes.DEFAULT_TYPE, doc):
    chat_id = update.effective_chat.id
    logger.info("[OpenVPN] Пользователь %s загружает файл: %s", chat_id, doc.file_name)

    tmp_path = f"/tmp/openvpn_{int(time.time())}.ovpn"
    file_info = await doc.get_file()
    try:
        await file_info.download_to_drive(tmp_path)
        logger.info("[OpenVPN] Файл скачан: %s => %s", doc.file_name, tmp_path)
    except Exception as e:
        logger.error("[OpenVPN] Ошибка скачивания: %s", e)
        await update.message.reply_text(f"Ошибка: не удалось скачать файл: {e}")
        return

    # Добавляем рекомендацию проверить IP
    await update.message.reply_text(
        "Новый OpenVPN конфиг загружен. Сейчас перезапустим сервис...\n\n"
        "СОВЕТ: Через 20 секунд проверьте IP, нажав кнопку <Проверить айпи>, чтобы убедиться, что конфигурация принята."
    )

    backup_path = None
    if os.path.isfile(OPENVPN_PATH):
        backup_path = make_backup(OPENVPN_PATH)

    try:
        os.replace(tmp_path, OPENVPN_PATH)
        logger.info("[OpenVPN] Новый конфиг перемещён в %s", OPENVPN_PATH)
    except Exception as e:
        logger.error("[OpenVPN] Ошибка копирования: %s", e)
        if backup_path:
            rollback(backup_path, OPENVPN_PATH)
        await update.message.reply_text(f"Ошибка при копировании файла: {e}")
        return

    try:
        disable_wireguard()
        enable_openvpn()
    except Exception as e:
        logger.error("[OpenVPN] Ошибка при переключении сервисов: %s", e)
        await update.message.reply_text(f"Ошибка при перезапуске сервисов: {e}")
        return

    await update.message.reply_text("OpenVPN конфигурация успешно обновлена и перезапущена.")
    await context.bot.send_message(chat_id=chat_id, text="OpenVPN: всё прошло успешно, сервис перезапущен.")

def make_backup(existing_path: str) -> str:
    bak_path = existing_path + f".bak_{int(time.time())}"
    try:
        os.rename(existing_path, bak_path)
        logger.info("[OpenVPN] Бэкап: %s => %s", existing_path, bak_path)
        return bak_path
    except Exception as e:
        logger.error("[OpenVPN] Не удалось сделать бэкап: %s", e)
        return None

def rollback(backup_path: str, current_path: str):
    logger.warning("[OpenVPN] Откат конфигурации.")
    if os.path.isfile(current_path):
        try:
            os.remove(current_path)
        except Exception as e:
            logger.error("[OpenVPN] Не удалось удалить %s: %s", current_path, e)
    if backup_path and os.path.isfile(backup_path):
        os.rename(backup_path, current_path)
        logger.info("[OpenVPN] Бэкап восстановлен: %s => %s", backup_path, current_path)

def disable_wireguard():
    logger.info("[OpenVPN] Останавливаем WireGuard.")
    run_cmd(["sudo", "systemctl", "stop", WIREGUARD_SERVICE])
    run_cmd(["sudo", "systemctl", "disable", WIREGUARD_SERVICE])

def enable_openvpn():
    logger.info("[OpenVPN] Включаем openvpn@client1.service")
    run_cmd(["sudo", "systemctl", "enable", OPENVPN_SERVICE])
    run_cmd(["sudo", "systemctl", "restart", OPENVPN_SERVICE])

def run_cmd(cmd_list):
    result = subprocess.run(cmd_list, capture_output=True, text=True)
    if result.returncode != 0:
        err = result.stderr.strip() or result.stdout.strip()
        raise Exception(f"Команда {cmd_list} вернула ошибку: {err}")
    return True, result.stdout.strip()
