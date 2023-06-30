#!/bin/bash
mkdir /etc/iptables
touch /etc/iptables/rules.v4
iptables-save > /etc/iptables/rules.v4
iptables -A INPUT -p tcp -s 192.168.1.0/24 -j ACCEPT
iptables -A INPUT -p udp -s 192.168.1.0/24 -j ACCEPT
iptables -A INPUT -s 89.17.55.74 -p tcp --dport 22 -j ACCEPT
iptables -A INPUT -s 89.17.55.74 -p tcp --dport 80 -j ACCEPT
iptables -A INPUT -s 89.17.55.74 -p tcp --dport 55555 -j ACCEPT
iptables -A INPUT -s 77.91.110.138 -p tcp --dport 22 -j ACCEPT
iptables -A INPUT -s 77.91.110.138 -p tcp --dport 80 -j ACCEPT
iptables -A INPUT -s 77.91.110.138 -p tcp --dport 55555 -j ACCEPT
iptables -A INPUT -s 195.208.108.246 -p tcp --dport 22 -j ACCEPT
iptables -A INPUT -s 195.208.108.246 -p tcp --dport 80 -j ACCEPT
iptables -A INPUT -s 195.208.108.246 -p tcp --dport 55555 -j ACCEPT
iptables -A INPUT -i lo -j ACCEPT
iptables -A OUTPUT -o lo -j ACCEPT
iptables -P INPUT DROP
iptables-save > /etc/iptables/rules.v4
echo "[Unit]
Description=IPv4 iptables firewall rules
Before=network.target

[Service]
ExecStart=/sbin/iptables-restore /etc/iptables/rules.v4
ExecReload=/sbin/iptables-restore /etc/iptables/rules.v4
ExecStop=/sbin/iptables-save -f /etc/iptables/rules.v4

[Install]
WantedBy=multi-user.target" > /etc/systemd/system/iptables-restore.service
systemctl daemon-reload
systemctl enable iptables-restore.service
echo "Обновление сервера успешно ввыполнено. Мои поздравления. Ухожу в перезагрузку."
reboot
