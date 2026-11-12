# Infrastructure Layer

> **Names in the examples (`OrderController`, `OrderRepository`, `AccountGateway`, `orderPlaced` event, the `orders` table, etc.) are illustrative only.** Use names from the current project's domain when writing real code. Copy the structure, not the identifiers.

`infra/` holds everything that touches the outside world: HTTP, database, file storage, message brokers, third-party HTTP services, the clock, the filesystem. It also holds the DI Registry and the composition root.

The single rule for this layer: **everything is interface-first**. The interface is consumed by `application/`; one or more adapters in `infra/` implement it.

## HTTP server abstraction

`infra/http/HttpServer.ts`:

```ts
export default interface HttpServer {
    route(method: "get" | "post" | "put" | "delete", url: string, callback: Function): void;
    listen(port: number): void;
}

export class ExpressAdapter implements HttpServer {
    app: Express;

    constructor() {
        this.app = express();
        this.app.use(cors());
        this.app.use(express.json());
    }

    route(method: "get" | "post" | "put" | "delete", url: string, callback: Function): void {
        this.app[method](url, async (req: Request, res: Response) => {
            try {
                const output = await callback(req.params, req.body, req.query);
                res.json(output);
            } catch (e: any) {
                res.status(422).json({ message: e.message });
            }
        });
    }

    listen(port: number): void {
        this.app.listen(port);
    }
}
```

Conventions:

- The interface is small on purpose. New verbs (`patch`, `delete`) are added by extending the union — not by exposing Express specifics.
- Application code never sees `Request`/`Response`. The adapter unpacks them and hands plain objects to the callback.
- Errors thrown anywhere downstream surface as `422 { message }`. To support richer mapping, expand the catch:

```ts
catch (e: any) {
    if (e instanceof NotFoundError)        return res.status(404).json({ message: e.message });
    if (e instanceof ValidationError)      return res.status(400).json({ message: e.message });
    if (e instanceof UnauthorizedError)    return res.status(401).json({ message: e.message });
    return res.status(422).json({ message: e.message });
}
```

This is the **only** place HTTP status codes appear. Use cases throw, the adapter maps.

## Controllers

Controllers register routes and bind them to use cases. They contain no business logic.

Example — an `OrderController` at `infra/controller/OrderController.ts`:

```ts
import { inject } from "../di/Registry";
import HttpServer from "../http/HttpServer";
import PlaceOrder from "../../application/usecase/PlaceOrder";
import GetOrder from "../../application/usecase/GetOrder";

export default class OrderController {
    @inject("httpServer") httpServer!: HttpServer;
    @inject("placeOrder") placeOrder!: PlaceOrder;
    @inject("getOrder")   getOrder!: GetOrder;

    constructor() {
        this.httpServer.route("post", "/orders", async (_params, body) => {
            return this.placeOrder.execute(body);
        });

        this.httpServer.route("get", "/orders/:orderId", async (params) => {
            return this.getOrder.execute(params.orderId);
        });
    }
}
```

Notes:

- Routes are registered in the constructor. The `new OrderController()` call in `api.ts` is the only side effect that wires them up.
- Each route is a one-liner: parse params, call `execute`, return the result. If a route grows beyond two or three lines, the missing piece is probably a use case, not controller logic.
- Validation lives at the route boundary if used (zod schema applied to `body`/`params` before `execute`).

## Repositories

Example — an `OrderRepository` at `infra/repository/OrderRepository.ts`:

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
            "insert into orders (order_id, account_id, side, quantity, price, status, created_at) values (?, ?, ?, ?, ?, ?, ?)",
            [
                order.getOrderId(),
                order.getAccountId(),
                order.getSide(),
                order.getQuantity(),
                order.getPrice(),
                order.getStatus(),
                order.getTimestamp(),
            ],
        );
    }

    async getOrderById(orderId: string): Promise<Order> {
        const [row] = await this.connection.query(
            "select * from orders where order_id = ?",
            [orderId],
        );
        if (!row) throw new Error("Order not found");
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
```

Rules:

- Interface and implementation in the same file (`OrderRepository.ts`).
- The interface is the default export; the implementation is named.
- Concrete implementations use `@inject("databaseConnection")` — no `new MySqlAdapter()`.
- `getById` throws on not-found; the caller handles the error or lets it propagate.
- **Row → entity mapping is explicit.** No reflection, no auto-mapping. Field names in DB use snake_case; the mapping line normalizes them. Numeric columns are explicitly cast (`Number(row.quantity)`) because MySQL drivers return decimals as strings.
- For collections, map row by row:

```ts
const rows = await this.connection.query("select * from orders where account_id = ?", [accountId]);
return rows.map(r => new Order(r.order_id, /* ... */));
```

See `mysql.md` for the connection adapter.

## Gateways

Gateways wrap calls to **other services**. Same shape as repositories.

Example — an `AccountGateway` at `infra/gateway/AccountGateway.ts`:

```ts
export default interface AccountGateway {
    getAccountById(accountId: string): Promise<{ accountId: string; name: string; email: string }>;
}

export class AccountGatewayHttp implements AccountGateway {
    constructor(private readonly baseUrl: string) {}

    async getAccountById(accountId: string) {
        const response = await axios.get(`${this.baseUrl}/accounts/${accountId}`);
        if (response.status === 422) throw new Error(response.data.message);
        return response.data;
    }
}
```

Notes:

- The base URL is a **constructor argument**, never hardcoded. `api.ts` reads `process.env.ACCOUNT_SERVICE_URL` and passes it. Same rule applies to timeouts, auth tokens, and API keys — all config is constructor-injected.
- Gateway methods return **domain-shaped objects**, not the library's response envelope. No `AxiosResponse`, no `Response` from `fetch`, no `IncomingMessage`. Declare an explicit return type (e.g. `Promise<{ accountId: string; name: string; email: string }>`) and map inside the adapter.
- **Map error responses at the boundary.** Use cases see thrown `Error`s uniformly:
  - 4xx with a typed body → `throw new Error(body.message)` (or a domain-specific error class when the use case needs to branch on it).
  - 5xx / network failure → let axios throw; do not swallow.
  - Do not leak `AxiosError` into the application layer — catch and re-throw as a neutral `Error` if you need to rewrite the message.
- Use **`axios`** as the HTTP client in gateway adapters for consistency across services. Native `fetch` is acceptable only when no axios feature is needed; pick one per service and stay consistent.
- Set a **per-request timeout** on every gateway. A gateway without a timeout can hang the event loop on a dead upstream.
- Share a single `axios` instance per adapter to reuse the HTTP keep-alive agent:

```ts
export class AccountGatewayHttp implements AccountGateway {
    private readonly http: AxiosInstance;
    constructor(baseUrl: string, timeoutMs = 5000) {
        this.http = axios.create({ baseURL: baseUrl, timeout: timeoutMs });
    }

    async getAccountById(accountId: string) {
        const response = await this.http.get(`/accounts/${accountId}`);
        if (response.status === 422) throw new Error(response.data.message);
        return response.data;
    }
}
```

### Writing a new gateway in four steps

1. Create `src/infra/gateway/<Service>Gateway.ts` with the interface (default export) and `<Service>GatewayHttp` class (named export). Define typed `Input*` / `Output*` types next to the interface.
2. Read config in `src/api.ts`: `const svcUrl = process.env.FOO_SERVICE_URL!;`. Register: `Registry.getInstance().register("fooGateway", new FooGatewayHttp(svcUrl));`.
3. Consume in a use case via `@inject("fooGateway") fooGateway!: FooGateway;` — typed as the interface, never the concrete adapter.
4. For tests, write `InMemoryFooGateway implements FooGateway` and register it in `beforeEach`. Never mock `axios`, never use `nock`.

## Composition root (`src/api.ts`)

The single place where:

- env vars are read,
- concrete adapters are instantiated,
- dependencies are registered,
- the HTTP server starts.

Typical shape:

```ts
import "dotenv/config";
import Registry from "./infra/di/Registry";
import { ExpressAdapter } from "./infra/http/HttpServer";
import { MySqlAdapter } from "./infra/database/DatabaseConnection";
import { OrderRepositoryDatabase } from "./infra/repository/OrderRepository";
import { AccountGatewayHttp } from "./infra/gateway/AccountGateway";
import { AzureBlobStorage } from "./infra/storage/BlobStorage";
import { PdfKitGenerator } from "./infra/pdf/PdfGenerator";
import PlaceOrder from "./application/usecase/PlaceOrder";
import GetOrder from "./application/usecase/GetOrder";
import OrderController from "./infra/controller/OrderController";

async function main() {
    const httpServer = new ExpressAdapter();
    const db = new MySqlAdapter({
        host: process.env.DB_HOST!,
        port: Number(process.env.DB_PORT ?? 3306),
        user: process.env.DB_USER!,
        password: process.env.DB_PASSWORD!,
        database: process.env.DB_NAME!,
    });
    const blob = new AzureBlobStorage({
        connectionString: process.env.AZURE_STORAGE_CONNECTION_STRING!,
        container:        process.env.AZURE_STORAGE_CONTAINER!,
    });

    Registry.getInstance().register("httpServer", httpServer);
    Registry.getInstance().register("databaseConnection", db);
    Registry.getInstance().register("blobStorage", blob);
    Registry.getInstance().register("pdfGenerator", new PdfKitGenerator());
    Registry.getInstance().register("orderRepository", new OrderRepositoryDatabase());
    Registry.getInstance().register("accountGateway", new AccountGatewayHttp(process.env.ACCOUNT_SERVICE_URL!));
    Registry.getInstance().register("placeOrder", new PlaceOrder());
    Registry.getInstance().register("getOrder",   new GetOrder());

    new OrderController();
    httpServer.listen(Number(process.env.PORT ?? 3000));
}

main();
```

The `!` after env reads is acceptable here because `api.ts` is the boundary where missing config should fail loudly at startup. For belt-and-braces, gate them through a small `getEnv(name)` helper that throws with a clear message.

## Mediator (in-process events)

Use a `Mediator` at `infra/mediator/Mediator.ts` when one use case raises an event that other code reacts to inside the same process.

```ts
export default class Mediator {
    handlers: { event: string; callback: Function }[] = [];

    register(event: string, callback: Function) {
        this.handlers.push({ event, callback });
    }

    async notifyAll(event: string, data: any) {
        for (const handler of this.handlers) {
            if (handler.event === event) await handler.callback(data);
        }
    }
}
```

Use cases call `mediator.notifyAll("orderPlaced", order)`. Controllers register handlers in their constructor:

```ts
this.mediator.register("orderPlaced", async (order: Order) => {
    await this.queue.publish("orderPlaced", { /* DTO */ });
});
```

This keeps the use case ignorant of the queue and lets tests subscribe directly.

## What goes in `infra/`

| Concern | Folder |
|---------|--------|
| Express adapter, Fastify adapter | `http/` |
| MySQL adapter | `database/` |
| Repositories | `repository/` |
| External HTTP services | `gateway/` |
| Azure Blob Storage | `storage/` |
| PDFKit | `pdf/` |
| RabbitMQ / Kafka | `queue/` |
| Mailer (SMTP, SES, SendGrid) | `mailer/` |
| In-process event bus | `mediator/` |
| Logger | `logger/` |
| The Registry | `di/` |
| Controllers | `controller/` |

Each follows the same shape: interface in the file's default export, one or more adapters as named exports.
