# Invumo Design System Contract

Status: Approved design contract

Approved visual direction: 2026-08-23

Approved implementation contract: 2026-08-23

Last updated: 2026-08-23

This document is the canonical design contract for Invumo. It defines how the internal application and shared customer-facing presentation foundations are built so that visual and interaction changes propagate through one system rather than through page-specific styling.

The approved visual identity is modern, high-clarity, achromatic application chrome with saturated colour reserved for semantic state. The implementation composition, component API, and enforcement details in this document are approved Phase 1 constraints.

## 1. Authority and precedence

This contract controls:

- design tokens;
- typography, spacing, shape, elevation, and motion;
- shared UI primitives and application components;
- state presentation;
- page composition boundaries;
- responsive and accessibility behavior;
- visual testing and enforcement;
- how the internal application is kept separate from Company branding.

Product behavior, authorization, financial state, and domain vocabulary remain controlled by the product and architecture documents. When a visual example conflicts with this contract, this contract wins. When this contract conflicts with a domain rule, the domain rule wins and this contract must be corrected explicitly.

The owner-supplied HTML is a visual reference only. It must never be imported, copied, converted, or treated as production markup, CSS, component code, navigation scope, or application behavior. Experimental variants inside it are not approved choices.

This approved contract supersedes every earlier visual proposal, simulation, and provisional direction. In particular, the earlier “ledger ink” exploration and every ledger, paper, bookmark, book, rubber-stamp, skeuomorphic, ornamental, or nostalgic accounting motif are retired and must not influence implementation.

The product name is **Invumo**. `Invuma` in the supplied reference files is a source typo and must not appear in application copy, assets, metadata, or code identifiers.

## 2. Contract language

The words **must**, **must not**, **should**, and **may** are intentional:

- **Must / must not** — required for every implementation.
- **Should** — expected unless a documented accessibility, product, or technical constraint requires an exception.
- **May** — an allowed option already covered by the system.

An exception is not a page-level CSS patch. It must be solved at the narrowest reusable token, component, or variant boundary and reviewed against every existing use of that boundary.

## 3. Non-negotiable principles

1. **One system, not individually styled pages.** Pages compose shared components and provide data. They do not invent colours, typography, control treatments, status treatments, radii, shadows, or local component variants.
2. **Saturated colour means semantic state.** The internal application is otherwise achromatic. Interactivity is communicated through fill, border, weight, underline, cursor, focus, and position—not a new hue.
3. **No hard-coded colours in feature code.** Raw colour values exist only in the approved central token definition and the separately controlled outward-brand resolver. React pages, feature components, Blade templates, and tests consume semantic tokens or shared components.
4. **Components own appearance.** A visual change to a shared component must propagate to every use. A page must not override the component to preserve an older appearance.
5. **Component APIs express meaning.** Callers request `variant="danger"`, `status="overdue"`, `density="compact"`, or another approved semantic option. They do not pass arbitrary colour, font, border, radius, or shadow values.
6. **Use the fewest colours that communicate the truth.** v1 has exactly three saturated semantic hues: lime, red, and amber. There is no fourth saturated hue and no internal brand-primary hue.
7. **Financial information is visually precise.** Numeric content uses the approved monospaced face and tabular figures. Currency, quantity, date, and identifier columns align consistently.
8. **Accessibility is part of the component contract.** Keyboard behavior, focus visibility, labels, contrast, touch targets, responsive behavior, loading, validation, empty, and error states are not page-specific finishing work.
9. **Modern means restrained and direct.** No old-ledger, paper, bookmark, book, rubber-stamp, skeuomorphic, ornamental, or nostalgic accounting motifs. Status badges are crisp digital UI elements, not literal stamps.
10. **Light mode only in v1.** The near-black sidebar is application chrome, not a dark-mode theme.

## 4. Scope and branding boundary

### 4.1 Internal application

This complete contract applies to every authenticated React/Inertia screen, including authentication, onboarding, settings, operational lists, editors, detail screens, dashboards, audit views, dialogs, sheets, and error states.

No Company may theme the internal application. The active Company name must always be clear, but its logo or brand colour must not alter navigation, buttons, links, focus, status, charts, or other application chrome.

### 4.2 Public documents, PDFs, and email

Customer-facing quote/invoice pages, PDFs, and transactional email use shared outward-facing templates and presentation components. They must not be styled separately per page or per document type.

They reuse:

- Atkinson Hyperlegible typography; Phase 5's selected PDF renderer embeds the pinned source-owned Next and Mono font files and passed the Romanian/multi-page compatibility proof;
- the numeric alignment and formatting rules;
- the same spacing and hierarchy discipline;
- shared Company-brand validation and contrast resolution;
- shared document composition rather than one-off Quote and Invoice templates.

They differ intentionally:

- the validated Company primary brand colour may provide restrained accents;
- the internal status colour system and status badges do not appear in generated PDFs;
- email-client limitations may require resolved inline styles, but those values come from the shared outward-theme resolver rather than literals in individual templates.

The internal application tokens and Company outward-brand tokens are separate namespaces and must never be merged.

### 4.3 Logo boundary

This logo contract is the separately approved brand decision recorded as durable decision D-132; it is not derived from the Phase 1 typography system or any Phase 11 feature batch. Archivo is permitted only inside the product mark and does not become a third application-content typeface.

The approved Invumo product mark is the uppercase `INVUMO` word set in Archivo 900 inside a fixed lime chip. The letters are always ink `#121418`, the chip is always lime `#A3E635`, tracking is fixed at `0.05em`, and the restrained corner radius must never become a pill. The mark is never split, recoloured, inverted, or set in the product interface typeface.

Visible application surfaces render the mark through the shared code-owned `AppLogo` component and the source-owned Archivo 900 font. Exact logo colours live only in dedicated semantic tokens in the global theme; Company branding and application state tokens never alter them. The sidebar uses the 14px size, public headers use the 18px size, and authentication uses the 29px size. Below the 11px wordmark floor, use the supplied `I` tile instead of shrinking the word.

Supplied raster assets are reserved for browser and operating-system surfaces that require files, including the native 16px and 32px favicons and 180px Apple touch icon. The code-owned mark remains authoritative for normal application rendering.

## 5. System architecture

The implementation has four visual layers:

```text
Semantic design tokens
        ↓
Source-owned shadcn/ui primitives
        ↓
Invumo application components and patterns
        ↓
Thin pages that compose components and bind data/actions
```

### 5.1 Semantic tokens

Tailwind CSS 4 exposes semantic utilities backed by CSS custom properties. The global theme file is the only internal-UI source of raw colour values. Components consume names such as `background`, `foreground`, `border`, `primary`, `danger`, or `status-paid`; they never consume a palette colour by number.

Do not use:

- Tailwind palette utilities such as `bg-red-500`, `text-blue-600`, or `border-zinc-200`;
- arbitrary colour utilities such as `bg-[#14181C]`;
- inline `style` colour values;
- JavaScript/TypeScript colour constants inside pages or feature components;
- copied colour literals in chart configuration;
- a second internal theme file.

### 5.2 Source-owned shadcn/ui primitives

shadcn/ui supplies source-owned primitives rather than a runtime visual framework. The project customizes each primitive once to consume Invumo tokens and contract states.

The primitive layer owns controls such as Button, Input, Select, Checkbox, Badge, Dialog, AlertDialog, Sheet, DropdownMenu, Tooltip, Table, Tabs, Pagination, Skeleton, Alert, and Empty. Upstream shadcn updates must be reviewed and merged; they must not overwrite Invumo tokens or component behavior blindly.

Feature pages must not restyle shadcn primitives. If a primitive needs a new behavior or semantic variant, add it centrally and verify all uses.

### 5.3 Invumo application components

Application components give domain meaning to primitives. Examples include `PageHeader`, `MetricStrip`, `OperationalTable`, `DocumentStatus`, `MoneyValue`, `CustomerCombobox`, `FormSection`, `ActivityTimeline`, `DestructiveActionDialog`, and `DocumentEditorShell`.

Domain state presentation is centralized here. A page must not map `overdue` to red, construct a status badge, format a monetary value, or decide a destructive confirmation treatment independently.

### 5.4 Pages

Pages may:

- select and order approved components;
- provide data, translations, actions, routes, and permissions;
- choose documented component variants;
- compose responsive regions through approved layout primitives.

Pages must not:

- declare raw colours, font sizes, font families, font weights, radii, shadows, border treatments, or focus styles;
- recreate an existing component with local JSX;
- use `className` to change a shared component's visual appearance;
- invent a local badge, alert, empty state, table toolbar, form field, modal, or page header;
- add local media queries for behavior that belongs to a shared component;
- copy a component to avoid changing its other uses.

Layout-only utilities may be used through shared `Stack`, `Inline`, `Grid`, `Cluster`, and responsive-shell primitives. If an ordinary page repeatedly needs the same composition, promote it to an application pattern.

## 6. Colour tokens

The values below are the complete internal v1 palette. They are documented here for review and stored once in the implementation's global token definition. New raw values require an explicit design-contract change.

### 6.1 Content and neutral tokens

| Semantic token      | Value     | Required use                                   |
| ------------------- | --------- | ---------------------------------------------- |
| `background`        | `#FFFFFF` | Main content and unraised surfaces             |
| `page`              | `#F7F8FA` | Page canvas behind bounded surfaces            |
| `surface-subtle`    | `#FAFBFC` | Table headers and row hover                    |
| `surface-inset`     | `#F4F6F8` | Disabled/inherited controls and ghost hover    |
| `border`            | `#DEE3E9` | Controls and bounded surfaces                  |
| `border-strong`     | `#C3CBD3` | Quiet statuses and unchecked controls          |
| `divider`           | `#E7EBF0` | Section boundaries                             |
| `rule`              | `#EEF1F5` | Table rows and chart grids                     |
| `foreground`        | `#14181C` | Primary text and ink fills                     |
| `foreground-mid`    | `#4A5361` | Field labels and secondary controls            |
| `foreground-muted`  | `#5B6470` | Supporting text and captions                   |
| `foreground-subtle` | `#8A929E` | Placeholders and disabled/decorative text only |
| `selection`         | `#EEF1F5` | Selected rows and neutral selected regions     |
| `row-affordance`    | `#6E7783` | Persistent row-navigation chevron              |

`foreground-subtle` must not be used for normal body copy because it does not meet normal-text contrast on white.

### 6.2 Sidebar tokens

| Semantic token              | Value     |
| --------------------------- | --------- |
| `sidebar-background`        | `#121418` |
| `sidebar-surface`           | `#1B1F26` |
| `sidebar-border`            | `#262A33` |
| `sidebar-foreground`        | `#F4F5F7` |
| `sidebar-muted`             | `#8E949F` |
| `sidebar-nav`               | `#9CA2AD` |
| `sidebar-hover`             | `#1D212A` |
| `sidebar-active`            | `#22262F` |
| `sidebar-active-foreground` | `#FFFFFF` |
| `sidebar-count`             | `#767D89` |
| `sidebar-avatar`            | `#262A33` |
| `sidebar-avatar-foreground` | `#C6CBD4` |

Inactive navigation text uses `sidebar-nav`; active navigation text uses `sidebar-foreground`. The active navigation item uses `sidebar-active` plus a thin `money-fill` leading marker. Parent navigation remains active throughout its child and detail routes. It must never become a large lime, Company-coloured, or saturated block.

### 6.3 Semantic-state tokens

| Semantic token            | Value     | Use                                                                                              |
| ------------------------- | --------- | ------------------------------------------------------------------------------------------------ |
| `money-fill`              | `#A3E635` | Paid/Accepted/Completed fills, collected charts, active-nav marker, approved product-mark accent |
| `money-fill-foreground`   | `#1B2708` | Text/icons on `money-fill`                                                                       |
| `money-text`              | `#4D7C0F` | Positive money figures only                                                                      |
| `danger-fill`             | `#E11900` | Overdue/Rejected/failure status fills                                                            |
| `danger-foreground`       | `#FFFFFF` | Text/icons on `danger-fill`                                                                      |
| `danger-text`             | `#D81800` | Overdue figures, validation, destructive labels                                                  |
| `danger-hover`            | `#B31400` | Confirmed destructive fill hover                                                                 |
| `danger-surface`          | `#FBE9E7` | Destructive hover/confirmation support                                                           |
| `danger-border`           | `#F2CFCB` | Destructive outline                                                                              |
| `danger-on-ink`           | `#FF8A7A` | Destructive text inside an ink bulk-action band                                                  |
| `warning-fill`            | `#FFB020` | Partial/Expired/Paused status fills                                                              |
| `warning-fill-foreground` | `#2E1D00` | Text/icons on `warning-fill`                                                                     |
| `warning-text`            | `#8A5A0B` | Inline warning copy/icons                                                                        |
| `status-quiet-background` | `#FFFFFF` | Quiet statuses                                                                                   |
| `status-quiet-foreground` | `#14181C` | Issued/Sent/Active/Unpaid text and dot                                                           |
| `status-quiet-border`     | `#C3CBD3` | Issued/Sent/Active/Unpaid outline                                                                |
| `status-muted-foreground` | `#5B6470` | Draft/Cancelled/Archived text                                                                    |
| `status-muted-border`     | `#DCE0E4` | Draft/Cancelled/Archived outline                                                                 |

There is no blue semantic token in v1. Issued, Sent, Active, and informational content are neutral ink treatments. The experimental blue link and informational treatments in the HTML reference are excluded.

### 6.4 Interaction aliases

shadcn/Tailwind aliases map to the semantic tokens rather than creating another palette:

| Alias                                    | Maps to                                       |
| ---------------------------------------- | --------------------------------------------- |
| `primary` / `primary-foreground`         | `foreground` / white                          |
| `secondary` / `secondary-foreground`     | white / `foreground`                          |
| `muted` / `muted-foreground`             | `surface-inset` / `foreground-muted`          |
| `accent` / `accent-foreground`           | `surface-inset` / `foreground`                |
| `card` / `card-foreground`               | `background` / `foreground`                   |
| `popover` / `popover-foreground`         | `background` / `foreground`                   |
| `destructive` / `destructive-foreground` | context-dependent destructive variant / white |
| `input`                                  | `border`                                      |
| `ring`                                   | `foreground`                                  |

Links are ink-coloured. Hover and keyboard focus may add an underline; links must not use a semantic-state colour.

### 6.5 Colour-use restrictions

- Bright lime is never text and never a generic accent.
- Olive is only positive money text and is never clickable.
- Red fill is reserved for loud danger statuses and the final destructive action inside a confirmation dialog.
- Amber is reserved for warning/partial state and warning feedback.
- Selection, navigation, hover, focus, informational messages, feature badges, and neutral chart categories never borrow status colour.
- Status colours may not be used merely to make a page more visually interesting.
- A new state reuses one of the existing semantic roles or replaces an existing system role after explicit review; it does not add a fourth hue.

## 7. Typography

### 7.1 Font families

- **Atkinson Hyperlegible Next** is the only internal interface face.
- **Atkinson Hyperlegible Mono** is the only numeric/data face.
- Fonts are self-hosted and covered by their SIL Open Font License.
- Use only weights required by the semantic hierarchy. v1 should load regular, medium/semibold where available, and bold rather than every weight by default.
- Fallbacks must preserve clear system rendering if the webfont fails.

Use the monospaced face with tabular figures for:

- money and currency amounts;
- quantities and tax percentages;
- dates and times;
- Quote, Invoice, transaction, recurring-template, Customer-reference, and PO-reference identifiers;
- IBAN, SWIFT/BIC, registration identifiers, and internal codes;
- counts, percentages, chart ticks, and numeric table labels.

Ordinary names, email addresses, descriptions, button labels, navigation, helper text, and prose remain in the interface face.

### 7.2 Semantic type roles

Pages consume named components/roles, not font classes:

| Role            | Contract                                         |
| --------------- | ------------------------------------------------ |
| `PageTitle`     | 24px/32px, bold, tight tracking, interface face  |
| `PageSubtitle`  | 14px/20px, regular, muted foreground             |
| `Breadcrumb`    | 14px/20px, regular, ink/muted hierarchy          |
| `SectionTitle`  | 16px/24px, semibold                              |
| `SurfaceTitle`  | 14px/20px, semibold                              |
| `Body`          | 14px/20px, regular                               |
| `BodyStrong`    | 14px/20px, semibold                              |
| `SecondaryText` | 12px/16px, regular, muted foreground             |
| `MetaLabel`     | 11px/16px, bold mono, uppercase, 0.10em tracking |
| `MetricValue`   | 20px/28px, bold mono, tabular figures            |
| `TableValue`    | 13px/20px, regular mono, tabular figures         |
| `TableAmount`   | 13px/20px, bold mono, tabular figures            |
| `StatusLabel`   | 11px/16px, bold mono, uppercase, 0.07em tracking |

Only `MetaLabel`, table/column labels, compact financial codes where explicitly specified, and `StatusLabel` use uppercase. Interface text must not be uppercased for decoration.

Do not skip levels to make a local section look important. A page has one `PageTitle`; regions use `SectionTitle`; bounded components use `SurfaceTitle`.

### 7.3 Typography verification

The Phase 5 PDF/font gate is complete. Its regression coverage continues to:

- verify `ă â î ș ț` as comma-below forms in React, public pages, and the selected PDF renderer;
- verify `B/8`, `O/0/D`, and `1/I/l` remain distinguishable;
- test real Customer names, document numbers, currencies, and translated labels at operational table widths;
- confirm numeric baselines and decimal alignment across totals and line items;
- confirm font fallback does not make controls unusable while fonts load.

The dashboard and PDF Romanian checks are one shared verification obligation, not duplicate independent gates.

## 8. Spacing, shape, borders, elevation, and motion

### 8.1 Spacing

Use a 4px base grid with the shared steps `4, 8, 12, 16, 20, 24, 32, 40, 48`. Components select from those steps. Feature pages must not introduce arbitrary gaps or padding.

Default density:

- page-edge spacing: 32px on wide screens, 24px on compact desktop/tablet, 16px on narrow screens;
- component/internal spacing: normally 8–20px;
- section separation: normally 24–32px;
- table-cell vertical padding: normally 12px, expanding when a secondary line wraps;
- compact controls are allowed only through a documented `size` or `density` variant.

Whitespace creates grouping; do not wrap every region in a card to manufacture separation.

### 8.2 Shape

- Status badges: 4px radius.
- Buttons, fields, menus, and company switcher: 6px radius.
- Bounded surfaces, dialogs, sheets, and empty states: 8px radius.
- Circular shape is reserved for avatars, activity markers, radio controls, and genuinely circular icons.
- Large pill controls and oversized rounded cards are not part of the v1 identity.

### 8.3 Borders and elevation

- Standard borders and dividers are 1px.
- Focus uses a 2px ink ring with a 2px offset.
- Page hierarchy is created by alignment, whitespace, subtle backgrounds, and borders—not decorative shadows.
- Ordinary cards, tables, metric strips, and side panels have no shadow.
- Popovers, dropdowns, and dialogs may use the single shared overlay shadow where separation from underlying content is necessary.
- No component may define its own shadow.

### 8.4 Motion

Motion communicates state change; it is not decoration.

- Use the shared fast transition for hover/focus and the shared standard transition for open/close.
- Do not animate layout for routine table updates.
- Loading indicators must not shift surrounding layout.
- Honor `prefers-reduced-motion` in every shared component.
- No page introduces bespoke keyframes.

## 9. Application shell and responsive layout

### 9.1 Desktop shell

The authenticated shell uses:

- a 232px near-black left sidebar;
- a flexible white/light-neutral content workspace;
- one shared dense application frame that uses the same `max-w-7xl` width across Company and Platform screens; narrower page-specific application frames are prohibited, while explicit full-width workspace variants remain available when later workflows genuinely require them;
- a persistent active-Company switcher near the top;
- the sidebar collapse control in the product-identity row, remaining available
  at the top of the collapsed icon rail without reserving a separate desktop
  header strip;
- collapsed-rail controls expose one horizontally centered primary visual while
  hiding expanded labels and secondary indicators;
- restrained active navigation with an ink surface and thin lime leading marker;
- user/settings controls at the bottom of the sidebar;
- one shared `PageHeader` followed by page content.

The route/navigation gate determines actual v1 destinations and their order. Labels shown in the HTML reference do not add Purchase Orders, Contracts, Reports, or any other unapproved product scope.

### 9.2 Narrow screens

The same component system must remain usable on smaller screens:

- the sidebar becomes the shared navigation sheet rather than a second mobile navigation design;
- a compact mobile header owns the navigation-sheet trigger;
- page-header actions wrap or move into an approved overflow treatment;
- action buttons keep at least a 44px touch target;
- forms collapse through shared grid variants;
- tables never widen the page or place content outside the viewport; the shared table primitive uses bounded fixed layout and safe cell wrapping, while an approved readable minimum width scrolls only inside its keyboard-focusable table region on narrower screens;
- dialogs use a responsive shared dialog/drawer policy and keep titles/actions visible while content scrolls;
- no feature creates its own breakpoint behavior.

The web application is responsive, not a native-mobile or mobile-first redesign.

## 10. Component contracts

### 10.1 Layout and hierarchy

| Component/pattern                    | Responsibility                                                                       |
| ------------------------------------ | ------------------------------------------------------------------------------------ |
| `AppShell`                           | Sidebar, narrow-screen navigation, main workspace, skip link, global boundaries      |
| `AppSidebar`                         | Product identity slot, Company switcher, authorized navigation, user/settings region |
| `PageHeader`                         | Optional breadcrumb, one page title, subtitle/summary, primary and secondary actions |
| `DocumentWorkspaceHeader`            | Dense document identity, status, actions, and section navigation                     |
| `PageSection`                        | Consistent vertical separation and optional section divider                          |
| `SectionHeader`                      | Section title, supporting copy, and a restrained action region                       |
| `Surface`                            | Bounded neutral region only when a border/background materially improves grouping    |
| `Stack`, `Inline`, `Cluster`, `Grid` | Approved layout rhythm without visual styling                                        |
| `FormSection`                        | Standard form heading, description, fields, and action placement                     |
| `DetailPanel`                        | Definition-list presentation for identity/settings details                           |

Every authenticated page uses `AppShell` and normally uses `PageHeader`. Dense document editors use the shared `DocumentWorkspaceHeader` variant so identity, saved-state feedback, document actions, and editor section navigation remain reachable together. A page may omit visible breadcrumbs when the route is already obvious, but it must not recreate either header locally. The shared page header stays visually open and uses the page stack for separation; it does not add a bottom divider or local bottom padding.

### 10.2 Buttons and actions

| Variant               | Treatment                            | Use                                                |
| --------------------- | ------------------------------------ | -------------------------------------------------- |
| `primary`             | Ink fill, white text                 | The main forward action in an action region        |
| `secondary`           | White fill, ink text, neutral border | Important alternative action                       |
| `ghost`               | Transparent, ink text                | Low-emphasis action                                |
| `destructive`         | White, danger text, danger border    | Destructive entry point outside confirmation       |
| `destructive-confirm` | Danger fill, white text              | Final destructive action inside `AlertDialog` only |
| `on-ink`              | Ink-band-safe outlined treatment     | Bulk actions on selected rows                      |

Rules:

- Use no more than three action ranks in one region.
- A stable action region has one primary. A modal/dialog is its own action region.
- Rare actions belong in a shared overflow menu.
- Send-with-options uses the shared split-button pattern.
- Disabled and loading states preserve button width; loading composes the shared Spinner and disables repeat submission.
- Icons use the configured single icon library, inherit `currentColor`, and follow Button sizing. Do not mix icon families.
- Icon-only buttons require an accessible name and Tooltip.
- Collapsible filter controls use the shared filter-toggle button: the collapsed control is secondary with an ink count badge, while the expanded control is primary with a lime count badge. Pages do not restyle this state locally.

Permanent deletion of an issued, sent, or publicly shared document uses `DestructiveActionDialog` with the stronger confirmation mode required by the financial-state specification. Pages do not improvise this friction.

### 10.3 Status presentation

All status appearance comes from one typed `StatusPresentation` registry and one `StatusBadge` component. The registry is keyed by domain meaning, not by a caller-supplied colour.

| Semantic group       | States                                        | Presentation                                                                   |
| -------------------- | --------------------------------------------- | ------------------------------------------------------------------------------ |
| Positive final       | Paid, Accepted, Completed                     | Solid lime, dark text, leading check                                           |
| Action/failure       | Overdue, Rejected, delivery/operation failure | Solid red, white text                                                          |
| Intermediate warning | Partial, Expired, Paused                      | Solid amber, dark text                                                         |
| Quiet active         | Issued, Sent, Active, Unpaid                  | White, ink text, strong border, ink dot                                        |
| Uncommitted          | Draft                                         | White, muted text, dashed muted border, grey dot                               |
| Inactive retained    | Cancelled, Archived                           | White, muted text, muted border; related totals struck through where specified |

Badges are compact, mono, uppercase, letterspaced, and slightly rounded. Solid badges have no dot; outlined badges do. They must look like modern digital state labels, never analog stamps or labels pasted onto paper.

Invoice lifecycle, payment state, and overdue flag remain separate domain values. The centralized presentation registry applies these display rules:

- Draft and Cancelled display their lifecycle state only.
- An Issued and Paid Invoice displays Paid.
- An Issued and Partial Invoice displays Partial and additionally Overdue when the derived flag is true.
- An Issued, Unpaid, non-overdue Invoice displays Issued.
- An Issued, Unpaid, overdue Invoice displays Overdue; Unpaid is implied rather than adding a redundant second badge.

This visual compression never changes stored state. Detail views and accessible labels must expose the complete underlying facts.

Status text is localized centrally. Use `Partial` / `Parțial`, not `Partially paid`. Both English and Romanian status labels must be tested inside the same badge widths.

### 10.4 Money, dates, numbers, and identity

Shared formatters/components own formatting:

- `MoneyValue` — ISO/symbol display, precision, sign, alignment, semantic tone;
- `QuantityValue` and `PercentageValue` — exact string values and tabular figures;
- `LocalDate` / `LocalDateTime` — locale and Company-timezone display;
- `DocumentNumber` — document identifier without coloured-link styling;
- `CustomerReference` — PO/reference presentation;
- `RegistrationValue` — tax/business IDs;
- `BankValue` — IBAN/SWIFT with safe wrapping and copy behavior.

Pages must not call browser number formatting ad hoc, convert monetary strings to binary floating point, or select semantic money colour themselves.

### 10.5 Forms

Forms use the shared shadcn Field composition and Invumo form patterns:

- `FieldGroup` and `Field` own vertical rhythm;
- `FieldSet` and `FieldLegend` group related options;
- labels, descriptions, required indicators, errors, and inherited-source captions have one treatment;
- `data-invalid` and `aria-invalid` communicate validation;
- controls use the shared height, border, focus, disabled, and loading states;
- validation always includes a text explanation; colour is not the only cue;
- inherited/default values use the inset surface and a source caption such as `Net 30 · from customer`;
- forms do not clear valid input after a server validation error.

Use the shared searchable Combobox for Customers and Products & Services. Inline creation uses the shared scrollable creation dialog, preserves the parent editor, retains invalid modal values, and selects the new record after a successful save.

The shared Combobox interaction contract is behavioral, not page-specific:

- opening shows all eligible options until the user enters a query;
- `ArrowDown` and `ArrowUp` move a distinct keyboard highlight without changing the stored selection;
- `Enter` selects the highlighted option and may select the sole visible result when no option has yet been highlighted;
- `Escape` and outside click close without selecting, and focus returns predictably to the trigger;
- current selection and keyboard/search highlight use distinct state treatment, and selection is never communicated by colour alone;
- listbox/option, active-descendant, accessible-name, and multiselect semantics must match the actual mode.

Short, static option sets use the shared non-searchable Select behavior, including arrow-key navigation, `Enter` selection, `Escape` dismissal, and first-character typeahead supplied by the approved primitive. Pages do not recreate either selector family.

Dates use one `DateField`/Calendar composition with locale-aware display, direct keyboard entry where supported, clear validation, and the same four-digit-year bounds as the domain. Currency, tax, language, timezone, Country, and other option lists use shared Select/Combobox behavior rather than individually styled controls.

`FileUpload` owns idle, drag, selected, uploading, validation-error, success, replace, and remove states for Company logos and any later approved upload. Pages supply file rules and copy; they do not style drop zones or progress independently. Server-authoritative Company-logo rules, private storage, and serving remain governed by [`../architecture/uploads-and-storage.md`](../architecture/uploads-and-storage.md); the component is feedback and selection UI, not a security boundary.

Long forms use `FormSection` and shared grids. They do not create individually styled cards for every small field group.

### 10.6 Dialogs, sheets, and confirmations

- Every Dialog, Sheet, and Drawer has an accessible title.
- Standard dialogs use documented size variants; callers cannot pass arbitrary width.
- Long forms scroll inside the shared body region while the title and actions remain reachable.
- Tooltips, dropdowns, Combobox lists, popovers, and other floating content use the approved primitive portal so scrolling, overflow, and transformed ancestors cannot clip them; increasing a local z-index is not an acceptable substitute.
- Narrow-screen behavior comes from the component, not the page.
- Ordinary confirmations use `AlertDialog`.
- Destructive confirmations state the affected record, consequence, recoverability, and blocking dependencies.
- Stronger confirmation is an explicit variant, not a bespoke modal.

### 10.7 Feedback and system messages

Use one `SystemMessage`/Alert contract:

| Type                 | Presentation                                            |
| -------------------- | ------------------------------------------------------- |
| Neutral confirmation | Solid ink surface with white text                       |
| Money success        | White surface, lime leading edge, olive icon/amount     |
| Warning              | White surface, amber leading edge and warning-text icon |
| Error                | White surface, red leading edge and danger-text icon    |
| Informational        | Neutral ink treatment; never blue                       |

System messages use a coloured edge, not a coloured fill, so they cannot be confused with status badges. Toasts use the same semantic mapping through the shared Sonner integration; pages do not configure toast colours.

Every asynchronous region defines shared loading, empty, partial-error, retry, success, and stale-data behavior. Use the shared Skeleton, Spinner, Empty, and ErrorState components rather than custom placeholders.

### 10.8 Operational tables

`OperationalTable` and its subcomponents own:

- table header typography and background;
- search/filter/action toolbar composition;
- stable sorting indicators;
- selectable-row and bulk-action behavior;
- pagination and per-page controls;
- loading, empty, no-results, and error states;
- content-driven column sizing, responsive minimum widths, and horizontal scrolling;
- row navigation, keyboard focus, trailing chevron, and overflow action behavior;
- numeric alignment and secondary-line treatments.

Required behavior:

- Header labels use `MetaLabel`.
- Rows use rules rather than cards.
- The entire eligible row navigates and is keyboard focusable.
- The identifying value remains plain ink rather than a coloured link.
- The trailing chevron is the persistent navigation cue.
- The overflow button stops row navigation and owns secondary actions.
- Columns use content-driven sizing by default; pages do not assign equal or arbitrary percentage widths.
- Identity and long-text columns keep shared usable minimum and maximum widths, wrapping safely when their content reaches the cap.
- Status, amount, and action columns remain compact; badges and action groups do not wrap merely to satisfy an artificial column width.
- Monetary columns align right and use `MoneyValue`.
- Selected rows use the neutral selection token.
- Bulk actions appear in the standard ink band.
- Every present and future table uses the shared `Table`/`OperationalTable` containment boundary; page-level horizontal overflow is prohibited.
- A viewport narrower than the content's readable width scrolls the table region horizontally; it does not hide totals, crush names, stack actions, or widen the document.

Feature-specific column definitions and filters are data configuration. Their appearance is not configurable by the page.

Operational list pages use one shared composition contract across Customers,
Quotes, Invoices, Transactions, and recurring templates:

- the full shared application frame and open `PageHeader` treatment;
- a compact summary-filter region before the table when supported by the
  domain;
- one search/filter/sort toolbar, with advanced filters collapsed by default;
- summary selections and collapsed filters apply immediately, preserve the
  visible active-filter count, and do not expand the filter region;
- one active-filter treatment, one pagination/per-page treatment, and the
  shared operational-table states;
- row information ordered as identity, core reference/date facts, financial
  or operational state, then actions;
- the same concept uses the same Laravel-owned common translation key and
  shared cell/component everywhere. A feature catalog must not rename a
  shared concept locally; for example, document lists use the common
  `Issue / due date`, `Customer reference`, `Status`, and `Actions` labels.

Changing a shared operational-list component or common label must propagate
to every list that consumes that concept. Feature code owns only genuine
domain-specific filters, cells, values, and actions.

### 10.9 Metric strips and dashboards

Summary metrics use `MetricStrip`: adjacent cells separated by rules, not a grid of floating rounded cards. Labels use `MetaLabel`; values use `MetricValue`; semantic colour is limited to overdue money, received money, and neutral values.

Dashboards must remain operational rather than decorative. Do not add a chart or metric that does not answer a supported v1 operational question.

The Company dashboard uses one selected-currency context across its balance summary, aging distribution, metric strip, attention panels, and recent rows. The compact aging bar is a semantic operational distribution, not a trend chart; it uses the approved neutral, warning, and danger tokens and always repeats meaning in text and values.

### 10.10 Charts

Charts use the shared Chart wrapper and one centralized palette:

- Status data reuses money-fill, warning-fill, danger-fill, and neutral draft.
- Neutral data uses the ink ramp `#14181C`, `#414A56`, `#6E7783`, `#9EA6B0`, `#CBD1D8`; a sixth series becomes `Other` rather than adding colour.
- Lime means money received only.
- Currencies are never combined into one additive series. Multi-currency views use small multiples.
- Gridlines use `rule`; labels use muted foreground; ticks and data labels use mono tabular figures.
- Greyscale/print meaning must remain understandable; status segments may add pattern as a second cue.
- Chart configuration consumes semantic palette helpers and cannot contain local colour literals.

### 10.11 Activity and audit presentation

`ActivityTimeline` owns actor, action, timestamp, context, and semantic marker layout. The same event type must look the same on Customer, Quote, Invoice, recurring, membership, and audit screens. Timeline markers are neutral unless an existing semantic state is genuinely part of the event.

The full audit trail may expose more detail by permission, but it does not receive a separate visual language.

### 10.12 Quote, Invoice, and recurring-template editors

Quote, Invoice, and recurring-template editors are configurations of one shared `DocumentEditorShell`, not three independently styled forms. The shell owns:

- editor page/header and unsaved-state treatment;
- responsive Build, Payments, and Sharing navigation where those capabilities exist;
- a compact document-facts sidebar and, for Invoices, the current balance summary;
- Customer selection and inline creation;
- document identity, issue/due/validity/reference fields;
- searchable Product & Service selection and inline creation;
- editable/reorderable line composition;
- quantity, unit, period, price, discount, and tax field alignment;
- server/preview calculation reconciliation feedback;
- subtotal, discount, tax, paid, outstanding, and total presentation;
- Terms & Conditions, notes, bank, delivery, and recipient sections;
- validation summary, save/issue/send actions, and narrow-screen behavior.

Quote-specific validity/acceptance, Invoice-specific payment state, and recurring-specific name/schedule/inheritance controls enter through typed slots or documented variants. They do not fork the editor layout or restyle shared sections.

The Invoice workspace keeps its Draft form mounted while the user switches between Build, Payments & adjustments, and Sharing & reminders, so unsaved values are never discarded by local navigation. On narrow screens the sidebar follows the active section in normal document flow, the tab strip scrolls inside its own region, and actions wrap without widening the page. A send shortcut opens the real sharing tools and remains unavailable while the Invoice form has unsaved changes.

Lines use one `DocumentLinesEditor` across all three aggregates. It preserves keyboard-accessible reordering, visible drag affordance, stable focus after add/remove/reorder, searchable catalog/manual-line paths, and a horizontal-scroll strategy for narrow screens. A line field must not move, rename, or change control treatment only because the parent is a Quote, Invoice, or recurring template.

Preview calculations may update immediately, but all preview amounts retain the same formatting and reconciliation treatment as server-authoritative results. A stale-save or server-recalculation conflict uses the shared blocking message/review pattern rather than a page-specific warning.

### 10.13 Settings

Account and Company settings use one `SettingsShell` with shared section navigation, page heading, form sections, inherited/default captions, unsaved-state handling, and save/error feedback. Account and Company scope must be visually explicit, but each setting category must not become a differently styled mini-application.

On narrow screens, settings navigation collapses through the shared responsive navigation component. Saving is scoped to the visible form/action region, and every Save action uses the normal Button hierarchy rather than a special settings colour.

Company appearance uses one `BrandColourField` and shared outward-document preview. Presets, custom-hex validation, contrast fallback, and preview state belong to that component/service boundary. Selecting a Company colour must never recolour the settings page or any other authenticated UI.

The outward-brand resolver persists canonical uppercase `#RRGGBB` values and owns the only raw Company-colour literals outside the global application tokens. New Companies use neutral ink `#14181C`; Ink, Navy, Forest, Burgundy, and Violet are reusable shortcuts, not a closed enum. The resolver chooses whichever of black or white has greater contrast for text on the brand-colour background. For ordinary outward text and rules on white, it uses the chosen colour only when the applicable contrast threshold passes and otherwise falls back to neutral ink. Every outward renderer and the shared live preview consume this same result shape.

### 10.14 Authentication and onboarding

Authentication, verification, recovery, invitation acceptance, first-Company creation, and empty-account onboarding use one `AuthShell`/`OnboardingShell` family. They use the same fonts, tokens, fields, actions, feedback, and accessibility contract without the authenticated sidebar.

These screens may simplify layout but may not introduce a marketing-style gradient, illustration palette, alternative primary colour, or separate form design. Product marketing outside the application is not governed by this v1 internal-application contract.

## 11. Selection, focus, and interaction states

- Focus is a visible 2px ink ring with a 2px offset. Dark chrome uses the shared high-contrast inverse focus token.
- Hover never changes semantic meaning or introduces hue.
- Keyboard focus and hover must expose the same available actions.
- Checkboxes are square, slightly rounded, white/strong-border unchecked and ink/white-check selected.
- Selected rows and items use the neutral selection surface.
- Unsaved editor state uses shared copy/iconography rather than a new colour.
- Disabled and inherited are distinct semantics even if both use an inset surface; captions and control behavior must explain which applies.
- Permission-hidden actions are omitted. Permission-available but state-blocked actions may be disabled only when a clear explanation is accessible.
- Success, validation, and failure must never rely on colour alone.

## 12. Accessibility and localization

The design system must meet WCAG 2.2 AA for supported journeys.

Verified token pairs from the approved palette:

| Pair                                    | Contrast                                  |
| --------------------------------------- | ----------------------------------------- |
| foreground on white                     | 15.6:1                                    |
| muted foreground on white               | 5.6:1                                     |
| subtle foreground on white              | 3.1:1; large/decorative/disabled use only |
| money-fill foreground on money-fill     | 10.4:1                                    |
| white on danger-fill                    | 4.7:1                                     |
| warning-fill foreground on warning-fill | 8.9:1                                     |
| money-text on white                     | 5.0:1                                     |
| danger-text on white                    | 4.6:1                                     |
| row-affordance on white                 | 4.6:1                                     |

Non-negotiable behavior:

- Every status communicates through text plus shape/border/fill/lightness, never hue alone.
- Keyboard order follows visible reading order.
- A skip link reaches main content.
- Dialog focus is trapped and restored correctly.
- Icon-only controls have accessible names.
- Tooltips are supplementary, never the only source of required information.
- Forms associate labels, descriptions, and errors programmatically.
- Live asynchronous feedback uses appropriate accessible announcements without excessive interruption.
- Pointer targets are at least 44px on narrow/touch layouts; compact desktop targets remain keyboard accessible and meet minimum applicable guidance.
- Content supports browser zoom without loss of information or action.
- English and Romanian copy may expand; components must not rely on fixed label widths or truncate required actions silently.
- Dates, currencies, names, and status labels are tested in both launch languages.

## 13. Icons and imagery

- Use one icon family selected with the approved shadcn project profile; do not mix libraries.
- Icons are simple modern strokes, inherit `currentColor`, and never introduce decorative hue.
- Components own icon size and alignment.
- Do not use icons as the sole state or action label when ambiguity is possible.
- Avoid decorative illustrations in operational application screens.
- Do not introduce book, ledger, receipt-paper, bookmark, seal, or vintage accounting imagery as Invumo identity.
- Company logos appear only where product scope calls for them and do not recolour the internal interface.

## 14. Component ownership and naming

Use three ownership levels:

1. `ui` — source-owned shadcn primitives customized to the Invumo token layer.
2. `app` — domain-neutral Invumo patterns such as PageHeader, OperationalTable, SystemMessage, and FormSection.
3. `domain` — business-aware components such as DocumentStatus, MoneyValue, CustomerCombobox, and DocumentEditorShell.

Do not create page-named visual components such as `InvoicesBlueBadge`, `CustomerPageTitle`, or `SettingsCardStyle`. A page-specific component is acceptable only when it encapsulates page behavior/composition; it must still build entirely from the shared visual layers.

Shared components should not expose unrestricted `className` or `style` escape hatches for visual overrides. A layout slot may accept a constrained layout class only when composition requires it. Data-driven geometry, such as chart dimensions, goes through an approved component API and never carries raw colour.

When two pages need the same composition, promote it. When one page needs a visual exception, first determine whether the component contract is incomplete.

## 15. Design-system change protocol

A visual or interaction change follows this order:

1. Identify the semantic reason for the change.
2. Determine whether it belongs to a token, primitive, application component, domain component, or composition pattern.
3. Update this contract when meaning, vocabulary, or a public component API changes.
4. Change the single owning token/component.
5. Update its state matrix, accessibility coverage, and visual regression reference.
6. Review every existing use for intended propagation.
7. Remove any page workaround made obsolete by the shared fix.

Do not preserve inconsistent old styling by forking a component. A temporary exception must have a named reason, owner, removal condition, and tracker item; it must not silently become a second system.

New component variants require a reusable semantic distinction. “This page looks better” is not a variant.

## 16. Enforcement in code and CI

Phase 1 must establish automated guardrails proportionate to these rules:

- raw internal colour literals are allowlisted only in the global design-token definition;
- outward Company colour values enter only through the validated outward-theme resolver;
- feature/page TypeScript and JSX reject raw hex, RGB/HSL/OKLCH values, arbitrary Tailwind colour utilities, and numbered Tailwind palette utilities;
- page modules are restricted from importing internal styling helpers or bypassing approved component layers;
- shared component visual overrides through page `className`/`style` are rejected or reviewed explicitly;
- formatting/linting and type checks run in CI;
- critical components have behavioral and accessibility tests for every variant/state;
- a development/test-only component gallery renders the complete state matrix in English and Romanian;
- browser visual-regression coverage protects the shell, typography, buttons, forms, statuses, feedback, table states, dialogs, and responsive layouts;
- the pinned GitHub Ubuntu runner owns byte-level visual-regression references because PNG font rasterization is operating-system dependent; local and other runners retain the same behavioral, accessibility, JavaScript, typography-selection, and responsive-state checks;
- failed canonical visual comparisons retain a short-lived expected/actual/difference artifact for review before a reference is changed;
- every canonical reference update follows the [visual snapshot baseline contract](../development/visual-snapshot-baselines.md), records hash-bound screen/cause/change/inspection evidence, and is rejected by CI when that evidence is absent or stale;
- representative tests verify no Company brand colour leaks into authenticated application chrome.

Do not add Storybook or another design-system runtime by default. The component gallery may be a test harness excluded from production routing and bundles unless later evidence justifies a separate tool.

## 17. Required component-state matrices

Each shared component is incomplete until applicable states are covered:

- default;
- hover;
- keyboard focus;
- active/pressed;
- disabled;
- loading;
- validation error;
- empty/no results;
- permission-hidden or state-blocked;
- selected/unselected;
- inherited/overridden;
- English/Romanian;
- wide/narrow viewport;
- reduced motion.

Not every component implements every state, but omission must be intentional rather than accidental.

## 18. Definition of done

### 18.1 Design-system foundation

The Phase 1 design-system foundation is ready only when:

- the semantic token layer exactly matches this approved contract;
- Atkinson Hyperlegible Next and Mono are self-hosted and verified;
- shadcn primitives consume semantic tokens without page-level colour overrides;
- AppShell, PageHeader, typography roles, actions, forms, status presentation, feedback, operational-table foundations, empty/loading states, overlays, and responsive navigation exist as shared components;
- the status registry covers every v1 domain state and approved combination;
- English/Romanian and representative narrow-screen states exist in the component gallery;
- automated guards reject raw colour use and major component-layer violations;
- accessibility and visual-regression checks cover the core matrix;
- the custom application shell is built only after the route/navigation composition gate is approved.

### 18.2 New page or feature

A page is visually complete only when:

- it composes approved shared components;
- it adds no raw colour or page-owned typography/control/status styling;
- all actions use the standard hierarchy and confirmation patterns;
- money, dates, identifiers, state, forms, messages, and tables use their centralized components;
- loading, empty, no-results, error, validation, disabled, permission, and success states are covered;
- it works in English and Romanian;
- it remains usable at the required narrow viewport;
- keyboard, focus, screen-reader labeling, zoom, and contrast behavior are verified;
- its tests prove the contract-relevant states.

## 19. Explicit exclusions

- Dark mode in v1.
- Company theming of the internal application.
- Page-specific themes or visual identities.
- Raw Tailwind palette colours in feature code.
- Per-page status mappings, form styles, button variants, table styles, dialogs, or empty states.
- A generic brand-primary colour for the authenticated application.
- Indigo, blue-link, blue-informational, or experimental primary treatments from the HTML reference.
- Additional saturated semantic hues.
- Decorative gradients, glassmorphism, paper textures, oversized shadows, and excessive rounded cards.
- Old-ledger, bookmark, book, rubber-stamp, or nostalgic accounting motifs.
- Copying or importing the supplied reference HTML/CSS/logic.
- Per-Company custom fonts or internal-dashboard theming.
- A separate design-system runtime or documentation application without demonstrated need and explicit approval.

## 20. Related documents

- [Master Build Brief](../product/master-build-brief.md)
- [Core Domain Rules](../product/domain-rules.md)
- [Application Architecture Baseline](../architecture/application-architecture.md)
- [Quote, Invoice, and Financial State](../architecture/document-and-financial-state.md)
- [Owner/Admin/Member Permission Matrix](../architecture/role-permission-matrix.md)
- [Development Tracker](../development/development-tracker.md)
