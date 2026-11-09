FROM php:8.2-apache
RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers
# Production-friendly PHP settings: mas malaking upload (logo/QR/products), at hindi ipinapakita ang errors sa browser
RUN printf 'upload_max_filesize=6M\npost_max_size=10M\ndisplay_errors=Off\nlog_errors=On\nexpose_php=Off\n' > /usr/local/etc/php/conf.d/kapihan.ini
COPY . /var/www/html/
RUN mkdir -p /var/www/html/uploads/products /var/www/html/uploads/settings \
 && chown -R www-data:www-data /var/www/html/uploads
