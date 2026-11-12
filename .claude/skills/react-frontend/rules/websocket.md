---
paths:
  - "**/*.{ts,tsx,js,jsx}"
---

# WebSocket

Applies when a feature needs realtime feedback and the frontend talks to a Laravel backend over
broadcasting — typically Laravel Echo with a Pusher-compatible broadcaster.

Channel names and event names are a **contract shared with the backend**. Both sides must use
identical keys, so changing one without the other silently breaks the feature at runtime rather
than at build time.

## How it works

Locate the project's existing broadcast layer before adding anything — it is normally a single
module that owns connection setup, and you should extend it rather than calling `new Echo` from a
component.

- **Connection** — one place asserts/creates the connection with the configured broadcaster.
  Reuse it; never open a second connection per component.
- **Authentication** — private channels require an authorizer, which posts to the backend's
  broadcasting auth endpoint with the session cookies and CSRF token and exchanges them for a
  channel auth token. If the project already has an authorizer, route new channels through it.
- **Declared channels and events** — channel and event names should be declared as types in one
  place, not written as inline string literals at each call site, so a rename is a type error.
- **Subscription** — subscribe through the project's broadcast hook rather than touching the
  connection directly. One hook call opens the channel and listens for the named event.

Shape of a subscription:

```tsx
useBroadcast({
  channelId: CHANNEL_IDS.someResource,
  channel: `some-resource.${resourceId}`,
  event: EVENTS.someResourceUpdated,
  onMessage: (data) => {
    /* handle the payload the backend dispatched for that channel */
  },
});
```

## Rules

- A channel is scoped to a single resource instance — include the resource ID in the channel name
  so a user only receives events for records they are authorised to see.
- Realtime is an **enhancement, not a source of truth**. Treat an event as a signal to refetch or
  patch cached state, and make sure the feature still works correctly if the socket never
  connects.
- Always unsubscribe on unmount — the project's hook should handle this, so if you find yourself
  managing listeners by hand, that is a sign you bypassed it.
