import logging
from modules.utils import load_latest_record

logger = logging.getLogger(__name__)
HOME_METRICS = '/var/www/html/data/home_metrics_daemon.json'

MEMORY_STATE = {
    "no_data_count": 0,
    "no_data_threshold": 3
}

def check_memory_usage(threshold=80):
    """
    Считывает 'ram' из home_metrics_daemon.json.
    """
    data = load_latest_record(HOME_METRICS)
    ram = data.get('ram')

    if ram is None:
        MEMORY_STATE["no_data_count"] += 1
        if MEMORY_STATE["no_data_count"] >= MEMORY_STATE["no_data_threshold"]:
            msg = "Ошибка памяти: Нет данных о памяти (пропало несколько раз подряд)"
            logger.error(msg)
            return {
                "alert": True,
                "level": 3,
                "message": msg,
                "usage_percent": None
            }
        else:
            msg = f"ОЗУ: временно нет данных (подряд {MEMORY_STATE['no_data_count']} раз)"
            logger.info(msg)
            return {
                "alert": False,
                "level": 0,
                "message": msg,
                "usage_percent": None
            }
    else:
        MEMORY_STATE["no_data_count"] = 0

        level = 3 if ram >= 90 else 2 if ram >= 80 else 1 if ram >= 70 else 0
        alert = (ram >= threshold)

        msg = f"ОЗУ: {ram:.1f}%"
        if level:
            msg += f" (Уровень {level})"

        return {
            "alert": alert,
            "level": level,
            "message": msg,
            "usage_percent": ram
        }
