import logging
from telegram.ext import ContextTypes

logger = logging.getLogger(__name__)

async def send_warning_notification(
    context: ContextTypes.DEFAULT_TYPE,
    chat_id: int,
    text: str,
    reply_markup=None
):
    """
    «Предупреждение» (например, о повышенной загрузке CPU, Memory и т.п.)
    """
    try:
        await context.bot.send_message(
            chat_id=chat_id,
            text=text,
            reply_markup=reply_markup
        )
    except Exception as e:
        logger.warning(f"[warning_notification] Не удалось отправить сообщение {chat_id}: {e}")
