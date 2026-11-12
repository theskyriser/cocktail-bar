---
paths:
  - "**/*.{ts,tsx,js,jsx}"
---

# REM Sizing Guidelines

Applies to any project whose global stylesheet sets a **non-default or breakpoint-dependent root
font size** (e.g. a 14px base that steps up to 16px on very large screens). Check the project's
global CSS for its actual `html { font-size }` rule before doing UI work.

## Summary

- Use **rem** units for sizing by default, so everything scales with the root font size.
- When a value must be px (a third-party component that only accepts numeric pixels, a table
  library, a deliberate hairline like `text-[10px]`), compute it from the **live** root font size
  via the project's rem→px utility rather than hardcoding a conversion.
- Always verify the UI at **every** root-font-size breakpoint before marking a UI task complete.
  A layout that is correct at one base size can overflow or clip at the other.

## Why it matters

A hardcoded px value does not scale when the root font size changes, so it drifts out of
proportion with the rem-based layout around it at one breakpoint or the other. The same rem value
resolves to different pixel sizes per base size:

| Declaration | Root 14px | Root 16px |
|---|---|---|
| `font-size: 1.5rem` | 21px | 24px |
| `line-height: 2rem` | 28px | 32px |

## Editor preview

Tailwind IntelliSense shows rem values converted to px using a single configured base, so its
preview can only ever match one of the breakpoints. Set `tailwindCSS.rootFontSize` in the
workspace settings to whichever base you want previewed, and remember the hint is approximate at
the other breakpoint — trust the browser, not the tooltip.
