# Servidor web do Cali Burger: PHP 8.3 + Apache servindo a pasta public/.
FROM php:8.3-apache

RUN docker-php-ext-install mysqli \
    && a2enmod rewrite headers

# Só a pasta public/ fica acessível pelo navegador; app/ e database/ ficam de fora.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf

COPY docker/php.ini /usr/local/etc/php/conf.d/caliburger.ini
COPY docker/apache.conf /etc/apache2/conf-available/caliburger.conf
RUN a2enconf caliburger

COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html/public/imagens/lanches
