---
paths:
  - "**/*.php"
---

# Backend Testing Guide

## Manual / exploratory API testing pattern

When manually exercising endpoints (Postman, `curl`, `tinker`) rather than running the automated suite:

1. **Authenticate first** against this project's login endpoint, e.g.:

```
POST http://localhost/api/auth/api-token-login

{ "email": "{test-user-email}", "password": "{test-user-password}" }
```

Use the returned access token as an `Authorization: Bearer {accessToken}` header on every subsequent request.

2. **Resolve any scoping ID the domain requires** (e.g. a tenant/organisation/account ID) from a `GET` on the relevant dashboard/me endpoint before calling scoped resource routes — most multi-tenant APIs nest resources under a scope ID (`/api/{scope}/{scopeId}/...`).

3. **Build up related resources in dependency order** — create the parent resource first, then children that reference its ID, mirroring the domain's actual aggregate structure (e.g. create a parent record, then a child record that references the parent's ID, then a `PATCH` to link them if the domain requires an explicit assignment step).

4. **Use randomised/unique values** for any field with a uniqueness constraint (names, emails, codes) so repeated test runs don't collide with prior data.

Replace the placeholder endpoints/fields above with this project's actual routes and request shapes.
