# Dependency Injection — Registry + `@inject`

> **Names in the examples (`orderRepository`, `walletRepository`, `httpServer`, `mediator`, `PlaceOrder`, etc.) are illustrative only.** Use names from the current project's domain when writing real code. Copy the structure, not the identifiers.

The DI mechanism is small enough to fit on one screen and powerful enough to wire an entire service. It combines four classical patterns in one file: **Singleton** (the registry has one instance), **Service Locator** (lookup by name), **Decorator** (`@inject` annotates a property), and **Proxy** (the property defers resolution until first access).

Understanding it is essential — every use case, controller, and adapter uses it.

## The Registry

The `Registry` class lives in `infra/di/Registry.ts`:

```ts
export default class Registry {
    private dependencies: { [name: string]: any } = {};
    static instance: Registry;

    private constructor() {}

    register(name: string, dependency: any) {
        this.dependencies[name] = dependency;
    }

    inject(name: string) {
        const dependency = this.dependencies[name];
        if (!dependency) throw new Error(`Dependency not found: ${name}`);
        return dependency;
    }

    static getInstance() {
        if (!Registry.instance) {
            Registry.instance = new Registry();
        }
        return Registry.instance;
    }
}

export function inject(name: string) {
    return function (target: any, propertyKey: string) {
        target[propertyKey] = new Proxy({}, {
            get(_target: any, propertyKey: string) {
                const dependency = Registry.getInstance().inject(name);
                return dependency[propertyKey];
            },
        });
    };
}
```

Three things to internalize:

1. **The registry is a singleton.** `Registry.getInstance()` is the single source of truth for the running process.
2. **`@inject` installs a Proxy at class-definition time.** The decorator runs once when the class is loaded; the property becomes a Proxy whose `get` trap fetches the real dependency from the Registry on every access.
3. **Lazy resolution.** Because of the Proxy, the dependency does not need to be registered when the class is defined — only when its methods actually run. This is what makes the composition root work: classes can `@inject("foo")` before `Registry.register("foo", ...)` is called.

## Composition root

Typical shape of `src/api.ts`:

```ts
const httpServer = new ExpressAdapter();
const queue = new RabbitMQAdapter();
await queue.connect();

Registry.getInstance().register("httpServer", httpServer);
Registry.getInstance().register("queue", queue);
Registry.getInstance().register("databaseConnection", new MySqlAdapter(...));
Registry.getInstance().register("orderRepository", new OrderRepositoryDatabase());
Registry.getInstance().register("placeOrder", new PlaceOrder());

new OrderController();
httpServer.listen(3001);
```

Conventions:

- **Names are camelCase** and match the property name on consumers (`orderRepository`, `walletRepository`, `httpServer`, `databaseConnection`, `mediator`, `queue`).
- **Order matters only for instantiation side effects** (e.g., `await queue.connect()`). Registration order does not matter for the Proxy.
- **Concrete adapters are instantiated only here.** Nowhere else in the codebase should you see `new ExpressAdapter()` or `new MySqlAdapter()`.
- **Controllers are constructed last** because they typically register HTTP routes and event handlers as side effects of construction.

## Consumer pattern

In a use case or controller:

```ts
import { inject } from "../../infra/di/Registry";
import OrderRepository from "../../infra/repository/OrderRepository";

export default class PlaceOrder {
    @inject("orderRepository") orderRepository!: OrderRepository;

    async execute(input: Input) {
        await this.orderRepository.saveOrder(/* ... */);
    }
}
```

Two TypeScript-specific things:

- **Type the property with the interface**, not the implementation. `OrderRepository` (interface) — never `OrderRepositoryDatabase` (concrete).
- **The `!` after the property name** is a definite-assignment assertion. It tells the compiler "trust me, this is set." Without it, `strict` mode complains. The Proxy is in fact installed at class-definition time, so the assertion is honest.

## tsconfig requirement

```json
{
    "compilerOptions": {
        "experimentalDecorators": true,
        "strict": true
    }
}
```

`@inject` is the only decorator pattern this skill endorses. We are not opting into the broader experimental decorator ecosystem.

## Singleton — when and when not

The Registry itself uses the **Singleton** pattern (private constructor, static `getInstance()`). That is the *correct* use of Singleton: a single global registry of dependencies, intentionally shared across the process.

**Do not** Singleton anything else by reflex. If you find yourself writing `static getInstance()` on a service or repository, ask:

- Could this be **registered** in the Registry instead? Almost always yes.
- Is sharing a single instance an actual requirement (connection pool, in-memory cache, file handle)? If so, instantiate once in `api.ts` and register it; consumers get the same instance via `@inject` without coupling to a static method.

The Registry replaces ad-hoc Singletons across the rest of the codebase.

## Testing with the Registry

Integration tests instantiate concrete adapters and register them, just like `api.ts`:

```ts
beforeEach(() => {
    const db = new MySqlAdapter({ /* test config */ });
    Registry.getInstance().register("databaseConnection", db);
    Registry.getInstance().register("walletRepository", new WalletRepositoryDatabase());
    Registry.getInstance().register("orderRepository", new OrderRepositoryDatabase());
    Registry.getInstance().register("mediator", new Mediator());
    placeOrder = new PlaceOrder();
});
```

For unit-style tests of a use case in isolation, register **fakes** (small in-memory implementations of the same interface):

```ts
class InMemoryOrderRepository implements OrderRepository {
    private store = new Map<string, Order>();
    async saveOrder(o: Order) { this.store.set(o.getOrderId(), o); }
    async getOrderById(id: string) {
        const o = this.store.get(id);
        if (!o) throw new Error("Order not found");
        return o;
    }
    // ...
}

Registry.getInstance().register("orderRepository", new InMemoryOrderRepository());
```

Prefer fakes over mocks: a real implementation of the interface that lives in memory is easier to maintain than a mock and exercises the full contract.

## Common mistakes

- **Forgetting `experimentalDecorators: true`.** Compiler complains about `@inject`.
- **Typing the property with the concrete class.** Couples the consumer to the implementation; defeats the point of DI.
- **Reading the dependency in a class field initializer.** `myValue = this.someRepo.someMethod()` runs at construction; if the registry is not yet populated, this errors. Move the call into a method.
- **Calling `Registry.getInstance().register(...)` outside `api.ts` or test setup.** That breaks the "one place to wire" rule.
- **Using `@inject` in a domain class.** Domain must not depend on infrastructure. If a domain object needs data from a repo, the use case fetches it and passes it in.
