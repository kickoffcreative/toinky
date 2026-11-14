FROM php:8.4-apache@sha256:884f64b47174de2c0e813270fe7b24a18605729fcebe201302d98c38805530d0
RUN a2enmod rewrite headers
COPY .htaccess /var/www/html/.htaccess
COPY app/.htaccess /var/www/html/app/.htaccess
COPY app/SecurityHeaders.php /var/www/html/app/SecurityHeaders.php
COPY config/.htaccess /var/www/html/config/.htaccess
COPY database/.htaccess /var/www/html/database/.htaccess
COPY tools/.htaccess /var/www/html/tools/.htaccess
COPY public/.htaccess /var/www/html/public/.htaccess
COPY public/uploads/.htaccess /var/www/upload-policy/.htaccess
COPY public/uploads/.htaccess /var/www/html/public/uploads/.htaccess
COPY install.php /var/www/html/install.php
COPY tools/fixtures/apache_upload_setup.php /tmp/setup.php
COPY tools/apache_upload_guard_test.php /tmp/guard.php
RUN php /tmp/setup.php
