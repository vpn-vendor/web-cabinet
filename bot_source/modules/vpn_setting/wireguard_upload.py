import logging
import os
import time
import subprocess
from telegram import Update
from telegram.ext import ContextTypes

logger = logging.getLogger(__name__)

WIREGUARD_PATH = "/etc/wireguard/tun0.conf"
WIREGUARD_SERVICE = "wg-quick@tun0"
OPENVPN_SERVICE = "openvpn@client1.service"

async def handle_wireguard_file(update: Update, context: ContextTypes.DEFAULT_TYPE, doc):
    chat_id = update.effective_chat.id
    logger.info("[WireGuard] Пользователь %s загружает файл: %s", chat_id, doc.file_name)

    tmp_path = f"/tmp/wg_{int(time.time())}.conf"
    file_info = await doc.get_file()
    try:
        await file_info.download_to_drive(tmp_path)
        logger.info("[WireGuard] Файл скачан: %s => %s", doc.file_name, tmp_path)
    except Exception as e:
        logger.error("[WireGuard] Ошибка скачивания: %s", e)
        await update.message.reply_text(f"Ошибка: не удалось скачать файл: {e}")
        return

    backup_path = None
    if os.path.isfile(WIREGUARD_PATH):
        backup_path = make_backup(WIREGUARD_PATH)

    try:
        os.replace(tmp_path, WIREGUARD_PATH)
        logger.info("[WireGuard] Новый конфиг помещён в %s", WIREGUARD_PATH)
    except Exception as e:
        logger.error("[WireGuard] Ошибка копирования: %s", e)
        if backup_path:
            rollback(backup_path, WIREGUARD_PATH)
        await update.message.reply_text(f"Ошибка при копировании файла WireGuard: {e}")
        return

    # Отключаем OpenVPN, включаем WireGuard
    try:
        disable_openvpn()
        enable_wireguard()
    except Exception as e:
        logger.error("[WireGuard] Ошибка переключения сервисов: %s", e)
        await update.message.reply_text(f"Ошибка при перезапуске сервисов: {e}")
        return

    # Сообщение об успехе
    await update.message.reply_text("WireGuard конфигурация успешно обновлена и перезапущена.")
    # Дублируем
    await context.bot.send_message(
        chat_id=chat_id,
        text="WireGuard: всё прошло успешно, сервис перезапущен."
    )

def make_backup(existing_path: str) -> str:
    bak_path = existing_path + f".bak_{int(time.time())}"
    try:
        os.rename(existing_path, bak_path)
        logger.info("[WireGuard] Бэкап: %s => %s", existing_path, bak_path)
        return bak_path
    except Exception as e:
        logger.error("[WireGuard] Не удалось сделать бэкап: %s", e)
        return None

def rollback(backup_path: str, current_path: str):
    logger.warning("[WireGuard] Откат конфигурации.")
    if os.path.isfile(current_path):
        try:
            os.remove(current_path)
        except Exception as e:
            logger.error("[WireGuard] Не удалось удалить %s: %s", current_path, e)
    if backup_path and os.path.isfile(backup_path):
        os.rename(backup_path, current_path)
        logger.info("[WireGuard] Восстановлен бэкап: %s => %s", backup_path, current_path)

def disable_openvpn():
    logger.info("[WireGuard] Отключаем openvpn@client1.service")
    run_cmd(["sudo", "systemctl", "stop", OPENVPN_SERVICE])
    run_cmd(["sudo", "systemctl", "disable", OPENVPN_SERVICE])

    old_ovpn = "/etc/openvpn/client1.conf"
    if os.path.isfile(old_ovpn):
        try:
            os.remove(old_ovpn)
            logger.info("[WireGuard] Удалён файл %s", old_ovpn)
        except Exception as e:
            logger.error("[WireGuard] Не удалось удалить старый OVPN: %s", e)

def enable_wireguard():
    logger.info("[WireGuard] Включаем wg-quick@tun0")
    run_cmd(["sudo", "systemctl", "enable", WIREGUARD_SERVICE])
    run_cmd(["sudo", "systemctl", "restart", WIREGUARD_SERVICE])

def run_cmd(cmd_list):
    result = subprocess.run(cmd_list, capture_output=True, text=True)
    if result.returncode != 0:
        err = result.stderr.strip() or result.stdout.strip()
        raise Exception(f"Команда {cmd_list} вернула ошибку: {err}")
    return True, result.stdout.strip()
