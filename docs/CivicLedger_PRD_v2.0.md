# CivicLedger: Product Requirements Document v2.0

DECENTRALTECH / CIVICLEDGER / CRYPTCAMP

WHAT to build • goals • personas • rules • acceptance criteria • traceability

**Status:** Proposed • Hackathon MVP • Synthetic data only • Detailed edition • 25 October 2026

> **Scope baseline:** Six functional MVP modules (F1–F6). Former F5 community needs, F6 donations, F7 projects and F10 notifications are not part of the MVP. Legacy F8 certificates → F5; legacy F9 reporting → F6. This v2.0 expands v1.0; it adds detail but no new scope.

Detailed edition of PRD v1.0; same scope, more testable.

## 1. Product vision and positioning

CivicLedger is a blockchain-powered web system for NSTP service activities. It supports event registration, supervised attendance, approval of service hours, integrity checking of approved records, supplementary digital certificates and accomplishment reporting.

> **Core honesty principle.** Blockchain evidence detects post-approval alteration of a record. It does not prove that a student physically participated, and a CivicLedger certificate is supplementary unless the school formally adopts it. All copy, demos and public pages must say so.

### 1.1 Product goals

| # | Goal | How the MVP shows it |
|---|---|---|
| G1 | Accurate student service histories | One attendance row per session; server-derived minutes; versioned corrections |
| G2 | Faster staff review | Live roster, exception highlighting, one-screen approval detail |
| G3 | Restricted access | Role + section scoped queries and policies on every read and write |
| G4 | Independent integrity checks | Public verification page recomputes commitment and compares with chain |
| G5 | No duplicate credit | Unique constraints, rotating QR, replay rejection |
| G6 | Privacy by design | No PII on chain; public page minimal |

### 1.2 Non-goals

- Proving real-world participation or replacing the school's official NSTP records.
- Cryptocurrency, tokens, wallets or payments. Students never need a wallet.
- Community needs, donations, project monitoring, notifications, bulk onboarding, SIS integration, native apps, offline scanning, production deployment.

## 2. Problem statement and outcomes

Attendance sheets, spreadsheets and manual certificate checks are fragmented. They cause delayed hour tallies, duplicate-credit risk and certificates that are difficult to verify independently.

| Problem today | Effect | MVP response |
|---|---|---|
| Paper or spreadsheet attendance | Duplicates, transcription errors, slow tallying | Rotating-QR check-in/out with unique constraint |
| Manual hour computation | Inconsistent rounding and disputes | Server-derived minutes with documented rule (pending school confirmation) |
| Certificates verified by phone/email | Slow; easy to forge | QR link to public verification with proof state |
| Records editable without trace | Disputes cannot be resolved | Versioning, audit log, on-chain commitment |

## 3. Users, personas and needs

| Role | Persona (synthetic) | Needs | Key permissions | Key limits |
|---|---|---|---|---|
| Student | Ana, 1st-year NSTP student, mobile phone | Find events, register once, scan quickly, see own approved hours and certificates | Read own data; register; scan | Cannot see others' data; cannot approve |
| Coordinator | Mr. Cruz, NSTP coordinator for sections A and B | Publish events, view roster, confirm participation, approve hours | `hours.review`, `attendance.manual` in assigned sections | No access outside assigned section; cannot approve own claim |
| Certificate signer | Dr. Lim, authorized by school | Issue and revoke certificates on eligible records | `certificates.issue`, `certificates.revoke` | Only with explicit permission; revocation needs a reason |
| Administrator | IT staff | Manage accounts, roles, assignments, view audit | Manage users/assignments | Cannot approve hours unless also scoped coordinator; actions audited |
| External verifier | Employer or school office | Check a certificate quickly | Public read of limited facts | Sees no student number, email or attendance detail |
| Faculty adviser | Optional scoped coordinator | Same as coordinator in assigned section | After school approval | Treated as coordinator via `scope_role` |

## 4. Scope: six MVP modules

| ID | Module | Minimum demo outcome | Depends on |
|---|---|---|---|
| F1 | Accounts & role permissions | Seeded student/coordinator/admin; scope and self-approval checks | — |
| F2 | Events & registration | Create/publish event; eligible student registers once | F1 |
| F3 | QR attendance & hour review | Rotating QR check-in/out; manual fallback; approve hours | F1, F2 |
| F4 | Blockchain-backed record integrity | Anchor approved record commitment on EVM testnet; detect tamper | F3 |
| F5 | Certificates & public verification | Authorized issuance, QR link, validity and revocation | F3, F4 |
| F6 | Dashboard & reports | Role-scoped totals and basic activity summary | F1–F5 |

## 5. Business rules and definitions

| ID | Rule | Status |
|---|---|---|
| BR-01 | Only approved service records count toward official totals; pending and rejected hours never count. | Fixed |
| BR-02 | Approved minutes are derived by the server from check-in and check-out times, never typed by the client. | Fixed |
| BR-03 | Default rule for MVP: minutes = floor((check_out − check_in) / 1 minute), capped at session length. Break and rounding rules are configurable and pending school confirmation. | Proposed |
| BR-04 | A user can never approve their own submitted claim. | Fixed |
| BR-05 | A student has at most one attendance row per session and one registration per event. | Fixed |
| BR-06 | A correction to an approved record creates a new version with a reason; the earlier version remains. | Fixed |
| BR-07 | Certificate issuance requires eligibility (e.g. minimum approved minutes for the term; threshold set by school) and signer permission. | Threshold pending |
| BR-08 | At most one currently issued certificate per student per term; reissue supersedes the old one. | Proposed |
| BR-09 | A revoked certificate never appears valid, even if its on-chain proof is confirmed. | Fixed |
| BR-10 | Chain unavailability never deletes or blocks local approved records; proof stays Pending/Unavailable. | Fixed |
| BR-11 | Public pages never show student number, email or attendance detail. | Fixed |

### 5.1 Glossary

| Term | Meaning |
|---|---|
| Approval state | Pending / Approved / Rejected for hours. |
| Proof state | Queued / Submitted / Confirmed / Failed for the blockchain anchor. |
| Validity state | Current / Superseded / Revoked for a record or certificate. |
| Verification result | Computed outcome shown to a verifier: Verified, Pending, Mismatch, Not Found, Unavailable (plus Revoked / Superseded). |
| Commitment | Salted or keyed digest of a canonical record payload; the only record-derived value placed on chain. |
| Record key | Opaque, non-guessable identifier used on chain in place of any personal identifier. |
| Manual fallback | Staff-entered attendance with a logged reason when QR scanning is impossible. |

## 6. Detailed functional requirements

Each requirement has an ID, a priority (P0 = required for the hackathon demo), a behavior statement and testable acceptance criteria in Given / When / Then form.

### 6.1 F1 Accounts and permissions

#### F1.1 Student data isolation [P0]

A student sees only their own attendance, approved hours and certificates.

| AC | Given / When / Then |
|---|---|
| F1.1-A1 | Given student A is signed in, when A opens My Hours, then only A's rows are listed. |
| F1.1-A2 | Given student A, when A requests another student's record by URL or API, then the system returns 403/404 and logs the attempt. |

**Edge cases:**

- Suspended student signs in: blocked with a clear message.
- A student who is also assigned as a section coordinator sees staff screens only for the assigned section.

#### F1.2 Coordinator scope [P0]

Coordinator actions are limited to assigned program/section.

| AC | Given / When / Then |
|---|---|
| F1.2-A1 | Given coordinator A assigned to section 1, when A lists events, then only events for section 1 (or A's own) appear. |
| F1.2-A2 | Given coordinator A, when A tries to approve hours for a section-2 student, then the system denies with a permission-denied state. |
| F1.2-A3 | Given an assignment is removed, when the coordinator reloads, then access is lost immediately. |

#### F1.3 Administration of accounts and assignments [P0]

Administrator can create/suspend accounts, grant roles and assign sections.

| AC | Given / When / Then |
|---|---|
| F1.3-A1 | Given an admin, when a role or assignment changes, then an audit row records actor, before and after. |
| F1.3-A2 | Given a non-admin, when calling admin routes, then access is denied. |

**Edge cases:**

- Removing the last admin is blocked.

#### F1.4 Signer permission for certificates [P0]

Issuing and revoking certificates require the explicit signer permission.

| AC | Given / When / Then |
|---|---|
| F1.4-A1 | Given a coordinator without `certificates.issue`, when attempting to issue, then access is denied. |
| F1.4-A2 | Given a signer, when issuing for a student outside the signer's authorized scope, then the action is denied. |

#### F1.5 No self-approval and auditable permission changes [P0]

No user may approve their own claim; all permission changes are logged.

| AC | Given / When / Then |
|---|---|
| F1.5-A1 | Given a coordinator who is also a registered student on an event, when they open their own attendance for approval, then Approve is disabled and the server rejects the request. |
| F1.5-A2 | Given any role change, then an audit log row exists and is visible to admins. |

### 6.2 F2 Events and registration

#### F2.1 Event drafts [P0]

A coordinator creates a draft with title, description, venue, component, start/end time and eligible section.

| AC | Given / When / Then |
|---|---|
| F2.1-A1 | Given valid input, when saved, then the event is created as draft and not visible to students. |
| F2.1-A2 | Given end before start, when saving, then a field-level error explains the problem. |
| F2.1-A3 | Given a section outside the coordinator's assignment, when saving, then the section choice is rejected. |

**Edge cases:**

- Times are entered in Asia/Manila and stored in UTC.
- Editing a published event's time warns that registered students will see the change.

#### F2.2 Lifecycle with visible status [P0]

Events move draft → published → completed, or published → cancelled, with a visible status badge.

| AC | Given / When / Then |
|---|---|
| F2.2-A1 | Given a draft event, when published, then eligible students see it in the event list. |
| F2.2-A2 | Given a published event, when cancelled, then registration closes, open sessions close, and students see Cancelled. |
| F2.2-A3 | Given a completed or cancelled event, when edited, then edits are blocked. |

#### F2.3 Register once per event [P0]

Eligible students can register once per event; capacity is respected if set.

| AC | Given / When / Then |
|---|---|
| F2.3-A1 | Given an eligible unregistered student, when registering, then a registration is created and the button changes to Registered. |
| F2.3-A2 | Given a registered student, when registering again (including double-click or retry), then no duplicate is created and the existing registration is shown. |
| F2.3-A3 | Given a student not in the eligible section, when opening the event, then registration is unavailable with a reason. |
| F2.3-A4 | Given capacity is reached, when registering, then the student sees Event full. |

**Edge cases:**

- Two simultaneous requests: the unique constraint guarantees one row; the loser receives the existing registration.

#### F2.4 Authorized roster [P0]

A coordinator sees the roster of registered students for events in their scope.

| AC | Given / When / Then |
|---|---|
| F2.4-A1 | Given an event in scope, when opening the roster, then name, status and attendance state are shown, and student number only if needed for staff. |
| F2.4-A2 | Given an event out of scope, when opening the roster, then access is denied. |

### 6.3 F3 QR attendance and hour review

#### F3.1 Rotating QR per active session [P0]

The coordinator opens a session and the screen shows a QR code that rotates about every 30 seconds.

| AC | Given / When / Then |
|---|---|
| F3.1-A1 | Given an open session, when the coordinator views Live QR, then a QR is shown with a visible countdown and auto-refresh. |
| F3.1-A2 | Given a closed or scheduled session, when requesting a QR, then none is issued. |

#### F3.2 Check-in and check-out without duplicate credit [P0]

A registered student scans to check in, later scans to check out.

| AC | Given / When / Then |
|---|---|
| F3.2-A1 | Given a registered student and a valid QR, when scanning, then check-in is recorded with server time. |
| F3.2-A2 | Given the student already checked in, when scanning a check-in QR again, then no new row is created and the existing check-in time is shown. |
| F3.2-A3 | Given an expired or screenshot QR, when scanning, then the scan fails with "This code has expired. Ask the coordinator to show the current code." |
| F3.2-A4 | Given an unregistered student, when scanning, then the scan fails with a registration-required message. |
| F3.2-A5 | Given check-out without a prior check-in, then the scan is rejected. |

**Edge cases:**

- Student device clock wrong: irrelevant, server clock is authoritative.
- No signal: show "Ask your coordinator for a manual entry". No offline check-in in MVP.
- Camera permission denied: show instructions and an alternative to ask staff.

#### F3.3 Authorized manual fallback [P0]

Staff with `attendance.manual` can add or correct attendance with a mandatory reason.

| AC | Given / When / Then |
|---|---|
| F3.3-A1 | Given a staff member in scope, when saving a manual entry with a reason of at least 10 characters, then attendance is saved with `source=manual` and an adjustment row is written. |
| F3.3-A2 | Given no reason, when saving, then the request is rejected. |
| F3.3-A3 | Given a student account, when attempting manual entry, then access is denied. |

#### F3.4 Approve, reject or request revision [P0]

The coordinator reviews each attendance and approves, rejects (reason required) or requests revision (reason required). Only approved hours count.

| AC | Given / When / Then |
|---|---|
| F3.4-A1 | Given a submitted attendance in scope, when approved, then a service record is created/updated with server-derived minutes, approver and timestamp. |
| F3.4-A2 | Given rejection, then `approved_minutes` stays 0 and the student sees Rejected with the reason; no credit is invented. |
| F3.4-A3 | Given an attendance for the approver's own claim, when approving, then the request fails. |
| F3.4-A4 | Given an attendance with missing check-out, then it is flagged as an exception and cannot be approved without a logged correction. |

**Edge cases:**

- Bulk approve of clean rows is optional P1; exceptions must be reviewed individually.

#### F3.5 Corrections keep history [P0]

Changing an approved record creates a new version with a reason; earlier versions remain viewable by authorized staff.

| AC | Given / When / Then |
|---|---|
| F3.5-A1 | Given an approved record, when corrected, then version N+1 exists, version N is unchanged, and a reason is stored. |
| F3.5-A2 | Given a correction, then a new proof is queued for the new version and the old proof stays as history (validity Superseded). |

### 6.4 F4 Blockchain-backed record integrity

#### F4.1 Anchor approved records [P0]

Each approved record version receives a privacy-preserving commitment anchored on an EVM testnet.

| AC | Given / When / Then |
|---|---|
| F4.1-A1 | Given an approval, when the transaction commits, then a proof row is queued and a job submits the commitment. |
| F4.1-A2 | Given the chain is slow, then approval is not blocked; the proof shows Queued/Submitted. |
| F4.1-A3 | Given inspection of the chain data, then no name, email, student number or attendance times appear. |

#### F4.2 Separate approval, proof and validity states [P0]

Users see three distinct states and never one merged badge.

| AC | Given / When / Then |
|---|---|
| F4.2-A1 | Given an approved record with a submitted transaction, then UI shows Approval: Approved, Proof: Submitted, Validity: Current. |
| F4.2-A2 | Given the transaction is mined but below required confirmations, then Proof stays Submitted, not Confirmed. |

#### F4.3 Verification results [P0]

Verification returns exactly one of Verified, Pending, Mismatch, Not Found or Unavailable.

| AC | Given / When / Then |
|---|---|
| F4.3-A1 | Given a confirmed proof and matching recomputed commitment, then Verified. |
| F4.3-A2 | Given proof not yet confirmed, then Pending. |
| F4.3-A3 | Given the RPC cannot be reached, then Unavailable, never Verified. |
| F4.3-A4 | Given an unknown token, then Not Found. |

#### F4.4 Tamper detection [P0]

If approved content changes outside the versioning process, verification yields Mismatch.

| AC | Given / When / Then |
|---|---|
| F4.4-A1 | Given an approved confirmed record, when `approved_minutes` is edited directly in the database, then verification returns Mismatch. |
| F4.4-A2 | Given Mismatch, then the UI explains in plain language and offers staff the audit trail. |

### 6.5 F5 Certificates and public verification

#### F5.1 Issuance with eligibility [P0]

An authorized signer issues a certificate only when eligibility is satisfied.

| AC | Given / When / Then |
|---|---|
| F5.1-A1 | Given a student with sufficient approved minutes and a confirmed proof, when the signer issues, then a certificate with number and token is created and a proof is queued. |
| F5.1-A2 | Given insufficient approved minutes, when issuing, then the action is blocked with the shortfall shown. |
| F5.1-A3 | Given an already issued certificate for the term, when issuing again, then the signer is offered supersede instead. |

#### F5.2 Unpredictable public identifier and QR link [P0]

Each certificate has a random token and QR code pointing to `/verify/{token}`.

| AC | Given / When / Then |
|---|---|
| F5.2-A1 | Given a certificate, then the token is at least 128 bits of randomness and not derived from student or certificate number. |
| F5.2-A2 | Given many requests with random tokens, then rate limiting applies. |

#### F5.3 Public view with minimal facts [P0]

The public page shows certificate number, issuing body label, term, approved hours, validity and proof state, plus a disclaimer.

| AC | Given / When / Then |
|---|---|
| F5.3-A1 | Given a valid certificate, then the page shows one primary outcome (Verified) and optional expandable technical evidence (network, transaction). |
| F5.3-A2 | Given any public view, then student number, email and attendance detail are absent. |
| F5.3-A3 | Given the page, then it states that integrity is not proof of actual participation. |

**Edge cases:**

- Student name display is a school decision: default is initials or masked name; full name only if the school approves.

#### F5.4 Revocation and supersession [P0]

A signer can revoke (reason, confirmation) or supersede; both are traceable.

| AC | Given / When / Then |
|---|---|
| F5.4-A1 | Given a signer clicks Revoke, then a confirmation dialog requires a reason. |
| F5.4-A2 | Given a revoked certificate, then the public page shows Revoked and never Verified. |
| F5.4-A3 | Given a superseded certificate, then the page shows Superseded with a pointer to the current one by certificate number only. |

#### F5.5 Supplementary status [P0]

Every certificate surface says it is supplementary, not an official completion record, unless the school adopts it.

| AC | Given / When / Then |
|---|---|
| F5.5-A1 | Given any certificate view or PDF/print, then the supplementary statement is visible. |

### 6.6 F6 Dashboard and reporting

#### F6.1 Student dashboard [P0]

Student sees personal totals and statuses.

| AC | Given / When / Then |
|---|---|
| F6.1-A1 | Given approved, pending and rejected records, then totals show approved minutes only as official; pending is shown separately. |

#### F6.2 Coordinator dashboard [P0]

Coordinator sees scoped event, attendance, approval and proof summaries.

| AC | Given / When / Then |
|---|---|
| F6.2-A1 | Given scope sections 1 and 2, then counts include only those sections. |
| F6.2-A2 | Given approvals awaiting action, then they are highlighted on the dashboard. |

#### F6.3 Official totals exclusion [P0]

Official totals exclude pending and rejected hours.

| AC | Given / When / Then |
|---|---|
| F6.3-A1 | Given a pending record, then it does not change the official total until approved. |

#### F6.4 Basic accomplishment summary [P0]

A basic on-screen accomplishment summary is available; formal exports are pilot-phase.

| AC | Given / When / Then |
|---|---|
| F6.4-A1 | Given a coordinator, when opening Reports, then counts of events, attendees, approved hours and proof statuses for the selected term are shown. |

## 7. End-to-end journey

1. Coordinator publishes an event.
2. Students register.
3. Coordinator opens a session; students scan the rotating QR to check in and out.
4. Coordinator reviews exceptions and approves hours.
5. System anchors the approved version on an EVM testnet.
6. Authorized signer issues a certificate.
7. External verifier scans the certificate QR and sees Verified.
8. Presenter alters an approved database value; verification shows Mismatch.

## 8. Non-functional product requirements

| ID | Requirement | Target |
|---|---|---|
| NFR-01 | Mobile-first scanning | Student can reach scan from dashboard in at most 2 taps |
| NFR-02 | QR expiry | 30 s default, server clock |
| NFR-03 | Chain outage tolerance | Approvals succeed with chain down; proof pending |
| NFR-04 | Accessibility | WCAG 2.2 AA; status never conveyed by color alone |
| NFR-05 | Performance (demo) | Page loads under 2 s on typical Wi-Fi with seeded data; scan result under 1.5 s |
| NFR-06 | Privacy | Synthetic data only until DPO review and school approval |
| NFR-07 | Reliability priority | P0 reliability outranks extra features after feature freeze |

## 9. Success and acceptance criteria

| ID | Criterion | Verified by |
|---|---|---|
| AC-01 | End-to-end demo works with synthetic records | Scripted run-through |
| AC-02 | Expired QR is rejected | F3.2-A3 |
| AC-03 | Duplicate attendance is blocked | F3.2-A2 |
| AC-04 | Self-approval is blocked | F1.5-A1, F3.4-A3 |
| AC-05 | Proof shows Confirmed only after chain confirmation | F4.2-A2 |
| AC-06 | Public page hides student ID, email and attendance detail | F5.3-A2 |
| AC-07 | Tampered approved record returns Mismatch | F4.4-A1 |
| AC-08 | Revoked certificate never appears valid | F5.4-A2 |

Pilot targets (for example review time reduction or duplicate-credit rate) need baseline measurement and school agreement; none are promised for the hackathon.

## 10. Traceability matrix

| Requirement | Screens (App Flow) | Tables (Schema) | Routes (TRD) |
|---|---|---|---|
| F1.x | S01, S15, S17 | `users`, `roles`, `user_roles`, `permissions`, `user_section_assignments`, `audit_logs` | `/login`, `/admin/users` |
| F2.x | S03, S04, S08, S09 | `events`, `event_sessions`, `registrations` | `/events`, `/events/{id}`, `/events/{id}/register` |
| F3.x | S05, S06, S10, S11 | `qr_challenges`, `attendance`, `attendance_adjustments`, `service_records`, `service_record_versions` | `/sessions/{id}/qr`, `/attendance/scan`, `/hours/review` |
| F4.x | S12, S16 | `blockchain_proofs` | `/proofs/{id}`, `/verify/{token}` |
| F5.x | S07, S13, S16 | `certificates`, `certificate_events`, `blockchain_proofs` | `/certificates`, `/verify/{token}` |
| F6.x | S02, S08, S14 | queries over `service_records`, `attendance`, `blockchain_proofs` | `/dashboard`, `/reports` |

## 11. Risks and mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Testnet RPC outage during demo | No live confirmation | Fallback RPC and preconfirmed fixture |
| Users read "blockchain verified" as "participation verified" | Misleading claims | Mandatory disclaimer on public page and certificates |
| Hour rounding rules undecided | Disputed totals | Configurable rule; school decision logged as open item |
| Scope creep into excluded features | Unreliable demo | Feature freeze midway through Day 2; excluded list enforced in review |
| QR sharing or screenshots | False attendance | Short expiry, server clock, registration check, unique constraint, coordinator review |
| Privacy exposure | Legal risk | Synthetic data only; no PII on chain; DPO review before pilot |

## 12. Dependencies and open product decisions

| # | Decision needed from school | Default assumed in MVP |
|---|---|---|
| D1 | Service-hour rounding and break rules | Floor to minute, no break deduction |
| D2 | Certificate eligibility threshold | Configurable minutes per term (placeholder) |
| D3 | Signer authority and scope | Admin assigns permission per signer |
| D4 | Assigned-section policy | Coordinator limited to assigned sections |
| D5 | Relationship to official NSTP records | Supplementary only |
| D6 | Lawful processing basis and pilot owner | Synthetic data only |
| D7 | Student name display on public page | Masked / initials |

No claim of official NSTP recognition is made anywhere in the product.
