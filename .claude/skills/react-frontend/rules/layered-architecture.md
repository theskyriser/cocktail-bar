---
paths:
  - "**/*.{ts,tsx,js,jsx}"
---

# Layered Architecture Rules

## Architecture Overview

The frontend follows a **four-layer architecture** organized by domain context modules:

```
View Layer          (React components — render UI, handle interactions)
    |
    v depends on
Hook Layer          (React hooks — bridge state, compose domain + data)
    |
    v depends on
Domain Layer        (Zod schemas, types, enums, pure functions — no React)
    |
    v depends on
Data Layer          (Provider functions, adapters — API communication)
    |
    v depends on
External Systems    (REST APIs, WebSockets)
```

Each domain lives in its own **context module** — one directory per bounded context, which
mirrors the layering internally. Follow whatever naming the project already uses for these
modules (`src/context.{domain}/`, `src/features/{domain}/` and `src/modules/{domain}/` are all
common); the structure inside matters more than the prefix:

```
context.{domain}/
  _domain/          <- Domain Layer (schemas, types, enums, pure utils)
  components/       <- View Layer (React components)
  hooks/            <- Hook Layer (domain-specific hooks)
  data/             <- Data Layer (API provider functions, adapters)
  store/            <- State Layer (Zustand stores, only when React Query is insufficient)
  constants/        <- Domain constants
  util/             <- Domain pure utility functions
```

---

## 1. Layer Responsibilities

### Data Layer (`data/`)

The data layer is the **anti-corruption layer** between the backend API and the domain.

| Allowed                                                      | Forbidden                  |
| ------------------------------------------------------------ | -------------------------- |
| Calls through the shared typed fetch wrapper                  | Business rule logic        |
| Zod schema validation on request/response                    | React imports              |
| Adapter transformations (API shape to domain shape)          | UI rendering               |
| Registering providers in the central provider registry        | Direct domain calculations |

```typescript
// context.customer/data/getCustomer.ts

const customerAdapter = createApiResponseAdapter<TCustomerType, TCustomer>(
  (customerRes) => ({
    ...customerRes,
    data: {
      ...customerRes.data,
      createdAt: customerRes.data.createdAt
        ? new Date(customerRes.data.createdAt)
        : null,
      categoryId: customerRes.data.categoryId
        ? Number(customerRes.data.categoryId)
        : null,
    },
  }),
);

export const getCustomer = async (opts: { data: { customerId: string } }) => {
  const res = await fetchApi(`customers/${opts.data.customerId}`, {
    method: "GET",
    schema: { data: customerSchema },
  });

  return customerAdapter(res);
};
```

**Rules:**

- Every provider function MUST include a Zod `schema` for response validation.
- Use `createAdapter` or `createApiResponseAdapter` when the API response shape differs from the domain type (e.g., string dates to `Date` objects).
- The adapter is the ONLY place where API-to-domain conversion happens. Components and hooks never see raw API shapes.
- Every provider MUST be registered in the project's central provider registry, under the map matching its kind (plain read, paginated read, or write).
- Never call `fetch` or `axios` directly. Always go through the project's shared fetch wrapper, so auth, scoping, error shaping and schema validation are applied consistently.

### Domain Layer (`_domain/`)

The domain layer defines the **shape of business data** and **pure business logic**. It has zero React dependencies.

| Allowed                                                    | Forbidden                                    |
| ---------------------------------------------------------- | -------------------------------------------- |
| Zod schemas (`model.schemas.*.ts`)                         | React imports (`useState`, `useEffect`, JSX) |
| TypeScript types derived from schemas (`model.types.*.ts`) | API calls or network logic                   |
| Enum constants with Zod integration (`model.enum.*.ts`)    | UI references (classNames, DOM)              |
| Pure utility functions (`model.util.*.ts`)                 | Side effects of any kind                     |
| Label maps, icon maps, status maps                         | Framework-specific code                      |

```typescript
// context.order/_domain/model.enum.order.ts

export const ORDER_STATE = {
  ACTIVE: "ACTIVE",
  EXPIRED: "EXPIRED",
  PROCESSING: "PROCESSING",
  FULFILLED: "FULFILLED",
} as const;

export const orderStateEnum = z.nativeEnum(ORDER_STATE);

export const ORDER_STATE_LABEL_MAP = {
  [orderStateEnum.enum.ACTIVE]: "Active",
  [orderStateEnum.enum.EXPIRED]: "Expired",
  [orderStateEnum.enum.PROCESSING]: "Processing",
  [orderStateEnum.enum.FULFILLED]: "Fulfilled",
} as const;
```

```typescript
// context.customer/_domain/model.types.customer.ts

export const customerSchema = z.object({
  id: z.string(),
  label: z.string(),
  name: z.string().nullable(),
  status: customerStatusEnum,
  // ...
});

export const customerResourceSchema = customerSchema.merge(
  z.object({
    createdAt: z.date().nullable(),
    categoryId: z.number().nullable(),
  }),
);

export type TCustomerType = z.infer<typeof customerSchema>; // API shape
export type TCustomer = z.infer<typeof customerResourceSchema>; // Domain shape

export const emptyCustomer: Omit<TCustomer, "id"> = {
  label: "",
  name: "",
  status: customerStatusEnum.enum.ACTIVE,
  // ...
};
```

**Rules:**

- Zod schemas are the **single source of truth**. Types are always derived via `z.infer<typeof schema>`.
- Never use `interface`. Always use `type`.
- Separate API shape (`TCustomerType`) from domain shape (`TCustomer`) when they differ. The adapter in the data layer converts between them.
- Enums follow the pattern: `const object` + `z.nativeEnum()` + label map.
- Pure utility functions in `model.util.*.ts` must be testable without React context.
- Domain files MUST NOT import from `components/`, `hooks/`, `data/`, or `store/`.

### Hook Layer (`hooks/`)

Hooks are the **bridge** between React state and the domain/data layers.

| Allowed                                         | Forbidden                                      |
| ----------------------------------------------- | ---------------------------------------------- |
| `useData` / `useDataMutation` calls             | Direct `fetch` / `axios` calls                 |
| React state management (`useState`, `useMemo`)  | Business logic that belongs in `_domain/`      |
| Composing domain functions with React lifecycle | Rendering JSX                                  |
| Query invalidation via `useQueryClient`         | Data transformation that belongs in an adapter |

```typescript
// context.customer/hooks/useSaveCustomer.tsx

export const useSaveCustomer = ({ onSuccess }: { onSuccess?: () => void }) => {
  const { isPending, call, error } = useDataMutation({
    provider: "updateCustomer",
    onSuccess,
  });

  return {
    saveCustomer: call,
    isSavingCustomer: isPending,
    saveCustomerErrors: error,
  };
};
```

**Rules:**

- Hooks wrap `useData` or `useDataMutation` and return a **domain-friendly interface** (renamed fields, composed logic).
- Hooks MUST NOT contain inline business logic. Delegate calculations to domain utility functions.
- Hooks MUST NOT render JSX.
- Return an object with descriptive names (`saveCustomer`, `isSavingCustomer`) instead of generic React Query names (`mutate`, `isPending`).
- **Extract cross-cutting concerns into dedicated hooks from the start.** When a feature requires data fetching (`useData`, `useParams`) + business rule evaluation + UI state management (modals, refs, pending state), bundle these into a single purpose-specific hook. The component receives a clean interface and only orchestrates when to call what. Do NOT start with logic inline in the component and refactor later.

### View Layer (`components/`)

Components render UI. They consume hooks and domain types but never access the data layer directly.

| Allowed                                   | Forbidden                      |
| ----------------------------------------- | ------------------------------ |
| JSX rendering                             | Direct `fetchApi` calls |
| Consuming hooks (`useData`, domain hooks) | Inline business logic          |
| Tailwind classes from theme               | Hardcoded colors, `style={{}}` |
| `<Icon>` component                        | Raw SVG                        |
| `<Form>` component with Zod resolver      | Untyped forms                  |

**Rules:**

- Components are **pure functions of their props** whenever possible.
- Container components compose hooks and pass data down to presentational children.
- Max 200 lines per component file. Decompose if exceeded.
- Max 50 lines per function. Extract if exceeded.
- **Components MUST NOT fetch data for business rule evaluation.** If a component needs server data to make a decision (e.g., checking limits, validating transitions, conditional interception), that data fetching + business logic MUST live in a dedicated hook. The component only calls the hook and wires results to JSX.

---

## 2. Dependency Direction (Strict)

```
components/ -> hooks/ -> _domain/
                    \-> data/    -> _domain/
```

- `components/` MAY import from `hooks/`, `_domain/`, `constants/`, `util/`.
- `hooks/` MAY import from `data/` (via `useData`/`useDataMutation`), `_domain/`, `store/`.
- `data/` MAY import from `_domain/` (schemas, types).
- `_domain/` MUST NOT import from `components/`, `hooks/`, `data/`, or `store/`.

**Never reverse dependencies.** The domain layer is the foundation; everything depends on it, but it depends on nothing above it.

---

## 3. Strategy Pattern (Replace Scattered Conditionals)

When the same conditional logic appears (or would appear) in multiple files, encapsulate it in the domain layer using a **map object** or **strategy function**.

### BAD: Shotgun Surgery

```typescript
// file1.tsx
const icon =
  status === "ACTIVE"
    ? "check.svg"
    : status === "EXPIRED"
      ? "x.svg"
      : "clock.svg";

// file2.tsx
const label =
  status === "ACTIVE" ? "Active" : status === "EXPIRED" ? "Expired" : "Pending";

// file3.tsx
const color =
  status === "ACTIVE"
    ? "text-success"
    : status === "EXPIRED"
      ? "text-error"
      : "text-warning";
```

Adding a new status requires changes in 3+ files.

### GOOD: Encapsulated in Domain

```typescript
// _domain/model.enum.order.ts
export const ORDER_STATE_LABEL_MAP = {
  [orderStateEnum.enum.ACTIVE]: "Active",
  [orderStateEnum.enum.EXPIRED]: "Expired",
  [orderStateEnum.enum.PROCESSING]: "Processing",
} as const;

export const ORDER_STATE_ICON_MAP = {
  [orderStateEnum.enum.ACTIVE]: "check.svg",
  [orderStateEnum.enum.EXPIRED]: "x.svg",
  [orderStateEnum.enum.PROCESSING]: "clock.svg",
} as const;

export const ORDER_STATE_CLASS_MAP = {
  [orderStateEnum.enum.ACTIVE]: "text-success",
  [orderStateEnum.enum.EXPIRED]: "text-error",
  [orderStateEnum.enum.PROCESSING]: "text-warning",
} as const;
```

```typescript
// components/OrderStatusBadge.tsx
export const OrderStatusBadge = ({ state }: { state: TOrderState }) => (
    <span className={ORDER_STATE_CLASS_MAP[state]}>
        <Icon src={ORDER_STATE_ICON_MAP[state]} size={4} />
        {ORDER_STATE_LABEL_MAP[state]}
    </span>
)
```

Adding a new status = one change in `_domain/`, zero changes in components.

**Rules:**

- When a value varies by enum/state, create a `*_MAP` constant in the domain layer.
- Components read from maps, never branch on raw enum values.
- Always use `satisfies` on map constants to enforce exhaustiveness. For a map keyed by an enum type, use `satisfies Record<TEnumType, ValueType>`. Combine with `as const` to preserve literal types: `} as const satisfies Record<TChannel, string>`. This catches missing enum cases at compile time.
- For complex behavior that varies by type, use a **strategy function map**:

```typescript
// _domain/model.util.lineItem.ts
type TLineItemStrategy = {
  getLabel: (item: TLineItem) => string;
  calculateTotal: (item: TLineItem) => number;
};

export const LINE_ITEM_STRATEGY_MAP: Record<TOrderItemType, TLineItemStrategy> =
  {
    [ORDER_ITEM_TYPES.STANDARD]: {
      getLabel: (item) => `Standard - ${item.name}`,
      calculateTotal: (item) => item.monthlyRate * item.quantity,
    },
    [ORDER_ITEM_TYPES.PREMIUM]: {
      getLabel: (item) => `Premium - ${item.name}`,
      calculateTotal: (item) => item.unitPrice * item.quantity,
    },
  };
```

---

## 4. Data Flow Patterns

### Reading Data

```
Component
  -> useData({ provider: 'getCustomer' }, { customerId })
    -> provider registry resolves getCustomer function
      -> getCustomer() calls fetchApi() with Zod schema
        -> Zod validates response
          -> createApiResponseAdapter transforms to domain type
            -> returns CreateApiResponse<TCustomer>
              -> useData returns { data, isLoading, isError, error }
```

```typescript
// In a component
const { data, isLoading } = useData(
  { provider: "getCustomer", enabled: !!customerId },
  { data: { customerId } },
);
```

### Writing Data

```
Component
  -> useDataMutation({ provider: 'updateCustomer' })
    -> returns { call, isPending, error, bind }
      -> call({ customer: { ...formData, id } })
        -> updateCustomer() calls fetchApi() with method + body + schema
          -> onSuccess: showToast() + queryClient.invalidateQueries()
```

```typescript
// In a hook
const { call, isPending, error } = useDataMutation({
  provider: "updateCustomer",
  onSuccess: () => {
    showToast("Customer saved!", "success");
    queryClient.invalidateQueries({ queryKey: ["getCustomer"] });
  },
});
```

### Form Flow

```
<Form form={useForm({ resolver: zodResolver(schema), defaultValues })}>
  -> <Fields /> uses useFormContext() for inputs
  -> <Footer /> uses useFormContext().handleSubmit()
    -> Zod validates form data
      -> onSubmit(data: z.infer<typeof schema>)
        -> useDataMutation.call({ ...data })
          -> success: showToast + invalidateQueries
          -> error: setErrors(getFormErrors(error))
```

```typescript
// Container: composes form + mutation
export const CustomerForm = ({ customer, onSave }: TCustomerFormProps) => {
    const { saveCustomer, saveCustomerErrors } = useSaveCustomer({ onSuccess: onSave })

    const customerForm = useForm({
        resolver: zodResolver(customerFormSchema),
        mode: 'onChange',
        defaultValues: {
            name: customer.name,
            displayName: customer.displayName,
        },
    })

    const onSubmit = (data: TCustomerForm) => {
        saveCustomer({ customer: { ...data, id: customer.id } })
    }

    return (
        <Form form={customerForm} onSubmit={onSubmit}>
            <CustomerFields customerId={customer.id} />
            <CustomerFormFooter />
        </Form>
    )
}

// Presentational: renders fields using form context
export const CustomerFields = ({ customerId }: { customerId: string }) => {
    const { register } = useFormContext<TCustomerForm>()

    return (
        <div className="flex flex-col gap-3">
            <Input {...register('name')} label="Name" />
            <Input {...register('displayName')} label="Display Name" />
        </div>
    )
}

// Action: handles submit via form context
export const CustomerFormFooter = () => {
    const { handleSubmit } = useFormContext<TCustomerForm>()
    const { setErrors } = useFormContextExtended()
    const { saveCustomer, isSavingCustomer, saveCustomerErrors } = useSaveCustomer({
        onError: (err) => setErrors?.(getFormErrors(err)),
    })

    return (
        <Button onClick={handleSubmit(saveCustomer)} isLoading={isSavingCustomer}>
            Save
        </Button>
    )
}
```

**Rules:**

- Forms ALWAYS receive server data via props (not fetched inside the form).
- Forms ALWAYS use `zodResolver(schema)` for validation.
- Never use `FieldValues` type from React Hook Form. Always use `z.infer<typeof schema>`.
- Split form into: Container (schema + mutation), Fields (inputs via `useFormContext`), Footer (submit action).

---

## 5. State Management Hierarchy

Use the **least powerful tool** that solves the problem:

| Priority | Tool                                  | When to Use                                                       |
| -------- | ------------------------------------- | ----------------------------------------------------------------- |
| 1        | React Query cache (`useData`)         | Server-derived data (default choice)                              |
| 2        | URL search params (`useSearchParams`) | Panel state, filters, navigation state                            |
| 3        | Component-local `useState`            | UI-only state (toggling, hover, form input)                       |
| 4        | `useFormContext` (React Hook Form)    | Form field state                                                  |
| 5        | Zustand store                         | Cross-component client state that doesn't belong in URL or server |

**Rules:**

- React Query is the **single source of truth** for server state. Never duplicate it into Zustand or Context.
- Zustand stores use `createSelectors` for auto-generated selectors: `useStore.use.propertyName()`.
- Never subscribe to the entire Zustand store. Always select specific properties.
- Prefer URL search params over Zustand for state that should survive page refresh or be shareable.

### Zustand Store Pattern (with Immer)

When Zustand is needed, use the `immer` middleware for immutable state updates for complex objects:

```typescript
// store/useSelectionStore.ts
export const useSelectionStore = create<TSelectionState & TSelectionActions>()(
  immer((set) => ({
    selection: { state: {}, rows: {} },
    selectionData: {},

    setSelection: (groupId, state, keepOld = false) =>
      set((draft) => {
        draft.selection.state = keepOld
          ? { ...draft.selection.state, ...state }
          : state;
      }),

    updateSelectionItem: (itemId, groupId, newData) =>
      set((draft) => {
        const items = draft.selectionData?.[groupId];
        const index = items?.findIndex((item) => item.id === itemId) ?? -1;

        if (index !== -1) {
          draft.selectionData[groupId][index] = {
            ...draft.selectionData[groupId][index],
            ...newData,
          };
        }
      }),
  })),
);
```

Immer lets each action mutate a `draft` and still produce a new immutable state, which keeps
deeply-nested updates readable — the alternative is a tower of spread operators that is easy to
get subtly wrong.

---

## 6. Domain Modeling (No Classes)

Model domains with **Zod schemas + type inference + pure functions** instead of classes. The
schema carries the validation rules, the inferred type carries the shape, and pure functions
carry the behaviour.

### Schema as Source of Truth

```typescript
// _domain/model.schemas.customer.ts (validation rules)
export const customerFormSchema = z.object({
  name: z.string().min(2).max(100),
  displayName: z.string().nullable(),
  reference: z.string().nullable(),
});

// _domain/model.types.customer.ts (types derived from schema)
export type TCustomerForm = z.infer<typeof customerFormSchema>;
```

### Enum + Label Map + Icon Map (Strategy via Maps)

```typescript
// _domain/model.enum.order.ts
export const ORDER_STATE = { ACTIVE: "ACTIVE", EXPIRED: "EXPIRED" } as const;
export const orderStateEnum = z.nativeEnum(ORDER_STATE);
export type TOrderState = z.infer<typeof orderStateEnum>;

export const ORDER_STATE_LABEL_MAP = {
  [orderStateEnum.enum.ACTIVE]: "Active",
  [orderStateEnum.enum.EXPIRED]: "Expired",
} as const satisfies Record<TOrderState, string>;

export const ORDER_STATE_ICON_MAP = {
  [orderStateEnum.enum.ACTIVE]: "check.svg",
  [orderStateEnum.enum.EXPIRED]: "clock.svg",
} as const satisfies Record<TOrderState, string>;

export const ORDER_STATE_CLASS_MAP = {
  [orderStateEnum.enum.ACTIVE]: "bg-success",
  [orderStateEnum.enum.EXPIRED]: "bg-warning",
} as const satisfies Record<TOrderState, string>;
```

The class maps reference **semantic theme tokens** (`bg-success`), never literal palette values —
see the project's theme config for the available tokens.

### Pure Domain Utility Functions

```typescript
// _domain/model.util.customer.ts
export const formatCustomerLabel = (customer: TCustomer): string =>
  customer.name ?? `${customer.firstName} ${customer.lastName}`;

export const isCustomerActive = (customer: TCustomer): boolean =>
  customer.status === CUSTOMER_STATUS.ACTIVE;
```

**Rules:**

- Never use classes for domain modeling. Use Zod schemas + types + pure functions.
- Every enum follows: `const OBJECT` + `z.nativeEnum()` + `z.infer` type + label/icon/class maps.
- All `*_MAP` constants keyed by an enum type MUST use `as const satisfies Record<TEnumType, ValueType>` to enforce exhaustiveness at compile time.
- Domain utility functions are pure: same input, same output, no side effects.
- Empty/default state objects (`emptyCustomer`) live in `model.types.*.ts`.

---

## 7. Adapter as Anti-Corruption Layer

The adapter pattern isolates API shape changes from the rest of the application.

```typescript
// data/getCustomer.ts
import { createApiResponseAdapter } from "@/common/util/adapter";

// This is the ONLY place that knows the API returns dates as strings
const customerAdapter = createApiResponseAdapter<TCustomerType, TCustomer>(
  (res) => ({
    ...res,
    data: {
      ...res.data,
      createdAt: res.data.createdAt ? new Date(res.data.createdAt) : null,
    },
  }),
);
```

**Rules:**

- Use `createAdapter` for raw data transformations.
- Use `createApiResponseAdapter` for `CreateApiResponse<T>` wrapper transformations.
- Adapters live in the `data/` folder, next to the provider function that uses them.
- If the API shape matches the domain type exactly, no adapter is needed.
- When the API changes, ONLY the adapter changes. No component or hook modifications.

---

## 8. Error Handling Flow

Errors flow through a structured pipeline:

```
fetchApi throws DetailedError
  -> useData/useDataMutation catches it
    -> Categorizes blame: 'user' | 'server' | 'runtime'
      -> useData returns { data, isLoading, isError, error }
      -> useDataMutation returns { call, isPending, isError, error } (+ error.getDetails().blame for categorization)
```

### In Forms

```typescript
const { call, error } = useDataMutation({
  provider: "updateCustomer",
  onError: (err) => {
    // Server validation errors (422) -> set on form fields
    setErrors?.(getFormErrors(err));
  },
});
```

### In Components

```typescript
const { data, isLoading, isError } = useData({ provider: 'getCustomer' }, { data: { customerId } })

if (isLoading) return <Skeleton />
if (isError) return <ErrorState />

return <CustomerDetails customer={data.data} />
```

**Rules:**

- Never `try/catch` around `useData` or `useDataMutation`. Use the returned `error`, `isLoading`, and `isError` properties.
- Form validation errors: Zod schema (client) + `getFormErrors(error)` (server 422).
- Use `showToast` for user-facing notifications on mutation success/failure.

---

## 9. Testing Per Layer

### Domain Layer (`_domain/`): Pure Unit Tests

```typescript
describe("formatCustomerLabel", () => {
  it("returns the name when available", () => {
    const customer = { ...emptyCustomer, id: "1", name: "Acme" };
    expect(formatCustomerLabel(customer)).toBe("Acme");
  });
});
```

No React, no mocks, no providers. Fast and deterministic.

### Data Layer (`data/`): Schema Validation Tests

```typescript
describe("customerSchema", () => {
  it("validates a correct customer object", () => {
    const result = customerSchema.safeParse(validCustomer);
    expect(result.success).toBe(true);
  });

  it("rejects missing required fields", () => {
    const result = customerSchema.safeParse({});
    expect(result.success).toBe(false);
  });
});
```

### Hook Layer (`hooks/`): Integration Tests

```typescript
describe("useSaveCustomer", () => {
  it("calls updateCustomer provider on save", async () => {
    // Mock the provider, test the hook behavior
  });
});
```

### View Layer (`components/`): Component Tests with RTL

```typescript
describe('CustomerForm', () => {
    it('renders the name field', () => {
        render(
            <MockWrapper>
                <CustomerForm customer={mockCustomer} formTitle="Edit" />
            </MockWrapper>,
        )
        expect(screen.getByLabelText('Name')).toBeInTheDocument()
    })
})
```

**Rules:**

- Test user-visible behavior, not implementation details.
- Use `getByText`, `getByRole`, `getByLabelText`. Avoid `data-testid` except for icons and skeletons.
- Mock paths use full relative paths, never path aliases — alias resolution in the test runner often differs from the bundler's, and a mock that silently fails to apply produces a passing test of the real implementation.
- Wrap components in the project's shared test wrapper so query client, router and theme providers are present.

---

## 10. Anti-Patterns

| Anti-Pattern                                          | Why It's Wrong                       | Correct Approach                                                                             |
| ----------------------------------------------------- | ------------------------------------ | -------------------------------------------------------------------------------------------- |
| `fetchApi` inside a component                  | Couples view to network layer        | Create a provider in `data/`, consume via `useData`                                          |
| Business logic in `useEffect`                         | Untestable, lifecycle-coupled        | Extract to pure function in `_domain/model.util.*`                                           |
| Same conditional in 3+ files                          | Shotgun surgery on every change      | Create a `*_MAP` in `_domain/model.enum.*`                                                   |
| Inline Zod schema in component                        | Duplicated, untestable validation    | Define in `_domain/model.schemas.*`, import it                                               |
| `z.infer` on an anonymous schema                      | No reusable type                     | Name the schema, export both schema and type                                                 |
| `interface TProps`                                    | Inconsistent with `type` convention  | `type TProps = { ... }`                                                                      |
| `class CustomerService`                               | Classes are not the modeling unit here | Pure functions + Zod schemas + type inference                                              |
| Zustand store for server data                         | Stale, duplicated state              | Use React Query cache via `useData`                                                          |
| Raw API types in components                           | Coupled to external system           | Use adapter to convert to domain types                                                       |
| `any` type                                            | Unsafe, defeats TypeScript           | Use `unknown` + type narrowing, or proper typing                                             |
| `console.log` left in code                            | Debug noise in production            | Remove. Only `console.warn` and `console.error` allowed                                      |
| Prop spreading `{...props}`                           | Hidden dependencies, hard to trace   | Pass properties explicitly                                                                   |
| `useData`/`useParams` in component for business logic | Mixes data+logic+state in view layer | Extract a dedicated hook that owns data fetching, business rule, and UI state (modals, refs) |

---

## 11. Checklist Before Completing Any Feature

- [ ] Domain types/schemas live in `_domain/` with `model.schemas.*` / `model.types.*` / `model.enum.*` naming
- [ ] Data providers live in `data/` and are registered in the central provider registry
- [ ] Adapters handle API-to-domain conversion in `data/` (not in hooks or components)
- [ ] Hooks in `hooks/` bridge `useData`/`useDataMutation` to components with domain-friendly names
- [ ] Components in `components/` only use hooks and domain types, never call the data layer directly
- [ ] Components do NOT use `useData`/`useParams` for business rule evaluation — extract a dedicated hook instead
- [ ] Enums use `const object` + `z.nativeEnum()` + label/icon/class maps
- [ ] No scattered conditionals; all enum-dependent values use `*_MAP` objects
- [ ] Forms use `zodResolver(schema)` and split into Container, Fields, and Footer
- [ ] No `interface`, no `any`, no `default export`, no `style={{}}`
- [ ] Tests exist for each layer: domain (pure), data (schema), hooks (integration), components (RTL)
- [ ] The project's test, typecheck, and lint commands all pass
