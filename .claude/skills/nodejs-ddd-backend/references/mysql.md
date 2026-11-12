# MySQL — Connection, Queries, Repositories

> **Names in the examples (`OrderRepository`, `OrderRow`, the `orders` table, column names like `order_id` / `account_id`, etc.) are illustrative only.** Use names from the current project's domain and schema. Copy the structure, not the identifiers.

Use **MySQL via `mysql2/promise`**. The shape is: an interface (`DatabaseConnection`) consumed by repositories, a single concrete adapter that owns the pool, and parameterized queries everywhere.

## Driver choice

Use `mysql2/promise`. It is the maintained successor to the original `mysql` driver, returns Promises natively, supports prepared statements, and uses a connection pool out of the box.

```bash
npm install mysql2@^3
```

Pin the `^3` major — current mainline, Node 22 compatible, MySQL 8.4 wire-protocol tested. `@types/mysql2` is bundled — no extra `@types` package.

## The `DatabaseConnection` interface

The interface lives in `infra/database/DatabaseConnection.ts`:

```ts
export default interface DatabaseConnection {
    query<T = any>(statement: string, params?: any[]): Promise<T[]>;
    one<T = any>(statement: string, params?: any[]): Promise<T | undefined>;
    transaction<T>(fn: (tx: DatabaseConnection) => Promise<T>): Promise<T>;
    close(): Promise<void>;
}
```

Start narrow and extend as needs appear: `query` covers the base case, `one` is convenient for "fetch a single row," and `transaction` is required as soon as a use case writes to multiple tables.

## The MySQL adapter

```ts
import mysql, { Pool, PoolConnection, RowDataPacket } from "mysql2/promise";
import DatabaseConnection from "./DatabaseConnection";

export type MySqlConfig = {
    host: string;
    port: number;
    user: string;
    password: string;
    database: string;
    connectionLimit?: number;
};

export class MySqlAdapter implements DatabaseConnection {
    private pool: Pool;

    constructor(config: MySqlConfig) {
        this.pool = mysql.createPool({
            host: config.host,
            port: config.port,
            user: config.user,
            password: config.password,
            database: config.database,
            waitForConnections: true,
            connectionLimit: config.connectionLimit ?? 10,
            namedPlaceholders: false,
            decimalNumbers: false, // return DECIMAL as string; cast explicitly in repositories
            dateStrings: false,
        });
    }

    async query<T = any>(statement: string, params: any[] = []): Promise<T[]> {
        const [rows] = await this.pool.execute<RowDataPacket[]>(statement, params);
        return rows as T[];
    }

    async one<T = any>(statement: string, params: any[] = []): Promise<T | undefined> {
        const rows = await this.query<T>(statement, params);
        return rows[0];
    }

    async transaction<T>(fn: (tx: DatabaseConnection) => Promise<T>): Promise<T> {
        const conn = await this.pool.getConnection();
        await conn.beginTransaction();
        try {
            const tx = new TransactionConnection(conn);
            const result = await fn(tx);
            await conn.commit();
            return result;
        } catch (e) {
            await conn.rollback();
            throw e;
        } finally {
            conn.release();
        }
    }

    async close(): Promise<void> {
        await this.pool.end();
    }
}

class TransactionConnection implements DatabaseConnection {
    constructor(private readonly conn: PoolConnection) {}

    async query<T = any>(statement: string, params: any[] = []): Promise<T[]> {
        const [rows] = await this.conn.execute<RowDataPacket[]>(statement, params);
        return rows as T[];
    }

    async one<T = any>(statement: string, params: any[] = []): Promise<T | undefined> {
        const rows = await this.query<T>(statement, params);
        return rows[0];
    }

    async transaction<T>(fn: (tx: DatabaseConnection) => Promise<T>): Promise<T> {
        // Already inside a transaction — run inline.
        return fn(this);
    }

    async close(): Promise<void> {
        // No-op: the pool owns the underlying connection.
    }
}
```

Why these settings:

- **`waitForConnections: true` + `connectionLimit`.** Keeps a bounded pool. Default 10 is right for most services; tune up if the service is genuinely concurrent.
- **`decimalNumbers: false`.** MySQL `DECIMAL` columns lose precision when JS coerces to `Number`. Returning strings forces the repository to make an explicit decision (`Number(row.amount)` for safe values, a `Money` value object for currency).
- **`pool.execute` not `pool.query`.** `execute` uses prepared statements server-side, which is faster on repeated queries and safer.
- **Transaction connection is wrapped** so nested code reuses the same physical connection. The wrapper's `transaction` method runs the callback inline rather than starting a nested transaction (MySQL doesn't natively support nested transactions; savepoints are deliberate, not implicit).

## Parameterized queries — always

```ts
// CORRECT
await db.query("select * from orders where account_id = ? and status = ?", [accountId, "open"]);

// FORBIDDEN
await db.query(`select * from orders where account_id = '${accountId}'`);
```

Even for "trusted" inputs (internal IDs, enums), use parameters. The cost is zero; the consistency prevents the one accidental injection.

For `IN` clauses, build the placeholder list explicitly:

```ts
const placeholders = ids.map(() => "?").join(",");
await db.query(`select * from orders where order_id in (${placeholders})`, ids);
```

The static SQL string is hand-written; only the placeholders interpolate, never values.

## Repository pattern (MySQL specifics)

```ts
export default interface OrderRepository {
    saveOrder(order: Order): Promise<void>;
    updateOrder(order: Order): Promise<void>;
    getOrderById(orderId: string): Promise<Order>;
}

export class OrderRepositoryDatabase implements OrderRepository {
    @inject("databaseConnection") connection!: DatabaseConnection;

    async saveOrder(order: Order): Promise<void> {
        await this.connection.query(
            `insert into orders
                (order_id, account_id, side, quantity, price, fill_quantity, fill_price, status, created_at)
             values (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
            [
                order.getOrderId(),
                order.getAccountId(),
                order.getSide(),
                order.getQuantity(),
                order.getPrice(),
                order.getFillQuantity(),
                order.getFillPrice(),
                order.getStatus(),
                order.getTimestamp(),
            ],
        );
    }

    async updateOrder(order: Order): Promise<void> {
        await this.connection.query(
            `update orders
                set fill_quantity = ?, fill_price = ?, status = ?
              where order_id = ?`,
            [
                order.getFillQuantity(),
                order.getFillPrice(),
                order.getStatus(),
                order.getOrderId(),
            ],
        );
    }

    async getOrderById(orderId: string): Promise<Order> {
        const row = await this.connection.one<OrderRow>(
            "select * from orders where order_id = ?",
            [orderId],
        );
        if (!row) throw new Error("Order not found");
        return this.toEntity(row);
    }

    private toEntity(row: OrderRow): Order {
        return new Order(
            row.order_id,
            row.account_id,
            row.side,
            Number(row.quantity),
            Number(row.price),
            Number(row.fill_quantity),
            Number(row.fill_price),
            row.status,
            row.created_at,
        );
    }
}

type OrderRow = {
    order_id: string;
    account_id: string;
    side: string;
    quantity: string;       // DECIMAL → string
    price: string;
    fill_quantity: string;
    fill_price: string;
    status: string;
    created_at: Date;
};
```

Notes:

- **Row type is named** (`OrderRow`) and uses snake_case to match MySQL columns. The mapping function (`toEntity`) is the only place that knows about the row shape.
- **Numeric columns from `DECIMAL` are explicitly converted** with `Number(...)`. For currency, prefer a `Money` value object that takes a string and stores cents.
- **`getById` throws on not found.** Use cases catch and rephrase if needed.
- **No SELECT \*** in production hot paths — list columns explicitly so adding a column doesn't surprise the mapper.

## Transactions

When a use case writes to multiple tables, wrap them in a transaction:

```ts
// In a use case
async execute(input: Input) {
    await this.db.transaction(async (tx) => {
        const orderRepo  = new OrderRepositoryDatabase();   // bound to tx via DI scope
        const walletRepo = new WalletRepositoryDatabase();
        // ...
    });
}
```

Because the DI registry is process-global, scoped transactions need a different approach. Two options:

1. **Parameterize repositories with a connection** in their constructor so a transaction can build per-tx instances:

   ```ts
   export class OrderRepositoryDatabase implements OrderRepository {
       constructor(private readonly connection: DatabaseConnection) {}
       // ...
   }
   ```

   The use case then does `new OrderRepositoryDatabase(tx)` inside the transaction callback. This is a deviation from the `@inject` pattern, used **only** for transactional repositories.

2. **Pass the `tx` connection through** to repository methods that support it:

   ```ts
   await this.orderRepo.saveOrder(order, tx);
   ```

   Less elegant but doesn't require restructuring DI.

Pick (1) for new code that genuinely needs cross-aggregate transactions. Most use cases write to a single aggregate root and don't need a transaction beyond what MySQL's per-statement atomicity already gives.

## Migrations

Use **Knex (`knex@^3`) as the migration runner**. Knex is scoped to schema changes only — repositories stay on raw parameterized SQL through the `DatabaseConnection` port (see above). Zero `import knex` lines in `infra/repository/`. Keeping Knex out of repositories preserves the hexagonal boundary: swapping Knex later, or promoting it to query-builder duty, is a `knexfile` + scripts change — not a per-repository rewrite.

Why Knex for migrations:

- It's the most widely used migration runner in the Node ecosystem and uses `mysql2` as its driver — the same driver the runtime app already ships, so no second connection library.
- The schema builder (`table.increments`, `table.string`, `table.foreign`, etc.) compiles to valid MySQL 8.4 DDL and is readable enough to review without mentally translating to SQL.
- First-class TypeScript support for `.ts` migrations; they participate in the same `strict` compile settings as the rest of the codebase.

### Install

Knex belongs in `devDependencies` — the runtime container never imports it. The `migrate` Docker service uses the `build` stage image (which includes devDependencies), so Knex is available during migration runs without bloating the runtime image.

```bash
npm install --save-dev knex ts-node
```

`mysql2` is already in `dependencies` (see "Driver choice" above); Knex auto-detects it when `client: "mysql2"`.

### `knexfile.ts`

Place at the repo root:

```ts
import type { Knex } from "knex";

const base: Knex.Config = {
    client: "mysql2",
    connection: {
        host:     process.env.DB_HOST ?? "mysql",
        port:     Number(process.env.DB_PORT ?? 3306),
        user:     process.env.DB_USER,
        password: process.env.DB_PASSWORD,
        database: process.env.DB_NAME,
    },
    migrations: {
        directory:      "./migrations",
        extension:      "ts",
        loadExtensions: [".ts"],
        tableName:      "knex_migrations",
    },
};

const config: Record<string, Knex.Config> = {
    development: base,
    test:        base,
    production:  base,
};

export default config;
```

Notes:

- **`client: "mysql2"`** — reuses the driver already in `dependencies`.
- **`migrations.directory: "./migrations"`** — matches the skill's "migrations at repo root" rule.
- **`migrations.extension: "ts"`** — authored as TypeScript. The `migrate` Docker service has `ts-node` available via devDependencies.
- **Connection is read from env** — the composition root rule (`src/api.ts` reads env) doesn't apply here: `knexfile.ts` is a CLI config, not an application adapter, so env access is allowed.

### Package scripts

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

### Authoring a migration

Scaffold via the `migrate` service:

```bash
docker compose run --rm migrate npm run migrate:make create_orders
```

Produces `migrations/20260421093015_create_orders.ts`:

```ts
import type { Knex } from "knex";

export async function up(knex: Knex): Promise<void> {
    await knex.schema.createTable("orders", (table) => {
        table.string("order_id", 36).primary();
        table.string("account_id", 36).notNullable().index();
        table.enu("side", ["buy", "sell"]).notNullable();
        table.decimal("quantity", 20, 8).notNullable();
        table.decimal("price", 20, 8).notNullable();
        table.decimal("fill_quantity", 20, 8).notNullable().defaultTo(0);
        table.decimal("fill_price", 20, 8).notNullable().defaultTo(0);
        table.string("status", 16).notNullable();
        table.timestamp("created_at").notNullable().defaultTo(knex.fn.now());
    });
}

export async function down(knex: Knex): Promise<void> {
    await knex.schema.dropTable("orders");
}
```

Rules:

- **File name** is `YYYYMMDDHHMMSS_<description>.ts` — Knex stamps the timestamp prefix automatically; do not rename.
- **Forward-only in production.** `down` exists so local dev can experiment (`npm run migrate:rollback`); production never runs `down` — fix-forward with a new migration instead.
- **No application code imports from `migrations/`.** It's a deploy-time artifact, not runtime code. Never `import "../migrations/..."` from `src/`.
- **No seeds co-located with migrations.** If seed data is needed, use Knex's seeds feature with a separate directory; tests build their own fixtures via the repositories.

### Running migrations

Always via the `migrate` Docker service. Never `npm run migrate` on the host.

```bash
docker compose run --rm migrate                              # apply pending
docker compose run --rm migrate npm run migrate:make foo     # scaffold a new migration
docker compose run --rm migrate npm run migrate:rollback     # roll back the last batch (dev only)
docker compose run --rm migrate npm run migrate:status       # show applied vs pending
```

## Health checks

The MySQL adapter should expose a lightweight `ping()` for readiness probes:

```ts
async ping(): Promise<void> {
    await this.pool.query("select 1");
}
```

Add it to the `DatabaseConnection` interface and call it from a `/health` route.

## Common mistakes

- **Reading `process.env` inside `MySqlAdapter`.** Pass config via constructor; `api.ts` reads env.
- **Returning rows directly from the repository.** Map to a domain entity (or DTO for read models) before returning.
- **Using `pool.query` instead of `pool.execute`** for queries with parameters. `execute` uses prepared statements; safer and faster.
- **Coercing `DECIMAL` via `+row.amount`** without thinking. For currency, always go through a value object.
- **Forgetting `await pool.end()` in tests' afterAll**, leaving connections open and Vitest or Jest hanging.
