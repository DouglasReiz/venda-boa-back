FROM php:8.5-fpm

# Instala dependências do sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Limpa o cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Instala extensões do PHP necessárias para o Laravel e Financeiro
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Instala o Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Define o diretório de trabalho
WORKDIR /var/www

EXPOSE 9000
CMD ["php-fpm"]