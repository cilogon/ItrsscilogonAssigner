---
title: ItrsscilogonAssigner Documentation - Plan
type: docs
date: 2026-10-01
topic: plugin-documentation
artifact_contract: ce-unified-plan/v1
product_contract_source: ce-brainstorm
execution: code
---

# ItrsscilogonAssigner Documentation - Plan

## Goal Capsule

- **Objective:** A newer CILogon staff member can learn what the ItrsscilogonAssigner plugin does in the ITRSS COmanage Registry deployment, how it is configured, how to tell it is working, how it fails, and why it behaves as it does, without reading the PHP or asking the maintainer.
- **Means:** A short README at the repository root that links to one reference page under `docs/` (KTD1).
- **Product authority:** The repository maintainer confirmed this scope on 2026-10-01. The Product Contract outranks the Planning Contract, and the code in `Model/ItrsscilogonAssigner.php` is the authority on behaviour. Documentation for the other ITRSS plugins and the ITRSS solution architecture document are separate work and not active scope here.
- **Execution profile:** Documentation only. No PHP, configuration, or test changes.
- **Stop conditions:** Stop and report if the code contradicts a statement the Product Contract requires the page to make, or if writing the page would need a fact that is neither in the code nor under Maintainer-supplied facts.
- **Who finishes:** The implementing agent writes and verifies both files. The maintainer reviews and merges the pull request.
- **Open blockers:** None.

---

## Product Contract

### Summary

Add a short README that introduces the plugin and links to a single reference page in `docs/`. The page covers the plugin's role, its inputs, the eppn scope mapping, Registry configuration, how to verify it, failure handling, and the design reasons behind it. The reasons come from the maintainer and are recorded below.

### Problem Frame

The repository has no README and no documentation. Newer CILogon staff who need to understand the plugin either read `Model/ItrsscilogonAssigner.php` or ask the maintainer. Some of what they need is not in the code at all: why certain campus scopes get extra eppn variants, why extra identifiers skip provisioning, and how many OIDC sub values a correctly handled person should have. Those answers exist only in the maintainer's head.

The maintainer is documenting each ITRSS plugin in parallel and will later write an overview of the whole ITRSS solution architecture. Each plugin's documentation needs to stand alone now and be easy to link to later.

### Key Decisions

- **A short README plus a `docs/` folder.** This matches the layout the maintainer is using for the other ITRSS plugin repositories. (session-settled: user-directed — chosen over a full README only, a policy document in `cilogon/itrss-policies` linked from a one-line README, and leaving the pattern undecided: consistency with the parallel plugin docs.) Governs R1, R2.
- **One reference page with a short troubleshooting section.** (session-settled: user-directed — chosen over separate pages per purpose and a guide organised around staff questions: the plugin is small enough for one page, and that is least to maintain.) Governs R2, R12.
- **The health check is a table of expected OIDC sub counts by eppn scope, plus a note about records created before the fix.** (session-settled: user-directed — chosen over giving counts without the history and treating three values as current behaviour: staff will still see older records with three values and need to know why.) Governs R9, R10.
- **Compound Engineering artifacts stay in `docs/`.** Staff documentation and `docs/plans/` will share the folder. (session-settled: user-directed — chosen over moving CE artifacts by setting `docs_root` and keeping them out of the repository.) Governs R2.

### Requirements

**Entry point**

- R1. The repository root has a short README that names the plugin, says in one or two sentences what it does for the ITRSS deployment, and links to the reference page.
- R2. All other documentation is on a single reference page under `docs/`, which a reader can find without confusing it with the CE working files in `docs/plans/`.

**Role in the deployment**

- R3. The page explains that the plugin is the identifier assigner behind the ITRSS CO's "CILogon user name" Identifier Assignment, of type OIDC sub, and that it gets CILogon user identifiers from the CILogon OA4MP dbService.
- R4. The page has a clearly marked place where the future ITRSS solution architecture document can be linked, without describing the wider architecture itself.

**Behaviour**

- R5. The page states what the plugin needs from a CO Person record (an eppn Identifier, an official EmailAddress, and an official Name with both given and family names) and that it runs only for CO Person records.
- R6. The page gives the eppn scope mapping as a table: `missouri.edu` sends the person's eppn plus `@umsystem.edu` and `@mizzou.edu` variants; `umh.edu`, `umkc.edu`, `mst.edu` and `umsl.edu` send the eppn plus an `@umsystem.edu` variant; any other scope sends only the eppn.
- R7. The page explains that each eppn is sent to the dbService with the UM System IdP entity ID and the person's name and email address. The first identifier returned becomes the assigned value, and any others are added to the person as extra OIDC sub Identifiers.

**Configuration and verification**

- R8. The page describes the Registry configuration that uses the plugin, as set out under Maintainer-supplied facts. It notes that the IdP entity ID and the OA4MP URL are fixed in the code, so changing either one means changing the code.
- R9. The page tells staff how to confirm the plugin is working: each new CO Person record has the number of OIDC sub values given by the R6 table, and each value follows the usual CILogon username (sub claim) pattern.
- R10. The page notes that records created before the 2026-09-17 fix (commit `fcfea53`) have three OIDC sub values for every scope except `umh.edu` and `umkc.edu`, which the bug did not affect and which have two.

**Failures and troubleshooting**

- R11. The page lists each failure the plugin reports (no eppn, no official email address, no official name, a dbService error for a given eppn, and no identifiers returned), with what each means and what staff should check.
- R12. A short troubleshooting section answers the question of why a person has one, two or three OIDC sub values, citing R6 and R10.

**Design rationale**

- R13. The page records the design reasons listed under Maintainer-supplied facts, presented as the maintainer's explanation rather than inferred from the code.

### Maintainer-supplied facts

The maintainer provided these facts during the brainstorm. They are not in the code, so the page must use them as given and not invent alternatives.

- **Registry configuration:** The ITRSS CO is the only CO. Only one Identifier Assignment uses the plugin. Its Description is "CILogon user name" and its type is OIDC sub.
- **Why the `@umsystem.edu` variant:** University central IT said that eventually every record will change and all SAML IdP assertions will use eppns with the `umsystem.edu` scope. `mizzou.edu` is an older pattern that may or may not still be in use.
- **Why one fixed IdP:** The plugin is specific to ITRSS, which is part of the University of Missouri System.
- **Why extra identifiers:** One identifier is the primary. The others will probably never be used. They were created because, when the plugin was written, it was not known how predictably an eppn scope maps to the value the SAML IdP actually asserts.
- **Why extra identifiers skip provisioning:** It makes the saves faster. The plugin only runs while a new CO Person record is being built, through enrollment or a Pipeline. Registry runs provisioning once that work is complete, so the extra identifiers get provisioned then.
- **Why the IdP and OA4MP URL are hardcoded:** The plugin was written in a hurry. This should be fixed at some point, but not now.

### Acceptance Examples

- AE1. **Covers R6, R9.** **Given** a new person with eppn `jdoe@missouri.edu`, **when** a staff member checks the page, **then** they can tell the record should have three OIDC sub values.
- AE2. **Covers R6, R9.** **Given** a new person with eppn `jdoe@umkc.edu`, **then** the page tells the reader to expect two OIDC sub values.
- AE3. **Covers R6, R9.** **Given** a new person with eppn `jdoe@umsystem.edu`, **then** the page tells the reader to expect one OIDC sub value.
- AE4. **Covers R10, R12.** **Given** a person with a `umsystem.edu` eppn whose record was created before the fix and has three OIDC sub values (a new record with that scope has one), **then** the troubleshooting section explains why without the reader suspecting a current fault.
- AE5. **Covers R11.** **Given** an enrollment that fails with "No official Name found", **then** the page tells staff to check that the CO Person has an official Name with both given and family names.

### Success Criteria

- The maintainer reviews the page and finds no factual errors against the code or the facts above.
- Using only the README and the page, a reader who has not seen the PHP can work out AE1 through AE5.

### Scope Boundaries

- The ITRSS solution architecture document. It will be written later and will link to this page.
- Documentation for the other ITRSS plugins.
- Installing the plugin into the Registry image. CILogon staff already know how to do this, as with all Registry plugins.
- Code changes, including making the IdP entity ID and OA4MP URL configurable, and cleaning up the extra OIDC sub values on records created before the fix.
- Moving CE artifacts out of `docs/`.

<!-- ce-section: work-relationships -->
### How This Work Fits Together

This plan covers documentation for the ItrsscilogonAssigner plugin only. The broader effort below is the maintainer's current understanding, not a committed roadmap.

- Documentation for each other ITRSS COmanage Registry plugin: can proceed independently of this plan; shares the README-plus-`docs/` layout.
- The ITRSS solution architecture document: depends on the per-plugin docs, and links to this page at the place R4 provides.

### Sources / Research

- `Model/ItrsscilogonAssigner.php`: all plugin behaviour, including the scope mapping, the dbService call, error handling, and how extra identifiers are saved.
- `Lib/lang.php`: the text of each error message.
- Commit `fcfea53`: the 2026-09-17 fix to the scope comparison; before it, every scope except `umh.edu` and `umkc.edu` received both the `@umsystem.edu` and `@mizzou.edu` variants.
- The sibling EntraSource plugin README is an example of a fuller plugin README in the same Registry deployment.

---

## Planning Contract

**Product Contract preservation:** changed: R10, AE4 -- corrected the history of records created before the fix to match commit `fcfea53` (`umh.edu` and `umkc.edu` were not affected). The settled health-check decision is unchanged. The two Deferred to Planning questions were resolved as KTD1 and KTD2 and removed from the Product Contract.

### Key Technical Decisions

- KTD1. **The reference page is `docs/README.md`.** GitHub renders a folder's README when someone opens the folder, so a reader who opens `docs/` lands on the page instead of having to tell it apart from `docs/plans/`. The same name will also work in every other plugin repository that uses the README-plus-`docs/` layout. Implements R2.
- KTD2. **Quote error messages exactly as Registry shows them.** Staff will be searching for the text they saw on screen, so the page reproduces each string from `Lib/lang.php`. For the dbService error, the page shows `%1$s` as the eppn that failed. Implements R11.
- KTD3. **Describe behaviour in staff terms, not code terms.** The page names CO Person records, Identifiers, Identifier types, and the dbService. It does not name PHP classes, methods, or variables, except where a staff member would need the term to talk to a developer. Facts are checked against `Model/ItrsscilogonAssigner.php`, but the page is written for someone who will not read it.

### Assumptions

- The staff audience already knows general COmanage Registry terms (CO, CO Person, Identifier, Identifier Assignment, Enrollment Flow, Pipeline, provisioning) and how plugins are installed, so the page does not define them.
- The R4 link point can be a short "Related documentation" section saying that the ITRSS solution architecture overview will be linked there once it exists. No placeholder URL is invented.

---

## Implementation Units

### U1. Reference page

**Goal:** Write the single reference page that answers every staff question in the Product Contract.

**Requirements:** R2 through R13; AE1 through AE5. Governed by the README-plus-`docs/`, one-page, and per-scope health-check Key Decisions (R2, R9, R10, R12).

**Dependencies:** None.

**Files:**
- Create `docs/README.md`

**Approach:**
1. Open with what the plugin is and where it fits (R3), followed by the related-documentation section that the architecture overview will link to later (R4).
2. Requirements on the CO Person record (R5).
3. How it works: the scope table (R6), then the dbService exchange, the primary identifier, and the extra identifiers (R7).
4. Configuration in Registry and the hardcoded values (R8).
5. Checking that it works: per-scope counts and the sub pattern (R9), plus the note about records created before the fix (R10).
6. Errors: a table of each exact message, what it means, and what to check (R11, KTD2).
7. Troubleshooting: why a person has one, two, or three OIDC sub values (R12).
8. Design rationale: one short item for each Maintainer-supplied fact (R13).

Follow KTD3 throughout.

**Patterns to follow:** The sibling EntraSource plugin README in the same Registry deployment is an example of plain heading-led plugin documentation. Use the Product Contract's Maintainer-supplied facts word for word in substance.

**Test expectation:** none. This is a documentation-only change and the repository has no test suite. U1 is checked by the verification below.

**Verification:**
- Every statement about behaviour matches `Model/ItrsscilogonAssigner.php`, and every quoted message matches `Lib/lang.php`.
- A reader using only the page can work out AE1 through AE5.
- Every Maintainer-supplied fact appears on the page, and no design reason appears that is not among them.

### U2. Root README

**Goal:** Give the repository a short front page that sends readers to the reference page.

**Requirements:** R1.

**Dependencies:** U1.

**Files:**
- Create `README.md`

**Approach:** Use a title naming the plugin, one or two sentences on what it does for the ITRSS deployment, and a relative link to `docs/README.md`. Nothing else, so the two files cannot drift apart.

**Test expectation:** none. This is a documentation-only change.

**Verification:** The relative link resolves to `docs/README.md`, and the README's description agrees with the opening of the reference page.

---

## Verification Contract

The repository has no test suite, linter, or formatter (`Test/` contains only placeholders), so verification consists of review checks:

- **Fact check:** compare every behavioural statement and quoted message on `docs/README.md` against `Model/ItrsscilogonAssigner.php` and `Lib/lang.php`.
- **Acceptance check:** walk through AE1 through AE5 using only `README.md` and `docs/README.md`.
- **Link check:** every relative link in both files resolves to a file in the repository.
- **Scope check:** the diff adds only `README.md`, `docs/README.md`, and this plan, and changes no PHP.

---

## Definition of Done

- U1 and U2 are complete and their verification passes.
- All four checks in the Verification Contract pass.
- No placeholder text, TODO, or invented URL remains in either file. The R4 section explains in words that the architecture overview is still to come.
- No abandoned drafts or extra documentation files are left in the diff.
