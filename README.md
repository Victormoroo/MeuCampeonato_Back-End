# Meu Campeonato — API

API REST que cadastra e **simula um campeonato eliminatório de exatamente 8 times**, com quartas de
final, semifinais, disputa de terceiro lugar e final. Os placares de cada partida são gerados por um
**script Python real (`teste.py`)** executado pela aplicação. O projeto roda **inteiramente com
Docker** — nada precisa ser instalado no host além do Git e do Docker.

## Sumário

- [Tecnologias](#tecnologias)
- [Requisitos locais](#requisitos-locais)
- [Instalação e execução](#instalação-e-execução)
- [Configuração](#configuração)
- [Regras do campeonato](#regras-do-campeonato)
- [Fluxo da API](#fluxo-da-api)
- [Endpoints](#endpoints)
- [Exemplos (curl)](#exemplos-curl)
- [Estrutura da resposta](#estrutura-da-resposta)
- [Arquitetura e decisões técnicas](#arquitetura-e-decisões-técnicas)
- [Banco de dados](#banco-de-dados)
- [Testes e qualidade](#testes-e-qualidade)
- [Banco de desenvolvimento](#banco-de-desenvolvimento)
- [Collection Postman](#collection-postman)

## Tecnologias

Versões de imagem fixadas (sem `latest`), confirmadas nos arquivos Docker:

| Componente | Versão | Onde |
|-----------|--------|------|
| PHP | 8.4.23 (CLI) | `docker/php/Dockerfile` (`php:8.4.23-cli-bookworm`) |
| Laravel | 13.x | `composer.json` |
| MySQL | 8.4.10 | `compose.yaml` (`mysql:8.4.10`) |
| Python | 3.11 | instalado no contêiner da aplicação |
| Composer | 2.10.2 | copiado da imagem `composer:2.10.2` |
| PHPUnit | — | dependência de desenvolvimento |
| Laravel Pint | — | dependência de desenvolvimento |

Extensões PHP habilitadas na imagem: `pdo_mysql` e `mbstring`.

## Requisitos locais

No computador do avaliador são necessários **apenas**:

- **Git**
- **Docker** com **Docker Compose v2** (comando `docker compose`)

**PHP, Composer, MySQL e Python não precisam ser instalados no host** — todos rodam dentro dos
contêineres.

## Instalação e execução

Fluxo para um clone novo:

```bash
git clone https://github.com/Victormoroo/MeuCampeonato_Back-End.git
cd MeuCampeonato_Back-End
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Notas importantes:

- A aplicação fica disponível em **http://localhost:8000**.
- O **MySQL não é publicado no host** — apenas o serviço `app` o acessa, pela rede interna do Compose.
- As dependências PHP são instaladas **pelo entrypoint, dentro do contêiner** (`composer install` na
  subida); por isso a primeira execução é mais lenta.
- Os dados do banco ficam em um **volume Docker** (`mysql-data`) e persistem entre reinícios.
- **Não use `docker compose down -v`** se quiser preservar os dados — a flag `-v` remove os volumes.

Comandos úteis do dia a dia:

```bash
docker compose ps            # estado dos serviços
docker compose logs -f app   # logs da aplicação (Ctrl+C para sair)
docker compose stop          # para os serviços mantendo tudo
docker compose down          # remove os contêineres, mantém os volumes/dados
```

## Configuração

O `.env.example` já vem com valores **locais de desenvolvimento**; não é preciso editar nada para
começar. Variáveis relevantes:

| Variável | Valor padrão | Descrição |
|----------|--------------|-----------|
| `APP_URL` | `http://localhost:8000` | URL base da aplicação |
| `DB_CONNECTION` | `mysql` | Driver do banco |
| `DB_HOST` | `mysql` | **Nome do serviço** no Docker Compose (não é `localhost`) |
| `DB_PORT` | `3306` | Porta interna do MySQL |
| `DB_DATABASE` | `meu_campeonato` | Nome do banco |
| `DB_USERNAME` | `meu_campeonato` | Usuário do banco (desenvolvimento) |
| `DB_PASSWORD` | `secret` | Senha do banco (desenvolvimento) |
| `PYTHON_BINARY` | `python3` | Binário usado para executar `teste.py` |
| `PYTHON_PROCESS_TIMEOUT` | `5` | Timeout (segundos) do processo Python |

`DB_HOST=mysql` funciona porque `mysql` é o nome do serviço no `compose.yaml`, resolvido pela rede
do Docker. A `APP_KEY` é gerada localmente por `php artisan key:generate` e **não** é versionada.

## Regras do campeonato

- Um campeonato tem **exatamente 8 times**.
- Os nomes são **normalizados com `trim`** antes da validação.
- Os nomes dos times devem ser **distintos ignorando maiúsculas/minúsculas** (e espaços nas bordas).
- **Quartas de final:** os 8 times são **sorteados** em 4 confrontos.
- **Semifinais:** os 4 vencedores das quartas são **embaralhados novamente** e formam 2 confrontos.
- **Terceiro lugar:** disputado pelos **perdedores das semifinais**.
- **Final:** disputada pelos **vencedores das semifinais**.
- Ao final, `final_position` recebe: **1** (campeão), **2** (vice), **3** (vencedor do terceiro lugar)
  e **4** (perdedor do terceiro lugar). Os **eliminados nas quartas ficam com `final_position` null**
  (o enunciado não define posições de 5 a 8).
- Cada partida recebe **dois placares entre 0 e 7**, gerados pelo `teste.py`.
- A **pontuação acumulada** de cada time é **gols marcados menos gols sofridos** em suas partidas.
- A soma das variações de pontos dos dois times em uma partida é **sempre zero**.
- O **vencedor** é definido, nesta ordem:
  1. maior **placar**;
  2. em empate no placar, maior **pontuação acumulada**;
  3. persistindo o empate, menor **`registration_order`** (inscrito primeiro).

Não há prorrogação, pênaltis nem empate final: toda partida sempre define vencedor e perdedor.

## Fluxo da API

1. **Cadastrar** um campeonato com 8 times (`POST /api/championships`).
2. **Simular** o campeonato usando o `id` retornado (`POST /api/championships/{championship}/simulate`).
3. **Consultar** o campeonato e seu histórico (`GET /api/championships/{championship}`).
4. **Listar** os campeonatos (`GET /api/championships`).

## Endpoints

| Método | Rota | Objetivo | Códigos HTTP |
|--------|------|----------|--------------|
| `POST` | `/api/championships` | Cadastra um campeonato com 8 times | `201`, `422` |
| `POST` | `/api/championships/{championship}/simulate` | Simula o campeonato completo | `200`, `404`, `409`, `502` |
| `GET` | `/api/championships/{championship}` | Detalhe/histórico de um campeonato | `200`, `404` |
| `GET` | `/api/championships` | Lista paginada de campeonatos | `200` |

Significado dos códigos:

- **201** — campeonato criado.
- **200** — consultas e simulação bem-sucedidas.
- **422** — falha de validação no cadastro (ex.: != 8 times, nomes duplicados, nome vazio).
- **404** — campeonato inexistente.
- **409** — campeonato não pode ser simulado (não está `pending`, não tem 8 times, ou já tem partidas).
- **502** — o gerador externo de placar (Python) falhou.

Não existem endpoints de atualização ou remoção.

## Exemplos (curl)

Todos os exemplos enviam `Accept: application/json`. A **criação** também envia
`Content-Type: application/json`, pois possui corpo JSON; os `GET` e o `POST` de simulação (sem corpo)
não precisam de `Content-Type`. O `id` retornado na criação deve substituir `{championship}` (abaixo,
`1`) nos comandos seguintes.

**1. Criar campeonato (8 times distintos):**

```bash
curl -X POST http://localhost:8000/api/championships \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Copa do Bairro",
    "teams": ["Águias", "Tigres", "Leões", "Panteras", "Falcões", "Lobos", "Dragões", "Tubarões"]
  }'
```

Resposta (resumida):

```text
{ "data": { "id": 1, "name": "Copa do Bairro", "status": "pending", "teams": [ ... ] } }
```

**2. Simular (use o `id` retornado no lugar de `1`):**

```bash
curl -X POST http://localhost:8000/api/championships/1/simulate \
  -H "Accept: application/json"
```

Resposta (resumida):

```text
{ "data": { "id": 1, "status": "completed", "started_at": "...", "completed_at": "...",
  "teams": [ ... ], "games": [ ... ] } }
```

**3. Consultar o detalhe:**

```bash
curl http://localhost:8000/api/championships/1 \
  -H "Accept: application/json"
```

**4. Listar campeonatos:**

```bash
curl http://localhost:8000/api/championships \
  -H "Accept: application/json"
```

Resposta (resumida):

```text
{ "data": [ ... ], "links": { ... }, "meta": { ... } }
```

## Estrutura da resposta

- Toda resposta de recurso usa o **envelope `data`**.
- A **listagem** é paginada, com `data`, `links` e `meta`.
- No **detalhe**, os `teams` vêm **ordenados por `registration_order`** e os `games` **ordenados por
  `id`**.
- Em cada partida, **`home_team`** e **`away_team`** representam os participantes; **`winner`** e
  **`loser`** ficam `null` enquanto a partida ainda não tiver resultado e, após a disputa, ambos são
  preenchidos.
- Os resources públicos **não expõem foreign keys internas** (ex.: `championship_id`, `home_team_id`,
  `winner_team_id` etc.).

## Arquitetura e decisões técnicas

- **Actions** para os casos de uso: `CreateChampionship`, `PlayGame` e `SimulateChampionship`.
- **Serviços de domínio** puros para pontuação e desempate: `TeamPointsCalculator`, `GameWinnerResolver`
  (com o value object `GameOutcome`) e `Score` / `GameScore`.
- **`ScoreGenerator`** é um **contrato** (interface); **`PythonScoreGenerator`** é a implementação de
  infraestrutura que executa o `teste.py` via a API de Process do Laravel.
- **Form Request** (`StoreChampionshipRequest`) para validação e normalização da entrada.
- **API Resources** (`ChampionshipResource`, `TeamResource`, `GameResource`) para a serialização.
- **Transações** garantem atomicidade: a simulação inteira é persistida em uma única transação.
- **`lockForUpdate`** protege campeonato e times contra alterações concorrentes durante a simulação.
- Os **oito placares são gerados fora da transação geral** (o processo Python não roda com locks de
  banco abertos); dentro da transação, cada partida é disputada com o placar já gerado.
- **Testes** unitários (regras puras), de **feature** (endpoints e casos de uso) e de **integração**
  (execução real do `teste.py`).

## Banco de dados

Três tabelas principais:

- **`championships`** — o campeonato: `name`, `status` (`pending` / `in_progress` / `completed`),
  `started_at`, `completed_at`.
- **`teams`** — os times de um campeonato: `name`, `registration_order` (1–8), `points`,
  `final_position` (nullable).
- **`games`** — as partidas de um campeonato: `stage` (fase), `sequence`, participantes
  (`home`/`away`), placares, vencedor, perdedor e `played_at`.

Times e partidas pertencem a um campeonato. Cada partida guarda seus dois participantes, os placares,
o vencedor, o perdedor, a fase e a sequência dentro da fase.

## Testes e qualidade

Todos os comandos rodam **dentro do contêiner** (nada é executado no host):

```bash
docker compose exec app php artisan test                    # suíte completa
docker compose exec app php artisan test --testsuite=Unit   # apenas testes unitários
docker compose exec app ./vendor/bin/pint --test           # checagem de estilo (sem alterar)
docker compose exec app composer validate                  # valida composer.json / composer.lock
```

A suíte de testes usa **SQLite em memória** (configurado no `phpunit.xml`) e **não afeta** o banco
MySQL de desenvolvimento.

## Banco de desenvolvimento

> **Atenção:** o comando abaixo **apaga e recria todas as tabelas**, destruindo os dados do banco de
> desenvolvimento. Use somente quando realmente quiser limpar o banco.

```bash
docker compose exec app php artisan migrate:fresh
```

## Collection Postman

A collection versionada fica em:

```
docs/postman/meu-campeonato.postman_collection.json
```

Como usar:

1. No Postman, **Import** → selecione o arquivo acima.
2. A collection já traz a variável **`base_url`** com o valor padrão **`http://localhost:8000`**.
3. Execute as requisições na ordem: **Criar campeonato → Simular → Consultar → Listar**.

A requisição **Criar campeonato** salva automaticamente o `id` retornado na variável de collection
**`championship_id`**, permitindo executar **Simular** e **Consultar** sem editar nada manualmente.
