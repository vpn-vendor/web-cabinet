import logging
import time

from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
from telegram.ext import ContextTypes, JobQueue

from modules.security.login_manager import (
    load_bot_config, unify_users_format, parse_user_item
)

from modules.metrics.cpu_monitor import check_cpu_usage
from modules.metrics.memory_monitor import check_memory_usage
from modules.metrics.disk_monitor import check_disk_usage
from modules.metrics.network_monitor import check_network

from .system_notifications import send_system_notification
from .warning_notifications import send_warning_notification
from .tips_notifications import send_tip_notification

logger = logging.getLogger(__name__)

# Настройки для пропавших метрик
MISSING_DATA_TIMEOUT = 10

# Пороги (warning)
CPU_THRESHOLD = 80
MEM_THRESHOLD = 80
DISK_THRESHOLD = 90
LATENCY_THRESHOLD = 200
LOSS_THRESHOLD = 5

# Глобальное состояние (для пропавших данных)
METRICS_STATE = {
    "cpu": {
        "lastSeenOk": None,
        "missingWarned": False,
        "muteUntil": 0.0,
        "muteLevel": 0,
    },
    "memory": {
        "lastSeenOk": None,
        "missingWarned": False,
        "muteUntil": 0.0,
        "muteLevel": 0,
    },
    "disk": {
        "lastSeenOk": None,
        "missingWarned": False,
        "muteUntil": 0.0,
        "muteLevel": 0,
    },
    # network - по желанию
}

MUTE_TIME_1 = 30 * 60   # 30 минут
MUTE_TIME_2 = 24 * 3600 # 24 часа
MUTE_TIME_3H = 3 * 3600 # 3 часа

# Список последних предупреждений (борьба со спамом)
RECENT_WARNINGS = []


async def monitor_metrics(context: ContextTypes.DEFAULT_TYPE):
    now = time.time()

    cpu_res = check_cpu_usage(CPU_THRESHOLD)
    mem_res = check_memory_usage(MEM_THRESHOLD)
    disk_res = check_disk_usage(DISK_THRESHOLD)
    net_res = check_network(LATENCY_THRESHOLD, LOSS_THRESHOLD)

    # Обновляем "lastSeenOk"
    for metric_name, metric_res in (("cpu", cpu_res), ("memory", mem_res), ("disk", disk_res)):
        if metric_res["usage_percent"] is not None:
            METRICS_STATE[metric_name]["lastSeenOk"] = now
            METRICS_STATE[metric_name]["missingWarned"] = False

    # Ищем метрики, у которых lastSeenOk уже давно
    missing_list = []
    for metric, st in METRICS_STATE.items():
        # Сбрасываем mute
        if now > st["muteUntil"] and st["muteLevel"] > 0:
            st["muteLevel"] = 0
            st["muteUntil"] = 0.0

        last_ok = st["lastSeenOk"]
        if last_ok is None or (now - last_ok) > MISSING_DATA_TIMEOUT:
            missing_list.append(metric)

    msgs_missing = []
    for metric in missing_list:
        st = METRICS_STATE[metric]
        if st["missingWarned"]:
            continue
        if now < st["muteUntil"]:
            continue

        msgs_missing.append(f"Ошибка {metric}: Нет данных о {metric}")
        st["missingWarned"] = True

    if msgs_missing:
        # Кнопка «Заблокировать»
        keyboard = [[InlineKeyboardButton("Заблокировать", callback_data="blockmenu|missing_data")]]
        reply_markup = InlineKeyboardMarkup(keyboard)
        text = "⚠️ Системное уведомление:\n" + "\n".join(msgs_missing)
        await _send_to_all_authorized(context, text, reply_markup, system=True)

    # Предупреждения
    alerts = []
    all_res = [cpu_res, mem_res, disk_res, net_res]
    for r in all_res:
        if r.get("alert"):
            alerts.append(r["message"])

    if alerts:
        text_warning = "⚠️ Предупреждение о повышенных показателях:\n" + "\n".join(alerts)
        # Спам-блок
        if check_spam_block(text_warning):
            logger.warning("Автоблокировка: повторяющееся предупреждение => 30 минут mute для WARNING")
            # mute всех
            for metric in METRICS_STATE:
                METRICS_STATE[metric]["muteLevel"] = 1
                METRICS_STATE[metric]["muteUntil"] = now + MUTE_TIME_1
            return
        else:
            RECENT_WARNINGS.append((now, text_warning))
            while len(RECENT_WARNINGS) > 20:
                RECENT_WARNINGS.pop(0)

        kb_warning = [[InlineKeyboardButton("Заблокировать", callback_data="blockmenu|warning_up")]]
        reply_markup = InlineKeyboardMarkup(kb_warning)
        await _send_to_all_authorized(context, text_warning, reply_markup, warning=True)


def check_spam_block(new_text: str, spam_limit=3, time_window=60.0) -> bool:
    now = time.time()
    recent = [(t, txt) for (t, txt) in RECENT_WARNINGS if (now - t) < time_window]
    same_count = sum(1 for (t, txt) in recent if txt == new_text)
    if same_count >= spam_limit - 1:
        return True
    return False


def schedule_notifications(job_queue: JobQueue):
    job_queue.run_repeating(monitor_metrics, interval=2, first=2)
    logger.info("Фоновые уведомления (monitor_metrics) запланированы.")


async def _send_to_all_authorized(
    context: ContextTypes.DEFAULT_TYPE,
    text: str,
    reply_markup=None,
    system: bool = False,
    warning: bool = False,
):
    cfg = load_bot_config()
    unify_users_format(cfg)
    auth_list = cfg.get("authorized_users", [])

    for user_str in auth_list:
        chat_id = parse_user_item(user_str)
        try:
            if system:
                await send_system_notification(context, chat_id, text, reply_markup=reply_markup)
            elif warning:
                await send_warning_notification(context, chat_id, text, reply_markup=reply_markup)
            else:
                await context.bot.send_message(chat_id=chat_id, text=text, reply_markup=reply_markup)
        except Exception as e:
            logger.warning(f"Не удалось отправить уведомление пользователю {user_str}: {e}")


# ---------- CALLBACK для MUTE ----------
async def mute_callback(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query = update.callback_query
    await query.answer()

    data = query.data.split("|")
    if len(data) < 2:
        return

    if data[0] == "mute":
        # mute|cpu|30
        if len(data) != 3:
            return
        metric, mode = data[1], data[2]
        if metric not in METRICS_STATE and metric != "ALL":
            return

        now = time.time()
        if metric == "ALL":
            # Глушим всё
            if mode == "30":
                for m in METRICS_STATE:
                    METRICS_STATE[m]["muteUntil"] = now + MUTE_TIME_1
                    METRICS_STATE[m]["muteLevel"] = 1
                await query.edit_message_text("Уведомления по всем метрикам отключены на 30 минут.")
            elif mode == "3h":
                for m in METRICS_STATE:
                    METRICS_STATE[m]["muteUntil"] = now + MUTE_TIME_3H
                    METRICS_STATE[m]["muteLevel"] = 1
                await query.edit_message_text("Уведомления по всем метрикам отключены на 3 часа.")
            elif mode == "24h":
                for m in METRICS_STATE:
                    METRICS_STATE[m]["muteUntil"] = now + MUTE_TIME_2
                    METRICS_STATE[m]["muteLevel"] = 2
                await query.edit_message_text("Уведомления по всем метрикам отключены на 24 часа.")
        else:
            # mute конкретной метрики
            if mode == "30":
                METRICS_STATE[metric]["muteUntil"] = now + MUTE_TIME_1
                METRICS_STATE[metric]["muteLevel"] = 1
                await query.edit_message_text(
                    text=f"Уведомления по {metric} отключены на 30 минут."
                )
            elif mode == "3h":
                METRICS_STATE[metric]["muteUntil"] = now + MUTE_TIME_3H
                METRICS_STATE[metric]["muteLevel"] = 1
                await query.edit_message_text(
                    text=f"Уведомления по {metric} отключены на 3 часа."
                )
            elif mode == "24h":
                METRICS_STATE[metric]["muteUntil"] = now + MUTE_TIME_2
                METRICS_STATE[metric]["muteLevel"] = 2
                await query.edit_message_text(
                    text=f"Уведомления по {metric} отключены на 24 часа."
                )

    elif data[0] == "blockmenu":
        # Меню «Заблокировать»
        block_type = data[1]
        keyboard = [
            [InlineKeyboardButton("На 30 минут", callback_data="mute|ALL|30")],
            [InlineKeyboardButton("На 3 часа", callback_data="mute|ALL|3h")],
            [InlineKeyboardButton("На 24 часа", callback_data="mute|ALL|24h")],
            [InlineKeyboardButton("Назад", callback_data="blockmenu_back")],
        ]
        markup = InlineKeyboardMarkup(keyboard)
        await query.edit_message_text(
            text="Выберите время блокировки для этого уведомления:",
            reply_markup=markup
        )

    elif data[0] == "blockmenu_back":
        await query.edit_message_text("Блокировка уведомлений отменена.")
    else:
        pass
