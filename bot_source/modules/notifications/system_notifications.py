import logging
from telegram.ext import ContextTypes

logger = logging.getLogger(__name__)

async def send_system_notification(
    context: ContextTypes.DEFAULT_TYPE,
    chat_id: int,
    text: str,
    reply_markup=None
):
    """
    Отправляет «Системное уведомление» — пропавшие метрики, сбои, etc.
    """
    try:
        await context.bot.send_message(
            chat_id=chat_id,
            text=text,
            reply_markup=reply_markup
        )
    except Exception as e:
        logger.warning(f"[system_notification] Не удалось отправить сообщение {chat_id}: {e}")
