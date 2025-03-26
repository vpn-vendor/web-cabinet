from telegram import ReplyKeyboardMarkup, KeyboardButton

def get_authorized_keyboard():
    """
    Клавиатура для авторизованных:
      1) Помощь 💬
      2) [Показать статистику 📊, Загрузить конфиг 📂]
      3) Настройки ⚙️
    """
    keyboard = [
        [KeyboardButton("Помощь 💬")],
        [KeyboardButton("Показать статистику 📊"), KeyboardButton("Загрузить конфиг 📂")],
        [KeyboardButton("Настройки ⚙️")]
    ]
    return ReplyKeyboardMarkup(keyboard, resize_keyboard=True)

def get_unauthorized_keyboard():
    """Пустая/минимальная клавиатура для неавторизованных."""
    return ReplyKeyboardMarkup([[]], resize_keyboard=True)
