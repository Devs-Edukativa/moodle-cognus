# Use uma imagem base oficial do PHP com Apache
FROM php:8.3-apache

# Instalar dependências de sistema para o Moodle
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libxml2-dev \
    libzip-dev \
    zlib1g-dev \
    libicu-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    libldap2-dev \
    ghostscript \
    git \
    unzip \
    g++ \
    && rm -rf /var/lib/apt/lists/*

# Configurar e instalar extensões PHP necessárias para o Moodle
RUN docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    mysqli \
    pdo \
    pdo_mysql \
    soap \
    zip \
    intl \
    opcache \
    exif

# Configurar Apache
RUN a2enmod rewrite expires headers ssl

# Configurar PHP para Moodle
RUN { \
    echo 'memory_limit = 4G'; \
    echo 'upload_max_filesize = 10G'; \
    echo 'post_max_size = 10G'; \
    echo 'max_execution_time = 300'; \
    echo 'max_input_vars = 5000'; \
    echo 'opcache.enable = 1'; \
    echo 'opcache.memory_consumption = 128'; \
    echo 'opcache.interned_strings_buffer = 8'; \
    echo 'opcache.max_accelerated_files = 4000'; \
    echo 'opcache.revalidate_freq = 60'; \
    echo 'opcache.fast_shutdown = 1'; \
} > /usr/local/etc/php/conf.d/moodle.ini

# Copiar código do Moodle para o container
COPY . /var/www/html/

# Criar diretório para moodledata (será montado como volume)
RUN mkdir -p /var/www/moodledata && \
    chown -R www-data:www-data /var/www/moodledata

# Ajustar permissões do código
RUN chown -R www-data:www-data /var/www/html

# Configurar DocumentRoot
RUN sed -i 's|/var/www/html|/var/www/html|g' /etc/apache2/sites-available/000-default.conf

# Expor porta 80
EXPOSE 80

# Healthcheck
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Iniciar Apache
CMD ["apache2-foreground"]