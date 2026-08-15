FROM php:8.1-cli

# Langsung salin isi folder www ke dalam root app
COPY ./www /app
WORKDIR /app

# Jalankan server PHP langsung tanpa router tambahan
CMD php -S 0.0.0.0:${PORT:-8080}