import logging
from telegram import Update
from telegram.ext import ContextTypes

from modules.security.login_manager import is_authorized, remove_authorized_user
from modules.keyboards import get_authorized_keyboard, get_unauthorized_keyboard

from modules.metrics.cpu_monitor import check_cpu_usage
from modules.metrics.memory_monitor import check_memory_usage
from modules.metrics.disk_monitor import check_disk_usage

logger = logging.getLogger(__name__)


def build_ascii_chart(cpu_usage, mem_usage, disk_usage):

    def usage_bar(usage, total_bars=30):
        """Создаёт одну «полосу» из # и . на total_bars символов."""
        if usage is None:
            return "[нет данных]"
        filled = int(round(usage * total_bars / 100.0))
        if filled > total_bars:
            filled = total_bars
        return "[" + "#" * filled + "." * (total_bars - filled) + "]"

    def format_line(label, usage):
        """Формирует одну строку вида:
           CPU  [####.............] 23.4% ⚠️
        """
        if usage is None:
            bar_str = "[нет данных]"
            usage_str = "--"
            warn_symbol = ""
        else:
            bar_str = usage_bar(usage)
            usage_str = f"{usage:.1f}%"
            # Если порог >=80 — ставим «⚠️»
            warn_symbol = " ⚠️" if usage >= 80 else ""

        # label выравниваем слева на 4-5 символов, чтобы было ровно
        return f"{label:<5}{bar_str} {usage_str}{warn_symbol}"

    cpu_line = format_line("CPU", cpu_usage)
    mem_line = format_line("RAM", mem_usage)
    dsk_line = format_line("DISK", disk_usage)

    chart = (
        "==== ASCII Мониторинг ====\n"
        f"{cpu_line}\n"
        f"{mem_line}\n"
        f"{dsk_line}\n"
    )
    return chart


async def cmd_help(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Обработчик /help"""
    chat_id = update.effective_chat.id
    if is_authorized(chat_id):
        kb = get_authorized_keyboard()
        text = (
            "Доступные слэш-команды:\n"
            "• /logout — Выйти из бота\n"
            "• /setting — Настройки\n"
            "• /set_config — Загрузить конфиг\n"
            "• /metrics — Показать статистику\n"
            "• /help — Помощь\n\n"
            "А также кнопки под полем ввода."
        )
    else:
        kb = get_unauthorized_keyboard()
        text = "Вы не авторизованы"

    await update.message.reply_text(text, reply_markup=kb)


async def cmd_metrics(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Обработчик /metrics (Показ статистики)"""
    chat_id = update.effective_chat.id
    if not is_authorized(chat_id):
        return

    # Получаем метрики (cpu, mem, disk)
    cpu_res = check_cpu_usage()
    mem_res = check_memory_usage()
    disk_res = check_disk_usage()

    # Генерируем ASCII-график
    chart = build_ascii_chart(
        cpu_res['usage_percent'],
        mem_res['usage_percent'],
        disk_res['usage_percent']
    )

    # Итоговое сообщение
    msg = chart

    kb = get_authorized_keyboard()
    await update.message.reply_text(msg, reply_markup=kb)


async def cmd_logout(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Обработчик /logout (выход)"""
    chat_id = update.effective_chat.id
    if not is_authorized(chat_id):
        return

    remove_authorized_user(chat_id)
    await update.message.reply_text(
        "Вы вышли из бота. Для повторного входа используйте /login <логин> <пароль>."
    )


async def cmd_setting(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Обработчик /setting - настройки бота (заглушка)"""
    chat_id = update.effective_chat.id
    if not is_authorized(chat_id):
        return

    await update.message.reply_text(
        "Меню настроек пока в разработке. В будущем тут будут переключатели настроек."
    )


async def cmd_set_config(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Обработчик /set_config - загрузка/изменение конфига"""
    chat_id = update.effective_chat.id
    if not is_authorized(chat_id):
        return

    await update.message.reply_text(
        "Загрузка/изменение конфига пока в разработке/тех.обслуживании."
    )
