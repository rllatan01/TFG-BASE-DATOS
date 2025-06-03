UserData:
  Fn::Base64: !Sub |
    #!/bin/bash
    DOMAIN="wordpress-rufino.duckdns.org"
    SSL_DIR="/etc/ssl/live/$DOMAIN"

    mkdir -p $SSL_DIR
    mv /tmp/fullchain.pem $SSL_DIR/
    mv /tmp/privkey.pem $SSL_DIR/
    chmod 600 $SSL_DIR/privkey.pem
    chmod 644 $SSL_DIR/fullchain.pem

    a2enmod ssl

    cat > /etc/apache2/sites-available/$DOMAIN-ssl.conf <<EOF
    <VirtualHost *:443>
        ServerName $DOMAIN
        DocumentRoot /var/www/html

        SSLEngine on
        SSLCertificateFile $SSL_DIR/fullchain.pem
        SSLCertificateKeyFile $SSL_DIR/privkey.pem

        <Directory /var/www/html>
            AllowOverride All
        </Directory>
    </VirtualHost>
    EOF

    a2ensite $DOMAIN-ssl
    a2dissite 000-default.conf
    systemctl reload apache2
