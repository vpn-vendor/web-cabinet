# modules/vpn_setting/manager_vpn.py

import logging
from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import ContextTypes, CallbackQueryHandler, MessageHandler, CommandHandler, filters

from modules.security.login_manager import is_authorized
from modules.keyboards import get_authorized_keyboard
from .openvpn_upload import handle_openvpn_file
from .wireguard_upload import handle_wireguard_file
from .check_ip import on_check_ip_callback

logger = logging.getLogger(__name__)

MENU_CHOICE, WAITING_OVPN_FILE, WAITING_WG_FILE = range(3)

def get_vpn_keyboard():
    kb = [
        [
            InlineKeyboardButton("OpenVPN", callback_data="vpn_openvpn"),
            InlineKeyboardButton("WireGuard", callback_data="vpn_wireguard")
        ],
        [
            InlineKeyboardButton("Проверить айпи", callback_data="vpn_check_ip")
        ],
        [
            InlineKeyboardButton("Назад", callback_data="vpn_back")
        ]
    ]
    return InlineKeyboardMarkup(kb)

def get_cancel_keyboard():
    kb = [
        [InlineKeyboardButton("Отмена", callback_data="vpn_cancel")]
    ]
    return InlineKeyboardMarkup(kb)

async def vpn_set_config_start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    chat_id = update.effective_chat.id
    if not is_authorized(chat_id):
        return

    if context.user_data.get("vpn_state") is not None:
        # Пользователь уже в режиме – предлагаем выйти через кнопку «Отмена».
        await update.message.reply_text(
            "Вы уже находитесь в режиме загрузки конфигурации. Если хотите выйти, нажмите 'Отмена'.",
            reply_markup=get_cancel_keyboard()
        )
        return

    text = (
        "Выберите конфигурацию, которую хотите загрузить:\n"
        "1) OpenVPN\n"
        "2) WireGuard\n"
        "3) Проверить айпи\n\n"
        "Для проверки внешнего IP нажмите кнопку <Проверить айпи>\n"
        "Чтобы выйти из режима, нажмите <Назад>\n\n"
        "ВНИМАНИЕ: Если после загрузки конфигурации бот перестанет отвечать, возможно, связь нарушилась."
    )
    markup = get_vpn_keyboard()
    await update.message.reply_text(text, reply_markup=markup)
    context.user_data["vpn_state"] = MENU_CHOICE

async def on_vpn_callback_query(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query = update.callback_query
    if not is_authorized(query.message.chat_id):
        await query.answer("Вы не авторизованы.")
        return

    await query.answer()
    data = query.data

    if data == "vpn_openvpn":
        text = (
            "Вы выбрали OpenVPN.\n"
            "Новый OpenVPN конфиг загружен. Сейчас перезапустим сервис...\n"
            "СОВЕТ: Через 20 секунд проверьте IP, нажав кнопку 'Проверить айпи'."
            "\n\nОтправьте файл .ovpn.\nЕсли передумали, нажмите /cancel или введите 'Отмена'."
        )
        await query.message.edit_text(text, reply_markup=get_vpn_keyboard())
        context.user_data["vpn_state"] = WAITING_OVPN_FILE

    elif data == "vpn_wireguard":
        text = (
            "Вы выбрали WireGuard.\n"
            "Новый WireGuard конфиг загружен. Сейчас перезапустим сервис...\n"
            "СОВЕТ: Через 20 секунд проверьте IP, нажав кнопку 'Проверить айпи'."
            "\n\nОтправьте файл .conf.\nЕсли передумали, нажмите /cancel или введите 'Отмена'."
        )
        await query.message.edit_text(text, reply_markup=get_vpn_keyboard())
        context.user_data["vpn_state"] = WAITING_WG_FILE

    elif data == "vpn_check_ip":
        await on_check_ip_callback(update, context)
        # После проверки остаёмся в режиме загрузки, клавиатура не меняется.
        return

    elif data == "vpn_back" or data == "vpn_cancel":
        # 1. Отвечаем на callback (убираем «часики» в интерфейсе Telegram)
        await query.answer()
        # 2. Шлём обычное сообщение в чат об отмене
        await query.message.reply_text("Действие отменено. Вы вернулись в главное меню.")
        # 3. Убираем inline-клавиатуру из старого сообщения
        await query.edit_message_reply_markup(reply_markup=None)
        # 4. Сбрасываем состояние, если пользователь был в режиме загрузки
        context.user_data["vpn_state"] = None

    else:
        await query.message.edit_text("Неизвестный вариант.")
        context.user_data["vpn_state"] = None

async def on_vpn_file_message(update: Update, context: ContextTypes.DEFAULT_TYPE):
    if not is_authorized(update.effective_chat.id):
        return

    state = context.user_data.get("vpn_state")
    text_in = (update.message.text or "").lower().strip()

    if text_in in ["отмена", "/cancel", "cancel"]:
        await update.message.reply_text("Загрузка VPN-конфигурации отменена.", reply_markup=get_authorized_keyboard())
        context.user_data["vpn_state"] = None
        return

    if not update.message.document:
        await update.message.reply_text("Пришлите файл конфигурации или введите 'Отмена'.")
        return

    doc = update.message.document
    file_name = doc.file_name.lower() if doc.file_name else ""

    if state == WAITING_OVPN_FILE:
        if not file_name.endswith(".ovpn"):
            await update.message.reply_text("Ошибка: нужен файл .ovpn.\nПопробуйте снова или 'Отмена'.")
            return
        await handle_openvpn_file(update, context, doc)
        context.user_data["vpn_state"] = None

    elif state == WAITING_WG_FILE:
        if not file_name.endswith(".conf"):
            await update.message.reply_text("Ошибка: нужен файл .conf.\nПопробуйте снова или 'Отмена'.")
            return
        await handle_wireguard_file(update, context, doc)
        context.user_data["vpn_state"] = None

async def cancel_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    context.user_data["vpn_state"] = None
    await update.message.reply_text("Действие отменено.", reply_markup=get_authorized_keyboard())

def register_vpn_handlers_to(app):
    app.add_handler(CallbackQueryHandler(on_vpn_callback_query, pattern=r"^vpn_"))
    app.add_handler(MessageHandler(filters.Document.ALL, on_vpn_file_message))
    app.add_handler(CommandHandler("cancel", cancel_command))
    app.add_handler(MessageHandler(filters.TEXT & filters.Regex("(?i)^(отмена|cancel)$"), cancel_command))
