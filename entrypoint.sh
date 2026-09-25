#!/bin/sh
set -e

php artisan config:cache

# Rotas e eventos em cache: evita reler/compilar routes/*.php e varrer os
# listeners em toda requisição. Não é crítico — se falhar por algum motivo,
# o app sobe normalmente sem esse cache em vez de derrubar o deploy.
php artisan route:cache || echo "route:cache falhou, seguindo sem cache de rotas"
php artisan event:cache || echo "event:cache falhou, seguindo sem cache de eventos"

# O servidor embutido do PHP (usado pelo `artisan serve`) atende UMA
# requisição por vez quando PHP_CLI_SERVER_WORKERS não está definido. Medido
# em produção: 6 chamadas paralelas (o que qualquer página do front dispara)
# ficavam enfileiradas e a última só respondia depois de ~12s, mesmo cada
# uma levando ~2s sozinha. Com vários workers elas passam a ser atendidas em
# paralelo. 4 cabe folgado na memória da instância (cada worker ~30-40MB).
# --no-reload é obrigatório: sem ele o `artisan serve` ignora a variável
# (só avisa "Unable to respect PHP_CLI_SERVER_WORKERS") e sobe um único
# servidor. Recarregar ao mudar o .env não serve pra nada dentro do container.
export PHP_CLI_SERVER_WORKERS=${PHP_CLI_SERVER_WORKERS:-4}

# Worker de fila contínuo (coleta + resumo de notícias). Antes a fila só
# andava quando um cron externo chamava /api/schedule/processar-fila-noticias
# a cada poucos minutos; quando ele não chamava, os resumos expiravam sem
# nunca rodar (visto no banco em 25/09: notícias de 24/09 "attempted too many
# times" com 0 tentativas). Agora qualquer Job despachado — pelo ciclo de 12h
# ou pelo botão do admin — é processado logo. Loop pra se reerguer sozinho se
# o worker cair; --max-time recicla o processo a cada hora. QUEUE_WORKER=0
# desliga (os endpoints de drenagem continuam funcionando como antes).
if [ "${QUEUE_WORKER:-1}" = "1" ]; then
  (
    while true; do
      php artisan queue:work --queue=coleta,resumo --sleep=5 --max-time=3600 --memory=128 || true
      sleep 5
    done
  ) &
fi

php artisan serve --host=0.0.0.0 --port=${PORT:-10000} --no-reload
