# Imagem para publicar o site na nuvem (Render.com) sem precisar do XAMPP.
# Roda Apache + PHP 8 + MariaDB no mesmo contêiner. O banco é criado a partir de
# database/arte_na_cozinha.sql sempre que o contêiner inicia (dados de demonstração).
FROM php:8.2-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends mariadb-server \
 && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers expires deflate \
 && sed -ri 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

COPY . /var/www/html/
RUN rm -rf /var/www/html/.git /var/www/html/includes/config.local.php \
 && chown -R www-data:www-data /var/www/html/uploads \
 && chmod +x /var/www/html/deploy/iniciar.sh

ENV PORT=10000
EXPOSE 10000
CMD ["/var/www/html/deploy/iniciar.sh"]
