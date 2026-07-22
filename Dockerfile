FROM php:8.4-fpm

# Set working directory
WORKDIR /var/www

# Install dependencies for PHP, Node, and Puppeteer
RUN sed -i 's/Components: main/Components: main contrib non-free non-free-firmware/g' /etc/apt/sources.list.d/debian.sources || sed -i 's/main/main contrib non-free/g' /etc/apt/sources.list || true \
    && apt-get update \
    && apt-get install -y debconf-utils \
    && echo ttf-mscorefonts-installer msttcorefonts/accepted-mscorefonts-eula select true | debconf-set-selections \
    && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libsqlite3-dev \
    zip \
    unzip \
    git \
    curl \
    libnss3 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libcups2 \
    libdrm2 \
    libxkbcommon0 \
    libxcomposite1 \
    libxdamage1 \
    libxfixes3 \
    libxrandr2 \
    libgbm1 \
    libasound2 \
    libpango-1.0-0 \
    libcairo2 \
    libxss1 \
    libx11-xcb1 \
    libxcb1 \
    libx11-6 \
    libglib2.0-0 \
    fonts-liberation \
    ttf-mscorefonts-installer \
    fonts-farsiweb \
    zlib1g-dev \
    xdg-utils \
    wget \
    gnupg \
    chromium \
    libreoffice \
    ghostscript \
    imagemagick \
    libmagickwand-dev \
    pandoc \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && sed -i 's/rights="none" pattern="PDF"/rights="read|write" pattern="PDF"/g' /etc/ImageMagick-6/policy.xml || true \
    && sed -i '/<policymap>/a \  <policy domain="coder" rights="read|write" pattern="PDF" />' /etc/ImageMagick-6/policy.xml || true \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring exif pcntl bcmath gd xml zip \
    && pecl install imagick \
    && docker-php-ext-enable imagick

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Puppeteer globally
RUN npm install -g puppeteer

# Setup directory and assign permissions
COPY . /var/www
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
