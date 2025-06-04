#!/bin/bash

DOMAIN="wordpress-rufino.duckdns.org"
EMAIL="rllatan01@educantabria.es"
APACHE_IPS=("10.215.20.100" "10.215.20.101")

apt update
apt install -y certbot openssh-client
systemctl stop haproxy
# Obtener certificado
certbot certonly --standalone --non-interactive --agree-tos --email $EMAIL -d $DOMAIN
# Combinar .pem
mkdir -p /etc/haproxy/certs
cat /etc/letsencrypt/live/$DOMAIN/fullchain.pem /etc/letsencrypt/live/$DOMAIN/privkey.pem > /etc/haproxy/certs/$DOMAIN.pem
chmod 600 /etc/haproxy/certs/$DOMAIN.pem
rm -r /etc/haproxy/haproxy.cfg
# Configurar HAProxy
cat << EOF > /etc/haproxy/haproxy.cfg
global
    log /dev/log local0
    log /dev/log local1 notice
    daemon
    maxconn 2048

defaults
    log global
    mode http
    option httplog
    timeout connect 5s
    timeout client 50s
    timeout server 50s

frontend https_front
    bind *:443 ssl crt /etc/haproxy/certs/$DOMAIN.pem
    default_backend wordpress_backend

frontend http_front
    bind *:8080
    redirect scheme https if !{ ssl_fc }

backend wordpress_backend
    balance roundrobin
    server web1 10.215.20.100:443 ssl verify none check
    server web2 10.215.20.101:443 ssl verify none check
    
EOF
    
systemctl enable haproxy
systemctl restart haproxy

# Copiar certificados a los servidores Apache usando la clave generada
for ip in "${APACHE_IPS[@]}"; do
  echo "Copiando certificados a $ip..."
  scp -i /home/ubuntu/vockey.pem /etc/letsencrypt/live/$DOMAIN/fullchain.pem ubuntu@$ip:/tmp/
  scp -i /home/ubuntu/vockey.pem /etc/letsencrypt/live/$DOMAIN/privkey.pem ubuntu@$ip:/tmp/
done
