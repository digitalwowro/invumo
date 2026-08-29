# Canonical Visual Snapshot Baselines

Status: Approved quality contract  
Last updated: 2026-08-25

Canonical PNG snapshots are byte-level references owned by the pinned GitHub Ubuntu runner. Their base64 storage is intentionally machine-oriented, so a changed snapshot is not review evidence by itself.

## Required evidence

Every current baseline has one hash-bound entry in `tests/Browser/visual-baseline-reviews.json`. A baseline update must record:

- the exact protected screen and viewport/locale;
- the application-code commit or change that caused the rendering difference;
- the intended visual differences;
- confirmation that the rendered result was viewed;
- the GitHub run/artifact or other precise inspection source;
- the SHA-256 hash of the decoded canonical PNG.

The complete quality gate rejects a missing entry, stale hash, untracked snapshot, duplicate entry, or incomplete inspection record. A commit message alone is insufficient.

## Refresh workflow

1. Let the pinned GitHub runner produce expected, actual, and difference images. Do not create a canonical baseline from a local operating system.
2. Download and view all three images at original resolution. Check the complete protected surface, both launch languages when affected, narrow behavior when affected, clipping/overflow, state meaning, and accessibility-relevant visibility.
3. Match every visible difference to an intended source change. If any difference is unexplained, fix the regression instead of refreshing the baseline.
4. Adopt the exact reviewed GitHub `actual` PNG only after the rendering is accepted, using `node scripts/adopt-visual-baseline.mjs <reviewed-actual.png> <snapshot.snap>` so the PNG signature is checked and its decoded hash is reported.
5. Update the matching evidence entry with its decoded PNG hash and review facts.
6. Run `npm run visual-snapshots:check` plus the directly affected browser tests locally.
7. At phase closeout, push and manually dispatch the phase quality gate. Require the runner's exact byte comparison to pass; if it fails, inspect the uploaded artifacts and adopt only the reviewed runner-produced actual image before rerunning the phase gate.

Never run snapshot-update mode repeatedly until CI becomes green. Failed comparison artifacts are evidence to inspect, not generated approval.

## Recorded table-layout review

The English and Romanian desktop gallery baselines updated in `903295c` were caused by the shared content-driven table layout in `a989b0d`. The GitHub Ubuntu expected, actual, and difference images from run `32823698233` were inspected before the exact runner-produced actual PNGs were adopted. The visible change was limited to intended operational-table column allocation; the full gallery, both languages, semantic status treatments, actions, containment, and surrounding component states remained correct. Replacement run `32824332431` passed.

## Product-mark review

The English desktop, Romanian desktop, and narrow-navigation gallery baselines were updated from the exact GitHub Ubuntu actual PNGs produced by Phase 11 gate run `33278632381`. The expected, actual, and difference images were viewed at original resolution. The visible difference was confined to replacing the old placeholder with the approved lime code-rendered `INVUMO` mark introduced by `9075ca3`; gallery content, responsive containment, component states, and navigation remained correct.
