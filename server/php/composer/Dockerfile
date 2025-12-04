# Composer əsas imicini istifadə edirik
FROM composer:latest

# Exif genişləndirilməsi üçün lazım olan paketləri quraşdırırıq
RUN apk update && \
    apk add --no-cache \
    libjpeg-turbo-dev \
    libexif-dev && \
    docker-php-ext-install exif && \
    apk del libjpeg-turbo-dev libexif-dev  # müvəqqəti asılılıqları silirik

# Əlavə olaraq, lazım olsa PHP və digər uzantıları quraşdıra bilərik
