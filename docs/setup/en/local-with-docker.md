# Run It on Your Own Computer, With Docker

This is the easiest way to get a working copy of the whole site on your own
machine — to look at it, to show it, or to develop it. Docker installs and
starts every piece for you (the database, Redis, storage, mail catcher,
background workers); you install almost nothing yourself.

Read [`overview.md`](overview.md) first if you have not.

## What you will end up with

- The public site at **`http://localhost:8300/en`** (and `/ru`).
- The staff panel at **`http://localhost:8300/portal-admin`**.
- The owner cabinet at **`http://localhost:8300/cabinet`**.
- A starter administrator you can sign in with.
- Supporting tools: the mail catcher at `http://localhost:8325`, the file
  storage console at `http://localhost:9101`, the queue dashboard at
  `http://localhost:8300/horizon`.

## Before you start

Install these two things. Nothing else.

- **Docker Desktop** (Windows or macOS) or **Docker Engine + the Compose
  plugin** (Linux). After installing, open it once and wait until it says it
  is running. Check it from a terminal:

  ```
  docker --version
  docker compose version
  ```

  Both should print a version number.

- **Git**, to download the code:

  ```
  git --version
  ```

- About **6 GB** of free disk space and a working internet connection for
  the first run (it downloads the pieces once).

You do **not** need PHP, PostgreSQL, Redis, or Node.js on your machine for
this guide.

## Steps

### 1. Get the code

Pick a folder for your projects, open a terminal there, and run:

```
git clone <the repository address> booking
cd booking
```

Ask a developer for the exact repository address if you do not have it. From
here on, every command is run **inside this `booking` folder**.

**You should see:** a `booking` folder containing `docker-compose.yml`,
`composer.json`, an `app/` folder, and many others.

### 2. Create your settings file

The project ships an example settings file. Copy it to the name the
application actually reads:

- macOS / Linux: `cp .env.example .env`
- Windows PowerShell: `Copy-Item .env.example .env`

**Do not edit it.** For this guide the example values are already correct.

**You should see:** a new file named `.env` next to `.env.example`.

### 3. Start everything

```
docker compose up -d --build
```

The first run takes several minutes — it downloads and builds the pieces.
Later runs take seconds. `-d` means it runs in the background; `--build`
means "build the app image from scratch", needed only the first time and
after the app's own Docker recipe changes.

This starts:

| Piece | Where you reach it |
| --- | --- |
| `nginx` — the web entrance | `http://localhost:8300` |
| `app` — the application | (behind nginx) |
| `postgres` — the database | port `5433` on your machine |
| `redis` — fast memory | port `6379` |
| `minio` — file storage | console at `http://localhost:9101` |
| `mailpit` — mail catcher | `http://localhost:8325` |
| `worker`, `scheduler`, `pulse` — background processes | (no page of their own) |

**You should see:** `docker compose ps` lists every service, and after a
minute the `postgres` and `redis` rows show `healthy`.

> If a command complains that a **port is already in use**, something else on
> your machine is using that number. See
> [`troubleshooting.md`](troubleshooting.md) → "A port is already in use".

### 4. Install the application's own parts

Docker started the *services*. Now set up the *application* inside the `app`
container. Run these one at a time:

```
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan filament:assets
docker compose exec -e CI=true app pnpm install
docker compose exec app pnpm build
```

What each one does:

1. `composer install` — download the PHP libraries the code needs.
2. `key:generate` — create the secret the application uses to encrypt
   cookies and sessions. Writes it into your `.env`.
3. `migrate --seed` — build the database's shape, then fill it with starter
   data (languages, countries, territory levels, object types, roles,
   permissions) **and** a starter administrator (`test@example.com` /
   `password`).
4. `filament:assets` — publish the staff panel's and cabinet's styling. Skip
   this and both panels render as unstyled text.
5. `pnpm install` — download the front-end libraries. `-e CI=true` stops it
   pausing for a confirmation it cannot receive here.
6. `pnpm build` — compile the site's styling and scripts.

**You should see:** `migrate --seed` prints a list of migrations each marked
`DONE`, then `Database seeding completed successfully`.

### 5. Open the site

Open **`http://localhost:8300`** in a browser. It redirects to
`http://localhost:8300/en`.

**You should see:** the tourism portal home page, with real seeded content
(territory names, object cards), styled correctly.

## How to tell it worked

Go through [`first-run-and-daily-tasks.md`](first-run-and-daily-tasks.md).
In short:

- `http://localhost:8300/up` shows a plain **`200`** / "OK".
- `http://localhost:8300/en` loads and is styled.
- `http://localhost:8300/portal-admin` shows a sign-in page.
- You can sign in there with `test@example.com` / `password` (it will then
  walk you through setting up a second login factor).

## Everyday use after the first setup

You never repeat steps 1–2. You rarely repeat step 4 (only after pulling new
code — see [`first-run-and-daily-tasks.md`](first-run-and-daily-tasks.md)).
Normal use:

| Task | Command |
| --- | --- |
| Start the site (already set up) | `docker compose up -d` |
| Stop it, keep the data | `docker compose stop` |
| Stop it and remove the containers (data survives) | `docker compose down` |
| See the application's live log | `docker compose logs -f app` |
| Open a terminal inside the app | `docker compose exec app bash` |
| Live-reload styling while developing | `docker compose exec app pnpm dev` (leave it running) |
| Erase and rebuild the database | `docker compose exec app php artisan migrate:fresh --seed` |

Your data lives in Docker "volumes" and survives `stop` and `down`. To wipe
it completely, add `-v`: `docker compose down -v` (irreversible).

## If something does not work

- **A port is already in use** → [`troubleshooting.md`](troubleshooting.md).
- **The home page redirects somewhere you cannot open, or the styling is
  missing** → you probably edited `.env` in step 2, or an old `.env` is
  present. Its `APP_URL` must read exactly `http://localhost:8300`. Fix it,
  then `docker compose exec app php artisan config:clear` and
  `docker compose exec app php artisan cache:clear`.
- **The panels look like plain text** → step 4.4 (`filament:assets`) was
  missed or failed. Re-run it.
- **`pnpm install` stops and waits, then fails** → run it with `-e CI=true`
  as shown in step 4.
- **Every page is very slow** (many seconds) → this is Docker's file-sharing
  overhead on Windows and macOS, and it is expected for heavy work. See
  [`troubleshooting.md`](troubleshooting.md) → "Everything is slow under
  Docker".
- **Anything else** → [`troubleshooting.md`](troubleshooting.md).
