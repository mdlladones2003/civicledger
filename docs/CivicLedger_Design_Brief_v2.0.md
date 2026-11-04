# CivicLedger: Design Brief v2.0

DECENTRALTECH / CIVICLEDGER / CRYPTCAMP

Brand • tokens • components • status variants • screen treatments • accessibility

**Status:** Proposed • Hackathon MVP • Synthetic data only • Detailed edition • 25 October 2026

> **Scope baseline:** Six functional MVP modules (F1–F6). Former F5 community needs, F6 donations, F7 projects and F10 notifications are not part of the MVP. Legacy F8 certificates → F5; legacy F9 reporting → F6. This v2.0 expands v1.0; it adds detail but no new scope.

Detailed edition of Design Brief v1.0; designs cover S01–S17 only.

## 1. Brand proposition and voice

**CivicLedger: credible civic participation records, not cryptocurrency speculation.** Proposed tagline: “Service recorded. Impact trusted.”

| Voice trait | Means | Example | Avoid |
|---|---|---|---|
| Plain-language | Short sentences, no jargon on primary surfaces | “Your hours are waiting for approval.” | “Attestation pending finality.” |
| Transparent | Say what is known and not known | “Integrity checks do not prove participation.” | Overclaiming “100% secure” |
| Professional | Calm, institutional, respectful | “This code has expired.” | Exclamation-heavy or playful errors |
| Community-minded | Acknowledge the service effort | “Thank you for serving.” | Gamified hype, coin or token language |

### 1.1 Words we use and avoid

| Use | Instead of |
|---|---|
| Record integrity check | Blockchain verification (on primary surfaces) |
| Anchored on [network name] | Minted, tokenized, on-chain asset |
| Approved hours | Credits, points |
| Certificate (supplementary record) | Official NSTP certificate |
| Technical evidence | Hash, proof payload (shown only inside expandable section) |

## 2. Visual identity

### 2.1 Color tokens

The suggested palette is preserved and extended with accessible text variants. Amber #D97706 has low contrast as text on white, so it is used for fills, borders and icons with dark text; amber text uses the darker token.

| Token | Hex | Use | Notes |
|---|---|---|---|
| --color-navy | #123047 | Headings, nav, primary text on light | High contrast on off-white |
| --color-teal | #0F766E | Primary actions, links, success/verified accents | White text on teal meets 4.5:1 |
| --color-offwhite | #F8FAFC | App background | |
| --color-slate | #475569 | Secondary text, borders, neutral states | Meets 4.5:1 on off-white |
| --color-amber | #D97706 | Pending fills, icon, accent border | Do not use as small text on white |
| --color-amber-text | #92400E | Pending text on amber tint | Pair with #FEF3C7 background |
| --color-red | #B91C1C | Errors, revoked, mismatch | White text on red meets 4.5:1 |
| --color-teal-tint | #CCFBF1 | Verified/approved background | Text navy or teal-900 |
| --color-red-tint | #FEE2E2 | Error/revoked background | Text #7F1D1D |
| --color-slate-tint | #E2E8F0 | Neutral/superseded/unavailable background | Text navy |

Designers must run a contrast check on every final text/background pair. The ratios above are targets to verify in the design tool, not substitutes for testing.

### 2.2 Typography

| Style | Font | Size / line height (mobile → desktop) | Weight |
|---|---|---|---|
| Display (dashboard totals) | Inter | 28/34 → 36/42 | 700 |
| H1 | Inter | 22/28 → 28/34 | 700 |
| H2 | Inter | 18/24 → 22/28 | 600 |
| H3 | Inter | 16/22 → 18/24 | 600 |
| Body | Inter | 16/24 | 400 |
| Small / caption | Inter | 14/20 | 400–500 |
| Mono (hashes, certificate numbers) | JetBrains Mono or system mono | 13/20 | 400 |

Body never below 16 px on mobile; caption never below 14 px. Numbers in tables use tabular figures. Fall back to an accessible system sans-serif stack if Inter fails to load.

### 2.3 Spacing, radius, elevation

| Token | Value |
|---|---|
| Spacing scale (Tailwind) | 4, 8, 12, 16, 24, 32, 48 px (1, 2, 3, 4, 6, 8, 12) |
| Page gutters | 16 px mobile, 24 px tablet, 32 px desktop; max content width 1200 px (staff), 480 px (student mobile column) |
| Radius | sm 6 px (inputs), md 10 px (cards), lg 16 px (dialogs); full for chips |
| Elevation | Flat by default; 1 subtle shadow for cards; 1 stronger for dialogs. No glows or heavy gradients. |
| Touch targets | Minimum 44 × 44 px; scan and primary actions 56 px high |

### 2.4 Iconography and imagery

- Lucide icons, 20 px default, 24 px in navigation, stroke 1.75–2.
- Icons always accompanied by text for status; icon-only buttons require accessible labels and tooltips.
- Avoid coin imagery, chain-link motifs, glowing network graphics and excessive gradients.
- No stock photos required; use simple geometric empty-state icons.

## 3. Component library and variants

Use shadcn/ui with Tailwind. Components below map to screens and carry the states required for implementation.

| Component | Used for | Variants / states required |
|---|---|---|
| Button | All actions | Primary (teal), Secondary (outline), Destructive (red), Ghost; sizes sm/md/lg; loading, disabled, focus |
| Card | Events, totals, certificates | Default, interactive (hover/focus), highlighted (amber border for action needed) |
| Badge | Statuses | Approval, Proof, Validity variants (see §4); icon + text |
| Alert | Inline explanations | Info, Success, Warning, Error; with action link |
| Table | Rosters, queues | Sticky header, row hover, selected, sortable header, empty, loading skeleton; converts to stacked cards below 640 px |
| Dialog | Confirm revoke/reject/cancel; manual entry | Default, destructive; focus trap; Esc closes; reason textarea validation |
| Input / Select / Form | All forms | Default, focus, error with message, disabled; labels always visible |
| Tabs | Admin, Proof detail | Keyboard arrow navigation; selected underline plus bold |
| Skeleton | Loading | Matches final layout |
| Toast | Non-blocking confirmations | Success, error; polite live region; 5 s, pausable; never the only record of an error |
| QR display | Live QR | Large (min 280 px), quiet zone, countdown ring + text, expired overlay |
| Scanner frame | S05 | Viewfinder overlay, torch toggle, result sheet |
| Stat tile | Dashboards | Value, label, optional trend; no color-only meaning |
| Timeline | Proof detail | Steps with state icons and timestamps |

## 4. Status vocabulary and variants

Three axes are separate and are never merged into one badge. Every badge has an icon, a text label and a color. Color is never the only signal.

| Axis | State | Label | Icon (Lucide) | Background / text | Helper text |
|---|---|---|---|---|---|
| Approval | Pending | Pending review | clock | Amber tint / amber-text | Waiting for coordinator |
| Approval | Approved | Approved | check-circle | Teal tint / navy | Counts toward official hours |
| Approval | Rejected | Rejected | x-circle | Red tint / dark red | Does not count; see note |
| Approval | Revision requested | Revision requested | pencil | Amber tint / amber-text | Staff will correct, then resubmit |
| Proof | Queued | Queued | hourglass | Slate tint / navy | Waiting to be sent |
| Proof | Submitted | Submitted | send | Amber tint / amber-text | Waiting for confirmations |
| Proof | Confirmed | Confirmed | shield-check | Teal tint / navy | Anchored and confirmed |
| Proof | Failed | Failed | alert-triangle | Red tint / dark red | Retry available to staff |
| Validity | Current | Current | badge-check | Teal tint / navy | Latest valid version |
| Validity | Superseded | Superseded | replace | Slate tint / navy | Replaced by a newer record |
| Validity | Revoked | Revoked | ban | Red tint / dark red | No longer valid |
| Verification | Verified | Verified | shield-check | Teal / white (banner) | Matches anchored version |
| Verification | Pending | Pending | clock | Amber tint / amber-text | Awaiting confirmation |
| Verification | Mismatch | Mismatch | shield-alert | Red / white (banner) | Does not match anchored version |
| Verification | Not Found | Not found | search-x | Slate tint / navy | No record for this link |
| Verification | Unavailable | Unavailable | wifi-off | Slate tint / navy | Could not check right now |

- A record may show up to three badges at once (for example Approved + Confirmed + Current); place them in a labeled row: “Approval · Proof · Validity”.
- Revoked overrides everything else visually: the public page leads with the Revoked banner.
- Never display a green Verified when the chain could not be checked.

## 5. UX principles and decisions

| Principle | Decision |
|---|---|
| Students reach scanning fast | Scan is the center bottom-tab on mobile and the primary button on S02; scanner opens in at most two taps from the dashboard |
| Coordinators see exceptions first | Approval queue sorts flagged rows to the top with amber left border and flag chips |
| One clear outcome for verifiers | S16 leads with a single banner; technical evidence collapsed by default |
| Explain failures | Every error says what happened, why, and what to do next |
| Progressive disclosure of blockchain | Use “record integrity” language by default; network and transaction hash only in expandable evidence |
| Calm status changes | Chain progress is nonblocking; updates announce politely and do not move layout |
| Honesty by design | Disclaimer visible on S16 and every certificate surface |

## 6. Screen-by-screen treatments (S01–S17)

Treatments describe layout and visual decisions. Content and behavior live in App Flow v2.0; priority mockups are marked ★.

#### S01 Sign in

| | Treatment |
|---|---|
| Mobile layout | Single column, logo and tagline top, form card, 56 px button. |
| Desktop layout | Centered 420 px card on off-white; optional navy side panel with tagline. |
| Key visual decisions | Demo-mode banner in amber tint. No marketing blockchain imagery. |

#### S02 Student dashboard

| | Treatment |
|---|---|
| Mobile layout | Stacked: greeting, big teal Scan button, next-event card, two stat tiles (Approved / Pending), recent list. |
| Desktop layout | Two columns: left tiles and recent; right next event and certificate. |
| Key visual decisions | Approved and Pending tiles separated and labeled; pending never added into the approved number. |

#### S03 ★ Eligible event list

| | Treatment |
|---|---|
| Mobile layout | Card list with date block on left, status chip on right, sticky filter chips. |
| Desktop layout | Three-column card grid or list with filters in a left rail. |
| Key visual decisions | Registered events show a teal outline and chip; full events show slate chip. |

#### S04 ★ Event detail and registration

| | Treatment |
|---|---|
| Mobile layout | Hero title, key facts list with icons, sticky bottom action bar (Register or Scan). |
| Desktop layout | Two columns: details left, action card right. |
| Key visual decisions | Primary button changes label by state (Register, Registered ✓, Scan Attendance, Full). |

#### S05 ★ QR scanner and result

| | Treatment |
|---|---|
| Mobile layout | Full-bleed camera, dimmed surround, 260 px square frame, instruction chip at top, torch and help buttons at bottom; result opens as bottom sheet. |
| Desktop layout | Centered 480 px camera card with instructions; recommends phone. |
| Key visual decisions | Success sheet: large check, time, text “Checked in at 8:02 AM”. Error sheets use red or amber icons plus plain explanation and Rescan primary button. |

#### S06 My attendance and hours

| | Treatment |
|---|---|
| Mobile layout | Summary tiles scroll horizontally; list of cards with chips; filter sheet. |
| Desktop layout | Tiles row plus data table. |
| Key visual decisions | Rejected cards show note inline, minutes shown as “0 credited”. |

#### S07 My certificates

| | Treatment |
|---|---|
| Mobile layout | Certificate card full-width with QR and copy-link button. |
| Desktop layout | Card with print-friendly preview on right. |
| Key visual decisions | Subtle navy header band; no ornate borders; supplementary-record statement under title; Revoked certificates desaturated with red banner. |

#### S08 Coordinator dashboard

| | Treatment |
|---|---|
| Mobile layout | Action-needed card first (amber border), then tiles, then today list. |
| Desktop layout | Three-column tile row; table of recent events below. |
| Key visual decisions | Action-needed card is the only amber-bordered element by default so it stands out. |

#### S09 Event editor

| | Treatment |
|---|---|
| Mobile layout | Single column form with sticky Save bar. |
| Desktop layout | Two columns: form left, sessions and status panel right. |
| Key visual decisions | Status badge at top; lifecycle buttons grouped; destructive Cancel at far end with confirm dialog. |

#### S10 ★ Live QR and roster

| | Treatment |
|---|---|
| Mobile layout | Tabs: QR \| Roster. QR tab shows code at max width with countdown. |
| Desktop layout | Split view: QR left (large for projector), roster table right. |
| Key visual decisions | Presentation mode: hides chrome, QR min 400 px, high contrast. Countdown ring plus numeric seconds. Flag chips in roster. |

#### S11 ★ Approval queue and detail

| | Treatment |
|---|---|
| Mobile layout | Queue as stacked cards; detail as full screen with sticky decision bar. |
| Desktop layout | Master-detail: queue left, detail right. |
| Key visual decisions | Decision bar has Approve (teal), Request revision (outline), Reject (red outline). Self-claim shows disabled Approve with inline explanation. |

#### S12 ★ Proof status and detail

| | Treatment |
|---|---|
| Mobile layout | List cards with three-badge row; detail with vertical timeline. |
| Desktop layout | Table with chips; detail drawer. |
| Key visual decisions | Technical values in mono with copy buttons; plain-language explanation always above technical values. |

#### S13 Issue and revoke certificate

| | Treatment |
|---|---|
| Mobile layout | Cards per eligible record; actions in bottom sheet. |
| Desktop layout | Table plus right-side eligibility panel. |
| Key visual decisions | Revoke is destructive-styled and requires dialog with reason; success shows verification link and QR. |

#### S14 Basic reports

| | Treatment |
|---|---|
| Mobile layout | Filters in sheet; metric tiles; per-event cards. |
| Desktop layout | Filter bar, tile row, table. |
| Key visual decisions | Pilot-only exports shown as disabled with label “Available in pilot”. Charts optional; if used, include a data table alternative. |

#### S15 ★ Users, scopes and audit

| | Treatment |
|---|---|
| Mobile layout | Tabs; users as cards; assignment editing in full-screen sheet. |
| Desktop layout | Tabs with tables; assignment matrix of sections by scope role. |
| Key visual decisions | Scope chips make boundaries visible; audit diff uses before/after columns with text labels, not color alone. |

#### S16 ★ Public certificate verification

| | Treatment |
|---|---|
| Mobile layout | Banner at top (full width), facts list, expandable Technical evidence, disclaimer. |
| Desktop layout | Centered 640 px column. |
| Key visual decisions | Banner is the single primary outcome; page works without login; minimal header with CivicLedger wordmark only; no navigation into the app. |

#### S17 System states

| | Treatment |
|---|---|
| Mobile layout | Full-page or inline modules. |
| Desktop layout | Same. |
| Key visual decisions | One icon, one sentence, one action. No humor. Request id collapsed under “Technical details”. |

## 7. Microcopy catalogue

This catalogue is the source of truth for final copy. Messages quoted in the PRD, App Flow and TRD state the intent and should match it where they cover the same situation.

| Situation | Copy |
|---|---|
| QR expired | This code has expired. Ask the coordinator to show the current code. |
| QR invalid | This isn’t a CivicLedger attendance code. Scan the code shown by your coordinator. |
| Duplicate scan | You already checked in at {time}. No action needed. |
| Not registered | You need to register for this event before scanning. |
| No signal | We can’t reach the server. If this continues, ask your coordinator for a manual entry. |
| Self-approval blocked | You can’t approve your own attendance. Ask another coordinator to review it. |
| Out of scope | You don’t have access to this section. Contact your administrator if this is a mistake. |
| Proof pending | Your approval is saved. The blockchain check is in progress and may take a few minutes. |
| Chain unavailable | We couldn’t reach the blockchain right now. Your records are safe. We’ll retry automatically. |
| Verified | Record integrity verified. This record matches the version anchored when it was approved. |
| Mismatch | This record doesn’t match the version anchored at approval. Contact the issuing office. |
| Revoked | This certificate has been revoked and is no longer valid. |
| Disclaimer (public) | Integrity checks show a record has not been altered since approval. They do not prove actual participation. This is a supplementary record, not an official completion record unless adopted by the school. |
| Revoke confirm | Revoke certificate {number}? It will no longer verify as valid. This action is recorded and can’t be undone. |

## 8. Accessibility (target WCAG 2.2 AA)

| Area | Requirement | Check |
|---|---|---|
| Keyboard | All actions operable by keyboard; logical tab order; no traps; dialogs return focus | Manual + axe |
| Focus | Visible 2 px focus ring in teal with offset; 3:1 against adjacent colors | Visual |
| Contrast | 4.5:1 ordinary text, 3:1 large text and UI components | Contrast tool on tokens |
| Color independence | Status uses icon + text + color | Grayscale review |
| Labels | Visible labels, programmatic association, descriptive button text | Screen reader |
| Errors | Error text linked with aria-describedby; summary at top; focus moves to first error | Manual |
| Live updates | Toasts and proof changes in polite aria-live regions; QR countdown not announced every second | Screen reader |
| Touch | Targets at least 44 × 44 px (WCAG 2.2 target size minimum is 24 px; we exceed it) | Device test |
| Scanner | Provide alternative to camera (ask staff for manual entry); instructions as text | Manual |
| Motion | Respect prefers-reduced-motion; countdown ring has a static alternative | Manual |
| Zoom/reflow | Usable at 200% zoom and 320 px width without horizontal scroll | Browser test |
| Language | Plain language; avoid unexplained abbreviations (NSTP spelled out on first use) | Content review |

## 9. Responsive behavior

| Breakpoint | Behavior |
|---|---|
| < 640 px | Single column; bottom tab bar (students); tables become stacked cards; sticky action bars |
| 640–1023 px | Two-column where useful; side drawer navigation for staff |
| ≥ 1024 px | Sidebar navigation for staff; master-detail layouts; tables |
| Projector (S10 presentation mode) | QR min 400 px, high contrast, minimal chrome |

## 10. Motion

- Durations 150–200 ms for hover/focus, 250 ms for sheets and dialogs; ease-out.
- QR refresh: crossfade only; no flashing; countdown ring updates smoothly or in 1 s steps in reduced-motion mode.
- Proof state changes: brief highlight of the badge (no layout shift).
- No confetti or celebratory animation; success is calm.

## 11. Synthetic data for design and demo

| Entity | Samples |
|---|---|
| Students (synthetic) | Ana Reyes, Ben Santos, Carla Dizon (ids DEMO-0001 to DEMO-0003) |
| Coordinator / signer / admin | Mr. Cruz (Coordinator, sections A and B), Dr. Lim (Signer), Alex Admin |
| Event | “Coastal Clean-up Drive”, Sat 24 Oct 2026, 8:00 AM–12:00 PM, Community Beach (demo), CWTS |
| Hours | Ana 183 min approved; Ben 175 min pending; Carla 0 credited (rejected: missing check-out) |
| Certificate | CL-2026-000001, term 2026-1, 3.05 hours, status Current, proof Confirmed |
| Failure fixtures | Expired QR, duplicate scan, Failed proof, Revoked certificate, Mismatch result |

## 12. Deliverables, handoff and review

### 12.1 Deliverables

1. Desktop and mobile designs for S01–S17, including loading, empty, error and permission states.
2. Clickable prototype of the core demo flow.
3. Design tokens (color, type, spacing, radius, shadow) as Tailwind config and CSS variables.
4. Status badge variants as a documented component.
5. Synthetic data samples.
6. Handoff notes for React/Inertia/shadcn/ui.

### 12.2 Handoff notes

- Provide Tailwind theme extension with the tokens in §2 and dark-mode excluded from MVP unless time allows.
- Name Figma components after shadcn/ui components; list props and states.
- Specify which screens use skeletons versus spinners; spinners only inside buttons.
- Export Lucide icon names for every status; do not substitute icons without review.
- Include copy deck (§7) as JSON for localization readiness; MVP copy is English.

### 12.3 Review plan with representative users

| Topic | Method | Success signal |
|---|---|---|
| Scan recovery | Moderated test: expired code, duplicate scan, no signal | Student recovers without help in under 30 s |
| Coordinator approval | Task: find exception, decide, retry a failed proof | Completes approval queue of 10 rows with no wrong decisions |
| Certificate status comprehension | Show Verified, Pending, Mismatch, Revoked pages; ask what each means | At least 8 of 10 participants state the correct meaning, including that integrity ≠ participation |

Thresholds above are proposed for the pilot and require school agreement; they are not hackathon obligations.

## 13. Exclusions

No community-needs, donation, project or notification UI. No coin or token imagery. No mock-ups for excluded features are required.
