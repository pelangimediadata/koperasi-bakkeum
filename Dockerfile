FROM php:8.1-cli

# Salin seluruh isi project ke /app di dalam container
COPY . /app
WORKDIR /app

# Jalankan server PHP dengan router yang mengarah ke folder www
CMD php -S 0.0.0.0:${PORT:-8080} router.php