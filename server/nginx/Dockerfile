# Alpine Linux üzərində minimal Nginx serveri yaradılır
FROM nginx:alpine

LABEL maintainer="Rasim Aghayev <rasimaqayev@gmail.com>" \
      version="1.0" \
      description="Production-ready Nginx server"

# Nginx istifadəçisi və qrupunun mövcudluğunu yoxlayıb yaratmaq
RUN addgroup -g 101 -S nginx && \
    adduser -S -D -H -u 101 -h /var/cache/nginx -s /sbin/nologin -G nginx -g nginx nginx || true

# Lazımi paketlərin quraşdırılması
RUN apk add --no-cache \
    curl \
    tzdata \
    ca-certificates

# Nginx üçün lazım olan direktoriyaların yaradılması və icazələrin verilməsi
RUN mkdir -p /var/cache/nginx \
             /var/run/nginx \
             /var/log/nginx \
             /var/www/html/be \
             /var/www/html/fe \
             /etc/nginx/conf.d && \
    chown -R nginx:nginx /var/cache/nginx \
                         /var/run/nginx \
                         /var/log/nginx \
                         /var/www/html

# Xüsusi Nginx konfiqurasiyasının kopyalanması
COPY --chown=nginx:nginx ./server/nginx/nginx.conf /etc/nginx/nginx.conf
COPY --chown=nginx:nginx ./server/nginx/default.conf /etc/nginx/conf.d/default.conf

# Statik faylların kopyalanması
COPY --chown=nginx:nginx ./src/be/ /var/www/html/be

# Sağlamlıq yoxlaması əlavə edirik
HEALTHCHECK --interval=30s --timeout=3s \
    CMD curl -f http://localhost/up || exit 1

# İşçi direktoriyasını müəyyən edirik
WORKDIR /var/www/html

# Portları expose edirik
EXPOSE 80 443

# Nginx istifadəçisinə keçirik
USER nginx

# Nginx serverini başlatırıq
CMD ["nginx", "-g", "daemon off;"]
