# Imagem base: PHP 8.4 CLI, com versão de patch fixada explicitamente (sem "latest").
FROM php:8.4.23-cli-bookworm

# Dependências de sistema (somente o necessário):
#  - ca-certificates: TLS para o Composer alcançar o Packagist
#  - git, unzip: exigidas pelo Composer para baixar/extrair pacotes
#  - libonig-dev: dependência de compilação da extensão mbstring
#  - python3: para executar futuramente o script Python do campeonato
# Extensões PHP:
#  - pdo_mysql: conexão da aplicação com o MySQL
#  - mbstring: exigida pelo Laravel
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        git \
        unzip \
        libonig-dev \
        python3 \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer disponibilizado copiando o binário da imagem oficial composer,
# com a versão fixada explicitamente em 2.10.2.
COPY --from=composer:2.10.2 /usr/bin/composer /usr/bin/composer

# Diretório de trabalho da aplicação.
WORKDIR /var/www/html

# Entrypoint: copiado e tornado executável na imagem, independentemente
# das permissões do arquivo no host (macOS).
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Código-fonte da aplicação. Em desenvolvimento, o bind mount sobrepõe este
# conteúdo, e o diretório vendor é fornecido por um volume nomeado.
COPY . .

# Porta do servidor de desenvolvimento do artisan.
EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
