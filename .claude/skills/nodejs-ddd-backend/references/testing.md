# Testing

> **Names in the examples (`Wallet.test.ts`, `PlaceOrder.test.ts`, `Order`, `Wallet`, etc.) are illustrative only.** Use names from the current project's domain when writing real tests. Copy the structure, not the identifiers.

The test pyramid:

```
test/
├── unit/          # domain classes in isolation
├── integration/   # use cases with real adapters (MySQL, etc.)
└── load/          # optional, for hot paths
```

Test runner is **Vitest** (globals mode). Files are named `*.test.ts`. Test names describe behavior, not implementation.

## Setup

```bash
npm install -D vitest
```

`vitest.config.ts`:

```ts
import { defineConfig } from "vitest/config";

export default defineConfig({
    test: {
        globals: true,
        environment: "node",
        testTimeout: 10000,
        clearMocks: true,
        include: ["test/**/*.test.ts"],
    },
});
```

`tsconfig.test.json` — extend the base config and add `vitest/globals` so TypeScript recognises the global `describe`, `it`, `expect`, `vi`, etc.:

```json
{
    "extends": "./tsconfig.json",
    "compilerOptions": {
        "noEmit": true,
        "types": ["node", "vitest/globals"]
    },
    "include": ["src/**/*.ts", "test/**/*.ts"]
}
```

`package.json` scripts:

```json
{
    "scripts": {
        "test":             "vitest run",
        "test:unit":        "vitest run test/unit",
        "test:integration": "vitest run test/integration --pool=forks --maxWorkers=1",
        "test:watch":       "vitest"
    }
}
```

`--pool=forks --maxWorkers=1` for integration tests serializes them so they don't fight over the same database rows.

## Unit tests

Test domain classes in **isolation**. No DI, no mocks, no DB. Construct the entity or value object directly and assert against its public surface.

Example — `test/unit/Wallet.test.ts`:

```ts
import Wallet from "../../src/domain/Wallet";
import Order from "../../src/domain/Order";

test("deposits credit the balance", () => {
    const wallet = Wallet.createWallet();
    wallet.deposit("USD", 10000);
    expect(wallet.getBalance("USD")).toBe(10000);
});

test("withdraw without funds throws", () => {
    const wallet = Wallet.createWallet();
    expect(() => wallet.withdraw("USD", 10000)).toThrow("Insufficient funds");
});

test("blockOrder reduces available balance", () => {
    const wallet = Wallet.createWallet();
    wallet.deposit("USD", 100000);
    const order = Order.createOrder(wallet.getAccountId(), "BTC-USD", "buy", 1, 78000);
    expect(wallet.blockOrder(order)).toBe(true);
    expect(wallet.getBalance("USD")).toBe(22000);
});
```

Properties of good unit tests:

- **Construct fresh state per test.** No shared `beforeAll` setup of mutable objects.
- **One behavior per test.** A failing test name should point at the broken rule.
- **Assert observable behavior**, not implementation details.
- **Verify error messages** when an invariant rejects (`toThrow("Insufficient funds")`).
- **Use `Wallet.createWallet()`**, the factory, not the raw constructor. Tests should use the same entry points as production code.

Aim for high unit-test density on domain classes. Every business rule should have a positive case and at least one rejection case.

## Integration tests

Test use cases end-to-end with **real adapters** — real MySQL connection, real `Mediator`, real repositories.

Example — `test/integration/PlaceOrder.test.ts`:

```ts
import Registry from "../../src/infra/di/Registry";
import { MySqlAdapter } from "../../src/infra/database/DatabaseConnection";
import { OrderRepositoryDatabase } from "../../src/infra/repository/OrderRepository";
import { WalletRepositoryDatabase } from "../../src/infra/repository/WalletRepository";
import Mediator from "../../src/infra/mediator/Mediator";
import PlaceOrder from "../../src/application/usecase/PlaceOrder";
import GetOrder from "../../src/application/usecase/GetOrder";
import Deposit from "../../src/application/usecase/Deposit";

let db: MySqlAdapter;
let placeOrder: PlaceOrder;
let getOrder: GetOrder;
let deposit: Deposit;

beforeEach(() => {
    db = new MySqlAdapter({
        host: process.env.TEST_DB_HOST!,
        port: Number(process.env.TEST_DB_PORT ?? 3306),
        user: process.env.TEST_DB_USER!,
        password: process.env.TEST_DB_PASSWORD!,
        database: process.env.TEST_DB_NAME!,
    });
    Registry.getInstance().register("databaseConnection", db);
    Registry.getInstance().register("mediator", new Mediator());
    Registry.getInstance().register("orderRepository", new OrderRepositoryDatabase());
    Registry.getInstance().register("walletRepository", new WalletRepositoryDatabase());
    deposit    = new Deposit();
    placeOrder = new PlaceOrder();
    getOrder   = new GetOrder();
});

afterEach(async () => {
    await db.close();
});

test("places a buy order against a funded wallet", async () => {
    const accountId = "00000000-0000-0000-0000-000000000001";
    await deposit.execute({ accountId, assetId: "USD", quantity: 100000 });

    const out = await placeOrder.execute({
        accountId,
        marketId: "BTC-USD",
        side: "buy",
        quantity: 1,
        price: 78000,
    });

    expect(out.orderId).toBeDefined();
    const order = await getOrder.execute(out.orderId);
    expect(order.quantity).toBe(1);
    expect(order.price).toBe(78000);
});
```

Properties of good integration tests:

- **Use a dedicated test database.** Same schema as prod, separate data. Connection details from env vars.
- **Per-test isolation**, not shared state. Either truncate tables in `beforeEach`, use unique IDs per test, or wrap each test in a transaction that rolls back at `afterEach`.
- **Close the connection in `afterEach`** so Vitest exits cleanly.
- **Don't mock the database.** That defeats the purpose. The whole point of an integration test is to verify the SQL is correct.
- **Don't test domain logic here.** Integration tests verify *the wiring*. Domain logic is unit-tested separately.

### Testing gateways — fakes, not nocks

Unit tests for use cases that depend on a gateway register an **in-memory fake** that implements the same interface. Never mock `axios`, never use `nock` — mocking the HTTP library couples the test to the adapter's implementation and defeats the port.

Canonical shape (place next to the test, or under `test/fakes/` if reused across suites):

```ts
class InMemoryAccountGateway implements AccountGateway {
    private accounts = new Map<string, { accountId: string; name: string; email: string }>();
    seed(a: { accountId: string; name: string; email: string }) {
        this.accounts.set(a.accountId, a);
    }
    async getAccountById(accountId: string) {
        const a = this.accounts.get(accountId);
        if (!a) throw new Error("Account not found");
        return a;
    }
}
```

Register the fake in `beforeEach`, seed fixtures, run the use case:

```ts
const accountGateway = new InMemoryAccountGateway();
accountGateway.seed({ accountId: "acc-1", name: "Jane", email: "jane@example.com" });
Registry.getInstance().register("accountGateway", accountGateway);
```

Use the real `AccountGatewayHttp` in a test only when the upstream is an internal service deployed alongside in compose (the cccat24 reference does this). For third-party APIs, always fake — a dead or flaky upstream must not be able to break your test suite.

**Contract tests (optional).** When a port has multiple implementations (HTTP adapter + in-memory fake), a small shared test file can run the same assertions against both to guarantee they satisfy the same contract. Skip in CI unless the upstream provides a stable test double.

### Avoiding flaky timing

Never `await sleep(10000)` to wait for a queue handler to finish — it's brittle and slow. Prefer:

- **Synchronous assertions on the in-process `Mediator`** when the side effect is in-process.
- **Polling with a short max timeout** when truly async:

  ```ts
  async function eventually<T>(fn: () => Promise<T | null>, timeoutMs = 2000): Promise<T> {
      const start = Date.now();
      while (Date.now() - start < timeoutMs) {
          const result = await fn();
          if (result !== null) return result;
          await new Promise(r => setTimeout(r, 50));
      }
      throw new Error("Timed out waiting for condition");
  }

  const order = await eventually(async () => {
      const o = await orderRepo.findById(orderId);
      return o.status === "filled" ? o : null;
  });
  ```

Reserve `sleep` for tests that are explicitly load tests; don't ship it in regular integration tests.

## API tests

A small number of end-to-end HTTP tests is valuable as a smoke test, but most behavior should already be covered by integration tests at the use-case level. API tests exist to catch routing mistakes and serialization problems, not business bugs.

```ts
import axios from "axios";

test("POST /orders returns 200 with order id", async () => {
    const res = await axios.post("http://localhost:3000/orders", { /* body */ });
    expect(res.status).toBe(200);
    expect(res.data.orderId).toBeDefined();
});
```

Run these against a Dockerized stack (see `docker.md`).

## Test data

Avoid fixture files. Build test data inline using factory methods on the entities themselves:

```ts
const wallet = Wallet.createWallet();
wallet.deposit("USD", 100000);
const order = Order.createOrder(wallet.getAccountId(), "BTC-USD", "buy", 1, 78000);
```

If a particular shape is needed across many tests, add a small helper in `test/support/` — never inside `src/`.

## Coverage

Aim for high coverage on `domain/` (90%+) and `application/usecase/` (80%+). `infra/` adapters get covered indirectly by integration tests; aim for the happy paths and known failure modes.

`vitest run --coverage` produces a report in `coverage/`. Don't gate CI on a strict coverage number; gate it on **all tests pass** and **every PR with behavior changes adds tests**.

## Common mistakes

- **Mocking the database.** Integration tests must hit the real engine — mocks hide schema mismatches.
- **Sharing state across tests** via module-level mutable variables, leading to order-dependent failures.
- **Not closing the DB pool**, leaving Vitest hanging at the end of the run.
- **Asserting on internal implementation** (private fields, intermediate calls) instead of observable behavior.
- **Long sleep-based waits** instead of polling helpers.
