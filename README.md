# Meu Campeonato — API (Back-End)

API de um **campeonato eliminatório de oito times**, desenvolvida em **Laravel 13** e executada
**inteiramente com Docker**. O projeto está sendo construído em etapas; este README cobre tudo o
que já foi implementado e explica, passo a passo, como colocar o ambiente no ar.

---

## Sumário

- [Stack e versões](#stack-e-versões)
- [Pré-requisitos](#pré-requisitos)
- [Como executar (passo a passo)](#como-executar-passo-a-passo)
- [Serviços e portas](#serviços-e-portas)
- [Variáveis de ambiente](#variáveis-de-ambiente)
- [Comandos úteis do dia a dia](#comandos-úteis-do-dia-a-dia)
- [Testes](#testes)
- [Domínio implementado](#domínio-implementado)
- [Esquema do banco de dados](#esquema-do-banco-de-dados)
- [Estrutura do projeto](#estrutura-do-projeto)
- [Progresso por etapas](#progresso-por-etapas)
- [Solução de problemas](#solução-de-problemas)

---

## Stack e versões

Todas as versões de imagem são **fixadas** (sem `latest`) para builds reprodutíveis:

| Componente | Versão | Onde |
|-----------|--------|------|
| PHP | 8.4.23 (CLI) | imagem `php:8.4.23-cli-bookworm` |
| Laravel Framework | 13.x | `composer.json` |
| Composer | 2.10.2 | copiado da imagem `composer:2.10.2` |
| MySQL | 8.4.10 | imagem `mysql:8.4.10` |
| Python | 3.11 | instalado no contêiner da aplicação |

Extensões PHP habilitadas na imagem: `pdo_mysql` e `mbstring` (as demais exigidas pelo Laravel
já vêm na imagem oficial).

---

## Pré-requisitos

- **Docker Desktop** (ou Docker Engine) com **Docker Compose v2** (comando `docker compose`).
- Nenhuma outra dependência local.

Confirme que o Docker está rodando:

```bash
docker version
```

---

## Como executar (passo a passo)

**1. Clone o repositório e entre na pasta:**

```bash
git clone <url-do-repositorio> MeuCampeonato_Back-End
cd MeuCampeonato_Back-End
```

**2. Crie o arquivo `.env` a partir do exemplo:**

```bash
cp .env.example .env
```

O `.env` é ignorado pelo Git. O `.env.example` já vem com credenciais **locais de desenvolvimento**
e apontando para o serviço `mysql` do Docker — não é preciso editar nada para começar.

**3. Suba os serviços (constrói a imagem na primeira vez):**

```bash
docker compose up -d --build
```

O que acontece automaticamente:
- o serviço **mysql** sobe primeiro e a aplicação só inicia quando o banco está **saudável** (healthcheck);
- ao iniciar, o contêiner da aplicação roda **`composer install`** (via *entrypoint*) e sobe o servidor em
  `http://localhost:8000`.

> A primeira execução é mais lenta (download das imagens + `composer install`).

**4. Gere a chave da aplicação (`APP_KEY`):**

```bash
docker compose exec app php artisan key:generate
```

**5. Rode as migrations (o entrypoint NÃO as executa automaticamente):**

```bash
docker compose exec app php artisan migrate
```

**6. Acesse a aplicação:**

- <http://localhost:8000>

Pronto. Para conferir que tudo está de pé:

```bash
docker compose ps
```

---

## Serviços e portas

| Serviço | Imagem | Porta (host → contêiner) | Descrição |
|---------|--------|--------------------------|-----------|
| `app`   | build local (`docker/php/Dockerfile`) | `8000 → 8000` | Aplicação Laravel (`php artisan serve`) |
| `mysql` | `mysql:8.4.10` | *(não publicada)* | Banco MySQL — acessível **apenas** pela rede interna do Compose |

A porta `3306` do MySQL **não é exposta** no host de propósito: somente o serviço `app` acessa o banco.

**Volumes nomeados:**
- `mysql-data` — persiste os dados do MySQL (`/var/lib/mysql`).
- `vendor` — persiste as dependências do Composer (`/var/www/html/vendor`).

O código-fonte é montado no contêiner via *bind mount*, então alterações no seu editor refletem
imediatamente na aplicação.

---

## Variáveis de ambiente

Principais valores já configurados no `.env.example` (desenvolvimento local):

```dotenv
APP_NAME="Meu Campeonato"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=mysql          # nome do serviço no compose.yaml
DB_PORT=3306
DB_DATABASE=meu_campeonato
DB_USERNAME=meu_campeonato
DB_PASSWORD=secret
```

> Essas são credenciais **apenas para desenvolvimento local**. O usuário `root` do MySQL usa a senha
> `root` (definida no `compose.yaml`) e também só existe dentro do ambiente Docker.

---

## Comandos úteis do dia a dia

Todos os comandos rodam **dentro do contêiner** `app`:

```bash
# Estado dos serviços
docker compose ps

# Logs da aplicação (Ctrl+C para sair)
docker compose logs -f app

# Abrir um shell no contêiner
docker compose exec app sh

# Artisan e Composer
docker compose exec app php artisan <comando>
docker compose exec app composer <comando>

# Console interativo (Tinker)
docker compose exec app php artisan tinker

# Migrations
docker compose exec app php artisan migrate:status   # ver status
docker compose exec app php artisan migrate           # aplicar pendentes
docker compose exec app php artisan migrate:fresh     # recriar do zero (apaga os dados!)

# Python (disponível no contêiner)
docker compose exec app python3 --version
```

**Ciclo de vida dos contêineres:**

```bash
docker compose stop        # para os serviços (mantém tudo)
docker compose down        # remove os contêineres (mantém os volumes/dados)
docker compose down -v     # remove também os volumes (APAGA o banco e o vendor)
```

---

## Testes

A suíte usa **SQLite em memória** (configurado no `phpunit.xml`) e o trait `RefreshDatabase`,
portanto os testes **não afetam** o banco MySQL de desenvolvimento.

```bash
docker compose exec app php artisan test
```

Verificação de estilo de código (Laravel Pint, já presente no projeto):

```bash
docker compose exec app ./vendor/bin/pint --test   # apenas verifica
docker compose exec app ./vendor/bin/pint          # corrige
```

---

## Domínio implementado

O campeonato é eliminatório, com **oito times**, e mantém o histórico de campeonatos anteriores.
Os nomes seguem em inglês no código e no banco.

**Entidades:**
- **Championship** — o campeonato (status, datas de início/fim).
- **Team** — os times inscritos (ordem de inscrição, pontos, classificação final).
- **Game** — as partidas (nome propositalmente **não** é `Match`, palavra reservada do PHP).

**Enums (armazenados como string):**
- `ChampionshipStatus`: `pending`, `in_progress`, `completed`.
- `GameStage`: `quarterfinal`, `semifinal`, `third_place`, `final`.

**Relacionamentos:**
- `Championship` → tem muitos `Team` e muitos `Game` (e um atalho para os times em ordem de inscrição).
- `Team` → pertence a um `Championship`; participa de jogos como mandante/visitante e tem jogos vencidos/perdidos.
- `Game` → pertence a um `Championship` e referencia `homeTeam`, `awayTeam`, `winner` e `loser`.

> Nesta etapa há **apenas** a modelagem/persistência. Não existem ainda endpoints, regras de
> pontuação, sorteio ou simulação do campeonato.

---

## Esquema do banco de dados

**`championships`**
- `id`, `name`, `status` (default `pending`), `started_at`, `completed_at`, timestamps.
- Índice em `status`.

**`teams`**
- `id`, `championship_id`, `name`, `registration_order` (1–8), `points` (inteiro, default `0`, aceita negativos),
  `final_position` (nullable), timestamps.
- Únicos por campeonato: `(championship_id, name)`, `(championship_id, registration_order)`,
  `(championship_id, final_position)` — múltiplos `final_position` nulos são permitidos.

**`games`**
- `id`, `championship_id`, `stage`, `sequence`, `home_team_id`, `away_team_id`,
  `home_score`/`away_score` (nullable), `winner_team_id`/`loser_team_id` (nullable), `played_at`, timestamps.
- Único: `(championship_id, stage, sequence)`. Índice: `(championship_id, stage)`.

**Ações `ON DELETE` das foreign keys:**
- `championship_id` (em `teams` e `games`): **CASCADE** — excluir um campeonato remove seus times e jogos.
- FKs de time em `games` (`home`/`away`/`winner`/`loser`): **RESTRICT** — impede que a exclusão isolada de
  um time destrua silenciosamente o histórico de partidas.

---

## Estrutura do projeto

Arquivos e diretórios mais relevantes:

```
.
├── compose.yaml                 # Orquestração dos serviços (app + mysql)
├── docker/
│   └── php/
│       ├── Dockerfile           # Imagem da aplicação (PHP 8.4 + Composer + Python 3)
│       └── entrypoint.sh        # composer install + inicia o artisan serve
├── app/
│   ├── Enums/                   # ChampionshipStatus, GameStage
│   └── Models/                  # Championship, Team, Game
├── database/
│   ├── factories/               # ChampionshipFactory, TeamFactory
│   └── migrations/              # championships, teams, games (+ padrão do Laravel)
└── tests/Feature/               # Testes de persistência e relacionamentos
```

---

## Progresso por etapas

- [x] **Etapa 1 — Estrutura inicial** do Laravel 13.
- [x] **Etapa 2 — Ambiente Docker** com PHP 8.4, Composer, Python 3 e MySQL 8.4.
- [x] **Etapa 3 — Modelagem e persistência** do domínio (enums, migrations, models, factories e testes).
- [ ] Próximas etapas do desafio (em desenvolvimento).

---

## Solução de problemas

**"Cannot connect to the Docker daemon"** — o Docker não está rodando. Abra o Docker Desktop e aguarde
ele ficar pronto.

**A porta 8000 já está em uso** — pare o processo que usa a porta ou ajuste o mapeamento em `compose.yaml`
(ex.: `"8001:8000"`) e acesse `http://localhost:8001`.

**Erro relacionado a `APP_KEY` / "No application encryption key"** — rode
`docker compose exec app php artisan key:generate`.

**Erro de conexão com o banco** — confirme que o `mysql` está `healthy` (`docker compose ps`) e que o
`.env` usa `DB_HOST=mysql`. Na primeira subida, o MySQL leva alguns segundos para inicializar.

**Quero começar do zero** — `docker compose down -v` remove os volumes (apaga o banco e o `vendor`);
depois repita o passo a passo de execução.
