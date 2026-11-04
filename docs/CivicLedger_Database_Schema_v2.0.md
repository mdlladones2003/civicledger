# CivicLedger: Database Schema v2.0

DECENTRALTECH / CIVICLEDGER / CRYPTCAMP

Column-level logical MySQL model • keys • constraints • transitions • seeds • tests

**Status:** Proposed • Hackathon MVP • Synthetic data only • Detailed edition • 25 October 2026

> **Scope baseline:** Six functional MVP modules (F1–F6). Former F5 community needs, F6 donations, F7 projects and F10 notifications are not part of the MVP. Legacy F8 certificates → F5; legacy F9 reporting → F6. This v2.0 expands v1.0; it adds detail but no new scope.

Logical specification for Laravel migrations. Not executable SQL.

## 1. Purpose, scope and what changed from v1.0

This document is the column-level logical specification of the CivicLedger MySQL data model. It is the input for Laravel migrations, seeders, model factories and policy tests. It expands Database Schema v1.0 with data types, nullability, defaults, enumerations, indexes, check constraints, state-transition rules, sample rows and a migration order.

**Scope guard.** Only tables required by modules F1–F6 are defined. No tables exist for community needs, donations, projects, milestones, notifications, invitations or bulk imports. A basic dashboard is computed from authorized queries, so there is no `report_exports` table.

### 1.1 Additions made in v2.0 (clarifications, not new scope)

v1.0 listed core columns only. Reading it against the PRD and App Flow revealed fields that the product requirements already need. They are added here and flagged so the team can reject them if undesired.

| Addition | Why it is needed | Source requirement |
|---|---|---|
| users.student_number (nullable, unique) | Students need a school identifier for rosters; it is also the field the public page must hide. | PRD BR-11, AC-06 (public page hides student ID) |
| events.venue, events.component, events.description | Coordinators create drafts with title, time, place and component. | PRD F2.1 |
| events.capacity (nullable) | Optional registration cap; null means unlimited. | PRD F2.3 (eligibility) |
| attendance.reviewed_by / reviewed_at / review_note | Approve, reject or request revision must be stored per attendance. | PRD F3.4 |
| service_records.review_note | Rejected hours must show a reason without inventing credit. | App Flow §2 |
| blockchain_proofs: record_type, record_version, attempts, last_error, submitted_at, block_number, confirmations | Queue retry, receipt reconciliation and proof detail screen. | TRD §7; App Flow S12 |
| qr_challenges.consumed_count | Detect replays within the expiry window. | TRD §6.2, §6.3 |

## 2. Conventions

- **Engine and charset:** MySQL 8+, InnoDB, `utf8mb4` with `utf8mb4_0900_ai_ci` collation.
- **Primary keys:** `BIGINT UNSIGNED AUTO_INCREMENT` named `id`. Foreign key columns use `<singular>_id`.
- **Public identifiers:** opaque random tokens (at least 128 bits, base62 or URL-safe base64, 22–32 chars). Never expose internal ids in public URLs.
- **Timestamps:** stored in UTC as `TIMESTAMP` (or `DATETIME(3)` where noted). The UI converts to Asia/Manila (UTC+08:00) for display.
- **Enums:** implemented as `VARCHAR(24)` plus CHECK constraint (or Laravel string-backed enum) rather than native `ENUM`, so rollbacks and additions are easy.
- **JSON columns:** `JSON` type; always canonicalized by the application before persistence when used as a hash input.
- **Foreign keys:** enforced everywhere except polymorphic `audit_logs.entity_type/entity_id`. Default `ON DELETE RESTRICT`; no cascading deletes on approved or audited data.
- **Soft deletes:** not used on approved, audited or proof-bearing tables. `users` uses `status` instead of deletion. `DELETE /account` anonymizes the row and suspends it, so history keeps its foreign keys.
- **Money / floats:** none. Time is stored as integer minutes (`approved_minutes`) to avoid float drift.

## 3. Entity relationship overview

| Parent | Child | Cardinality | Rule |
|---|---|---|---|
| users | user_roles | 1 : N | A user can hold several roles; unique (user, role). |
| programs, terms | sections | 1 : N each | A section is one program in one term; unique (program, term, code). |
| users + sections | user_section_assignments | N : M | Scope rows decide which sections a coordinator or student belongs to. |
| events | event_sessions | 1 : N | An event has at least one session. |
| events + users | registrations | N : M | A student registers once per event. |
| event_sessions | qr_challenges | 1 : N | Short-lived rotating challenges. |
| event_sessions + users | attendance | N : M | At most one row per student per session. |
| attendance | service_records | 1 : 0..1 | Each attendance has at most one service record. |
| service_records | service_record_versions | 1 : N | Append-only version history; one is current. |
| service_record_versions OR certificates | blockchain_proofs | 1 : 0..1 | Each proof references exactly one subject (CHECK). |
| certificates | certificate_events | 1 : N | Issue, revoke and supersede history. |
| certificates | certificates | 1 : 0..1 | supersedes_certificate_id self reference. |

## 4. Table specifications

### 4.0 Table list by migration group

| Group | Tables |
|---|---|
| G1 Identity & scopes | users, roles, user_roles, permissions, role_permissions, terms, programs, sections, user_section_assignments |
| G2 Events | events, event_sessions, registrations |
| G3 Attendance | qr_challenges, attendance, attendance_adjustments |
| G4 Hours | service_records, service_record_versions |
| G5 Certificates | certificates, certificate_events |
| G6 Proof | blockchain_proofs |
| G7 Audit | audit_logs |
| Framework | sessions, jobs, failed_jobs, cache, password_reset_tokens (Laravel defaults, not business tables) |

### G1 Identity and scopes

### 4.1 users

Every human account: students, coordinators, signers, administrators.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | Internal key; never exposed in URLs |
| name | VARCHAR(120) | NO | | Display name |
| email | VARCHAR(190) | NO | UNIQUE | Lower-cased on write |
| student_number | VARCHAR(32) | YES | UNIQUE | Students only; never public (v2.0 addition) |
| password | VARCHAR(255) | NO | | Bcrypt/Argon2 hash |
| status | VARCHAR(16) | NO | DEFAULT 'active'; CHECK IN ('active','suspended','archived') | Suspended users cannot sign in |
| last_login_at | TIMESTAMP | YES | | |
| remember_token | VARCHAR(100) | YES | | Laravel |
| created_at | TIMESTAMP | NO | DEFAULT CURRENT_TIMESTAMP | UTC |
| updated_at | TIMESTAMP | NO | ON UPDATE CURRENT_TIMESTAMP | UTC |

### 4.2 roles

Fixed role catalogue.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | Internal key; never exposed in URLs |
| code | VARCHAR(32) | NO | UNIQUE | student, coordinator, signer, admin |
| label | VARCHAR(64) | NO | | Human label |
| created_at | TIMESTAMP | NO | DEFAULT CURRENT_TIMESTAMP | UTC |
| updated_at | TIMESTAMP | NO | ON UPDATE CURRENT_TIMESTAMP | UTC |

### 4.3 user_roles

Role grants per user.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | Internal key; never exposed in URLs |
| user_id | BIGINT UNSIGNED | NO | FK users.id | |
| role_id | BIGINT UNSIGNED | NO | FK roles.id | |
| granted_by | BIGINT UNSIGNED | YES | FK users.id | Admin who granted; null for seed |
| created_at | TIMESTAMP | NO | | |

Unique index: `(user_id, role_id)`. Every grant or revoke writes an `audit_logs` row (PRD F1.5).

### 4.4 permissions and 4.5 role_permissions

Fine-grained permission codes attached to roles.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| permissions.id | BIGINT UNSIGNED | NO | PK | |
| permissions.code | VARCHAR(64) | NO | UNIQUE | e.g. certificates.issue, certificates.revoke, hours.review, attendance.manual |
| permissions.label | VARCHAR(120) | NO | | |
| role_permissions.role_id | BIGINT UNSIGNED | NO | FK roles.id | |
| role_permissions.permission_id | BIGINT UNSIGNED | NO | FK permissions.id | UNIQUE (role_id, permission_id) |

### 4.6 terms

Academic terms.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | Internal key; never exposed in URLs |
| code | VARCHAR(16) | NO | UNIQUE | e.g. 2026-1 |
| starts_at | DATE | NO | | |
| ends_at | DATE | NO | CHECK ends_at >= starts_at | |
| created_at | TIMESTAMP | NO | DEFAULT CURRENT_TIMESTAMP | UTC |
| updated_at | TIMESTAMP | NO | ON UPDATE CURRENT_TIMESTAMP | UTC |

### 4.7 programs

NSTP components or degree programs, per school definition.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | Internal key; never exposed in URLs |
| code | VARCHAR(24) | NO | UNIQUE | |
| name | VARCHAR(120) | NO | | |
| created_at | TIMESTAMP | NO | DEFAULT CURRENT_TIMESTAMP | UTC |
| updated_at | TIMESTAMP | NO | ON UPDATE CURRENT_TIMESTAMP | UTC |

### 4.8 sections

A program offered in a term.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | Internal key; never exposed in URLs |
| program_id | BIGINT UNSIGNED | NO | FK programs.id | |
| term_id | BIGINT UNSIGNED | NO | FK terms.id | |
| code | VARCHAR(24) | NO | UNIQUE (program_id, term_id, code) | |
| created_at | TIMESTAMP | NO | DEFAULT CURRENT_TIMESTAMP | UTC |
| updated_at | TIMESTAMP | NO | ON UPDATE CURRENT_TIMESTAMP | UTC |

### 4.9 user_section_assignments

Scope table. Drives every scoped query for coordinators and eligibility for students.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | Internal key; never exposed in URLs |
| user_id | BIGINT UNSIGNED | NO | FK users.id | |
| section_id | BIGINT UNSIGNED | NO | FK sections.id | |
| scope_role | VARCHAR(16) | NO | CHECK IN ('student','coordinator','adviser') | Role within this section |
| assigned_by | BIGINT UNSIGNED | YES | FK users.id | |
| created_at | TIMESTAMP | NO | | |

Unique: `(user_id, section_id, scope_role)`. Index: `(section_id, scope_role)` for roster lookups.

### G2 Events

### 4.10 events

Service activity owned by a coordinator for a program and optionally one section.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | Internal key; never exposed in URLs |
| public_token | CHAR(26) | NO | UNIQUE | Opaque id for URLs |
| coordinator_id | BIGINT UNSIGNED | NO | FK users.id | Creator and owner |
| program_id | BIGINT UNSIGNED | NO | FK programs.id | |
| section_id | BIGINT UNSIGNED | YES | FK sections.id | Null means whole program |
| title | VARCHAR(160) | NO | | |
| description | TEXT | YES | | v2.0 addition |
| venue | VARCHAR(190) | NO | | v2.0 addition (PRD F2.1 "place") |
| component | VARCHAR(16) | NO | CHECK IN ('ROTC','LTS','CWTS') | v2.0 addition; confirm with school |
| starts_at | DATETIME | NO | | UTC |
| ends_at | DATETIME | NO | CHECK ends_at > starts_at | UTC |
| capacity | SMALLINT UNSIGNED | YES | | Null = unlimited |
| status | VARCHAR(16) | NO | DEFAULT 'draft'; CHECK IN ('draft','published','cancelled','completed') | See transitions §6 |
| published_at | TIMESTAMP | YES | | |
| created_at | TIMESTAMP | NO | DEFAULT CURRENT_TIMESTAMP | UTC |
| updated_at | TIMESTAMP | NO | ON UPDATE CURRENT_TIMESTAMP | UTC |

Indexes: `(section_id, status, starts_at)`, `(coordinator_id, status)`, `(program_id, starts_at)`.

### 4.11 event_sessions

Time slots within an event. Attendance and QR attach to sessions.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | Internal key; never exposed in URLs |
| event_id | BIGINT UNSIGNED | NO | FK events.id | |
| public_token | CHAR(26) | NO | UNIQUE | Opaque session reference embedded in QR payload |
| starts_at | DATETIME | NO | | |
| ends_at | DATETIME | NO | CHECK ends_at > starts_at | |
| status | VARCHAR(16) | NO | DEFAULT 'scheduled'; CHECK IN ('scheduled','open','closed','cancelled') | QR only issued while open |
| opened_at | TIMESTAMP | YES | | |
| closed_at | TIMESTAMP | YES | | |
| created_at | TIMESTAMP | NO | DEFAULT CURRENT_TIMESTAMP | UTC |
| updated_at | TIMESTAMP | NO | ON UPDATE CURRENT_TIMESTAMP | UTC |

Index: `(event_id, status)`.

### 4.12 registrations

A student's registration for an event.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | Internal key; never exposed in URLs |
| event_id | BIGINT UNSIGNED | NO | FK events.id | |
| student_id | BIGINT UNSIGNED | NO | FK users.id | |
| status | VARCHAR(16) | NO | DEFAULT 'registered'; CHECK IN ('registered','withdrawn','cancelled') | |
| registered_at | TIMESTAMP | NO | DEFAULT CURRENT_TIMESTAMP | |
| created_at | TIMESTAMP | NO | DEFAULT CURRENT_TIMESTAMP | UTC |
| updated_at | TIMESTAMP | NO | ON UPDATE CURRENT_TIMESTAMP | UTC |

Unique: `(event_id, student_id)`. Index: `(student_id, event_id)`. Registration insert runs in a transaction that also checks section eligibility and capacity.

### G3 Attendance

### 4.13 qr_challenges

Server-issued rotating challenges. Only the hash of the nonce is stored.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | Internal key; never exposed in URLs |
| session_id | BIGINT UNSIGNED | NO | FK event_sessions.id | |
| nonce_hash | CHAR(64) | NO | UNIQUE | SHA-256 of nonce; raw nonce lives only in the signed QR |
| purpose | VARCHAR(12) | NO | CHECK IN ('check_in','check_out') | Challenge type |
| expires_at | DATETIME(3) | NO | | created_at + 30 s (configurable) |
| consumed_count | SMALLINT UNSIGNED | NO | DEFAULT 0 | Replay detection (v2.0) |
| created_at | DATETIME(3) | NO | | |

Index: `(session_id, expires_at)`. A scheduled job purges rows expired more than 24 hours ago (they carry no PII).

### 4.14 attendance

One row per student per session. Holds check-in/out and coordinator review.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | Internal key; never exposed in URLs |
| session_id | BIGINT UNSIGNED | NO | FK event_sessions.id | |
| student_id | BIGINT UNSIGNED | NO | FK users.id | |
| check_in_at | DATETIME(3) | YES | | Server clock |
| check_out_at | DATETIME(3) | YES | CHECK check_out_at IS NULL OR check_in_at IS NOT NULL AND check_out_at >= check_in_at | |
| source | VARCHAR(12) | NO | CHECK IN ('qr','manual') | Manual requires an adjustment row with reason |
| status | VARCHAR(20) | NO | DEFAULT 'checked_in'; CHECK IN ('checked_in','checked_out','submitted','approved','rejected','revision_requested') | See transitions §6 |
| reviewed_by | BIGINT UNSIGNED | YES | FK users.id | v2.0 addition |
| reviewed_at | TIMESTAMP | YES | | |
| review_note | VARCHAR(500) | YES | | Required for reject / revision |
| created_at | TIMESTAMP | NO | DEFAULT CURRENT_TIMESTAMP | UTC |
| updated_at | TIMESTAMP | NO | ON UPDATE CURRENT_TIMESTAMP | UTC |

Unique: `(session_id, student_id)`. Index: `(session_id, student_id)`, `(status, session_id)` for the approval queue.

### 4.15 attendance_adjustments

Immutable record of manual entries and corrections.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | Internal key; never exposed in URLs |
| attendance_id | BIGINT UNSIGNED | NO | FK attendance.id | |
| actor_id | BIGINT UNSIGNED | NO | FK users.id | Staff member |
| reason | VARCHAR(500) | NO | CHECK CHAR_LENGTH(reason) >= 10 | Required (PRD F3.3) |
| old_json | JSON | YES | | Null for manual create |
| new_json | JSON | NO | | |
| created_at | TIMESTAMP | NO | | |

No UPDATE or DELETE is permitted by application policy; revoke these grants from the app DB user where practical.

### G4 Hours and versioning

### 4.16 service_records

The approval outcome for one attendance. Only approved records count toward official totals.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | Internal key; never exposed in URLs |
| attendance_id | BIGINT UNSIGNED | NO | FK attendance.id; UNIQUE | One service record per attendance |
| student_id | BIGINT UNSIGNED | NO | FK users.id | Denormalised for scoped reporting |
| approved_minutes | INT UNSIGNED | NO | CHECK approved_minutes >= 0 | Server-derived; zero unless approved |
| status | VARCHAR(16) | NO | DEFAULT 'pending'; CHECK IN ('pending','approved','rejected') | Approval state |
| approved_by | BIGINT UNSIGNED | YES | FK users.id | Must differ from student_id (service layer) |
| approved_at | TIMESTAMP | YES | | |
| review_note | VARCHAR(500) | YES | | Reason shown to the student for a rejection or revision request |
| current_version | INT UNSIGNED | NO | DEFAULT 1 | Points at service_record_versions.version |
| created_at | TIMESTAMP | NO | DEFAULT CURRENT_TIMESTAMP | UTC |
| updated_at | TIMESTAMP | NO | ON UPDATE CURRENT_TIMESTAMP | UTC |

Indexes: `(student_id, status)`, `(status, approved_at)`. CHECK: `status <> 'approved' OR (approved_by IS NOT NULL AND approved_at IS NOT NULL)`.

### 4.17 service_record_versions

Append-only history of the canonical payload. Corrections add a version; they never overwrite.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | Internal key; never exposed in URLs |
| service_record_id | BIGINT UNSIGNED | NO | FK service_records.id | |
| version | INT UNSIGNED | NO | UNIQUE (service_record_id, version) | Starts at 1 |
| canonical_payload_json | JSON | NO | | Stable key order, UTC ISO-8601, integer minutes, no PII |
| payload_digest | CHAR(64) | NO | | SHA-256 of canonical bytes, for fast local integrity checks |
| reason | VARCHAR(500) | NO | | Why this version exists |
| created_by | BIGINT UNSIGNED | NO | FK users.id | |
| created_at | TIMESTAMP | NO | | |

### 4.18 record_secrets (removed)

Not used. Commitments are keyed with a per-record key derived from `CIVICLEDGER_PROOF_SECRET`, which lives outside the database, so no per-record nonce is stored (TRD §7.2).

### G5 Certificates

### 4.19 certificates

Supplementary digital certificate issued by an authorized signer.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | Internal key; never exposed in URLs |
| student_id | BIGINT UNSIGNED | NO | FK users.id | |
| term_id | BIGINT UNSIGNED | NO | FK terms.id | |
| issued_by | BIGINT UNSIGNED | NO | FK users.id | Signer |
| public_token | CHAR(32) | NO | UNIQUE | Unguessable, used in /verify/{token} |
| certificate_number | VARCHAR(32) | NO | UNIQUE | Human-readable, e.g. CL-2026-000123 |
| approved_minutes | INT UNSIGNED | NO | CHECK approved_minutes >= 0 | Snapshot at issuance |
| status | VARCHAR(16) | NO | DEFAULT 'issued'; CHECK IN ('issued','revoked','superseded') | Validity state |
| issued_at | TIMESTAMP | NO | | |
| revoked_at | TIMESTAMP | YES | | |
| supersedes_certificate_id | BIGINT UNSIGNED | YES | FK certificates.id | Older certificate this one replaces |
| created_at | TIMESTAMP | NO | DEFAULT CURRENT_TIMESTAMP | UTC |
| updated_at | TIMESTAMP | NO | ON UPDATE CURRENT_TIMESTAMP | UTC |

Indexes: `(public_token, status)`, `(student_id, term_id, status)`. Partial rule enforced in service: at most one `issued` certificate per `(student_id, term_id)`.

### 4.20 certificate_events

Append-only certificate history.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | Internal key; never exposed in URLs |
| certificate_id | BIGINT UNSIGNED | NO | FK certificates.id | |
| actor_id | BIGINT UNSIGNED | NO | FK users.id | |
| event_type | VARCHAR(16) | NO | CHECK IN ('issued','revoked','superseded','proof_confirmed') | |
| reason | VARCHAR(500) | YES | | Required for revoked and superseded |
| created_at | TIMESTAMP | NO | | |

### G6 Blockchain proof

### 4.21 blockchain_proofs

One row per attempt-chain for a subject (service record version or certificate). Holds no PII.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | Internal key; never exposed in URLs |
| service_record_version_id | BIGINT UNSIGNED | YES | FK service_record_versions.id | Exactly one subject set |
| certificate_id | BIGINT UNSIGNED | YES | FK certificates.id | Exactly one subject set |
| record_type | VARCHAR(16) | NO | CHECK IN ('service_record','certificate') | Mirrors the chosen FK |
| record_version | INT UNSIGNED | NO | DEFAULT 1 | On-chain version number |
| opaque_record_key | CHAR(66) | NO | UNIQUE | 0x + 32 random bytes; not guessable |
| commitment | CHAR(66) | NO | | 0x + 32 bytes digest |
| chain_id | BIGINT UNSIGNED | NO | | Sepolia proposed; pinned at kickoff |
| contract_address | CHAR(42) | NO | | ProofRegistry address |
| tx_hash | CHAR(66) | YES | | Null until submitted |
| block_number | BIGINT UNSIGNED | YES | | |
| confirmations | INT UNSIGNED | NO | DEFAULT 0 | |
| proof_status | VARCHAR(12) | NO | DEFAULT 'queued'; CHECK IN ('queued','submitted','confirmed','failed') | Proof lifecycle |
| validity_status | VARCHAR(12) | NO | DEFAULT 'current'; CHECK IN ('current','superseded','revoked') | Record validity |
| attempts | SMALLINT UNSIGNED | NO | DEFAULT 0 | |
| last_error | VARCHAR(500) | YES | | No sensitive payloads |
| submitted_at | TIMESTAMP | YES | | |
| confirmed_at | TIMESTAMP | YES | | |
| created_at | TIMESTAMP | NO | DEFAULT CURRENT_TIMESTAMP | UTC |
| updated_at | TIMESTAMP | NO | ON UPDATE CURRENT_TIMESTAMP | UTC |

CHECK: `(service_record_version_id IS NULL) <> (certificate_id IS NULL)`. Unique indexes on `service_record_version_id` and on `certificate_id` (MySQL allows multiple NULLs) guarantee one active proof per subject. Indexes: `(proof_status, chain_id)`, `(tx_hash)`.

### G7 Audit

### 4.22 audit_logs

Append-only audit trail for approvals, permission changes, issuance, revocation, manual entries and proof actions.

| Column | Type | Null | Default / constraint | Notes |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | Internal key; never exposed in URLs |
| actor_id | BIGINT UNSIGNED | YES | FK users.id | Null for system jobs |
| action | VARCHAR(64) | NO | | e.g. hours.approved, role.granted, certificate.revoked |
| entity_type | VARCHAR(48) | NO | | Polymorphic; no FK |
| entity_id | BIGINT UNSIGNED | NO | | Application validates target exists |
| before_json | JSON | YES | | Redacted of secrets and PII where possible |
| after_json | JSON | YES | | |
| ip_address | VARBINARY(16) | YES | | Optional |
| created_at | DATETIME(3) | NO | | |

Indexes: `(entity_type, entity_id, created_at)`, `(actor_id, created_at)`, `(action, created_at)`.

## 5. Enumerations and vocabularies

The UI vocabulary must keep three axes separate (Design Brief §4). The database mirrors that separation.

| Axis | Column | Values |
|---|---|---|
| Approval | service_records.status | pending, approved, rejected |
| Proof | blockchain_proofs.proof_status | queued, submitted, confirmed, failed |
| Validity | blockchain_proofs.validity_status / certificates.status | current, superseded, revoked (certificate column also uses issued) |
| Verification result (computed, not stored) | public /verify/{token} | Verified, Pending, Mismatch, Not Found, Unavailable (plus Revoked / Superseded display) |
| Event | events.status | draft, published, cancelled, completed |
| Session | event_sessions.status | scheduled, open, closed, cancelled |
| Attendance | attendance.status | checked_in, checked_out, submitted, approved, rejected, revision_requested |

## 6. State transitions

| Entity | From | To | Guard |
|---|---|---|---|
| events | draft | published | Coordinator owns event; required fields valid |
| events | published | completed / cancelled | Coordinator; cancelled blocks new registrations and closes open sessions |
| events | completed / cancelled | (none) | Terminal |
| event_sessions | scheduled | open | Event published; coordinator opens |
| event_sessions | open | closed | Coordinator or end time reached |
| attendance | (none) | checked_in | Valid QR; registration exists; session open |
| attendance | checked_in | checked_out | Valid check-out QR; check_out_at >= check_in_at |
| attendance | checked_out | submitted | System after session close |
| attendance | submitted | approved / rejected / revision_requested | Coordinator in scope; not the student; reason for reject or revision |
| attendance | revision_requested | submitted | After manual correction with logged reason |
| service_records | pending | approved | Writes new version, enqueues proof; approver <> student |
| service_records | approved | approved (v+1) | Correction creates a new version with reason; old version kept |
| blockchain_proofs | queued | submitted | Tx broadcast, tx_hash saved |
| blockchain_proofs | submitted | confirmed | Receipt success, right chain and contract, required confirmations reached |
| blockchain_proofs | submitted | failed | Reverted or retries exhausted; may be requeued |
| certificates | issued | revoked | Signer permission, confirmed dialog, reason |
| certificates | issued | superseded | Replacement issued; both linked |

## 7. Integrity, privacy and security rules

- **No PII on chain.** Only `opaque_record_key`, `commitment`, record type and version are submitted.
- **Commitment construction:** `commitment = HMAC-SHA256(derived_key, canonical_payload_json)`, with `derived_key = HMAC-SHA256(CIVICLEDGER_PROOF_SECRET, opaque_record_key)` (TRD §7.2).
- **Opaque key construction:** 32 random bytes, generated when the proof is queued.
- **Append-only tables:** `attendance_adjustments`, `service_record_versions`, `certificate_events`, `audit_logs`. The application DB user is denied UPDATE/DELETE on them where the host supports it.
- **Restricted deletion:** foreign keys use RESTRICT; approved, certificate and proof rows are never hard-deleted.
- **Scoped joins:** all queries that join users to attendance or hours go through policy-checked query scopes (student: own rows; coordinator: assigned sections).
- **Transactions:** registration, check-in/out, approval + version write + proof enqueue, and certificate issuance each run in a single DB transaction with row locks where noted.
- **Self-approval:** `service_records.approved_by <> student_id` enforced in the service layer and covered by tests; a DB trigger is optional hardening.
- **Audit validation:** audit writers verify the actor exists and the target row exists before insert, because no FK is possible on the polymorphic target.

## 8. Index and constraint checklist

| Table | Index / constraint | Purpose |
|---|---|---|
| events | INDEX (section_id, status, starts_at) | Eligible event list |
| registrations | UNIQUE (event_id, student_id); INDEX (student_id, event_id) | One registration; student lookup |
| attendance | UNIQUE (session_id, student_id) | Duplicate credit prevention |
| qr_challenges | UNIQUE (nonce_hash); INDEX (session_id, expires_at) | Replay and cleanup |
| service_records | UNIQUE (attendance_id); INDEX (student_id, status) | One record per attendance; totals |
| service_record_versions | UNIQUE (service_record_id, version) | Append-only versioning |
| blockchain_proofs | UNIQUE (opaque_record_key); CHECK exactly-one-subject; INDEX (proof_status, chain_id) | Subject integrity; worker polling |
| certificates | UNIQUE (public_token), UNIQUE (certificate_number); INDEX (public_token, status) | Public lookup |
| audit_logs | INDEX (entity_type, entity_id, created_at) | Entity history |

## 9. Canonical payload specification

The commitment depends on a stable serialization. The payload for a `service_record` version contains only non-identifying fields.

```text
{
  "approved_minutes": 180,
  "event_ref": "evt_01J8K2...",      // opaque event token
  "record_type": "service_record",
  "session_ref": "ses_01J8K3...",    // opaque session token
  "subject_ref": "sub_9fA1...",     // HMAC of student id, not the id
  "version": 1
}
```

- Keys sorted lexicographically; no whitespace; UTF-8; integers only for minutes; timestamps (if any) as UTC `YYYY-MM-DDTHH:MM:SSZ`.
- Unit tests assert byte-for-byte stability across PHP versions and re-serialization.
- Any change to an approved value changes the digest and therefore yields **Mismatch** at verification.

## 10. Synthetic seed dataset

Seeders are for demo and test environments only and must not run in production (guard on `APP_ENV`). Synthetic names and domains only (`example.test`).

| Entity | Seed rows | Notes |
|---|---|---|
| roles | 4 | student, coordinator, signer, admin |
| users | 1 admin, 2 coordinators, 1 signer, 6 students | Passwords from env/demo sheet |
| terms / programs / sections | 1 / 3 / 4 | Coordinator A scoped to section 1; Coordinator B to section 2 (scope tests) |
| events | 3 (draft, published, completed) | Published event has two sessions |
| registrations | 3 students on the published event | Matches demo walkthrough |
| attendance + service_records | 3 with versions: Ana approved, Ben pending, Carla rejected | Matches Design Brief §11; includes one revision example |
| blockchain_proofs | 1 preconfirmed fixture | Fallback if testnet RPC is down |
| certificates | 1 issued, 1 revoked | Supports verification and revocation tests |

#### Sample rows

| Table | Row (abbreviated) |
|---|---|
| users | id=11, name='Ana Reyes (demo)', email='ana@example.test', student_number='DEMO-0001', status='active' |
| registrations | id=1, event_id=2, student_id=11, status='registered' |
| attendance | id=1, session_id=3, student_id=11, check_in_at='2026-10-24 01:02:11.120', check_out_at='2026-10-24 04:05:40.300', source='qr', status='approved' |
| service_records | id=1, attendance_id=1, approved_minutes=183, status='approved', approved_by=2, current_version=1 |
| blockchain_proofs | id=1, record_type='service_record', opaque_record_key='0x7c1e…', commitment='0xa94f…', chain_id=11155111, proof_status='confirmed', validity_status='current' |
| certificates | id=1, certificate_number='CL-2026-000001', status='issued', approved_minutes=183 |

## 11. Laravel migration and rollback plan

1. G1 identity and scopes: users, roles, permissions, role_permissions, user_roles, terms, programs, sections, user_section_assignments.
2. G2 events: events, event_sessions, registrations.
3. G3 attendance: qr_challenges, attendance, attendance_adjustments.
4. G4 hours: service_records, service_record_versions.
5. G5 certificates: certificates, certificate_events.
6. G6 blockchain_proofs with the exactly-one-subject CHECK.
7. G7 audit_logs.
8. Seeders in dependency order; environment guard throws if `APP_ENV=production`.

- Each migration implements `down()`; CI runs `migrate:fresh --seed`, then `migrate:rollback` to zero, then `migrate` again.
- CHECK constraints require MySQL 8.0.16+; CI uses the same minor version as the demo host.
- Self-referencing FK (`certificates.supersedes_certificate_id`) is added after the table is created.

## 12. Schema acceptance tests

| ID | Test | Expected |
|---|---|---|
| DB-01 | Insert two registrations for same (event, student) | Unique violation |
| DB-02 | Insert two attendance rows for same (session, student) | Unique violation |
| DB-03 | Insert attendance with check_out_at earlier than check_in_at | CHECK violation |
| DB-04 | Insert service record with negative approved_minutes | CHECK violation |
| DB-05 | Insert proof with both subject FKs null, or both set | CHECK violation |
| DB-06 | Insert approved service record without approved_by | CHECK violation |
| DB-07 | Delete a user referenced by attendance | FK RESTRICT error |
| DB-08 | Add service_record_versions with duplicate (record, version) | Unique violation |
| DB-09 | Run full migrate, rollback, migrate on empty DB | Succeeds with no errors |
| DB-10 | Seeder run with APP_ENV=production | Aborts with error |
| DB-11 | Insert duplicate certificate public_token | Unique violation |
| DB-12 | Student-scoped query for another student's attendance (policy test) | Zero rows / 403 |

## 13. Excluded data structures

Do not implement `community_partners`, `community_requests`, `donation_*`, `projects`, `project_milestones`, `notifications`, `invitations` or `bulk_imports` in the MVP. These are separate future product decisions that require new scope approval.
