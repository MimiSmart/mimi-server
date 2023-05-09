#!/bin/bash

# Проверяем версию операционной системы
os_name=$(uname -s)

if [[ "$os_name" == "Linux" && "$(uname -m)" == "arm"* ]]; then
    echo "Операционная система поддерживается. Начинаю установку."
else
    echo "Операционная система не поддерживается"
    exit 1
fi


# Активируем необходимые модули
echo "Активирую необходимые модули Apache"
a2enmod headers > /dev/null 2>&1
a2enmod proxy > /dev/null 2>&1
a2enmod proxy_http > /dev/null 2>&1
a2enmod proxy_wstunnel > /dev/null 2>&1
a2enmod rewrite > /dev/null 2>&1


# Выполнение команды apt-get update
echo "Выполняю команду apt-get update"
apt-get update > /dev/null 2>&1

# Проверка успешности выполнения команды
if [ $? -eq 0 ]; then
    echo "Команда apt-get update выполнена успешно"
else
    echo "Ошибка выполнения команды apt-get update"
    exit 1
fi

# Проверям версию PHP и устанавливааем необходимые пакеты
echo "Проверяю версию PHP"
if php -v | grep -q "PHP 5"; then
  echo "Версия PHP - 5. Устанавливаю SQLite для PHP5"
  apt-get install -y php5-sqlite > /dev/null 2>&1
elif php -v | grep -q "PHP 7"; then
  echo "Версия PHP - 7. Устанавливаю SQLite3 для PHP7"
  apt-get install -y php-sqlite3 > /dev/null 2>&1
else
  echo "Неподдерживаемая версия версия PHP."
  exit 1
fi

# Закачиваем и подменяем конфигурационные файлы apache
echo "Скачиваю новую конфигурацию Apache"
wget https://raw.githubusercontent.com/MimiSmart/mimi-server/main/apache/000-default.conf?raw=true -O /etc/apache2/sites-enabled/000-default.conf > /dev/null 2>&1
wget https://raw.githubusercontent.com/MimiSmart/mimi-server/main/apache/apache2.conf?raw=true -O /etc/apache2/apache2.conf > /dev/null 2>&1
wget https://raw.githubusercontent.com/MimiSmart/mimi-server/main/apache/ports.conf?raw=true -O /etc/apache2/ports.conf > /dev/null 2>&1

# Перезапускаем службу apache
echo "Перезапускаю Apache службу"
service apache2 restart > /dev/null 2>&1

# Проверяем запустилась ли слуюба apache
if systemctl is-active --quiet apache2; then
  echo "Apache успешно перезапущена."
else
  echo "Служба Apache не запустилась корректно. Проверте журнал."
  exit 1
fi

# Создаём папку images и устанавливаем необходимые права
echo "Создаю папку Images"
mkdir /storage/images > /dev/null 2>&1
chmod 777 /storage/images > /dev/null 2>&1

# Проверяем успешно ли создана папка
if [ -d "/storage/images" ]; then
  echo "Директория Imagages успешно создана."
else
  echo "Не удалось создать директория images. Выход."
  exit 1
fi

# Загружаем новый API в папку /home/api
echo "Загружаю новый API"
wget https://raw.githubusercontent.com/MimiSmart/mimi-server/main/api/api.php?raw=true -O /home/api/api.php > /dev/null 2>&1

# Проверяем успешно ли скопирован файл.
if [ -f "/home/api/api.php" ]; then
  echo "API успешно установлен."
else
  echo "Не удалось установить API. Выход."
  exit 1
fi

# Загружаем новый плагин api_plugin.so и его аргументы в /home/sh2/plugins
echo "Загружаю новый API Plugin"
wget https://raw.githubusercontent.com/MimiSmart/mimi-server/main/plugin/api_plugin.so?raw=true -O /home/sh2/plugins/api_plugin.so > /dev/null 2>&1

# Check if file was downloaded successfully
if [ -f "/home/sh2/plugins/api_plugin.so" ]; then
  echo "API plugin успешно загружен."
else
  echo "Не удалось загрузить API Plugin. Выход."
  exit 1
fi

# Restart mimismart service in screen
echo "Пытаясь перезапустить сервер Mimiserver"
screen -S mimiserver -X stuff "qu$(printf \\r)"

if [ $? -eq 0 ]; then
    echo "Перезапуск сервера успешно выполнен."
else
    echo "Перезапуск сервера не выполенен, выполните вручную."
fi


# Загрузить сервер mediamtx
echo "Скачиваю сервер Mediamtx"
wget https://raw.githubusercontent.com/MimiSmart/mimi-server/main/midiamtx/mediamtx?raw=true -O /usr/local/bin/mediamtx > /dev/null 2>&1
wget https://raw.githubusercontent.com/MimiSmart/mimi-server/main/midiamtx/mediamtx.yml?raw=true -O /usr/local/etc/mediamtx.yml > /dev/null 2>&1

# Проверить успешно ли загружены файлы
if [ -f "/usr/local/bin/mediamtx" ] && [ -f "/usr/local/etc/mediamtx.yml" ]; then
  echo "Файлы mediamtx сервера успешно загружены."
else
  echo "Не удалось загрузить файлы сервера mediamtx."
  exit 1
fi

# Сделать mediamtx исполняемым
chmod +x /usr/local/bin/mediamtx

# Создаём службу mideamtx
echo "Создаю службу mediamtx"
cat <<EOF >/etc/systemd/system/mediamtx.service
[Unit]
Wants=network.target
[Service]
ExecStart=/usr/local/bin/mediamtx /usr/local/etc/mediamtx.yml
[Install]
WantedBy=multi-user.target
EOF

# Перезапускаем системный демон и запускаем слуюбу
systemctl daemon-reload > /dev/null 2>&1
systemctl enable mediamtx.service > /dev/null 2>&1
systemctl start mediamtx.service > /dev/null 2>&1

# Проверяем, что служба запущена
if systemctl is-active --quiet mediamtx.service; then
  echo "Служба mediamtx запущена."
else
  echo "Не удалось запустить службу mideamtx. Выход."
  exit 1
fi

# Копируем директорию vendor для api
echo "Качаем зависимости для API"
wget https://raw.githubusercontent.com/MimiSmart/mimi-server/main/api/vendor.zip?raw=true -O /home/api/vendor.zip > /dev/null 2>&1
unzip /home/api/vendor.zip -d /home/api/ > /dev/null 2>&1

# Проверяем успешно ли распакован архив
if [ -d "/home/api/vendor" ]; then
  echo "Архив зависимостей успешно распакован"
else
  echo "Ошибка при распаковке архива зависимостей. Выход."
  exit 1
fi

echo "Обновление сервера успешно ввыполнено. Мои поздравления."


