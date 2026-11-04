# CivicLedger: Technical Requirements Document v2.0

DECENTRALTECH / CIVICLEDGER / CRYPTCAMP

HOW to build • architecture • route contracts • QR and proof protocols • security • test matrix

**Status:** Proposed • Hackathon MVP • Synthetic data only • Detailed edition • 25 October 2026

> **Scope baseline:** Six functional MVP modules (F1–F6). Former F5 community needs, F6 donations, F7 projects and F10 notifications are not part of the MVP. Legacy F8 certificates → F5; legacy F9 reporting → F6. This v2.0 expands v1.0; it adds detail but no new scope.

Detailed edition of TRD v1.0. Illustrative code is a specification aid, not production code.

## 1. Technology baseline

| Layer | Choice | Notes |
|---|---|---|
| Backend | Laravel (PHP), pin a supported release at kickoff | Policies, Form Requests, service classes, queued jobs |
| Database | MySQL 8.0.16+ InnoDB | CHECK constraints required; see Schema v2.0 |
| Queue | Redis (preferred) or database driver | Retry with exponential backoff; separate `proofs` queue |
| Frontend | React + TypeScript + Inertia.js + Tailwind CSS + shadcn/ui | Lucide icons; Inter font |
| Smart contract | Solidity ProofRegistry on a public EVM testnet (Sepolia proposed) | Chain ID pinned in config; no mainnet |
| Chain client | PHP web3 client or small Node signing sidecar | Decision at kickoff; interface in §7 |
| QR | Server-side signed payload; browser QR render; mobile camera scanning via web API | No native app |
| Testing | Pest/PHPUnit, Vitest, Playwright, Hardhat/Foundry for contract | Quality gates §13 |
| CI | GitHub Actions or equivalent | Migrate, rollback, test, lint on each push |

> **Version pinning.** At implementation kickoff, record exact PHP, Laravel, Node, MySQL, Solidity compiler, OpenZeppelin (if used), chain ID and RPC providers in a `VERSIONS.md` file. Nothing in this TRD should be read as fixing a version.

## 2. Architecture and trust boundaries

```text
Browser (React/Inertia)
   |  HTTPS, session cookie, CSRF
   v
Laravel web routes / Inertia controllers
   |  Form Requests -> Policies -> Services
   v
MySQL (system of record)  <----  Laravel queue worker
                                    |  signs and submits
                                    v
                       RPC provider (primary + fallback)
                                    |
                                    v
                  EVM testnet: ProofRegistry contract

Public:  Browser -> /verify/{token} (read-only, rate limited)
```

- **System of record is MySQL.** The chain stores only an opaque key and commitment. A chain outage must not erase approved local records.
- **Signing key lives on the server** (env or secrets manager), never in the browser. Students have no wallets.
- **Public verification** is a narrow read-only endpoint; it can recompute a commitment but never returns the nonce, student data or internal ids.
- **Protected storage** holds any private attachments; downloads re-check authorization.

### 2.1 Trust boundaries

| Boundary | Untrusted side | Controls |
|---|---|---|
| Browser to server | Client input, device clock | Validation, CSRF, auth, server clock, rate limit |
| Server to RPC | RPC provider responses | Verify chain ID, contract address, receipt status, confirmations; fallback RPC |
| Public to verify endpoint | Anonymous callers | Unguessable token, rate limit, minimal response, no enumeration |
| App to database | Application bugs | FK, CHECK, unique constraints, restricted DB grants on append-only tables |

## 3. Module implementation map

| Module | Main components | Key classes (proposed names) |
|---|---|---|
| F1 | Auth, policies, scoped queries, audit | AuthController, UserPolicy, EventPolicy, HoursPolicy, ScopeService, AuditLogger |
| F2 | Event lifecycle, registration uniqueness | EventController, EventService, RegistrationService, StoreEventRequest |
| F3 | Signed QR challenge, attendance, review | QrChallengeService, AttendanceService, ReviewService, ScanRequest |
| F4 | Canonicalization, commitment, queue, reconciliation | CanonicalPayload, ProofService, AnchorProofJob, ChainClient |
| F5 | Certificate issuance, revoke/supersede, public lookup | CertificateService, CertificatePolicy, PublicVerificationController |
| F6 | Scoped summary queries | DashboardService, ReportService |

Do not create routes, jobs, models, migrations or services for legacy F5 (community needs), F6 (donations), F7 (projects) or F10 (notifications).

## 4. Authorization model

### 4.1 Policy matrix

| Ability | Student | Coordinator | Signer | Admin |
|---|---|---|---|---|
| View own hours / certificates | Yes | Own only | Own only | Own only |
| View event list | Eligible sections | Scoped | — | All (read) |
| Create / edit / publish event | No | Scoped section | No | No (unless scoped) |
| Register for event | Eligible | No | No | No |
| Open session / show QR | No | Own scoped events | No | No |
| Scan attendance | Registered only | No | No | No |
| Manual attendance entry | No | `attendance.manual`, scoped | No | No |
| Approve / reject hours | No | `hours.review`, scoped, not own | No | No |
| View proof status | Own | Scoped | Scoped | All (read) |
| Issue / revoke certificate | No | No | `certificates.issue` / `.revoke` | No (unless also signer) |
| Manage users / roles / assignments | No | No | No | Yes |
| View audit log | No | Scoped (own actions) | Own actions | Yes |
| Public verify | Public | Public | Public | Public |

- Every controller action calls a policy; every list uses a query scope (`forUser($user)`).
- Denied requests return 403 for known resources the user may not access and 404 where existence itself is sensitive. Both are logged without sensitive payloads.
- Approval services re-check `approver_id !== student_id` and scope inside the transaction.

## 5. HTTP route and service contracts

Authenticated UI uses Inertia (HTML + props). JSON endpoints are used for scan submission, polling and the public verifier. The scan endpoint uses the JSON error envelope in §5.8; form actions report the same conditions as field errors. Public verification URLs use opaque tokens. Authenticated routes use internal ids and rely on policies for access.

### 5.1 Route table

| Method | Path | Role | Purpose | Success |
|---|---|---|---|---|
| GET | `/` | Public | Home page | 200 Inertia |
| GET | `/design-system` | Public | Design system reference | 200 Inertia |
| GET/POST | `/login` | Guest | Session sign-in (S01) | 200 / 302 |
| POST | `/logout` | Signed in | Sign out | 302 |
| GET/POST | `/register` | Guest | Self-registration | 200 / 302 |
| GET/PUT | `/password/change` | Signed in | Change a temporary password | 200 / 302 |
| GET | `/me` | Signed in | Current user details | 200 JSON |
| GET | `/profile` | Signed in | Account details | 200 Inertia |
| DELETE | `/account` | Student | Anonymize the account after a password check; the row stays with status suspended | 302 |
| GET | `/dashboard` | Any signed in | Role-specific dashboard (S02/S08) | 200 Inertia |
| GET | `/events` | Any signed in | Eligible or scoped event list | 200 |
| GET | `/events/create` | Coordinator | Event editor (S09) | 200 |
| POST | `/events` | Coordinator | Create draft | 302 -> `/events/{id}` |
| GET | `/events/{id}` | Any signed in | Event detail | 200 |
| GET | `/events/{id}/edit` | Coordinator | Event editor (S09) | 200 |
| PUT | `/events/{id}` | Coordinator | Update draft/published | 302 |
| POST | `/events/{id}/publish` | Coordinator | Publish | 302 |
| POST | `/events/{id}/cancel` | Coordinator | Cancel | 302 |
| POST | `/events/{id}/complete` | Coordinator | Complete | 302 |
| POST | `/events/{id}/sessions` | Coordinator | Add session | 302 |
| POST | `/events/{id}/register` | Student | Register once (idempotent) | 302 |
| DELETE | `/events/{id}/register` | Student | Cancel registration before attendance exists | 302 |
| POST | `/sessions/{id}/open` | Coordinator | Open session | 302 -> `/sessions/{id}/qr` |
| POST | `/sessions/{id}/close` | Coordinator | Close session | 302 |
| GET | `/sessions/{id}/qr` | Coordinator | Live QR and roster screen (S10) | 200 Inertia |
| GET | `/sessions/{id}/qr/challenge` | Coordinator | Current challenge (poll about every 25 s) | 200 JSON |
| GET | `/sessions/{id}/roster`, `/sessions/{id}/live` | Coordinator | Live roster/attendance feed | 200 JSON |
| GET | `/attendance/scan` | Student | Scanner screen (S05) | 200 |
| POST | `/attendance/scan/challenge` | Student | Remaining lifetime of a scanned code, without recording attendance | 200 JSON |
| POST | `/attendance/scan` | Student | Submit scanned payload | 200 JSON, or 302 for a form post |
| POST | `/sessions/{id}/attendance/manual` | Coordinator | Manual entry with reason | 302 |
| PATCH | `/attendance/{id}/adjust` | Coordinator | Correct attendance with a reason; sends an approved claim back to review | 302 |
| GET | `/me/hours`, `/my/hours` | Student | My attendance and hours (S06) | 200 |
| GET | `/hours/review` | Coordinator | Approval queue (S11) | 200 |
| GET | `/hours/review/{recordId}` | Coordinator | Approval detail | 200 |
| POST | `/hours/review/{recordId}/approve` | Coordinator | Approve; creates a version and queues a proof | 302 |
| POST | `/hours/review/{recordId}/reject` | Coordinator | Reject with a reason | 302 |
| POST | `/hours/review/{recordId}/revise` | Coordinator | Request revision with a reason | 302 |
| GET | `/proofs` | Coordinator | Proof status list (S12) | 200 |
| GET | `/proofs/{id}` | Coordinator | Proof detail | 200 |
| POST | `/proofs/{id}/retry` | Coordinator | Requeue failed proof | 302 |
| GET | `/certificates` | Student, Signer, Admin | My certificates / eligible records / all certificates (S07/S13) | 200 |
| POST | `/certificates` | Signer | Issue | 302 |
| POST | `/certificates/{id}/revoke` | Signer | Revoke with reason | 302 |
| POST | `/certificates/{id}/supersede` | Signer | Issue replacement with reason | 302 |
| GET | `/reports` | Coordinator | Basic reports (S14) | 200 |
| GET | `/admin/users` | Admin | Users (S15) | 200 |
| POST | `/admin/users` | Admin | Create staff account | 302 |
| PATCH | `/admin/users/{id}` | Admin | Update / suspend | 302 |
| PUT | `/admin/users/{id}/roles` | Admin | Set roles | 302 |
| POST | `/admin/users/{id}/assignments` | Admin | Add section assignment | 302 |
| DELETE | `/admin/assignments/{id}` | Admin | Remove section assignment | 302 |
| GET | `/admin/audit` | Admin | Audit viewer | 200 |
| GET | `/verify/{token}` | Public | Public verification page (S16) | 200 |
| GET | `/api/verify/{token}` | Public | JSON result for the page | 200 |

### 5.2 Request / response contracts (key endpoints)

Form actions answer with a redirect and a flash message. Validation failures return to the form with field errors.

**POST /events** (Form Request: StoreEventRequest)

```text
Request: { "title": "string<=160", "description": "string?", "venue": "string<=190",
           "component": "ROTC|LTS|CWTS", "section_id": "int?",
           "starts_at": "ISO-8601 +08:00", "ends_at": "ISO-8601 +08:00", "capacity": "int?" }
Rules: ends_at > starts_at; section_id within coordinator scope; times converted to UTC
Response: 302 to /events/{id} | 422 { errors: { field: [msgs] } }
```

**POST /events/{id}/register**

```text
Request: (empty body)
Rules: policy checks eligibility; repeating the request keeps one registration
Response: 302 back with "You are registered."
```

**GET /sessions/{id}/qr/challenge**

```text
Response 200: { "token": "<encrypted token>", "expires_at": "2026-10-25T12:00:30+08:00", "ttl": 30 }
Errors: 403 forbidden | 409 session is not open
```

**POST /attendance/scan**

```text
Request: { "payload": "<scanned token>" }  (the field may also be named "token")
Response 200: { "result": "checked_in|checked_out|already_checked_in|already_checked_out",
                "at": "2026-10-25T01:02:11.120Z", "event": { "title": "..." } }
Errors (envelope §5.8): qr_expired 410 | qr_invalid 400 | not_registered 403 |
         session_closed 409 | invalid_state 409 (second scan too soon) | rate_limited 429
```

**POST /hours/review/{recordId}/approve**, **/reject**, **/revise**

```text
Request:  { "reason": "string?" }
Rules:    reason 5-500 characters, required for reject and revise; approver != student; in scope
Response: 302 to /hours/review with a success message
Errors:   403 own claim or out of scope | 422 validation or invalid state
```

**POST /certificates**

```text
Request: { "student_id": int, "term_id": int }
Rules: certificates.issue permission; signer covers the student and term; minimum approved minutes met;
       current proof of every approved record confirmed; no active certificate for (student, term)
Response: 302 back with a success message
Errors: 403 forbidden | 422 student_id: minute shortfall, confirmation pending, or active certificate exists
```

**POST /certificates/{id}/supersede**

```text
Request: { "reason": "string, 10-500 characters" }
Rules: certificates.revoke and certificates.issue permissions; signer scope; certificate is active;
       same eligibility checks as issuing
Response: 302 back with a success message
Errors: 403 forbidden | 422 reason, certificate no longer active, or eligibility failure
```

**GET /api/verify/{token}** (public)

```text
Response 200: {
  "result": "verified|pending|mismatch|not_found|unavailable|revoked|superseded",
  "validity": "current|revoked|superseded",
  "certificate": { "number": "CL-2026-000001", "term": "2026-1", "approved_hours": 3.05,
                   "issued_at": "2026-10-25", "holder": "A*** R***" },
  "proof": { "status": "confirmed", "network": "Sepolia", "tx_hash": "0x...", "checked_at": "..." },
  "disclaimer": "Integrity checks show a record has not been altered since approval. They do not prove actual participation. This is a supplementary record, not an official completion record unless adopted by the school."
}
Not found: 200 with result=not_found (same shape, certificate null) to avoid status-code probing
```

### 5.3 Idempotency

- Registration, scan, decision, issue and revoke endpoints are safe to retry: the unique constraint or state check returns the existing outcome instead of creating a duplicate.
- Queue jobs are idempotent: AnchorProofJob checks `proof_status` and `tx_hash` before sending.

### 5.4 Rate limits

| Endpoint | Limit (proposed) | Key |
|---|---|---|
| POST `/login` | 5 per minute | IP + email |
| POST `/attendance/scan` | 10 per minute | User |
| GET `/sessions/{id}/qr/challenge` | 12 per minute | User + session |
| GET `/verify/{token}`, `/api/verify/{token}` | 30 per minute | IP |
| POST `/attendance/scan/challenge` | 30 per minute | User |
| POST `/register` | 6 per minute | IP |
| Admin mutations | 30 per minute (not applied in code yet) | User |

### 5.5 Pagination, filtering, sorting

List screens use cursor or page-based pagination (25 per page), whitelisted sort columns and scoped filters (status, event, date). Roster and approval lists must remain usable at 300 rows.

### 5.6 Time handling

Input accepted with offset; stored in UTC; API returns ISO-8601 UTC with Z; the UI formats in Asia/Manila. Server clock is the only authority for QR expiry and check times.

### 5.7 Authentication and sessions

Cookie-based session auth for the web app (Laravel session, Secure, HttpOnly, SameSite=Lax), password hashing with Argon2id or bcrypt, lockout via rate limiter, session regeneration on login, optional staff MFA for pilot.

### 5.8 Error envelope

```json
{ "error": { "code": "qr_expired", "message": "This code has expired. Ask the coordinator to show the current code.",
             "retryable": true, "request_id": "01J8..." } }
```

`qr_expired`, `qr_invalid`, `not_registered`, `session_closed` and `invalid_state` are returned by the scan endpoint today. The other codes are reserved for JSON endpoints; form actions report those conditions as field errors or 403/422 responses.

| Code | HTTP | User-facing message intent |
|---|---|---|
| `qr_expired` | 410 | Code expired; rescan current code |
| `qr_invalid` | 400 | Not a CivicLedger code; rescan |
| `not_registered` | 403 | Register for the event first |
| `session_closed` | 409 | Session is not open |
| `no_check_in` | 409 | Check in before checking out |
| `self_approval_blocked` | 403 | You cannot approve your own claim |
| `out_of_scope` | 403 | Outside your assigned section |
| `invalid_state` | 409 | Action not allowed in current status |
| `not_eligible` | 403 on registration, 422 on certificate issue | Eligibility requirement not met |
| `already_issued` | 409 | Certificate already issued; supersede instead |
| `chain_unavailable` | 503 | Blockchain check temporarily unavailable |
| `rate_limited` | 429 | Too many attempts; wait and retry |
| `forbidden` | 403 | You do not have permission for this action |
| `event_full` | 409 | Event is full |
| `event_not_open_for_registration` | 422 | Registration is not open for this event |
| `session_not_open` | 409 | Session is not open, so no QR can be issued |
| `note_required` | 422 | A note is required to reject or request revision |

## 6. QR attendance protocol

### 6.1 Challenge format

```text
token = Crypt::encryptString(json({ "s": <session id>, "n": "<random 40-character nonce>", "e": <expiry epoch seconds> }))
Server stores: nonce_hash = HMAC-SHA256(nonce, APP_KEY), session_id, expires_at
```

Laravel encryption is authenticated, so the token is both opaque and tamper-proof. The token carries no purpose: a student's first valid scan is the check-in and the next is the check-out.

### 6.2 Validation sequence (inside one transaction)

1. Decrypt the token. Fail: `qr_invalid`.
2. Check `e` against the server clock. Fail: `qr_expired`.
3. Look up `qr_challenges` by session and `nonce_hash`. Missing: `qr_invalid`. Past its expiry: `qr_expired`.
4. Load the session; it must be open and its event published. Fail: `session_closed`.
5. Verify the user is a registered student of the event. Fail: `not_registered`.
6. First scan: insert attendance (unique (`session_id`, `student_id`)) and return `checked_in`.
7. Second scan: at least `CIVICLEDGER_MIN_CHECKOUT_SECONDS` after check-in, set `check_out_at` = now and status = `completed`, and open the service record claim. Sooner: `invalid_state`.
8. Later scans change nothing and return `already_checked_in` or `already_checked_out` with the original time.

### 6.3 Replay and abuse analysis

| Threat | Mitigation |
|---|---|
| Screenshot shared with friend | Expires in 30 s; friend must also be registered; unique constraint prevents double credit; coordinator review of exceptions |
| Replay of old payload | Server clock expiry; nonce hash lookup |
| Forged payload | Authenticated encryption; `APP_KEY` outside DB |
| Scan from remote location | Not prevented by design (documented limit). Coordinator reviews live roster; optional P1 geofence is out of MVP. |
| Brute-force scans | Rate limit per user; 429 |
| Clock manipulation on phone | Irrelevant; server time used |

Manual entry requires `attendance.manual`, a reason of at least 10 characters, writes `attendance_adjustments` and an audit row. No offline check-in in the MVP.

## 7. Blockchain proof protocol

### 7.1 Canonicalization

Serialize the approved record version into a stable canonical payload (Schema v2.0 §9): sorted keys, UTF-8, no whitespace, integer minutes, opaque references, no PII. CanonicalSerializer has golden-file tests.

### 7.2 Commitment and record key

Pinned for the MVP: a keyed HMAC-SHA256, with a per-record key derived from a secret held outside the database. Recomputing a commitment needs that secret, so no per-record nonce or `record_secrets` table is used.

```text
recordKey  = "0x" + hex(random_bytes(32))                 # opaque; stored in blockchain_proofs
derivedKey = HMAC_SHA256( CIVICLEDGER_PROOF_SECRET, recordKey )
commitment = "0x" + HMAC_SHA256( derivedKey, canonical_json )
On chain:    submit(recordKey, commitment, recordType, version)
```

### 7.3 ProofRegistry contract (interface)

```solidity
// SPDX-License-Identifier: MIT (illustrative interface, not audited)
interface IProofRegistry {
    enum RecordType { ServiceRecord, Certificate }
    event ProofRegistered(bytes32 indexed recordKey, uint32 version, bytes32 commitment, RecordType t);
    event ProofRevoked(bytes32 indexed recordKey, uint32 version);
    function register(bytes32 recordKey, bytes32 commitment, RecordType t, uint32 version) external; // onlyRole(WRITER)
    function revoke(bytes32 recordKey, uint32 version) external; // onlyRole(WRITER)
    function get(bytes32 recordKey, uint32 version) external view returns (bytes32 commitment, uint64 timestamp, bool revoked);
}
```

- Contract rejects overwriting an existing (recordKey, version); a correction registers version + 1.
- Only the server writer address holds WRITER; an admin address holds DEFAULT_ADMIN for key rotation.
- Contract tests cover duplicate register, role restriction, revoke, view of unknown key, and event emission.

### 7.4 Job pipeline

| Step | Job | Behavior |
|---|---|---|
| 1 | Queue (inside the approval or issuance transaction) | Create canonical payload, commitment and a `blockchain_proofs` row with queued; dispatch AnchorProofJob after commit |
| 2 | AnchorProofJob: submit | Skip if `tx_hash` set; send; save `tx_hash`, status submitted; backoff 15 s, 30 s, 1 m, 2 m, 5 m, 10 m; max 10 tries |
| 3 | AnchorProofJob: reconcile | Fetch receipt; require status success, correct `chain_id`, correct contract address and N confirmations (default 3); set confirmed, `confirmed_at`; otherwise retry after 20 s |
| 4 | Failure handling | Reverted, wrong chain or exhausted: failed with `last_error`; coordinator can retry via `/proofs/{id}/retry` |
| 5 | RPC fallback | Planned for the EVM client; see the note below |

A submitted transaction is never treated as confirmed.

> **Implementation status.** `EvmChainClient` is not implemented yet: every call reports the chain as unavailable, so proofs on that driver never confirm and no certificate can be issued. The demo uses `FakeChainClient`, which stores proofs in the cache and is blocked in production. Real anchoring still needs the deployed contract, a funded signer key and RPC URLs.

### 7.5 Verification algorithm

1. Find certificate by `public_token`. None: `not_found`.
2. If certificate revoked: return validity Revoked (primary outcome Revoked) even if proof is confirmed.
3. If superseded: return Superseded.
4. Load proof row. Failed: unavailable. Queued or submitted: pending.
5. Read the commitment for the record key from the chain. On failure: unavailable.
6. Recompute the canonical payload from current database values, then the keyed commitment.
7. Compare commitments. Equal: verified; different: mismatch; no record on chain for a confirmed proof: mismatch.
8. Return result with minimal facts and technical evidence block.

| Result | Meaning | UI primary outcome |
|---|---|---|
| Verified | Confirmed proof and recomputed commitment matches | Green check + "Record integrity verified" |
| Pending | Proof queued/submitted/awaiting confirmations | Amber + "Awaiting blockchain confirmation" |
| Mismatch | Recomputed commitment differs from chain | Red + "Record does not match the anchored version" |
| Not Found | Token or on-chain record unknown | Neutral + "No record found" |
| Unavailable | Chain or RPC cannot be checked | Slate + "Unable to check right now; try again" |
| Revoked / Superseded | Validity overrides integrity | Red / Slate + validity statement |

## 8. Security and privacy controls

| Area | Control |
|---|---|
| AuthN/AuthZ | Policy on every mutation and download; scoped queries; 403 logging |
| CSRF / XSS | Laravel CSRF; escape output; strict CSP (self + required scripts); no inline user HTML |
| Transport | HTTPS only; HSTS; secure cookies |
| Secrets | Env-managed or secrets manager: `APP_KEY`, `CIVICLEDGER_PROOF_SECRET`, and the chain signer and RPC keys once the EVM client exists; never logged; never sent to browser |
| Data minimization | Public API returns only listed fields; chain has no PII |
| Headers | X-Content-Type-Options, X-Frame-Options/frame-ancestors, Referrer-Policy |
| Logging | Structured JSON logs with `request_id`; no payloads, tokens or secrets |
| Uploads | None required in MVP; if added, store privately, authorize each download |
| Pilot gate | School DPO review, retention rules, key management (rotation), staff MFA, backups, penetration/security testing |

### 8.1 Key management and rotation

- No key id is stored with a proof, so rotating `CIVICLEDGER_PROOF_SECRET` makes existing commitments read as Mismatch. Plan key ids or re-anchoring before rotating it in a pilot.
- Chain signer is a dedicated testnet account with only testnet funds; never reuse a personal wallet.
- Document funding and recovery of the testnet account in the runbook; keep a funded backup account.

## 9. Data access and performance

- Use eager loading to prevent N+1 queries on rosters and approval queues.
- Index per Schema v2.0 §8; verify with EXPLAIN on the seeded dataset for event list, roster, approval queue and dashboard.
- Dashboard aggregates are computed from indexed queries; cache for 30–60 s per user scope if needed.
- Live roster polls every 5–10 s (or uses Server-Sent Events); payload excludes sensitive fields.

## 10. Frontend implementation notes

- Inertia pages map 1:1 to screens S01–S17 (see App Flow v2.0). Shared layout provides role-aware navigation, flash toasts and error boundary.
- Scanner uses getUserMedia with BarcodeDetector where available and a JS fallback library; show permission and error guidance.
- Proof and verification states use one StatusBadge component with three variants (approval, proof, validity), each with icon and text.
- Forms use shadcn/ui Form with server validation errors mapped to fields; submit buttons disable and show progress to avoid double submission.
- Time formatting helpers convert UTC to Asia/Manila.

## 11. Environment and configuration

| Variable | Purpose | Default |
|---|---|---|
| `APP_ENV` | local / production; seeders refuse to run in production | local |
| `CIVICLEDGER_QR_TTL` | QR challenge lifetime in seconds | 30 |
| `CIVICLEDGER_MIN_CHECKOUT_SECONDS` | Minimum gap between check-in and check-out scans | 30 |
| `CIVICLEDGER_ROUNDING` | floor / round / ceil | floor |
| `CIVICLEDGER_CERT_MIN_MINUTES` | Eligibility threshold | 1 (placeholder, school decides) |
| `CIVICLEDGER_PROOF_SECRET` | Secret for commitment keys; required, never `APP_KEY` | none |
| `CIVICLEDGER_CHAIN_DRIVER` | fake / evm | fake |
| `CIVICLEDGER_CHAIN_ID` | EVM chain id | 11155111 (Sepolia, to be pinned) |
| `CIVICLEDGER_CHAIN_NAME` | Network label | Sepolia |
| `CIVICLEDGER_CONTRACT_ADDRESS` | Contract address | zero address until deployed |
| `CIVICLEDGER_CONFIRMATIONS` | Required confirmations | 3 |
| `CIVICLEDGER_RPC_URL` / `CIVICLEDGER_RPC_FALLBACK_URL` | RPC URLs for the EVM client | none |

## 12. Operations and non-functional requirements

- Separate demo and production configuration; production launch is a separate gate requiring audits and school approval.
- Queue worker supervised (Supervisor/systemd) with restart; separate `proofs` queue with concurrency 1.
- Backups of MySQL daily with tested restore; migrations have rollback.
- Health endpoint `/health` (DB, Redis, queue lag, RPC reachability) for the demo operator; not public detail.
- A chain outage must not erase approved local records; proof stays pending/unavailable.
- UTC timestamps, mobile-responsive screens, accessible status messages.

### 12.1 Demo runbook (summary)

1. T-24h: deploy, migrate, seed; confirm signer account funded; confirm contract address.
2. T-1h: dry run complete flow; confirm preconfirmed fixture present; test fallback RPC by switching env.
3. During demo: use fixture if the live transaction is slow; narrate Pending honestly.
4. Tamper step: use a prepared SQL snippet or admin-only dev tool against the demo database; reset script afterwards.

## 13. Technical quality gates

| ID | Area | Test | Type |
|---|---|---|---|
| T-01 | Authz | Student cannot read another student's hours (UI and API) | Feature |
| T-02 | Authz | Coordinator out-of-scope event/roster/approval denied | Feature |
| T-03 | Registration | Unique constraint; concurrent double submit yields one row | Feature + concurrency |
| T-04 | QR | Expired payload rejected using server clock | Unit/Feature |
| T-05 | QR | Replay and tampered signature rejected | Unit |
| T-06 | Attendance | Check-in/out transition table incl. out-before-in | Unit |
| T-07 | Attendance | Duplicate scan returns `already_checked_in`, no second row | Feature |
| T-08 | Approval | Approve, reject, request revision; reason required | Feature |
| T-09 | Approval | Self-approval denied at service and policy layers | Feature |
| T-10 | Versioning | Correction creates version N+1 and keeps N | Feature |
| T-11 | Canonical | Serialization golden-file stability | Unit |
| T-12 | Proof | Status transitions queued -> submitted -> confirmed/failed | Unit |
| T-13 | Proof | Submitted tx never reported Confirmed; wrong chain/contract rejected | Unit |
| T-14 | Verify | Tampered DB value returns Mismatch | Feature + testnet |
| T-15 | Verify | RPC down returns Unavailable, never Verified | Feature |
| T-16 | Certificates | Issue eligibility, supersede, revoke; revoked never valid | Feature |
| T-17 | Privacy | Public response contains no student number/email/attendance | Feature |
| T-18 | Reports | Dashboard totals exclude pending/rejected; scoped by role | Feature |
| T-19 | Contract | ProofRegistry: duplicate, role, revoke, events | Hardhat/Foundry |
| T-20 | DB | Migrate, rollback, migrate; seeders blocked in production | CI |
| T-21 | E2E | Full journey with synthetic data on testnet | Playwright |
| T-22 | A11y | Axe checks on S01–S17; keyboard path for core flows | Playwright + axe |

Definition of done for a P0 feature: code merged, policy and unit tests green, migration reversible, copy reviewed for honesty statements, accessibility check passed, documented in the demo script.

## 14. Two-day implementation plan

| Block | Work | Exit check |
|---|---|---|
| Day 1 AM | Project skeleton, auth, roles, scopes, seeds; shadcn layout | Login works; scope tests T-01/T-02 |
| Day 1 midday | Events and registration | T-03 green |
| Day 1 PM | QR challenge, scanning, live roster, check-in/out | T-04 to T-07 green |
| Day 1 late | Approval, versioning, audit | T-08 to T-10 green |
| Day 2 AM | Contract deploy, canonicalization, job pipeline, reconciliation | T-11 to T-13, T-19 green |
| Day 2 midday (feature freeze) | Certificates, public verification, tamper demo | T-14 to T-17 green |
| Day 2 PM | Dashboard, polish, regression, rehearsal | T-18, T-20 to T-22 green |

P0 reliability outranks extra features. Anything not in this plan is deferred.

## 15. Risks and fallbacks

| Risk | Fallback |
|---|---|
| Testnet congestion | Preconfirmed fixture; narrate Pending honestly; retry job continues |
| RPC outage | Fallback provider; Unavailable state |
| Signer account out of funds | Backup funded account; runbook step |
| Camera scanning fails on a device | Manual entry with reason; second device; coordinator override |
| MySQL CHECK not enforced (old version) | Service-layer validation plus tests; require 8.0.16+ |
