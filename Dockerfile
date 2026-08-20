FROM php:8.2-apache

# Extensões PHP necessárias pelo projeto (conexão com MySQL/Aiven)
RUN docker-php-ext-install pdo pdo_mysql mysqli

RUN a2enmod rewrite

# Evita warning de "fully qualified domain name" nos logs
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Composer, para instalar o Dompdf (geração de PDF de orçamento/recibo)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copia primeiro só o composer.json para aproveitar cache de camada do Docker
COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist || true

# Copia o restante do projeto
COPY . .

# Aponta o DocumentRoot do Apache para a pasta public/ (é aí que ficam
# os front controllers do projeto — o resto do código fica fora do ar)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Garante que a pasta storage (uploads, logs) seja gravável
RUN mkdir -p storage/uploads/audio storage/uploads/fotos storage/uploads/pdf storage/logs \
    && chown -R www-data:www-data storage

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
