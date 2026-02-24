FROM php:8.2-apache

# ติดตั้ง PDO และ MySQLi extension
RUN docker-php-ext-install pdo pdo_mysql mysqli

# เปิดใช้งาน mod_rewrite
RUN a2enmod rewrite

# Configure Apache
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/docker-php.conf \
    && a2enconf docker-php

# ตั้งค่า Apache ให้ประมวลผลไฟล์ PHP
RUN echo "AddType application/x-httpd-php .php" >> /etc/apache2/apache2.conf

# คัดลอกไฟล์ทั้งหมดไปยัง container
COPY . /var/www/html

# ตั้งค่า permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# ลบไฟล์ที่ไม่ควรอยู่ใน web root
RUN cd /var/www/html && \
    rm -f Dockerfile docker-compose.yml composer.json composer.lock README.md .gitignore

EXPOSE 80