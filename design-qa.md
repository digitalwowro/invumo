# Invoice, Quote, and recurring line-editor design QA

## Source visual truth

- Owner-approved simulation: `/home/invumo/.codex/generated_images/01a05bd5-eab0-7911-995d-e1cb4da5081e/exec-011841de-4725-4b9a-9181-faefd18c7440.png`
- Supplied structural reference: `/home/invumo/.codex/attachments/b9fc74d8-ad42-4416-b1ab-4913829e541a/codex-clipboard-15f29615-bd2c-4ca5-968a-d45836f4a4f4.png`
- Supplied interaction reference: `/home/invumo/.codex/attachments/1d057085-8df6-45e8-9ba9-ef32970c4f6e/invoice.html`
- Supplied quiet-control reference: `/home/invumo/.codex/attachments/83d8d8bd-ebda-4cd4-b79f-d663b10fb7c7/codex-clipboard-fda42d61-e0d9-4b66-a4d4-eef3ea0b0b57.png`
- Supplied blocking empty-result reference: `/home/invumo/.codex/attachments/3b0b3702-819f-4fea-adcc-6947826cbc9f/codex-clipboard-d8229692-5805-4e72-9438-0dc18eaa0ed1.png`
- Owner-reported incorrect desktop fallback: `/home/invumo/.codex/attachments/3f7e9825-737a-4723-a440-33c6218ccf47/codex-clipboard-787956ba-5fc6-49d5-ad2d-8d3af7f73523.png`
- Owner annotations on the current 1440 × 817 Invoice Build view: remove duplicate/default tax provenance copy, center the line number, remove the tax-default and bank-account helper captions, make Record payment lime, and rebalance the table columns.
- The references establish content and direction. Invumo's existing components, tokens, type, and supported behavior remain authoritative.

## Rendered implementation evidence

- Desktop: `/home/invumo/invumo/tests/Browser/Screenshots/implementation-build-refined.png`
- Current Invoice desktop: `/home/invumo/invumo/tests/Browser/Screenshots/implementation-invoice-current-refined.png`
- Mobile: `/home/invumo/invumo/tests/Browser/Screenshots/implementation-mobile-line.png`
- Custom-product choice: `/home/invumo/invumo/tests/Browser/Screenshots/implementation-custom-product-choice.png`
- Discard-changes confirmation: `/home/invumo/invumo/tests/Browser/Screenshots/implementation-discard-changes-dialog.png`
- Desktop viewport and screenshot: 1440 × 1100 CSS px, device scale 1, 1440 × 1100 image pixels.
- Current Invoice viewport and screenshot: 1440 × 817 CSS px, device scale 1, 1440 × 817 image pixels.
- Approved simulation: 1480 × 1062 image pixels. It has the same 1.393 aspect ratio as the implementation and was evaluated at normalized scale rather than as a pixel-for-pixel source.
- Mobile viewport and screenshot: iPhone 15 preset, 390 × 844 image pixels.
- State: English new Invoice with one complete document-default-tax line on desktop; Romanian new Invoice with one expanded directly editable line on mobile.

## Comparison evidence

The supplied target table, quiet-control crop, blocking empty-result reference, and current desktop screenshots were opened in comparison inputs. The full views verified page hierarchy, the Customer/details-to-balance proportion, the balance contents, lime Record payment action, table position, table density, add-row control, and totals. The focused line-table pass verified all eleven columns, centered line number, product/service name with its description beneath, borderless-at-rest completed values, right-aligned numeric controls, single tax label, line total, and delete action. The custom-product comparison verified that a no-match search is a dismissible choice rather than a persistent warning: neutral copy is followed by the full, wrapped **Use “…” as a custom product or service** action. The discard-changes capture verifies the shared modal hierarchy, clear consequence copy, safe **Keep editing** exit, and destructive final confirmation without confusing the action with Invoice cancellation.

The mobile screenshot was inspected in the same comparison input. It verifies the intentional responsive adaptation: a compact summary expands into one two-column editor, keeping the complete field set without an 11-column horizontal-scroll workflow.

## Findings

- No actionable P0, P1, or P2 difference remains.
- Fonts and typography: the implementation uses the existing Invumo body/data/display families and hierarchy. Table metadata, financial values, and labels retain the approved optical weights and readable contrast.
- Spacing and layout rhythm: the Customer plus Invoice-details stack and the ordinary, non-sticky balance card are equal height. Rebalanced table tracks prioritize Product/description, item price, and line total while keeping fixed/short fields legible and the complete table free of horizontal overflow. The add-row control and totals retain the approved vertical sequence.
- Colors and tokens: neutral page, white sections, dark balance surface, lime money action, dividers, focus rings, and destructive action use existing semantic tokens. Redundant document-default and bank-account provenance captions are absent.
- Image and asset quality: the target and implementation contain no product imagery or decorative raster assets. Existing Lucide icons remain consistent; no fake SVG, CSS illustration, or placeholder asset was introduced.
- Copy and content: the supported labels and values match the requested structure. No reference-only capability was added.
- Interaction and accessibility: Add product or service appends one blank horizontal row with visible controls, which remain visible while its essential amounts are incomplete. The Product or Service field searches the active catalogue while accepting manual text in the same control; choosing a result fills detached defaults directly into the row without a modal. When no match exists, the typed name remains valid and the user may confirm it through the custom-product action or `Enter`; `Tab`, `Escape`, and outside click dismiss the surface without clearing the name. Completed wide rows hide persistent input chrome but restore it on hover/focus, while mobile cards retain explicit controls. Existing tax inheritance follows document-default changes while explicit overrides remain unchanged. Browser checks found no JavaScript, accessibility, or viewport-containment errors in the final desktop/mobile states.

## Comparison history

1. The earlier build retained a picker-first, read-only product field. The owner clarified that the old behavior must be removed: Add product or service now appends a blank row, and the product/service name and description are edited directly in the row.
2. Direct manual name and description values are now persisted and restored through the existing document-line description contract without a migration; existing catalog-linked lines remain readable and detach safely when their name is changed.
3. A Romanian mobile browser pass exposed an ambiguous text locator because the blank summary and field label shared the same copy. The field received a stable row-specific test hook; the user-facing layout did not change.
4. The first final Quote accessibility pass found insufficient contrast on the 10 px document-default caption. It now uses the existing 12 px muted-foreground treatment. The rerun passed accessibility and overflow checks.
5. The final desktop and mobile captures were compared again after those fixes. No P0/P1/P2 finding remained.
6. The owner then supplied a normal-desktop capture that still showed the compact card. The responsive switch had incorrectly used a 1536px global viewport query. It now observes the actual editor container and uses the complete table only when at least 1120px is available, retaining the compact mobile editor below that threshold rather than introducing internal horizontal scrolling.
7. The owner annotated the corrected table at 1440 × 817. The tax selector now presents the inherited rate once without exposing provenance copy, helper captions under Tax default and Bank account are removed, the line identifier is centered, numeric fields align consistently, column tracks are rebalanced, and Record payment uses the shared lime money treatment. Same-viewport current-Invoice and full-table captures were inspected after the supervised browser rerun.
8. The owner approved the reference's quieter completed-row treatment and clarified the three priority tracks. Completed wide rows now read as table data until hovered or focused; blank rows remain explicitly editable. Product/description, item price, and line total receive the largest dynamic allocations. The Product or Service cell now combines manual entry and active-catalogue search, applying selected defaults inline without restoring the removed picker-first flow.
9. The initial no-results surface could not be dismissed conveniently by users entering custom products. It now uses neutral copy and an explicit custom-product choice that preserves the typed name, wraps without truncation, and supports mouse, `Enter`, `Tab`, `Escape`, and outside-click dismissal.
10. Invoice, Quote, and recurring editors now expose **Discard changes** for saved records and **Clear draft** before first save. Both remain disabled while clean, confirm before losing local edits, and restore the complete saved or initial client baseline without a server mutation.

## Primary interactions verified

- Append and edit blank lines on Invoice, Quote, and recurring Drafts.
- Search and select a catalogue Product/Service directly inside a new row, or keep a manual name.
- Clear an unsaved creation form or discard persisted-record edits and restore its complete baseline.
- Save and reload independently entered product/service names and descriptions.
- Recalculate subtotal, tax, total, paid, and outstanding values.
- Change a document tax default and preserve explicit per-line overrides.
- Use the compact expanded editor on Romanian mobile.
- Check JavaScript errors, accessibility, and document-width containment in the supervised browser journeys.

## Follow-up polish

No P3 refinement is required for this handoff.

## Final result

final result: passed
