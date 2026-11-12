# TypeScript — Strict Typing Rules

> **Names in the examples (`OrderId`, `OrderRow`, `PlaceOrderBody`, `OrderPlacedEvent`, etc.) are illustrative only.** Use names from the current project's domain when writing real code. Copy the structure, not the identifiers.

The standard is **strong, explicit types at every boundary**. No `any`, no unsound casts, no `unknown` leaks past the adapter layer. This file is the contract.

Target **TypeScript `^5.9`** (current stable). `experimentalDecorators: true` remains required for the `@inject` property decorator; do not switch to the Stage-3 decorators proposal until the Registry + Proxy pattern is rewritten to match.

## tsconfig.json

```json
{
    "compilerOptions": {
        "target": "ES2022",
        "module": "commonjs",
        "outDir": "dist",
        "rootDir": "src",
        "strict": true,
        "noImplicitAny": true,
        "strictNullChecks": true,
        "strictFunctionTypes": true,
        "strictBindCallApply": true,
        "strictPropertyInitialization": true,
        "noImplicitThis": true,
        "alwaysStrict": true,
        "noUncheckedIndexedAccess": true,
        "noImplicitOverride": true,
        "noFallthroughCasesInSwitch": true,
        "noImplicitReturns": true,
        "noUnusedLocals": true,
        "noUnusedParameters": true,
        "exactOptionalPropertyTypes": true,
        "experimentalDecorators": true,
        "emitDecoratorMetadata": false,
        "esModuleInterop": true,
        "forceConsistentCasingInFileNames": true,
        "skipLibCheck": true,
        "resolveJsonModule": true
    },
    "include": ["src", "test"]
}
```

Two flags are non-negotiable:

- **`strict: true`** plus all strictness flags above.
- **`noUncheckedIndexedAccess: true`** — `array[i]` returns `T | undefined`. Forces narrowing before use; eliminates a whole class of off-by-one bugs.
- **`exactOptionalPropertyTypes: true`** — distinguishes `{ x?: number }` from `{ x: number | undefined }`. Treats them as different so optional means truly absent.

`experimentalDecorators` is enabled for the `@inject` pattern only.

## The "no any" rule

`any` is forbidden in:

- Function parameters
- Function return types
- Class properties
- Variable declarations
- Generic constraints

If you find yourself reaching for `any`, the right move is one of:

1. **Define the type.** Most uses of `any` are laziness — a row shape, a request body, a callback argument. Write the type.
2. **Use `unknown` at a true boundary** (parsing JSON, reading `req.body`), then **narrow immediately** with a type guard or zod schema.
3. **Use a generic.** When the function genuinely is polymorphic, `<T>` is the answer.

```ts
// BAD
function findById(id: string): any { /* ... */ }

// GOOD
function findById<T>(id: string): T | undefined { /* ... */ }
```

The single tolerated `any` is the **DI Registry**'s internal storage — `dependencies: { [name: string]: any }`. Consumers always type the injected property with the interface, so the `any` is contained inside the registry.

## The "no casting" rule

Avoid `as` casts and `<T>` type assertions. They are the compiler's "trust me" — and trust gets misplaced.

Acceptable cases:

- **At the boundary**, after a runtime check: `if (typeof x === "string") { /* x is string here, no cast needed */ }`.
- **`as const`** on literal arrays/objects you want to widen as readonly tuples — this is a different operator, not an unsafe cast.
- **`x as unknown as T`** is a code smell flag. If you write it, leave a comment explaining why and consider a type guard instead.

If a third-party type is wrong, write a wrapper that has the correct type and keep the cast inside the wrapper.

## The "no unknown leak" rule

`unknown` is acceptable **at a boundary** (HTTP body, queue payload, JSON parse). It must be narrowed before crossing into the rest of the code.

```ts
// HTTP route
this.httpServer.route("post", "/orders", async (_params, body) => {
    const input = PlaceOrderBody.parse(body); // zod throws on invalid; input is now fully typed
    return this.placeOrder.execute(input);
});
```

Once `input` exists, the rest of the call chain receives a typed value. Never pass `unknown` to a use case.

## Discriminated unions over optional bags

```ts
// BAD — everything optional, hard to reason about
type Input = {
    kind?: string;
    amount?: number;
    target?: string;
    fromAccount?: string;
};

// GOOD
type Input =
    | { kind: "deposit"; amount: number }
    | { kind: "withdraw"; amount: number }
    | { kind: "transfer"; amount: number; toAccountId: string };
```

The compiler will narrow on `kind` and force you to handle each case, including new ones added later.

## Branding for value-object identifiers

When a function takes "an order id," the type `string` is too loose — anything stringish satisfies it. Use a branded type to distinguish:

```ts
type Brand<T, B> = T & { readonly __brand: B };
type OrderId = Brand<string, "OrderId">;

export function asOrderId(s: string): OrderId {
    if (!/^[0-9a-f-]{36}$/i.test(s)) throw new Error("Invalid order id");
    return s as OrderId;
}
```

Branded types are an acceptable use of `as` because the brand is invented by the function and cannot leak.

For more elaborate identifiers, a value object class (`UUID`, `Email`) is preferred. Branding is for the cases where a class is overkill.

## Readonly by default

- Function parameters that are not mutated should be `Readonly<T>` or use `readonly` arrays (`readonly T[]`).
- Class fields that don't change after construction are `readonly`.
- Domain event fields are always `readonly`.

```ts
export default class OrderPlacedEvent {
    constructor(readonly orderId: string, readonly marketId: string) {}
}
```

## Interface vs type alias

The project standard is **`type`** for object shapes, DTOs, unions, and aliases; **`interface`** for contracts that consumers implement (HTTP server, repositories, gateways).

Rule of thumb:

- "I implement this contract" → `interface`.
- "This is the shape of some data" → `type`.

Examples: `HttpServer`, `OrderRepository`, `AccountGateway` are interfaces; `Input`, `Output`, `OrderRow` are types.

## Linting

Pair the strict tsconfig with `@typescript-eslint`:

```json
{
    "extends": [
        "eslint:recommended",
        "plugin:@typescript-eslint/recommended",
        "plugin:@typescript-eslint/recommended-requiring-type-checking"
    ],
    "rules": {
        "@typescript-eslint/no-explicit-any": "error",
        "@typescript-eslint/no-unsafe-assignment": "error",
        "@typescript-eslint/no-unsafe-call": "error",
        "@typescript-eslint/no-unsafe-member-access": "error",
        "@typescript-eslint/no-unsafe-return": "error",
        "@typescript-eslint/no-floating-promises": "error",
        "@typescript-eslint/await-thenable": "error",
        "@typescript-eslint/no-misused-promises": "error",
        "@typescript-eslint/consistent-type-imports": ["error", { "prefer": "type-imports" }],
        "@typescript-eslint/explicit-function-return-type": [
            "error",
            { "allowExpressions": true, "allowTypedFunctionExpressions": true }
        ]
    }
}
```

The `no-unsafe-*` rules catch the patterns where `any` slips in through library types. The `no-floating-promises` rule catches forgotten `await` calls — common and dangerous in async code.

## Strictness, applied to common patterns

### `req.body` in Express

```ts
// Express types req.body as `any`. Don't propagate it.
this.httpServer.route("post", "/orders", async (_params, body) => {
    const input = PlaceOrderBody.parse(body); // typed from here on
    return this.placeOrder.execute(input);
});
```

### MySQL row shapes

```ts
type OrderRow = {
    order_id: string;
    account_id: string;
    quantity: string;        // DECIMAL → string
    created_at: Date;
};

const row = await db.one<OrderRow>("select * from orders where order_id = ?", [id]);
```

The generic on `db.one<OrderRow>` keeps the row typed without a cast.

### Async iteration

```ts
for (const order of orders) {
    await this.repo.save(order); // sequential
}

// Parallel:
await Promise.all(orders.map(o => this.repo.save(o)));
```

`forEach` does not await. Always use `for...of` or `Promise.all`.

## Why this is worth it

Every `any` on an Express handler, a repository return type, or a DI property is a place a future refactor or schema change won't be caught at compile time. Paying the small upfront tax of explicit types avoids the long-tail debugging cost.
