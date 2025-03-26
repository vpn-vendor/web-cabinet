import json
import logging
import os
from telegram import Bot

logger = logging.getLogger(__name__)

# Путь к конфигурационному файлу
CONFIG_PATH = '/var/www/html/data/telegram_bot_config.json'

def load_config():
    try:
        with open(CONFIG_PATH, 'r') as f:
            return json.load(f)
    except Exception as e:
        logger.error(f"Ошибка загрузки конфигурации: {e}")
        return {}

def save_config(config):
    try:
        with open(CONFIG_PATH, 'w') as f:
            json.dump(config, f, indent=4)
    except Exception as e:
        logger.error(f"Ошибка сохранения конфигурации: {e}")

def load_latest_record(file_path):
    try:
        with open(file_path, 'r') as f:
            data = json.load(f)
        if isinstance(data, list) and data:
            return data[-1]
        return data
    except Exception as e:
        logger.error(f"Ошибка загрузки файла {file_path}: {e}")
        return {}

def send_message_to_all(text: str):
    cfg = load_config()
    token = cfg.get('api_key')
    if not token:
        logger.error("send_message_to_all: Нет API ключа в конфигурации.")
        return

    bot = Bot(token=token)
    authorized_users = cfg.get('authorized_users', [])
    if not authorized_users:
        logger.info("Нет авторизованных пользователей для рассылки.")
        return

    for user_id in authorized_users:
        try:
            bot.send_message(chat_id=user_id, text=text)
        except Exception as e:
            logger.warning(f"Не удалось отправить сообщение пользователю {user_id}: {e}")
