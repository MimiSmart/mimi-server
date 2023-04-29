#!/bin/bash

# Проверяем версию операционной системы
os_name=$(uname -s)

if [[ "$os_name" == "Linux" && "$(uname -m)" == "arm"* ]]; then
    echo "Операционная система поддерживается"
else
    echo "Операционная система не поддерживается"
    exit 1
fi


# Активируем необходимые модули
a2enmod headers > /dev/null 2>&1
a2enmod proxy > /dev/null 2>&1
a2enmod proxy_http > /dev/null 2>&1
a2enmod proxy_wstunnel > /dev/null 2>&1
a2enmod rewrite > /dev/null 2>&1

# Проверям версию PHP и устанавливааем необходимые пакеты
if php -v | grep -q "PHP 5"; then
  apt-get install php5-sqlite > /dev/null 2>&1
elif php -v | grep -q "PHP 7"; then
  apt-get install php-sqlite3 > /dev/null 2>&1
else
  echo "Неподдерживаемая версия версия PHP."
  exit 1
fi

# Закачиваем и подменяем конфигурационные файлы apache
wget https://raw.githubusercontent.com/MimiSmart/mimi-server/main/apache/000-default.conf?raw=true -O /etc/apache2/sites-available/000-default.conf > /dev/null 2>&1
wget https://raw.githubusercontent.com/MimiSmart/mimi-server/main/apache/apache2.conf?raw=true -O /etc/apache2/apache2.conf > /dev/null 2>&1

# Перезапускаем службу apache
service apache2 restart > /dev/null 2>&1

# Проверяем запустилась ли слуюба apache
if systemctl is-active --quiet apache2; then
  echo "Apache работает корректно."
else
  echo "Служба Apache не запустилась корректно. Проверте журнал."
  exit 1
fi

# Создаём папку images и устанавливаем необходимые права
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
wget https://raw.githubusercontent.com/MimiSmart/mimi-server/main/api/api.php?raw=true -O /home/api/api.php > /dev/null 2>&1

# Проверяем успешно ли скопирован файл.
if [ -f "/home/api/api.php" ]; then
  echo "api.php успешно загружен."
else
  echo "Не удалось загрузить api.php. Выход."
  exit 1
fi

# Загружаем новый плагин api_plugin.so и его аргументы в /home/sh2/plugins
wget https://github.com/MimiSmart/mimi-server/blob/main/plugin/api_plugin.so?raw=true -O /home/sh2/plugins/api_plugin.so > /dev/null 2>&1

# Check if file was downloaded successfully
if [ -f "/home/sh2/plugins/api_plugin.so" ]; then
  echo "api_plugin.so успешно загружен."
else
  echo "Не удалось загрузить api_plugin.so. Выход."
  exit 1
fi

# Restart mimismart service in screen
screen -S mimiserver -X stuff "qu$(printf \\r)"

if [ $? -eq 0 ]; then
    echo "Перезапуск сервера успешно выполнен."
else
    echo "Перезапуск сервера не выполенен, выполните вручную."
fi


# Загрузить сервер mediamtx
wget https://github.com/MimiSmart/mimi-server/blob/main/midiamtx/mediamtx?raw=true -O /usr/local/bin/mediamtx > /dev/null 2>&1
wget https://github.com/MimiSmart/mimi-server/blob/main/midiamtx/mediamtx.yml?raw=true -O /usr/local/etc/mediamtx.yml > /dev/null 2>&1

# Проверить успешно ли загружены файлы
if [ -f "/usr/local/bin/mediamtx" ] && [ -f "/usr/local/etc/mediamtx.yml" ]; then
  echo "Files downloaded successfully."
else
  echo "Failed to download files. Script will now exit."
  exit 1
fi

# Сделать mediamtx исполняемым
chmod +x /usr/local/bin/mediamtx

# Создаём службу mideamtx
cat <<EOF >/etc/systemd/system/rtsp-simple-server.service
[Unit]
Wants=network.target
[Service]
ExecStart=/usr/local/bin/rtsp-simple-server /usr/local/etc/rtsp-simple-server.yml
[Install]
WantedBy=multi-user.target
EOF

# Перезапускаем системный демон и запускаем слуюбу
systemctl daemon-reload > /dev/null 2>&1
systemctl enable mediamtx.service > /dev/null 2>&1
systemctl start mediamtx.service > /dev/null 2>&1

# Проверяем, что служба запущена
if systemctl is-active --quiet mediamtx.service; then
  echo "mediamtx запущена."
else
  echo "Не удалось запустить службу mideamtx. Выход."
  exit 1
fi

# Копируем директорию vendor для api
wget https://github.com/MimiSmart/mimi-server/archive/vendor.zip?raw=true -O /home/api/vendor.zip > /dev/null 2>&1
unzip /home/api/vendor.zip -d /home/api/ > /dev/null 2>&1

# Проверяем успешно ли распакован архив
if [ -d "/home/api/vendor" ]; then
  echo "Архив успешно распакован"
else
  echo "Ошибка при распаковке архива. Выход."
  exit 1
fi

echo "Обновление сервера успешно ввыполнено. Мои поздравления."


