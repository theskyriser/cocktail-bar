---
paths:
  - "**/*.{ts,tsx,js,jsx}"
---

# Icons

Applies when a project renders icons through a **single shared `Icon` component** backed by a
folder of SVG assets (a common setup, often using a library such as ReactSVG to inline the SVG).

Locate the project's own `Icon` component and asset folder before adding an icon — the paths
below are the conventional shape, not fixed locations.

## The pattern

- All icons are SVG files living in one asset directory, often split by style (e.g. `line` and
  `solid` variants).
- One `Icon` component is the only thing that renders them. It takes a `src` prop naming the
  asset.
- The `src` prop is typed by a **generated union** of the available filenames, produced by a
  script that scans the asset directory. That is what makes a missing or misspelled icon a
  compile error instead of a blank space at runtime.
- Sizing accepts *either* a single `size` prop *or* a `height`/`width` pair — never both. The
  types should enforce the exclusivity so the ambiguous call does not compile.

```tsx
const MyComponent = (props: TIconProps) => {
  const iconProps = getSizeOrWidthAndHeight({ height, width, size });

  return <Icon src="navigation-right.svg" {...iconProps} />;
};
```

## Rules

- **Never inline a raw `<svg>`** in a component. It bypasses the typed asset list, the sizing
  API, and any shared accessibility handling.
- **Add the asset, then regenerate the types.** After dropping a new SVG into the asset folder,
  run the project's icon type-generation script — the union type is generated, so hand-editing it
  will be overwritten.
- Pick an existing icon before adding a new one; near-duplicate assets accumulate quickly.
