---
paths:
  - "**/*.{ts,tsx,js,jsx}"
---

# Infinite Scrolling & Pagination

Applies when the backend returns a **standard Laravel paginator envelope** (as produced by
Laravel/Spatie Laravel-Data) and the frontend consumes it with TanStack Query's infinite queries.

Define the paginator meta once as a shared schema and reuse it everywhere — every paginated
endpoint returns the same envelope, so re-declaring it per feature is duplication that drifts.

```ts
export const paginationSchema = z.object({
  currentPage: z.number(),
  firstPageUrl: z.string().nullable().optional(),
  from: z.number().nullable().optional(),
  lastPage: z.number().nullable().optional(),
  lastPageUrl: z.string().nullable().optional(),
  nextPageUrl: z.string().nullable().optional(),
  path: z.string().nullable().optional(),
  perPage: z.number().nullable().optional(),
  prevPageUrl: z.string().nullable().optional(),
  to: z.number().nullable().optional(),
  total: z.number().nullable().optional(),
});
```

## Handling the Paginated Data

Create a fetch function for the paginated endpoint, in the data layer of the feature that owns it.
Use the project's **pagination-aware** fetch wrapper rather than the plain one — it is what appends
the page and filter query parameters and validates the envelope:

```ts
export const getItems = async ({
  page,
  filters,
}: {
  page: number;
  filters?: ReadOnlyURLSearchParams;
}) => {
  const searchParams = filters.toString();
  const { data, meta } = await fetchPaginationApi({
    endpoint: "items",
    page,
    filters: searchParams,
    opts: {
      schema: {
        data: getItemsResponseSchema,
      },
    },
  });

  return { data, meta };
};
```

If the project registers data providers in a central map keyed by name, add the new fetcher there
so the shared infinite-data hook can resolve it, then expose a thin named hook per endpoint:

```ts
export const useGetItems = () => useInfiniteData({ provider: "getItems" });
```

Such a hook should return both the raw `pages` and a flattened `infiniteData` — the concatenation
of every page loaded so far, which is what an infinite list renders.

## Infinite Scrolling

With the steps above in place, build the list on top of the infinite-data hook. Detect that the
user has reached the end of the scroll with the
[Intersection Observer API](https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API) —
observe the last rendered element and fetch the next page when it becomes visible:

```tsx
const { infiniteData, isLoading, fetchNextPage, hasNextPage, isFetching } =
  useGetItems();
const observer = useRef<IntersectionObserver>(null);
const lastElementRef = useCallback(
  (node: HTMLDivElement) => {
    if (isLoading || isFetching || !hasNextPage) return;
    if (observer.current) observer.current.disconnect();

    observer.current = new IntersectionObserver((entries) => {
      if (entries[0].isIntersecting) {
        fetchNextPage();
      }
    });

    if (node) observer.current.observe(node);
  },
  [fetchNextPage, hasNextPage, isFetching, isLoading],
);

if (isLoading) return <InitialLoadingState />;

return (
  <div className="flex flex-col gap-4">
    {infiniteData.map((item) => (
      <div key={item.id} ref={lastElementRef}>
        <div className="flex flex-row gap-2">{item.label}</div>
      </div>
    ))}

    {isFetching && <LoadingMoreState />}
  </div>
);
```

Note the two distinct loading states: the initial load replaces the list, while fetching a
subsequent page appends below it and must not unmount what is already on screen.

> **URL Search Params**
>
> If the query is filtered by URL search params, you **must** pass them through the `filters`
> parameter rather than building the URL yourself. The shared hook formats them *and* folds them
> into the query key — otherwise two different filter states share one cache entry and show each
> other's results.

### Infinite Scrolling with Virtualizer

For long lists, virtualize: render only the rows in (and just outside) the viewport. With a
virtualizer such as TanStack Virtual, drop the `IntersectionObserver` and drive pagination from
the virtualizer's own item range instead — the sentinel element is never rendered when it is far
off screen.

Pass `infiniteData` down to a child component so the virtualized view never has to handle
undefined or empty data:

```tsx
const { infiniteData, hasNextPage, isFetchingNextPage, fetchNextPage } =
  useInfiniteData({
    provider: "getItems",
  });

const virtualizer = useVirtualizer({
  count: hasNextPage ? infiniteData.length + 1 : infiniteData.length,
  getScrollElement: () => parentRef.current,
  estimateSize: () => 60,
  overscan: 5,
});

useEffect(() => {
  const [lastItem] = [...virtualizer.getVirtualItems()].reverse();
  if (!lastItem) {
    return;
  }
  if (
    lastItem.index >= infiniteData.length - 1 &&
    hasNextPage &&
    !isFetchingNextPage
  ) {
    fetchNextPage();
  }
}, [
  hasNextPage,
  fetchNextPage,
  infiniteData.length,
  isFetchingNextPage,
  virtualizer.getVirtualItems(),
]);
```

The scroll container is the measured parent; an inner element is sized to the full virtual height
so the scrollbar is correct, and each visible row is absolutely positioned at its measured offset:

```tsx
<div ref={parentRef} className="h-[500px] w-full overflow-auto">
  <div
    className="relative w-full"
    style={{ height: `${virtualizer.getTotalSize()}px` }}
  >
    {virtualizer.getVirtualItems().map((virtualRow) => {
      const isLoaderRow = virtualRow.index > infiniteData.length - 1;

      return (
        <div
          key={virtualRow.index}
          className="absolute left-0 top-0 w-full"
          style={{
            height: `${virtualRow.size}px`,
            transform: `translateY(${virtualRow.start}px)`,
          }}
        >
          {isLoaderRow ? (
            hasNextPage ? (
              <LoadingMoreState />
            ) : null
          ) : (
            <ItemRow item={infiniteData[virtualRow.index]} />
          )}
        </div>
      );
    })}
  </div>
</div>
```

Because the row count is `infiniteData.length + 1` while there is a next page, the extra trailing
index *is* the loader row — that is what the `isLoaderRow` check renders.
