# Invumo design QA

Status: Canonical inspected-reference record
Last updated: 2026-09-03

This is the single design-QA log for Invumo. Reference comparisons, rendered implementation evidence, findings, and final results belong here rather than in a repository-root log.

## Evidence contract

- The machine-readable registry is [`tests/Browser/design-qa-reviews.json`](../../tests/Browser/design-qa-reviews.json).
- Every source and implementation artifact cited below is stored in [`design-qa-evidence/`](design-qa-evidence/) and bound to its exact bytes by SHA-256 in the registry.
- `npm run design-qa:check` rejects a duplicate root log, missing or extra evidence artifact, stale hash, missing review metadata, broken document anchor, unlinked artifact, or machine-local `.codex`/browser-output path.
- Source HTML is retained only as owner-supplied visual context. It is not application source and was not copied into Invumo implementation code.
- These local reference-comparison records do not replace the pinned-runner [canonical visual snapshot baseline contract](visual-snapshot-baselines.md). A canonical `.snap` update still requires its separate runner-produced expected/actual/difference review.

<a id="invoice-operational-list"></a>

## Invoice operational-list review

### Durable evidence

- Owner-supplied source: [reference HTML](design-qa-evidence/invoice-list-reference.html), [desktop rendering](design-qa-evidence/invoice-list-reference.png), and [390 × 844 rendering](design-qa-evidence/invoice-list-reference-mobile.png).
- Invumo rendering: [expanded desktop filters](design-qa-evidence/invoice-list-desktop.png) and [narrow filters](design-qa-evidence/invoice-list-mobile.png).
- Registry review: `invoice-operational-list-2026-09-03`.

### Comparison and findings

- Compared state: expanded filters at a 1728 × 1117 desktop viewport plus the iPhone 15 narrow layout.
- Matched behavior: summary presets, search, lifecycle/payment/due filters, date presets, active-filter removal, complete sort menu, page size, cursor navigation, and row actions.
- The shared page header has no bottom divider, and summary-card filters update the applied-filter count without opening the detailed filter panel.
- The implementation has no viewport overflow; dense tables and segmented options remain contained and independently scrollable where needed.
- Export, bulk selection/actions, and numbered pagination are intentional omissions. Invumo retains authoritative cursor navigation and available Invoice actions.
- Existing Invumo shell, semantic tokens, shared table/status/form components, authorization, Laravel translations, and Inertia query behavior remain authoritative.
- No actionable P0, P1, or P2 difference remains.

### Verification

- `InvoiceListTest` passed two desktop/mobile browser tests and 24 assertions on 2026-09-03.
- Page identity, meaningful content, framework-overlay absence, JavaScript health, accessibility, viewport containment, filter interaction, and screenshot evidence passed.

Final result: passed

<a id="document-line-editor"></a>

## Invoice, Quote, and recurring line-editor review

### Durable source evidence

- [Owner-approved simulation](design-qa-evidence/line-editor-approved-simulation.png).
- [Structural table reference](design-qa-evidence/line-editor-structural-reference.png).
- [Interaction reference HTML](design-qa-evidence/line-editor-interaction-reference.html) and its [1440 × 1100 rendering](design-qa-evidence/line-editor-interaction-reference.png).
- [Quiet-control reference](design-qa-evidence/line-editor-quiet-controls-reference.png).
- [Blocking empty-result reference](design-qa-evidence/line-editor-no-match-reference.png).
- [Owner-reported incorrect desktop fallback](design-qa-evidence/line-editor-incorrect-desktop-fallback.png).

The references establish content and direction. Invumo's existing components, tokens, type, and supported behavior remain authoritative.

### Durable implementation evidence

- [Desktop Build view](design-qa-evidence/line-editor-build.png).
- [Current Invoice desktop](design-qa-evidence/line-editor-current-invoice.png).
- [Romanian mobile line editor](design-qa-evidence/line-editor-mobile.png).
- [Custom-product choice](design-qa-evidence/line-editor-custom-product-choice.png).
- [Discard-changes confirmation](design-qa-evidence/line-editor-discard-changes-dialog.png).
- Registry review: `document-line-editor-2026-09-03`.

The desktop Build capture uses a 1440 × 1100 viewport; the current-Invoice capture uses 1440 × 817; the mobile capture uses the iPhone 15 preset at 390 × 844. The approved simulation is 1480 × 1062 and was evaluated at normalized scale rather than as a pixel-for-pixel source.

### Comparison evidence

The target table, quiet-control crop, blocking empty-result reference, and desktop implementation captures were inspected at original resolution. The full views verify page hierarchy, the Customer/details-to-balance proportion, balance contents, lime Record payment action, table position and density, add-row control, and totals. The focused line-table pass verifies all eleven columns, centered line number, product/service name with description beneath, borderless-at-rest completed values, right-aligned numeric controls, one tax label, line total, and delete action.

The custom-product comparison verifies that a no-match search is a dismissible choice rather than a persistent warning: neutral copy is followed by the complete **Use “…” as a custom product or service** action. The discard-changes capture verifies the shared modal hierarchy, consequence copy, safe **Keep editing** exit, and destructive final confirmation without confusing the action with Invoice cancellation.

The mobile capture verifies the intentional responsive adaptation: a compact summary expands into one two-column editor, retaining the complete field set without an eleven-column horizontal-scroll workflow.

### Findings

- No actionable P0, P1, or P2 difference remains.
- Typography uses the existing Invumo body/data/display families and hierarchy. Table metadata, financial values, and labels retain approved optical weights and readable contrast.
- The Customer plus Invoice-details stack and ordinary non-sticky balance card are equal height. Rebalanced table tracks prioritize Product/description, item price, and line total while fixed/short fields remain legible and the complete table avoids horizontal overflow.
- Neutral page, white sections, dark balance surface, lime money action, dividers, focus rings, and destructive action use existing semantic tokens. Redundant document-default and bank-account captions are absent.
- The supported labels and values match the requested structure. No reference-only capability was added.
- Add product or service appends one blank row with explicit controls while required values are incomplete. Product or Service searches the active catalogue while accepting manual text; a selected result applies detached defaults without a modal. No-match custom entry supports mouse, `Enter`, `Tab`, `Escape`, and outside-click dismissal. Complete wide rows restore control affordances on hover/focus, and mobile cards keep explicit controls. Document-default tax changes update inherited lines while explicit overrides remain unchanged.

### Comparison history

1. The earlier build retained a picker-first, read-only product field. The owner clarified that Add product or service must append a blank row with directly editable name and description.
2. Manual name and description values were persisted through the existing document-line description contract; catalog-linked lines remain readable and detach safely when renamed.
3. A Romanian mobile pass exposed an ambiguous text locator. The field received a stable row-specific test hook without changing the user-facing layout.
4. The first Quote accessibility pass found insufficient contrast on a 10px document-default caption. It was corrected to the existing 12px muted-foreground treatment.
5. Desktop and mobile captures were compared again after those fixes, with no remaining P0/P1/P2 finding.
6. An owner-reported normal-desktop capture still showed the compact card because the switch used a 1536px global viewport query. It now observes the editor container and selects the table at 1120px of available width.
7. Owner annotations then removed duplicate/default tax provenance and helper captions, centered the line identifier, aligned numeric fields, rebalanced columns, and applied the lime money treatment to Record payment.
8. Completed wide rows now read as table data until hovered or focused; blank rows stay explicitly editable. Product/description, item price, and line total receive the flexible allocations.
9. The blocking no-results surface became neutral copy plus an explicit, dismissible custom-product choice that preserves typed text.
10. Saved editors gained **Discard changes** and new editors **Clear draft**, both restoring the complete relevant client baseline after confirmation without a server mutation.

### Primary interactions verified

- Append and edit blank lines on Invoice, Quote, and recurring Drafts.
- Search/select a catalogue Product/Service directly in a new row or retain a manual name.
- Clear an unsaved creation form or discard persisted-record edits and restore its complete baseline.
- Save/reload independently entered product/service names and descriptions.
- Recalculate subtotal, tax, total, paid, and outstanding values.
- Change a document tax default while preserving explicit line overrides.
- Use the compact expanded editor on Romanian mobile.
- Check JavaScript errors, accessibility, and document-width containment.

### Verification

- `InvoiceDraftTest` passed five browser tests and 104 assertions on 2026-09-03.
- The focused Quote custom-entry/discard journey passed one browser test and 44 assertions on 2026-09-03.
- Page identity, meaningful content, framework-overlay absence, JavaScript health, accessibility, screenshot evidence, and the target interactions passed.

Final result: passed
