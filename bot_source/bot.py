import logging
from telegram import Update
from telegram.ext import (
    ApplicationBuilder,
    ContextTypes,
    CommandHandler,
    MessageHandler,
    CallbackQueryHandler,
    filters
)

from modules.settings.manager_settings import show_settings_menu, settings_callback_handler
from modules.security.login_manager import (
    check_credentials,
    is_authorized,
    add_authorized_user,
    remove_authorized_user,
    load_bot_config
)
from modules.keyboards import get_authorized_keyboard, get_unauthorized_keyboard
from modules.commands_slash import (
    cmd_help,
    cmd_metrics,
    cmd_logout,
    cmd_setting,
    cmd_set_config
)
from modules.notifications.notification_manager import (
    mute_callback,
    schedule_notifications
)
from modules.vpn_setting.manager_vpn import (
    register_vpn_handlers_to,
    vpn_set_config_start
)

logging.basicConfig(
    filename='/var/log/telegram_bot.log',
    filemode='a',
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)
logger = logging.getLogger(__name__)

# /login команда
async def cmd_login(update: Update, context: ContextTypes.DEFAULT_TYPE):
    if len(context.args) != 2:
        return  # неправильный формат, просто выходим

    login_in, pass_in = context.args
    if check_credentials(login_in, pass_in):
        chat_id = update.effective_chat.id
        username = update.effective_user.username
        add_authorized_user(chat_id, username)

        kb = get_authorized_keyboard()
        await update.message.reply_text(
            "Авторизация успешна! Нажми на /help или на кнопки внизу чата.",
            reply_markup=kb
        )
    else:
        # Логин/пароль неверны => молчим или выводим что-то
        pass

# Обработка текстовых сообщений (Reply-кнопки)
async def on_text_message(update: Update, context: ContextTypes.DEFAULT_TYPE):
    chat_id = update.effective_chat.id
    if not is_authorized(chat_id):
        return  # игнорируем неавторизованного

    text_in = update.message.text.strip()

    if text_in == "Помощь 💬":
        await update.message.reply_text(
            "Доступные действия:\n"
            "• Помощь 💬\n"
            "• Показать статистику 📊\n"
            "• Загрузить конфиг 📂\n"
            "• Настройки ⚙️\n"
            "За помощью обращаться к @vpn_vendor\n"
            "Также доступны слэш-команды /help, /setting, /set_config, /metrics, /logout",
            reply_markup=get_authorized_keyboard()
        )

    elif text_in == "Показать статистику 📊":
        # Аналог /metrics
        await cmd_metrics(update, context)

    elif text_in == "Загрузить конфиг 📂":
        # Аналог /set_config
        await vpn_set_config_start(update, context)

    elif text_in == "Настройки ⚙️":
        # Аналог /setting
        await show_settings_menu(update, context)

    else:
        # По желанию: echo или ignore
        pass

def main():
    cfg = load_bot_config()
    token = cfg.get("api_key", "")
    if not token:
        logger.error("API ключ (api_key) не задан в telegram_bot_config.json!")
        return

    application = (
        ApplicationBuilder()
        .token(token)
        .read_timeout(60)
        .write_timeout(60)
        .build()
    )

    application.add_handler(CommandHandler("login", cmd_login))
    application.add_handler(CommandHandler("help", cmd_help))
    application.add_handler(CommandHandler("metrics", cmd_metrics))
    application.add_handler(CommandHandler("logout", cmd_logout))
    application.add_handler(CommandHandler("set_config", vpn_set_config_start))
    application.add_handler(CommandHandler("setting", show_settings_menu))

    # Текстовые сообщения (reply-кнопки)
    application.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, on_text_message))

    # Инлайн-кнопки
    application.add_handler(CallbackQueryHandler(mute_callback, pattern=r"^mute\|"))
    application.add_handler(CallbackQueryHandler(mute_callback, pattern=r"^blockmenu"))
    application.add_handler(CallbackQueryHandler(mute_callback, pattern=r"^blockmenu_back"))
    application.add_handler(CallbackQueryHandler(settings_callback_handler, pattern=r"^(toggle_feature_|settings_back)"))

    # Регистрируем VPN-хендлеры
    register_vpn_handlers_to(application)

    # Фоновые уведомления
    schedule_notifications(application.job_queue)

    from modules.security.bot_watchdog import start_watchdog
    start_watchdog(application)

    logger.info("Запускаем бота...")
    application.run_polling()

if __name__ == '__main__':
    main()
