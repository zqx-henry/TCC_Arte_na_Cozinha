#!/bin/sh
# Inicia o banco (MariaDB) e o Apache dentro do contêiner da hospedagem.
set -e

PORT="${PORT:-10000}"
DB_SENHA="${DB_SENHA:-$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')}"
CHAVE_APP="${CHAVE_APP:-$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')}"

# 1. Banco de dados
mkdir -p /run/mysqld && chown mysql:mysql /run/mysqld
if [ ! -d /var/lib/mysql/mysql ]; then
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql > /dev/null
fi
mariadbd --user=mysql --bind-address=127.0.0.1 --skip-networking=0 &
for i in $(seq 1 30); do
    mariadb-admin ping --silent 2> /dev/null && break
    sleep 1
done

if ! mariadb -e "USE arte_na_cozinha; SELECT 1 FROM produto LIMIT 1" > /dev/null 2>&1; then
    mariadb --default-character-set=utf8mb4 < /var/www/html/database/arte_na_cozinha.sql
fi
mariadb -e "CREATE USER IF NOT EXISTS 'site'@'127.0.0.1' IDENTIFIED BY '$DB_SENHA';
            ALTER USER 'site'@'127.0.0.1' IDENTIFIED BY '$DB_SENHA';
            GRANT ALL ON arte_na_cozinha.* TO 'site'@'127.0.0.1';
            FLUSH PRIVILEGES;"

# 2. Configuração do site (fica só dentro do contêiner, nunca vai para o GitHub)
cat > /var/www/html/includes/config.local.php <<PHP
<?php
return [
    'db_host'    => '127.0.0.1',
    'db_porta'   => 3306,
    'db_nome'    => 'arte_na_cozinha',
    'db_usuario' => 'site',
    'db_senha'   => '$DB_SENHA',
    'chave_app'  => '$CHAVE_APP',
    'debug'      => false,
];
PHP
chown www-data:www-data /var/www/html/includes/config.local.php

# 3. Apache na porta que a hospedagem informar
sed -ri "s/^Listen .*/Listen $PORT/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf
exec apache2-foreground
