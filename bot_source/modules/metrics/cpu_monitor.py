import logging
from modules.utils import load_latest_record

logger = logging.getLogger(__name__)
HOME_METRICS = '/var/www/html/data/home_metrics_daemon.json'

# Глобальное состояние (сколько раз подряд не нашли данных)
CPU_STATE = {
    "no_data_count": 0,     # счётчик подряд
    "no_data_threshold": 3  # после 3 подряд - выдаём alert
}

def check_cpu_usage(threshold=80):
    """
    Считывает значение CPU из home_metrics_daemon.json.
    """
    data = load_latest_record(HOME_METRICS)
    cpu = data.get('cpu')

    if cpu is None:
        # Нет данных: увеличим счётчик
        CPU_STATE["no_data_count"] += 1
        if CPU_STATE["no_data_count"] >= CPU_STATE["no_data_threshold"]:
            # Только теперь реально сообщаем "Ошибка CPU: Нет данных"
            msg = "Ошибка CPU: Нет данных о CPU (пропало уже несколько раз подряд)"
            logger.error(msg)
            return {
                "alert": True,
                "level": 3,
                "message": msg,
                "usage_percent": None
            }
        else:
            # Пока не превышен порог — alert=False (или вообще не тревожим)
            msg = f"CPU: временно нет данных (подряд {CPU_STATE['no_data_count']} раз)"
            logger.info(msg)
            return {
                "alert": False,
                "level": 0,
                "message": msg,
                "usage_percent": None
            }
    else:
        # Данные есть -> сброс счётчика
        CPU_STATE["no_data_count"] = 0

        level = 3 if cpu >= 90 else 2 if cpu >= 70 else 1 if cpu >= 50 else 0
        alert = (cpu >= threshold)

        msg = f"CPU: {cpu:.1f}%"
        if level:
            msg += f" (Уровень {level})"

        return {
            "alert": alert,
            "level": level,
            "message": msg,
            "usage_percent": cpu
        }
