FROM php:8.2-apache

RUN a2enmod rewrite headers
RUN docker-php-ext-install pdo pdo_mysql

COPY . /var/www/html/

RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/app.conf \
    && a2enconf app

EXPOSE 80
