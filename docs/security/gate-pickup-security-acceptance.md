# Gate Pickup Security Acceptance

## Project

- Application: SchoolSafe
- Module: Gate Pickup Transaction
- Framework: Laravel
- Acceptance date: 23 July 2026
- Environment used for regression: testing
- Testing database: `schoolsafe_test`

---

## 1. Acceptance Status

**Status: ACCEPTED**

The Gate Pickup Transaction security-hardening phase has completed its
automated regression process successfully.

The regression process includes route integrity, database integrity,
authorization, tenant isolation, idempotency, concurrency, audit
preservation, HTTP semantics, route identifier hardening, and sensitive
response cache prevention.

---

## 2. Authentication and Account Security

- [x] Guest users cannot access pickup history.
- [x] Guest users cannot access pickup-event detail.
- [x] Guest users cannot cancel a pickup event.
- [x] Guest users cannot cancel an individual student release.
- [x] JSON guest requests receive the localized `401` contract.
- [x] Browser guest requests redirect to `/login`.
- [x] Inactive accounts are rejected.
- [x] Accounts without a valid school binding are rejected.
- [x] Unauthorized roles are rejected.
- [x] Authentication failure precedence is tested.
- [x] Account state is rechecked at mutation time.

---

## 3. Authorization

- [x] School administrators can manage same-school pickup transactions.
- [x] Gate officers can cancel their own eligible pickup transactions.
- [x] Gate officers cannot cancel transactions confirmed by another officer.
- [x] Teachers cannot manage gate pickup transactions.
- [x] Cancellation permissions are recalculated at request time.
- [x] Authorization is rechecked inside the mutation flow.
- [x] Partial-event cancellation permissions are calculated per student.
- [x] Terminal events cannot be modified again.

---

## 4. Tenant Isolation

- [x] Pickup history is restricted to the authenticated user's school.
- [x] Pickup-event detail is restricted to the authenticated user's school.
- [x] Whole-event cancellation is tenant-scoped.
- [x] Individual-student cancellation is tenant-scoped.
- [x] Cross-tenant resources are concealed using `404`.
- [x] Missing and cross-tenant resources use equivalent concealment behavior.
- [x] Tenant binding is rechecked before database mutation.

---

## 5. Idempotency and Replay Protection

- [x] Repeating the same request does not create a second pickup event.
- [x] Reusing an idempotency key with a different payload is rejected.
- [x] A used verification attempt cannot be reused with another key.
- [x] Expired verification attempts are rejected.
- [x] Verification attempts are bound to the originating session.
- [x] Attempts bound to another session are rejected.
- [x] Successful replay returns the existing transaction safely.

---

## 6. Concurrency and Transaction Safety

- [x] Confirmation uses database transactions.
- [x] Cancellation uses database transactions.
- [x] Parent pickup-event rows are locked during cancellation.
- [x] Child pickup-event-student rows are locked during cancellation.
- [x] Remaining released students are recalculated while locked.
- [x] Parallel confirmations do not create duplicate transactions.
- [x] Parallel cancellations preserve a valid final state.
- [x] Concurrency tests use separate PHP processes and database connections.
- [x] Transaction retry behavior is enabled for cancellation operations.

---

## 7. Cancellation State Machine

- [x] Whole-event cancellation changes eligible released students to cancelled.
- [x] Partial student cancellation keeps the parent confirmed while students remain released.
- [x] Cancelling the final released student cancels the parent event.
- [x] Existing student cancellation audit data is preserved.
- [x] Repeated student cancellation is rejected.
- [x] Repeated whole-event cancellation is rejected.
- [x] A cancelled parent blocks further student cancellation.
- [x] Cancellation reason validation is enforced.
- [x] Cancellation reasons are normalized before storage.
- [x] Cancellation-window boundary behavior is tested.
- [x] Cancellation-window configuration is clamped to a safe range.
- [x] Cancellation eligibility is rechecked at mutation time.

---

## 8. Audit Integrity

- [x] Pickup events retain immutable identity snapshots.
- [x] Source-record changes do not alter historical audit snapshots.
- [x] Confirming-user audit data is retained.
- [x] Cancelling-user audit data is retained.
- [x] Parent cancellation timestamp and reason are retained.
- [x] Student cancellation timestamp and reason are retained.
- [x] Existing child audit data is not overwritten.
- [x] Failed validation does not create audit mutations.
- [x] Failed authorization does not create audit mutations.
- [x] Failed route matching does not create audit mutations.

---

## 9. History and Response Contracts

- [x] History only contains records belonging to the authenticated school.
- [x] Date filtering uses school-timezone boundaries.
- [x] Status, method, officer, and search filters can be combined.
- [x] Invalid filters are rejected.
- [x] Search values are normalized.
- [x] Overlong search values are rejected.
- [x] Officer filter options are tenant-scoped.
- [x] History ordering is deterministic.
- [x] Pagination input is validated.
- [x] History item payload has a strict safe contract.
- [x] Detail payload has a strict safe contract.
- [x] Sensitive internal fields are not exposed.

---

## 10. Route Identifier Hardening

- [x] Route identifiers only accept canonical positive integers.
- [x] Identifier `0` is rejected.
- [x] Leading-zero identifiers are rejected.
- [x] Negative identifiers are rejected.
- [x] Decimal identifiers are rejected.
- [x] Scientific-notation identifiers are rejected.
- [x] Hexadecimal-like identifiers are rejected.
- [x] Numeric separators are rejected.
- [x] Encoded whitespace and encoded plus values are rejected.
- [x] Fullwidth Unicode digits are rejected.
- [x] Arabic-Indic digits are rejected.
- [x] Oversized numeric identifiers are rejected before the controller.
- [x] The maximum signed 64-bit integer is accepted by route matching.
- [x] The first integer above signed 64-bit maximum is rejected.
- [x] Malformed identifiers produce `404` without mutation.

---

## 11. Route Registry Integrity

- [x] Required face-verification routes are registered.
- [x] Required pickup-event routes are registered.
- [x] Required gate routes are registered exactly once.
- [x] Gate route names are unique.
- [x] Gate method-and-URI combinations are unique.
- [x] Pickup-event store route is not duplicated.
- [x] Controller actions are locked by route-contract tests.
- [x] Route middleware contracts are tested.
- [x] Route parameter constraints are tested directly.
- [x] Static routes are positioned before conflicting dynamic routes.

---

## 12. HTTP Method Semantics

- [x] Detail endpoint accepts `GET` and `HEAD`.
- [x] Cancellation endpoints accept `PATCH`.
- [x] Wrong methods return `405 Method Not Allowed`.
- [x] `Allow` headers contain the expected methods.
- [x] Malformed identifiers return `404` instead of `405`.
- [x] Method matching occurs before authentication middleware.
- [x] Guest wrong-method requests do not expose resource existence.
- [x] `HEAD` responses have an empty body.
- [x] `HEAD` follows tenant concealment rules.
- [x] Canonical `OPTIONS` requests return safe method-discovery responses.
- [x] Malformed `OPTIONS` requests return `404`.
- [x] `TRACE` cannot reach pickup-event endpoints.
- [x] HTTP method discovery requests do not mutate data.

---

## 13. Sensitive Response Caching

The following header contract is applied to pickup-event responses:

```text
Cache-Control: private, no-store, no-cache, max-age=0, must-revalidate
Pragma: no-cache
Expires: 0