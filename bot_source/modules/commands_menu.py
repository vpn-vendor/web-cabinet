import logging
from telegram import BotCommand, BotCommandScopeChat
from telegram.ext import Application

logger = logging.getLogger(__name__)

# Неавторизованный: только /help
UNAUTHORIZED_COMMANDS = [
    BotCommand("help", "Помощь 💬")
]

# Авторизованный: пять команд, все с эмодзи
AUTHORIZED_COMMANDS = [
    BotCommand("logout", "Выйти из бота 🔒"),
    BotCommand("setting", "Настройки ⚙️"),
    BotCommand("set_config", "Загрузить конфиг 📂"),
    BotCommand("metrics", "Показать статистику 📊"),
    BotCommand("help", "Помощь 💬"),
]

async def set_user_commands(application: Application, user_id: int, authorized: bool):
    """Настраиваем кнопки синего меню для каждого юзера."""
    try:
        commands = AUTHORIZED_COMMANDS if authorized else UNAUTHORIZED_COMMANDS
        scope = BotCommandScopeChat(user_id)
        await application.bot.set_my_commands(commands, scope=scope)
        logger.info(
            f"Установили меню для user_id={user_id}, authorized={authorized}: "
            f"{', '.join([cmd.command for cmd in commands])}"
        )
    except Exception as e:
        logger.warning(f"set_user_commands: Не удалось установить команды: {e}")
