import logging
from modules.utils import load_latest_record

logger = logging.getLogger(__name__)
HOME_METRICS = '/var/www/html/data/home_metrics_daemon.json'

DISK_STATE = {
    "no_data_count": 0,
    "no_data_threshold": 3
}

def check_disk_usage(threshold=90):
    """
    Считывает 'disk' из home_metrics_daemon.json.
    """
    data = load_latest_record(HOME_METRICS)
    disk = data.get('disk')

    if disk is None:
        DISK_STATE["no_data_count"] += 1
        if DISK_STATE["no_data_count"] >= DISK_STATE["no_data_threshold"]:
            msg = "Ошибка диска: Нет данных о диске (пропало несколько раз подряд)"
            logger.error(msg)
            return {
                "alert": True,
                "level": 3,
                "message": msg,
                "usage_percent": None
            }
        else:
            msg = f"Диск: временно нет данных (подряд {DISK_STATE['no_data_count']} раз)"
            logger.info(msg)
            return {
                "alert": False,
                "level": 0,
                "message": msg,
                "usage_percent": None
            }
    else:
        DISK_STATE["no_data_count"] = 0

        level = 3 if disk >= 90 else 2 if disk >= 80 else 1 if disk >= 70 else 0
        alert = (disk >= threshold)

        msg = f"Диск: {disk:.1f}%"
        if level:
            msg += f" (Уровень {level})"

        return {
            "alert": alert,
            "level": level,
            "message": msg,
            "usage_percent": disk
        }
