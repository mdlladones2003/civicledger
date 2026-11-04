# CivicLedger Project Proposal

Nov 4, 2026 · @M.A

CivicLedger is a web system that helps schools run well-documented service activities and gives students approved service records and certificates anyone can verify. Community, skills and safety features extend it in the pilot phase.

## Problem

School service programs record time served, but they rarely record what students learned, who was on site, or whether the record can be trusted.

- **Learning is invisible.** Hour logs show duration, not the skills gained.
- **Field safety depends on paper.** Coordinators often rely on sign-in sheets to know who is at a site, and incidents are reported late.
- **Community need is loosely linked.** Activities are often planned without a documented request from the community they serve.
- **Records are hard to verify.** Paper logs and spreadsheets can be edited without a trace, and checking a certificate means calling the school.

## Solution

CivicLedger keeps one record from published event to verified credential, built around four pillars. The hackathon MVP delivers the core of each pillar. The rest is planned for the pilot phase and is not part of the MVP.

| Pillar | Challenge | In the MVP | Planned for the pilot phase |
|---|---|---|---|
| Community | Activities are not tied to a documented need | Coordinators publish service events for their assigned sections; students register once | Coordinators log a community need (with the partner's name) and link each event to it |
| Education (SDG 4) | Learning is not recorded | Approved service hours and supplementary certificates give each student a verifiable record | Activities carry competency tags; students add a short reflection; certificates list skills as well as hours |
| Safety | Weak on-site accountability | Check-in and check-out with a live roster show coordinators who is on site | A safety briefing comes before check-in, emergency contacts are on hand, and coordinators can flag incidents |
| Trust infrastructure (SDG 9) | Records are easy to alter and slow to verify | A private fingerprint of each approved record is anchored on a public test blockchain, so anyone can verify it on a phone with no wallet | — |

The MVP workflow is: publish an event, register students, scan a rotating QR to check in and out, review and approve hours, anchor the record, issue a certificate, then verify it from the certificate's QR code.

## SDG alignment

CivicLedger addresses SDG 4 (Quality Education) and SDG 9 (Industry, Innovation and Infrastructure). Each target below is the closest fit to a specific feature; the proposal does not claim measured impact before a pilot.

| SDG | Target | In the MVP | Planned for the pilot phase | Pilot evidence |
|---|---|---|---|---|
| 4 | 4.4 Skills relevant to employment and civic life | Verifiable service hours and certificates | Competency tags, reflections and skills-based certificates | Share of approved records with a skill tag and a reflection |
| 4 | 4.7 Knowledge and skills for sustainable development and global citizenship | Service events recorded from registration to approval | Events tied to a documented community need and an SDG theme | Number of events linked to a documented need |
| 9 | 9.c Access to information and communication technology | Mobile web app with no wallet and no app install | — | Share of students who check in using their own phone |
| 9 | 9.5 Technological capability and innovation | Blockchain used as shared, open verification infrastructure for schools | — | Verification checks completed by people outside the school |

## MVP scope

The hackathon MVP has six modules, as defined in PRD v2.0.

| Module | Minimum demo outcome |
|---|---|
| F1 Accounts and permissions | Student, coordinator, signer and administrator roles; section scope; no self-approval |
| F2 Events and registration | Coordinator creates and publishes an event; eligible students register once |
| F3 QR attendance and hour review | Rotating QR check-in and check-out, live roster, manual fallback with a reason, coordinator approval of hours |
| F4 Record integrity | Approved records anchored on an EVM test network; tampering is detected |
| F5 Certificates and public verification | Authorized issuance, QR link, public verification, validity and revocation |
| F6 Dashboard and reports | Role-scoped totals and a basic activity summary |

A partner portal, donations, project tracking and notifications are out of scope.

## Planned for the pilot phase

To keep the build realistic, these features are not in the MVP or the v2.0 specifications. Each needs new scope approval and specification updates before it is built.

- **Community needs:** coordinators log a need with the partner's name and publish a linked event. Partners do not get their own login; coordinators enter needs on the partner's behalf.
- **Skills credentials:** competency tags, reflection, and a certificate with a skills list.
- **Safety layer:** safety briefing, emergency contacts and an incident flag.
- **Impact dashboard:** people and communities served, SDG tags and safety counts.

## Trust model

Blockchain here proves that an approved record was not altered afterwards. It does not prove that a student physically took part.

- **What goes on chain:** only an opaque record key and a salted fingerprint of the record. No names, emails, student numbers or attendance times.
- **What the public sees:** one clear result (Verified, Pending, Mismatch, Not Found or Unavailable), the certificate number, the term and approved hours.
- **What strengthens participation evidence:** rotating QR codes, server time, registration checks and coordinator review. Partner confirmation is planned for the pilot phase.
- **What happens if the network is down:** approvals still save locally and the proof shows as pending, never as verified.
- **Status of certificates:** supplementary records, not official completion records, unless the school adopts them.
- **Planned safety features** will support coordinators and do not replace emergency services or the school's own safety protocol.

## Impact and measures

No baseline exists yet, so the pilot measures first and the school sets targets with the team. Indicators below are proposed, not promised. Those marked "Pilot features" can only be measured once the planned features exist.

| Area | Indicator | How it is measured | Needs |
|---|---|---|---|
| Education | Share of approved records with a skill tag and reflection | Database count per term | Pilot features |
| Education | Certificates issued that list skills | Certificate records | Pilot features |
| Community | Events linked to a documented community need | Event records | Pilot features |
| Community | Communities and partners served | Impact dashboard | Pilot features |
| Safety | Time for a coordinator to confirm who is on site | Timed demo against a paper sheet | MVP |
| Safety | Incidents flagged and time to flag them | Incident records | Pilot features |
| Trust | Verification checks completed by outside parties | Verify page logs | MVP |
| Trust | Altered records caught | Tamper test result (Mismatch) | MVP |

## Plan

The hackathon build runs in two days, and a pilot needs school approval first.

1. **Day 1 morning:** project skeleton, accounts, roles, section scope and seed data.
2. **Day 1 midday:** events and one-time registration.
3. **Day 1 afternoon:** rotating QR, check-in and check-out, and live roster.
4. **Day 1 evening:** coordinator approval, record versioning and audit log.
5. **Day 2 morning:** smart contract on a test network, canonical record fingerprint and proof queue.
6. **Day 2 midday (feature freeze):** certificates, public verification and the tamper demo.
7. **Day 2 afternoon:** dashboard, polish, regression tests and a full rehearsal.

After the hackathon: school and data-privacy review, scope approval for the pilot-phase features, a pilot with one section and one partner, then measurement against the indicators above.

## Risks and open decisions

| Risk | Mitigation |
|---|---|
| Test network is slow or down during the demo | Fallback RPC provider and one preconfirmed record; say plainly when a proof is pending |
| People read "blockchain verified" as "participation verified" | Disclaimer on every verification page and certificate |
| Scope grows past two days | Community, skills and safety features moved to the pilot phase; feature freeze on day 2 midday |
| QR codes shared by screenshot | 30-second expiry, server clock, registration check, coordinator review |
| Student data exposed | Synthetic data only; no personal data on chain; privacy review before any pilot |

Open decisions for the school: how service hours are rounded, the minimum hours for a certificate, who may sign certificates, whether names appear on the public page, and how CivicLedger certificates relate to official NSTP records. The proposal assumes floor-to-the-minute rounding, a placeholder hours threshold and masked names until the school decides.
