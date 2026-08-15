FROM php:8.1-cli

COPY . /app
WORKDIR /app

# Jalankan server PHP dengan direktori root /app/www
CMD php -S 0.0.0.0:${PORT:-8080} -t www