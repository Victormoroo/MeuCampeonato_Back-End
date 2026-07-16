#!/bin/sh
set -e

# Instala as dependências PHP (no volume nomeado montado em vendor) antes de
# iniciar a aplicação. Modo não interativo, adequado para desenvolvimento.
# Importante:
#  - usa "install" (nunca "update"), portanto NÃO altera o composer.lock;
#  - NÃO executa migrations;
#  - NÃO altera permissões de forma ampla.
composer install --no-interaction --prefer-dist --no-progress

# Encaminha corretamente os sinais ao processo principal (o CMD da imagem,
# ou seja, "php artisan serve --host=0.0.0.0 --port=8000").
exec "$@"
