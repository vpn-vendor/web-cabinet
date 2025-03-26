# modules/settings/manager_settings.py

import logging
from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import ContextTypes

logger = logging.getLogger(__name__)

# Пример настроек, которые можно менять (в реальном проекте – через базу или файл)
SETTINGS = {
    "feature_a": True,
    "feature_b": False,
}

def build_settings_menu() -> InlineKeyboardMarkup:
    """
    Создает inline клавиатуру для меню настроек.
    """
    buttons = [
        [
            InlineKeyboardButton(
                f"Функция A: {'Включена' if SETTINGS['feature_a'] else 'Выключена'}",
                callback_data="toggle_feature_a"
            )
        ],
        [
            InlineKeyboardButton(
                f"Функция B: {'Включена' if SETTINGS['feature_b'] else 'Выключена'}",
                callback_data="toggle_feature_b"
            )
        ],
        [
            InlineKeyboardButton("Назад", callback_data="settings_back")
        ]
    ]
    return InlineKeyboardMarkup(buttons)

async def show_settings_menu(update: Update, context: ContextTypes.DEFAULT_TYPE):
    text = (
        "Меню настроек:\n\n"
        "Здесь будут переключатели для включения/выключения функционала бота.\n"
        "Пока раздел находится в разработке.\n"
        "Планируется внесение изменений так же через веб-сайт через обычные переключатели."
    )
    markup = build_settings_menu()
    if update.message:
        await update.message.reply_text(text, reply_markup=markup)
    elif update.callback_query:
        await update.callback_query.edit_message_text(text, reply_markup=markup)

async def settings_callback_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """
    Обрабатывает нажатия на кнопки в меню настроек.
    """
    query = update.callback_query
    data = query.data

    if data == "toggle_feature_a":
        SETTINGS["feature_a"] = not SETTINGS["feature_a"]
        await query.answer("Функция A переключена")
        await show_settings_menu(update, context)
    elif data == "toggle_feature_b":
        SETTINGS["feature_b"] = not SETTINGS["feature_b"]
        await query.answer("Функция B переключена")
        await show_settings_menu(update, context)
    elif data == "settings_back":
        # 1. «Отвечаем» на callback, чтобы Telegram не считал запрос «висящим»
        await query.answer()
        # 2. Посылаем новое «обычное» сообщение в чат
        await query.message.reply_text("Действие отменено. Вы вернулись в главное меню.")
        # 3. Убираем inline-кнопки со старого сообщения (иначе они «зависнут»)
        await query.edit_message_reply_markup(reply_markup=None)
        # 4. При необходимости сбрасываем состояние (если есть):
        context.user_data["settings_state"] = None
    else:
        await query.answer("Неизвестная команда настроек")
