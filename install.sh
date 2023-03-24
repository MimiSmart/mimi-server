#!/bin/bash

# Активировать модули web сервера apache
a2enmod headers
a2enmod proxy
a2enmod proxy_http
a2enmod proxy_wstunnel
a2enmod rewrite

# Обновление конфигурации Web сервера Apache
curl -o /etc/apache/apache2.conf https://raw.githubusercontent.com/MimiSmart/mimi-server/main/apache/apache2.conf
curl -o /etc/apache/sites-enabled/000-default.conf https://raw.githubusercontent.com/MimiSmart/mimi-server/main/apache/000-default.conf
service apache2 restart
service apache2 status

# Обновление API
curl -o /home/api/api.php https://raw.githubusercontent.com/MimiSmart/mimi-server/main/api/api.php

# Обновление API плагина
curl -o /home/sh2/plugins/api_plugin.so https://raw.githubusercontent.com/MimiSmart/mimi-server/main/plugin/api_plugin.so
screen -S server -X quit || true
screen -dmS server sh -c '/home/sh2/server.sh; exec bash'

# Сервис для камер видеонаблюдения
curl -o /usr/local/bin/rtsp-simple-server https://raw.githubusercontent.com/MimiSmart/mimi-server/main/rtsp-server/rtsp-simple-server
curl -o /usr/local/etc/rtsp-simple-server.yml https://raw.githubusercontent.com/MimiSmart/mimi-server/main/rtsp-server/rtsp-simple-server.yml 
chmod +x /usr/local/bin/rtsp-simple-server

# Для автоматического запуcка сервера при старте системы создайте службу
tee /etc/systemd/system/rtsp-simple-server.service >/dev/null << EOF
[Unit]
Wants=network.target
[Service]
ExecStart=/usr/local/bin/rtsp-simple-server /usr/local/etc/rtsp-simple-server.yml
[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable rtsp-simple-server
systemctl start rtsp-simple-server
