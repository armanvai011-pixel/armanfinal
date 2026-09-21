# Server-owned data policy

All business data is server-owned. React components may hold transient view state in memory, but they must not persist or recover business records from browser storage.

## Required data flow

```text
React component → React Query hook → domain service → PHP REST API → MySQL
```

## Forbidden in business-data code

- `localStorage`, `sessionStorage`, and IndexedDB
- Browser keys such as `patients_*`, `registry`, `appointments`, `prescriptions`, `vitals_*`, `payments`, `drugReminders_*`, and `medAdminRecord_*`
- Client-generated IDs or client-side registry snapshots
- Fire-and-forget writes that report success before the PHP API confirms persistence

## Allowed client state

- React state used only for the current view or an unsaved form
- React Query's in-memory cache, which is disposable and is never the system of record
- Server-backed UI preferences through `storageAdapter.ts`
- HttpOnly server session cookies managed by the PHP API

A component that needs business data must use the corresponding service in `src/services`. The backend remains authoritative for authorization, validation, IDs, timestamps, ownership, and audit records.
