FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        python3 python3-pip ffmpeg \
    && rm -rf /var/lib/apt/lists/* \
    && python3 -m pip install --no-cache-dir --break-system-packages yt-dlp

RUN a2enmod rewrite headers

COPY apache-render.conf /etc/apache2/sites-available/000-render.conf
RUN a2ensite 000-render

COPY entrypoint.sh /usr/local/bin/ytdl-entry.sh
RUN chmod +x /usr/local/bin/ytdl-entry.sh

WORKDIR /var/www/html
COPY . /var/www/html

EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/ytdl-entry.sh"]
CMD ["apache2-foreground"]