# Invoice list design QA

- Source: the owner-supplied `invoices.html` reference; its implementation code was not reused.
- Compared state: expanded filters at a 1728 × 1117 desktop viewport, plus the iPhone 15 narrow layout.
- Inspected rendering: reference and implementation screenshots were opened at original resolution and compared side by side.
- Matched behavior: summary presets, search, lifecycle/payment/due filters, date presets, active-filter removal, complete sort menu, page size, cursor navigation, and row actions.
- Annotation verification: the shared page header has no bottom divider, and summary-card filters update the applied-filter count without opening the detailed filter panel.
- Responsive result: the page has no viewport overflow; dense tables and segmented options remain contained and independently scrollable where needed.
- Intentional omissions: Export, bulk selection/actions, and numbered pagination do not exist in Invumo. The implementation retains authoritative cursor navigation and available Invoice actions.
- Project adaptation: existing Invumo shell, semantic tokens, shared table/status/form components, authorization, Laravel translations, and Inertia query behavior remain authoritative.
- Verification: focused browser tests passed on desktop and iPhone 15 with no JavaScript or accessibility errors; focused HTTP coverage passed for filters, summaries, localization, and every cursor-sort page.

Final result: passed
