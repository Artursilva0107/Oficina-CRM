#!/bin/sh
set -e

# O Render define a porta que o container deve escutar através da
# variável de ambiente PORT (varia a cada deploy). O Apache, por padrão,
# está configurado pra escutar sempre na 80 — ajustamos isso aqui antes
# de iniciar, senão o Render nunca detecta o serviço como "no ar".
PORT="${PORT:-80}"

sed -i "s/^Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

exec "$@"
