# Обновление конфигурации МимиСмарт сервера для нового приложения. Внимание! Нижеуказанные действия применимы к CUARM. Не применяйте к MicroD+. 
## Активировать модули web сервера apache. Выполните команды в консоли.
```
a2enmod headers
a2enmod proxy
a2enmod proxy_http
a2enmod proxy_wstunnel
a2enmod rewrite

```

## Устнанавливаем php sqlite.
### Проверьте вашу версию php
```
php -v
```
### если версия 5, то устанавливаем пакет php5-sqlite для этой версии:
```
apt-get update
apt-get install php5-sqlite
```
### если же 7 версия, то устанавливаем php-sqlite3
```
apt-get install php-sqlite3
phpenmod sqllite3
```

## Обновление конфигурации Web сервера Apache
### Сравните файлы сервера apache /etc/apache/apache2.conf и /etc/apache/sites-enabled/000-default.conf с файлами https://github.com/MimiSmart/mimi-server/tree/main/apache, если есть отличия, внесите их в вашу кофигурацию. После того как сделали изменения выполните слудующие команды:
```
service apache2 restart
service apache2 status
```
### После выполнения команд вы должны получить работающий сервер apache.
```
● apache2.service - The Apache HTTP Server
     Loaded: loaded (/lib/systemd/system/apache2.service; enabled; vendor preset: enabled)
     Active: active (running) since Mon 2023-03-13 13:17:38 GMT; 5 days ago
       Docs: https://httpd.apache.org/docs/2.4/
    Process: 356 ExecStart=/usr/sbin/apachectl start (code=exited, status=0/SUCCESS)
   Main PID: 401 (apache2)
      Tasks: 11 (limit: 415)
        CPU: 13min 35.936s
     CGroup: /system.slice/apache2.service
             ├─  401 /usr/sbin/apache2 -k start
             ├─ 2365 /usr/sbin/apache2 -k start
             ├─ 2366 /usr/sbin/apache2 -k start
             ├─ 2367 /usr/sbin/apache2 -k start
             ├─22674 /usr/sbin/apache2 -k start
             ├─28463 /usr/sbin/apache2 -k start
             ├─28520 /usr/sbin/apache2 -k start
             ├─28807 /usr/sbin/apache2 -k start
             ├─29991 /usr/sbin/apache2 -k start
             ├─30005 /usr/sbin/apache2 -k start
             └─30463 /usr/sbin/apache2 -k start

Mar 13 13:17:35 raspberrypi systemd[1]: Starting The Apache HTTP Server...
Mar 13 13:17:37 raspberrypi apachectl[382]: AH00558: apache2: Could not reliably determine the server's fully qualified domain name, using 127.0.1.1. Set the 'ServerName' dir>
Mar 13 13:17:38 raspberrypi systemd[1]: Started The Apache HTTP Server.
```
## Обновление API.
### Скопируйте файл https://github.com/MimiSmart/mimi-server/blob/main/api/api.php в диреторию /home/api
```
curl -o /home/api/api.php https://github.com/MimiSmart/mimi-server/blob/main/api/api.php
```

## Обновление API плагина
### Скопируйте файл https://github.com/MimiSmart/mimi-server/blob/main/plugin/api_plugin.so в директорию /home/sh2/plugins и перезапустите сервер через screen. Если у вас имеется несколько screen, выберите screen сервера. Для выхода из screen используйте комбинацию клавиш Ctrl+A+D.
```
curl -o /home/sh2/plugins/api_plugins.so https://github.com/MimiSmart/mimi-server/blob/main/plugin/api_plugin.so
screen -rx
qu
```

## Сервис для камер видеонаблюдения
### Чтобы в новом приложении работали камеры видеонаблюдения, установите rtsp-server на ваш сервер.
```
curl -o /usr/local/bin/rtsp-simple-server https://github.com/MimiSmart/mimi-server/blob/main/rtsp-server/rtsp-simple-server
curl -o /usr/local/etc/rtsp-simple-server.yml https://github.com/MimiSmart/mimi-server/blob/main/rtsp-server/rtsp-simple-server.yml
chmod +x /usr/local/bin/rtsp-simple-server

```

### Для автоматического запуcка сервера при старте системы создайте службу.
```
tee /etc/systemd/system/rtsp-simple-server.service >/dev/null << EOF
[Unit]
Wants=network.target
[Service]
ExecStart=/usr/local/bin/rtsp-simple-server /usr/local/etc/rtsp-simple-server.yml
[Install]
WantedBy=multi-user.target
EOF
```

### Включите и стартуйте службу.
```
systemctl daemon-reload
systemctl enable rtsp-simple-server
systemctl start rtsp-simple-server
```

## Для корректной работы приложения сделайте переадресацию портов на роутере (15580-->192.168.1.125:80), если она еще не была сделана. В настройках приложения укажите порт 15580 в качестве улалённого порта, а в качестве локального укажите 80. Всё сервер готов к работе с новым приложением.
