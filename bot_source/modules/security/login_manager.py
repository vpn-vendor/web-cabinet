# telegram_bot/modules/login_manager.py
import logging
import json
import os

logger = logging.getLogger(__name__)

CONFIG_PATH = "/var/www/html/data/telegram_bot_config.json"

def load_bot_config():
    """Чтение конфигурации из JSON."""
    try:
        with open(CONFIG_PATH, "r") as f:
            return json.load(f)
    except Exception as e:
        logger.error(f"Ошибка чтения {CONFIG_PATH}: {e}")
        return {}

def save_bot_config(cfg: dict):
    """Сохранение конфигурации в JSON."""
    try:
        with open(CONFIG_PATH, "w") as f:
            json.dump(cfg, f, indent=4)
    except Exception as e:
        logger.error(f"Ошибка записи {CONFIG_PATH}: {e}")

def unify_users_format(cfg: dict):
    users = cfg.get("authorized_users", [])
    changed = False
    new_list = []
    for item in users:
        if isinstance(item, int):
            item = str(item)
            changed = True
        new_list.append(item)
    if changed:
        cfg["authorized_users"] = new_list

def build_user_string(chat_id: int, username: str|None) -> str:
    base = str(chat_id)
    if username:
        base += f" - @{username}"
    return base

def parse_user_item(item: str) -> int:
    """
    "123456 - @user" => 123456
    "123456"         => 123456
    """
    if " - @" in item:
        return int(item.split(" - @", 1)[0])
    else:
        return int(item)

def add_authorized_user(chat_id: int, username: str|None):
    cfg = load_bot_config()
    unify_users_format(cfg)

    new_str = build_user_string(chat_id, username)
    users = cfg.get("authorized_users", [])

    found = False
    for s in users:
        if parse_user_item(s) == chat_id:
            found = True
            break
    if not found:
        users.append(new_str)
        cfg["authorized_users"] = users
        save_bot_config(cfg)  # Пытается записать файл
        logger.info(f"Пользователь {new_str} успешно добавлен в authorized_users.")
    else:
        logger.info(f"Пользователь {new_str} уже есть в authorized_users.")

def remove_authorized_user(chat_id: int):
    cfg = load_bot_config()
    unify_users_format(cfg)

    users = cfg.get("authorized_users", [])
    index = None
    for i, item_str in enumerate(users):
        if parse_user_item(item_str) == chat_id:
            index = i
            break
    if index is not None:
        removed = users.pop(index)
        cfg["authorized_users"] = users
        save_bot_config(cfg)
        logger.info(f"Пользователь {removed} удалён из authorized_users.")
    else:
        logger.info(f"Пользователь {chat_id} не найден в authorized_users.")

def is_authorized(chat_id: int) -> bool:
    cfg = load_bot_config()
    unify_users_format(cfg)
    users = cfg.get("authorized_users", [])
    for s in users:
        if parse_user_item(s) == chat_id:
            return True
    return False

def check_credentials(login_input: str, pass_input: str) -> bool:
    cfg = load_bot_config()
    stored_login = cfg.get("credentials", {}).get("login")
    stored_pass  = cfg.get("credentials", {}).get("password")
    return (login_input == stored_login) and (pass_input == stored_pass)
