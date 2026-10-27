FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip git curl ca-certificates libaio1t64 libzip-dev libicu-dev \
    && ln -sf /usr/lib/$(uname -m)-linux-gnu/libaio.so.1t64 /usr/lib/$(uname -m)-linux-gnu/libaio.so.1 \
    && rm -rf /var/lib/apt/lists/*

# Oracle Instant Client 23 (basic + sdk)
RUN mkdir -p /opt/oracle \
    && cd /opt/oracle \
    && curl -fsSLO https://download.oracle.com/otn_software/linux/instantclient/instantclient-basic-linuxx64.zip \
    && curl -fsSLO https://download.oracle.com/otn_software/linux/instantclient/instantclient-sdk-linuxx64.zip \
    && unzip -qo instantclient-basic-linuxx64.zip \
    && unzip -qo instantclient-sdk-linuxx64.zip \
    && rm -f *.zip \
    && mv /opt/oracle/instantclient_* /opt/oracle/instantclient \
    && echo /opt/oracle/instantclient > /etc/ld.so.conf.d/oracle-instantclient.conf \
    && ldconfig

ENV LD_LIBRARY_PATH=/opt/oracle/instantclient

RUN docker-php-ext-install -j"$(nproc)" pcntl intl zip \
    && echo "instantclient,/opt/oracle/instantclient" | pecl install oci8 \
    && docker-php-ext-enable oci8

# Edital: PDF de até 20 MB e imagem de até 5 MB num mesmo envio.
# O cadastro de proponente (até 7 arquivos) mantém a própria regra de 2 MB por arquivo, conferida na validação.
RUN printf "upload_max_filesize=20M\npost_max_size=32M\n" > /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000
CMD ["sh", "-c", "php artisan storage:link --force; exec php artisan serve --host=0.0.0.0 --port=8000"]
