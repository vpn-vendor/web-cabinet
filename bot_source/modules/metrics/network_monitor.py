import logging
import os

logger = logging.getLogger(__name__)
LOG_PATH = '/var/log/ping_history.log'

NETWORK_STATE = {
    "no_data_count": 0,
    "no_data_threshold": 3
}

def check_network(latency_threshold=200, loss_threshold=5):
    """
    Считываем последнюю строку из /var/log/ping_history.log.
    """
    if not os.path.isfile(LOG_PATH):
        NETWORK_STATE["no_data_count"] += 1
        if NETWORK_STATE["no_data_count"] >= NETWORK_STATE["no_data_threshold"]:
            msg = "Ошибка сети: Файл /var/log/ping_history.log не найден (пропадает уже несколько раз)"
            logger.error(msg)
            return {
                "alert": True,
                "level": 3,
                "message": msg,
                "latency": None
            }
        else:
            msg = f"Сеть: пока нет файла ping_history.log (подряд {NETWORK_STATE['no_data_count']} раз)"
            logger.info(msg)
            return {
                "alert": False,
                "level": 0,
                "message": msg,
                "latency": None
            }

    # Файл есть, пытаемся прочесть
    try:
        with open(LOG_PATH, 'r') as f:
            lines = f.read().strip().splitlines()
        if not lines:
            # Пустой лог
            NETWORK_STATE["no_data_count"] += 1
            if NETWORK_STATE["no_data_count"] >= NETWORK_STATE["no_data_threshold"]:
                msg = "Ошибка сети: /var/log/ping_history.log пустой (несколько раз подряд)"
                logger.error(msg)
                return {
                    "alert": True,
                    "level": 3,
                    "message": msg,
                    "latency": None
                }
            else:
                msg = f"Сеть: лог пуст (подряд {NETWORK_STATE['no_data_count']} раз)"
                logger.info(msg)
                return {
                    "alert": False,
                    "level": 0,
                    "message": msg,
                    "latency": None
                }
        else:
            last_line = lines[-1].strip()
            parts = last_line.split()
            if len(parts) < 2:
                NETWORK_STATE["no_data_count"] += 1
                if NETWORK_STATE["no_data_count"] >= NETWORK_STATE["no_data_threshold"]:
                    msg = f"Ошибка сети: Некорректная строка '{last_line}' (несколько раз)"
                    logger.error(msg)
                    return {
                        "alert": True,
                        "level": 3,
                        "message": msg,
                        "latency": None
                    }
                else:
                    msg = f"Сеть: некорректная строка (подряд {NETWORK_STATE['no_data_count']} раз)"
                    logger.info(msg)
                    return {
                        "alert": False,
                        "level": 0,
                        "message": msg,
                        "latency": None
                    }
            else:
                # Успешно получили данные, сбрасываем счётчик
                NETWORK_STATE["no_data_count"] = 0
                latency = float(parts[1])

                # Уровень (пример)
                level = 3 if latency >= 110 else 2 if latency >= 100 else 1 if latency >= 90 else 0
                alert = (latency >= latency_threshold)

                msg = f"Сеть: задержка {latency:.1f} мс"
                if level:
                    msg += f" (Уровень {level})"

                return {
                    "alert": alert,
                    "level": level,
                    "message": msg,
                    "latency": latency
                }

    except Exception as e:
        # Ошибка чтения
        NETWORK_STATE["no_data_count"] += 1
        if NETWORK_STATE["no_data_count"] >= NETWORK_STATE["no_data_threshold"]:
            msg = f"Ошибка сети: Невозможно прочитать ping_history.log: {e} (неск. раз)"
            logger.error(msg)
            return {
                "alert": True,
                "level": 3,
                "message": msg,
                "latency": None
            }
        else:
            msg = f"Сеть: ошибка чтения (подряд {NETWORK_STATE['no_data_count']} раз)"
            logger.info(msg)
            return {
                "alert": False,
                "level": 0,
                "message": msg,
                "latency": None
            }
