import asyncio
import sys
import os
import logging
from telegram.error import TelegramError

logger = logging.getLogger(__name__)

# Порог количества последовательных ошибок, после которого запускается перезапуск
FAILURE_THRESHOLD = 2
# Интервал проверки в секундах
CHECK_INTERVAL = 10

async def watchdog_task(application):
    consecutive_failures = 0
    while True:
        try:
            # Попытка получить данные о боте.
            me = await application.bot.get_me()
            logger.info(f"Watchdog: бот работает, get_me() вернул: {me.username}")
            consecutive_failures = 0  # Сброс счетчика ошибок
        except TelegramError as te:
            consecutive_failures += 1
            logger.error(f"Watchdog: Ошибка при проверке работы бота: {te}. Последовательных ошибок: {consecutive_failures}")
        except Exception as e:
            consecutive_failures += 1
            logger.error(f"Watchdog: Неожиданная ошибка: {e}. Последовательных ошибок: {consecutive_failures}")

        if consecutive_failures >= FAILURE_THRESHOLD:
            logger.error("Watchdog: Превышен порог ошибок. Пытаюсь перезапустить процесс бота.")
            try:
                # Логгируем и перезапускаем процесс.
                os.execv(sys.executable, [sys.executable] + sys.argv)
            except Exception as restart_error:
                logger.error(f"Watchdog: Не удалось перезапустить процесс: {restart_error}")
                # Если перезапустить не удалось, просто продолжаем пытаться.
        await asyncio.sleep(CHECK_INTERVAL)

def start_watchdog(application):
    """
    Запускаем watchdog_task как фоновую задачу в event loop.
    """
    loop = asyncio.get_event_loop()
    loop.create_task(watchdog_task(application))
    logger.info("Watchdog: задача запущена.")
