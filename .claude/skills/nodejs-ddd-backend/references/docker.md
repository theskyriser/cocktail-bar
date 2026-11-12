# Docker — Development and Deployment

Docker is the unit of deployment **and** the unit of local development. The same `Dockerfile` that runs in production is used for builds; `docker-compose.yml` brings up the service plus its dependencies (MySQL, Azurite, the migrations job, optional message broker) for local work.

## Docker-only execution

**The project runs entirely in Docker — app, database, and migrations.** This is a hard project rule. Concretely:

- Developers never run `npm install`, `npm run build`, `npm test`, `node dist/...`, `tsc`, or `mysql` on the host. They run those commands **inside** the compose services.
- The MySQL instance is reachable only through the compose network. No host-side DB client connects to it.
- **Migrations are a first-class service (`migrate`)**, not a script someone runs on the host. The DB schema only changes through a committed migration file executed via `docker compose run --rm migrate`.
- CI and production use the same image the developer built, minus the dev-only volume mount and dev dependencies.

This eliminates "works on my machine" at the root: everybody — developer laptops, CI, prod — is on the same container runtime, the same Node version, and the same OS libraries.

The CLAUDE.md template at `templates/CLAUDE.md` in this skill codifies the Docker-only rule for the consuming project and includes the full command table.

## Multi-stage Dockerfile

```dockerfile
# syntax=docker/dockerfile:1.7

# 1. Dependencies — install once, reuse across builds.
FROM node:22-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm \
    npm ci

# 2. Build — compile TypeScript to dist/.
FROM node:22-alpine AS build
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .
RUN npm run build

# 3. Production deps only — drop devDependencies.
FROM node:22-alpine AS prod-deps
WORKDIR /app
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm \
    npm ci --omit=dev

# 4. Runtime — minimal image.
FROM node:22-alpine AS runtime
ENV NODE_ENV=production
WORKDIR /app

# Run as non-root.
RUN addgroup -S app && adduser -S app -G app
USER app

COPY --from=prod-deps --chown=app:app /app/node_modules ./node_modules
COPY --from=build     --chown=app:app /app/dist          ./dist
COPY --from=build     --chown=app:app /app/package.json  ./package.json

EXPOSE 3000
CMD ["node", "dist/api.js"]
```

Why this shape:

- **Four stages** (`deps` → `build` → `prod-deps` → `runtime`) maximize layer cache reuse. Changing source files invalidates only `build`; changing dependencies invalidates `deps` and `prod-deps`.
- **`npm ci`** (not `npm install`) for reproducible builds based on `package-lock.json`.
- **`--mount=type=cache`** speeds up CI builds by reusing the npm cache across runs (BuildKit feature; enable with `DOCKER_BUILDKIT=1`).
- **`--omit=dev`** in the runtime image strips Jest and/or Vitest, ts-node, etc. The image stays small (~150 MB).
- **Non-root user** (`app`) is the security default. Without it, a process inside the container runs as root.
- **`node:22-alpine`** because Node 22 is current LTS ("Jod"); Node 20 reached end-of-life on 2026-04-30. Alpine keeps the image small. If a native dependency needs glibc, switch to `node:22-slim` (Debian-based).

## .dockerignore

```
node_modules
dist
.git
.env*
coverage
*.log
test
.vscode
.idea
README.md
```

Without this, the build context bloats and `COPY . .` ships unwanted files into intermediate layers.

## docker-compose for local dev

```yaml
services:
  api:
    build:
      context: .
      target: build
    command: npm run dev
    volumes:
      - .:/app
      - /app/node_modules
    ports:
      - "3000:3000"
    environment:
      NODE_ENV: development
      PORT: 3000
      DB_HOST: mysql
      DB_PORT: 3306
      DB_USER: app
      DB_PASSWORD: app
      DB_NAME: app
      AZURE_STORAGE_CONNECTION_STRING: "DefaultEndpointsProtocol=http;AccountName=devstoreaccount1;AccountKey=Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==;BlobEndpoint=http://azurite:10000/devstoreaccount1;"
      AZURE_STORAGE_CONTAINER: receipts
    depends_on:
      mysql:
        condition: service_healthy
      azurite:
        condition: service_started
    healthcheck:
      test: ["CMD", "wget", "-qO-", "http://localhost:3000/health"]
      interval: 5s
      timeout: 2s
      retries: 10

  mysql:
    image: mysql:8.4
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE:      app
      MYSQL_USER:          app
      MYSQL_PASSWORD:      app
    ports:
      - "3306:3306"
    volumes:
      - mysql-data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-uapp", "-papp"]
      interval: 5s
      timeout: 2s
      retries: 20

  migrate:
    build:
      context: .
      target: build
    command: npm run migrate
    volumes:
      - .:/app
      - /app/node_modules
    environment:
      DB_HOST: mysql
      DB_PORT: 3306
      DB_USER: app
      DB_PASSWORD: app
      DB_NAME: app
    depends_on:
      mysql:
        condition: service_healthy
    profiles: ["tools"]

  azurite:
    image: mcr.microsoft.com/azure-storage/azurite
    command: azurite-blob --blobHost 0.0.0.0 --loose
    ports:
      - "10000:10000"
    volumes:
      - azurite-data:/data

volumes:
  mysql-data:
  azurite-data:
```

Notes:

- **`target: build`** in the api service uses the build-stage image (which has dev dependencies) so `npm run dev` (nodemon + ts-node) works.
- **`volumes: - .:/app, - /app/node_modules`** mounts source from host while preserving `node_modules` from the image. Edit-and-reload works without rebuilding.
- **`depends_on` with `condition: service_healthy`** waits for MySQL's healthcheck. Without this, the API starts before MySQL accepts connections.
- **`migrate` service** shares the `api` image (same `Dockerfile`, `target: build`) but overrides the command. It only starts when MySQL is healthy. `profiles: ["tools"]` keeps it out of `docker compose up -d` — it runs explicitly via `docker compose run --rm migrate`, so schema changes are intentional, not a startup side effect.
- **No `docker-entrypoint-initdb.d` mount on MySQL.** MySQL's initdb only runs on *first* container start and executes alphabetically — unreliable for versioned migrations. Use the `migrate` service instead.
- **Azurite** stands in for Azure Blob Storage locally. The well-known connection string above is the official emulator credential.
- **Healthcheck on the api** is hooked to a `/health` route that pings the DB. Required for orchestrators (ACI, Kubernetes).

## Production deployment

The runtime image (`target: runtime`, the default last stage) is what gets pushed:

```bash
docker build -t myorg/order-service:$(git rev-parse --short HEAD) .
docker push myorg/order-service:$(git rev-parse --short HEAD)
```

In production:

- Run with `--read-only --tmpfs /tmp` to harden the filesystem.
- Set `--init` so PID 1 reaps zombie children correctly.
- Pass env vars via the orchestrator's secret store (Azure Key Vault, ACI environment, Kubernetes Secrets) — never bake them into the image.

For Azure Container Apps / AKS, expose the port via a Kubernetes Service or Container Apps ingress; do not publish ports from inside the container.

## Migrations — the canonical workflow

Migrations are a **first-class Docker service**, not an afterthought. The `migrate` service in the compose file above is the only supported way to change the schema in any environment.

### File layout

```
<repo-root>/
├── migrations/
│   ├── 20260101120000_create_orders.sql
│   ├── 20260102090000_add_status_index.sql
│   └── ...
├── src/
├── Dockerfile
├── docker-compose.yml
└── package.json
```

- Migrations live in `migrations/` at the project root — **never** inside `src/`. They are a build-time artifact, not application code.
- File name: `YYYYMMDDHHMMSS_<description>.sql` (or `.ts` if you use a programmatic runner). Timestamped at creation so order is unambiguous across branches.
- **Forward-only in production.** Down migrations exist for local dev but are never relied on for rollback; the production strategy is fix-forward with a new migration.

### Runner choice

The runner is **[Knex](https://knexjs.org/) (`knex@^3`)**, used **only** for migrations — not as a query builder in repositories. The scaffolding, `knexfile.ts` template, migration authoring rules, and rationale live in `references/mysql.md` ("Migrations").

Knex belongs in `devDependencies`: the runtime container never imports it. The `migrate` service below uses the `build` stage image (which carries devDependencies + `ts-node`), so Knex and `.ts` migrations work without inflating the runtime image.

Required `package.json` scripts:

```json
{
    "scripts": {
        "migrate":          "knex --knexfile knexfile.ts migrate:latest",
        "migrate:make":     "knex --knexfile knexfile.ts migrate:make -x ts",
        "migrate:rollback": "knex --knexfile knexfile.ts migrate:rollback",
        "migrate:status":   "knex --knexfile knexfile.ts migrate:status"
    }
}
```

### Commands (host-facing)

These are what developers actually type. They are **the only** supported way to change the schema.

```bash
# Apply all pending migrations against the compose MySQL
docker compose run --rm migrate

# Scaffold a new migration file under migrations/
docker compose run --rm migrate npm run migrate:make <description>

# Roll back the last batch (dev only — never in production)
docker compose run --rm migrate npm run migrate:rollback

# List applied vs pending
docker compose run --rm migrate npm run migrate:status
```

Nothing else is authorized. If a developer reaches for `mysql -u app -p` on the host or `mysql` inside the api container to run DDL by hand, that's a violation of the Docker-only rule.

### Production

The same image runs in production. Wire the `migrate` command as a **pre-deploy job**:

- **Kubernetes**: an `initContainer` on the deployment, or (preferred) a `Job` that must complete before the rollout begins.
- **Azure Container Apps**: a pre-deploy revision step or a one-off `az containerapp job` execution that exits 0 before the app revision is promoted.
- **ECS / Fargate**: a one-off task run against the target cluster before updating the service.

Never tie migrations to application startup (`CMD: sh -c "npm run migrate && node dist/api.js"`). That couples deploy ordering to every process restart, breaks multi-replica startup, and makes zero-downtime rollouts fragile.

### Schema change PR checklist

Every PR that changes DB shape ships with:

- [ ] A new file in `migrations/`.
- [ ] Updated repository code that reads/writes the new shape.
- [ ] Updated integration tests against the migrated schema.
- [ ] No use of host-side SQL clients to work around missing migrations during development.

## Other deployment patterns

- **Worker process**: a separate `Dockerfile.worker` (or `target: worker` plus a different `CMD`) for queue consumers. Sharing the build stage keeps the image cache warm.
- **Multi-arch builds**: `docker buildx build --platform linux/amd64,linux/arm64 ...` for M-series Macs talking to amd64 production hosts.

## Common mistakes

- **`COPY . .` before `npm ci`** — invalidates the dependency layer on every source change. Copy `package.json` + `package-lock.json` first.
- **Running as root** — almost always wrong; create a user.
- **`npm install` in production** — non-deterministic; use `npm ci`.
- **Baking secrets into the image** — they live forever in image history. Use env vars or secret mounts.
- **Forgetting `EXPOSE`** — informational, but tools (Docker, Compose, K8s) use it for documentation and port discovery.
- **Running `tsc` in the runtime image** — keep TypeScript out of production; compile in `build`, copy `dist/` only.
