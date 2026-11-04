# CivicLedger: App Flow v2.0

DECENTRALTECH / CIVICLEDGER / CRYPTCAMP

User journeys • decisions • screen specifications S01–S17 • edge cases • demo script

**Status:** Proposed • Hackathon MVP • Synthetic data only • Detailed edition • 25 October 2026

> **Scope baseline:** Six functional MVP modules (F1–F6). Former F5 community needs, F6 donations, F7 projects and F10 notifications are not part of the MVP. Legacy F8 certificates → F5; legacy F9 reporting → F6. This v2.0 expands v1.0; it adds detail but no new scope.

Detailed edition of App Flow v1.0; screens limited to S01–S17.

## 1. Role-based navigation

| Role | Primary navigation (in order) | Landing screen |
|---|---|---|
| Student | Dashboard → Events → Event Detail → Scan Attendance → My Hours → Certificates | S02 |
| Coordinator | Dashboard → Events → Event Editor → Live QR/Roster → Review Hours → Proof Status → Reports | S08 |
| Signer | Eligible Records → Issue/Revoke Certificate | S13 |
| Administrator | Users → Roles/Assignments → Audit | S15 |
| External verifier | Certificate QR → public verification result | S16 |

### 1.1 Navigation rules

- **Role-aware navigation:** menus show only destinations the role may use; users with several roles get a role switcher (e.g. Coordinator + Signer).
- **Mobile:** bottom tab bar for students (Home, Events, Scan, Hours, Certificates) with Scan as the center primary action; staff use a top bar with a drawer.
- **Desktop:** left sidebar for staff, top bar for students.
- **Breadcrumbs and back:** consistent breadcrumb on detail pages (Events › Beach Clean-up › Roster); the browser back button never loses entered form data.
- **Permission denied:** explicit state (S17) naming what was blocked and how to get access; never a blank page.
- **Confirmation:** required before revoke, cancel event, reject hours, and role removal.
- **Non-blocking chain progress:** proof states appear inline; no screen blocks on a transaction.
- **Retry:** every failure state offers a clear retry or alternative action.

### 1.2 Site map

```text
/login                                S01
/dashboard                            S02 (student) | S08 (coordinator) | S13 landing (signer) | S15 (admin)
/events                               S03 (student)  | S08 list (coordinator)
/events/{id}                          S04 (student)  | S09 editor (coordinator)
/sessions/{id}/qr                     S10 (coordinator)
/register | /password/change          Account screens outside S01-S17
/profile                              Account details and student account deletion
/attendance/scan                      S05
/me/hours                             S06
/certificates                         S07 (student)  | S13 (signer)
/hours/review[/{id}]                  S11
/proofs[/{id}]                        S12
/reports                              S14
/admin/users | /admin/audit           S15
/verify/{token}                       S16 (public)
Shared states                         S17 (loading, empty, error, denied)
```

## 2. Detailed user flows

### 2.1 Student flow

| Step | Screen | Decision / action | Outcome |
|---|---|---|---|
| 1 | S01 | Sign in | Authorized section? Yes → S02. No → S17 denied ("Your account is not assigned to a section. Contact your coordinator.") |
| 2 | S03 | View eligible events | List of published events for the student's sections; empty state if none |
| 3 | S04 | Open event; already registered? | Yes: show registration status. No: Register button |
| 4 | S04 | Register | Toast "You are registered"; button becomes status chip. Retry-safe. |
| 5 | S04/S05 | Event active (session open)? | No: "Attendance opens at 8:00 AM". Yes: Scan Attendance CTA |
| 6 | S05 | Scan rotating QR; valid and unexpired? | No → inline reason and Rescan. Yes → check-in confirmation with time |
| 7 | S05 | Later scan check-out QR | Check-out confirmation with duration shown as "pending approval" |
| 8 | S06 | Wait for approval | Status chips: Submitted / Approved / Rejected / Revision requested |
| 9 | S06 | View approved minutes | Approved hours added to official total only when Approved |
| 10 | S07 | Access certificate when issued | Certificate card with QR, number, validity and proof state |

#### Student exceptions

| Situation | System behavior | Next action offered |
|---|---|---|
| QR invalid | Message "This isn't a CivicLedger attendance code" | Rescan |
| QR expired | Message "This code has expired. Ask the coordinator to show the current code." | Rescan |
| Not registered | Message "Register for this event first" | Button to event detail |
| No signal | Scanner cannot reach server; error banner | "Ask your coordinator for a manual entry" with explanation of reasoned manual entry |
| Camera blocked | Permission instructions per browser | Open settings help; ask staff |
| Already checked in | Shows original check-in time, no new credit | Go to My Hours |
| Attendance rejected | Status Rejected with coordinator reason; no minutes credited | Contact coordinator |
| Revision requested | Status shown with note | Wait for staff correction |

### 2.2 Coordinator flow

| Step | Screen | Decision / action | Outcome |
|---|---|---|---|
| 1 | S01 | Sign in; section assignment checked | No assignment → S17 denied with admin contact |
| 2 | S09 | Create event draft; validate fields | Inline errors; saved as Draft |
| 3 | S09 | Publish | Status Published; visible to eligible students |
| 4 | S08/S10 | Students register | Roster fills; counts update |
| 5 | S10 | Open session | Rotating QR displayed with countdown; live roster begins |
| 6 | S10 | Review attendance exceptions | Missing check-out, manual entries, duplicates highlighted |
| 7 | S11 | Approve / reject / request revision | Own claim → blocked; reason required for reject/revision |
| 8 | S12 | Inspect chain proof status | Queued / Submitted / Confirmed / Failed; Retry on Failed |
| 9 | S14 | Report | Scoped summary for term |

#### Coordinator exceptions

| Situation | Behavior |
|---|---|
| Approving own claim | Approve button disabled with tooltip; server rejects with self_approval_blocked |
| Chain unavailable | Approval completes; proof shows Pending/Unavailable; banner "Blockchain check is delayed. Your approval is saved. We will retry automatically." plus Retry now |
| Event edited after students registered | Warning dialog listing impact; confirmation required |
| Session left open past end time | Banner suggesting close; auto-close after configured grace |
| Student cannot scan | Manual entry dialog with required reason |
| Out of scope link | S17 denied state |

### 2.3 Signer flow

| Step | Screen | Action | Outcome |
|---|---|---|---|
| 1 | S13 | Open Eligible Records | List filtered by signer scope and eligibility; shows minutes, proof state, existing certificate |
| 2 | S13 | Select record; eligibility panel | Eligible: Issue enabled. Not eligible: shows shortfall |
| 3 | S13 | Issue | Certificate created; proof queued; QR link shown |
| 4 | S13 | Optional Revoke or Supersede | Dialog requires reason; confirmation; history logged |

### 2.4 Verifier flow

| Step | Decision | Result |
|---|---|---|
| 1 | Scan certificate QR → /verify/{token} | — |
| 2 | Token found? | No → Not Found |
| 3 | Certificate validity? | Revoked → Revoked; Superseded → Superseded (show current number) |
| 4 | Proof confirmed? | No → Pending |
| 5 | Chain reachable? | No → Unavailable (never Verified) |
| 6 | Commitment matches? | Yes → Verified; No → Mismatch |

### 2.5 Administrator flow

| Step | Screen | Action | Outcome |
|---|---|---|---|
| 1 | S15 | Users tab: create/suspend account | Audit row written |
| 2 | S15 | Roles & assignments tab: grant role, assign sections | Scope changes effective immediately; audit row |
| 3 | S15 | Audit tab: filter by actor, action, date | Read-only history |

## 3. State maps shown to users

| Object | States | Where shown |
|---|---|---|
| Event | Draft, Published, Cancelled, Completed | S03, S04, S08, S09 |
| Registration | Registered, Withdrawn, Cancelled | S04, S10 |
| Attendance | Checked in, Checked out, Submitted, Approved, Rejected, Revision requested | S05, S06, S10, S11 |
| Approval | Pending, Approved, Rejected | S06, S11 |
| Proof | Queued, Submitted, Confirmed, Failed | S11, S12, S13, S16 |
| Validity | Current, Superseded, Revoked | S07, S12, S13, S16 |
| Verification result | Verified, Pending, Mismatch, Not Found, Unavailable | S16 |

## 4. Screen specifications S01–S17

Each specification lists route, purpose, content, actions, states and errors. Designers use these with the Design Brief v2.0; developers pair each with the route contracts in TRD v2.0.

### S01 Sign in

| Field | Specification |
|---|---|
| Primary role | All staff and students |
| Route | /login |
| Purpose | Authenticate and route to the right dashboard. |
| Content | Email, password, show-password toggle, Sign in button, help text for lost access, product tagline "Service recorded. Impact trusted." Synthetic-data banner in demo mode. |
| Actions | Submit credentials; forgot-password link if enabled. |
| States and errors | Loading: button spinner. Error: "Email or password is incorrect." (no account enumeration). Locked: "Too many attempts. Try again in 1 minute." Suspended: "This account is not active. Contact your administrator." Denied: no section assignment. |

### S02 Student dashboard

| Field | Specification |
|---|---|
| Primary role | Student |
| Route | /dashboard |
| Purpose | Give the student their status and a one-tap path to events and scanning. |
| Content | Greeting; Scan Attendance primary button; next event card; totals (Approved hours, Pending hours shown separately); recent attendance with status chips; latest certificate card. |
| Actions | Scan; open event; open My Hours; open certificate. |
| States and errors | Empty: "No events yet. Check Events for upcoming activities." Loading: skeleton cards. Error: retry card. |

### S03 Eligible event list

| Field | Specification |
|---|---|
| Primary role | Student |
| Route | /events |
| Purpose | List published events for the student's sections. |
| Content | Event cards: title, date/time (Asia/Manila), venue, component, status chip (Open, Registered, Full, Completed), registration state. Filters: upcoming/past, registered. |
| Actions | Open detail; quick Register on card. |
| States and errors | Empty: "No eligible events right now." Error: retry. Denied: unassigned section message. |

### S04 Event detail and registration

| Field | Specification |
|---|---|
| Primary role | Student |
| Route | /events/{id} |
| Purpose | Show event facts and let the student register once. |
| Content | Title, description, venue, component, schedule, sessions with status, registration state, capacity if set, Scan CTA when a session is open. |
| Actions | Register; Scan Attendance; View My Hours. |
| States and errors | Already registered: chip + "You are registered". Full: disabled button + reason. Not eligible: reason. Cancelled: banner. Registration success: toast. Error: inline retry. |

### S05 QR scanner and scan result

| Field | Specification |
|---|---|
| Primary role | Student |
| Route | /attendance/scan |
| Purpose | Scan the coordinator's rotating QR to check in or out. |
| Content | Full-screen camera viewfinder with frame, instruction text, torch toggle if supported, current event name. Result panel: check-in/out time, event, Done button. |
| Actions | Scan; rescan; go to My Hours; fallback "Can't scan?" help. |
| States and errors | Success: large check icon, time, text label. Expired: "This code has expired…" + Rescan. Invalid: "This isn't a CivicLedger attendance code." Duplicate: "You already checked in at 8:02 AM." Not registered: link to event. Session closed. No signal: manual-entry guidance. Camera denied: steps. Rate limited: wait message. |

### S06 My attendance and hours

| Field | Specification |
|---|---|
| Primary role | Student |
| Route | /me/hours |
| Purpose | Show per-session attendance and approval outcomes. |
| Content | Summary tiles (Approved, Pending, Rejected counts and minutes). Table/cards: event, date, check-in, check-out, source (QR/manual), Approval chip, reviewer note if rejected or revision requested, Proof chip for approved rows. |
| Actions | Filter by term/status; open row detail. |
| States and errors | Empty: "No attendance yet." Rejected rows never show credited minutes. Loading: skeleton table. |

### S07 My certificates

| Field | Specification |
|---|---|
| Primary role | Student |
| Route | /certificates |
| Purpose | Show issued certificates and verification link. |
| Content | Certificate card: certificate number, term, approved hours, issued date, Validity chip, Proof chip, QR code, "Copy verification link", supplementary-record statement. |
| Actions | Open full certificate; copy link; download/print (if enabled in design). |
| States and errors | Empty: "No certificate issued yet. Certificates are issued by authorized staff after review." Revoked: red chip + reason category visible to holder; Superseded: link to current. |

### S08 Coordinator dashboard

| Field | Specification |
|---|---|
| Primary role | Coordinator |
| Route | /dashboard |
| Purpose | Highlight what needs action and give scoped summaries. |
| Content | Cards: events by status, today's sessions, attendance awaiting approval (highlighted), proofs Pending/Failed counts, quick links. Section selector limited to assignments. |
| Actions | Open event; start session; go to approval queue; go to proof status. |
| States and errors | Empty: "No events yet. Create your first event." Denied: no assignment. Loading skeleton. |

### S09 Event editor

| Field | Specification |
|---|---|
| Primary role | Coordinator |
| Route | /events/create, /events/{id}/edit |
| Purpose | Create and manage the event lifecycle. |
| Content | Form: title, description, venue, component select, section select (assigned only), start/end, capacity, sessions list (add/remove), status badge, lifecycle buttons (Publish, Cancel, Complete) with confirm dialogs. |
| Actions | Save draft; Publish; Cancel; Complete; add session. |
| States and errors | Validation errors inline with summary at top. Unsaved-changes prompt. Edit-after-publish warning. Terminal states read-only banner. |

### S10 Live QR and roster

| Field | Specification |
|---|---|
| Primary role | Coordinator |
| Route | /sessions/{id}/qr |
| Purpose | Run the session: show the rotating QR and watch attendance. A student's first valid scan is the check-in and the next is the check-out. |
| Content | Large QR with countdown ring and text "Refreshes in 18s"; session controls (Open/Close); roster table: name, registration, check-in, check-out, source, flag chips (Missing check-out, Manual, Duplicate attempt); counts; Add manual entry button. |
| Actions | Open/close session; manual entry (reason required); export none (pilot). |
| States and errors | QR refresh failure: banner "Can't refresh the code. Check your connection." with Retry. Session closed: QR hidden, roster read-only. Loading skeleton. Denied: out of scope. |

### S11 Approval queue and detail

| Field | Specification |
|---|---|
| Primary role | Coordinator |
| Route | /hours/review, /hours/review/{id} |
| Purpose | Review attendance and decide. |
| Content | Queue: filter by event/status, exceptions first, rows with student, times, computed minutes, flags. Detail: attendance timeline, minutes derivation, history of adjustments, previous versions, proof state, decision panel with note field. |
| Actions | Approve; Reject (reason); Request revision (reason); open correction. |
| States and errors | Self-claim: Approve disabled with explanation. Invalid state: refresh prompt. Success toast with proof status "Queued". Chain unavailable: non-blocking note. Concurrency: if another staff decided first, show message and refresh. |

### S12 Proof status and detail

| Field | Specification |
|---|---|
| Primary role | Coordinator (signers see proof status on S13) |
| Route | /proofs, /proofs/{id} |
| Purpose | Make the three axes (approval, proof, validity) understandable and let staff retry. |
| Content | List with Approval, Proof, Validity chips and last update. Detail: timeline (Queued → Submitted → Confirmed), network, chain id, contract (shortened), tx hash with copy, confirmations, attempts, last error (plain language), version history. |
| Actions | Retry (when Failed); copy hash; open explorer link; view audit trail. |
| States and errors | Failed: red explanation + Retry. Unavailable: amber explanation. Empty: "No proofs yet. Proofs appear after approval." |

### S13 Issue and revoke certificate

| Field | Specification |
|---|---|
| Primary role | Signer |
| Route | /certificates (signer view) |
| Purpose | Issue certificates for eligible records and manage revocation. |
| Content | Eligible records table (student masked per policy, term, approved minutes, proof state, certificate state). Eligibility panel; Issue dialog; Revoke/Supersede dialogs with reason; certificate history. |
| Actions | Issue; Revoke; Supersede; copy verification link. |
| States and errors | Not eligible: shortfall. Already issued: Supersede offered. Revoke confirm: "This certificate will no longer verify as valid." Denied: no signer permission. |

### S14 Basic reports

| Field | Specification |
|---|---|
| Primary role | Coordinator |
| Route | /reports |
| Purpose | On-screen accomplishment summary for scoped data. |
| Content | Filters: term, section, event. Metrics: events held, registrations, attendees, approved hours, pending hours, certificates issued, proof status counts. Table by event. Formal exports labeled "Pilot". |
| Actions | Change filters; open event. |
| States and errors | Empty: "No data for this selection." Loading skeleton. Denied: out of scope filters unavailable. |

### S15 Users, scopes and audit

| Field | Specification |
|---|---|
| Primary role | Administrator |
| Route | /admin/users, /admin/audit |
| Purpose | Manage accounts, roles, assignments and review audit. |
| Content | Tabs: Users (table, create/edit, suspend), Roles & assignments (role checkboxes, section assignment matrix), Audit (filters, before/after diff view). |
| Actions | Create user; change roles; assign sections; suspend; filter audit. |
| States and errors | Validation errors inline. Last-admin protection message. Confirm before removing roles. Audit empty state. |

### S16 Public certificate verification

| Field | Specification |
|---|---|
| Primary role | Public |
| Route | /verify/{token} |
| Purpose | Give an external verifier one clear outcome with optional evidence. |
| Content | Primary outcome banner (Verified, Pending, Mismatch, Not Found, Unavailable, Revoked, Superseded) with icon and text. Facts: certificate number, term, approved hours, issue date, holder initials. Proof summary. Expandable "Technical evidence" (network, transaction, checked time). Disclaimer: "Integrity checks show a record has not been altered since approval. They do not prove actual participation. This is a supplementary record, not an official completion record unless adopted by the school." |
| Actions | Expand evidence; retry check. |
| States and errors | Pending: explain confirmation delay. Unavailable: "We couldn't check the blockchain right now. Try again shortly." Mismatch: "This record doesn't match the version anchored at approval." Not Found: neutral message. Never shows student number, email or attendance. |

### S17 Loading, empty, error and denied states

| Field | Specification |
|---|---|
| Primary role | All |
| Route | Shared components |
| Purpose | Consistent system states across screens. |
| Content | Loading skeletons per layout; empty states with illustration-free icon, one sentence and one action; error state with message, request id (collapsed) and Retry; permission-denied with reason and contact path; 404; offline banner; session-expired prompt. |
| Actions | Retry; go back; contact admin; sign in again. |
| States and errors | Always keyboard accessible; live-region announcements for async changes. |

## 5. Edge cases and cross-screen rules

| ID | Case | Rule |
|---|---|---|
| E-01 | Two staff decide the same attendance | First commit wins; second sees "Already decided by another reviewer" and refreshed state |
| E-02 | Student scans after session closed | Rejected with session_closed; no attendance created |
| E-03 | Student checks out twice | Second scan returns already_checked_out; no change |
| E-04 | Missing check-out | Flagged exception; approve only after manual correction with reason |
| E-05 | Event cancelled with attendance already recorded | Attendance retained but unapprovable unless coordinator explicitly reopens (logged) |
| E-06 | Role removed while user is signed in | Next request denied; UI shows S17 |
| E-07 | Coordinator is also registered student | Cannot approve own attendance; may approve others |
| E-08 | Proof Failed after approval | Approval remains; Failed chip and Retry for staff; public page shows Pending/Unavailable, never Verified |
| E-09 | Certificate revoked while proof confirmed | Public page shows Revoked |
| E-10 | Corrected record after certificate issued | Certificate flagged "Review needed"; signer may supersede |
| E-11 | Session timeout mid-scan | Prompt to sign in again, returns to scanner |
| E-12 | Browser language/time zone differs | Times always shown in Asia/Manila with label |

## 6. Demo walkthrough

1. Coordinator signs in (S01), creates and publishes one event (S09).
2. Three synthetic students sign in on phones and register (S03/S04).
3. Coordinator opens the session (S10); students scan to check in; later check out (S05).
4. Coordinator opens the approval queue (S11) and approves; proof shows Queued then Confirmed on the EVM testnet (S12).
5. Signer issues a certificate (S13).
6. Verifier scans the certificate QR and sees Verified (S16).
7. Presenter changes an approved database value outside the app; reloading S16 shows Mismatch.
8. Presenter revokes the certificate; S16 shows Revoked.

Fallback path: if the live transaction is slow, switch to the preconfirmed fixture record and say so.

## 7. Exclusions

No screens for community needs, donations, projects or notifications. No email or in-app notification UI: students and staff see status only when they open the app.
