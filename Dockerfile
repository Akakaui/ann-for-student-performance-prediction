FROM php:8.2-cli

# Install PostgreSQL extension
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Install Python
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    && rm -rf /var/lib/apt/lists/*

# Install Python dependencies
COPY python_model/requirements.txt /app/python_model/requirements.txt
RUN pip3 install --break-system-packages -r /app/python_model/requirements.txt

# Copy application files
COPY . /app

WORKDIR /app/php_app/public

EXPOSE $PORT

CMD php -S 0.0.0.0:$PORT
