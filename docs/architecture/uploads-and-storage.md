# Upload and Private Asset Storage

Status: Approved Phase 1 architecture contract  
Last updated: 2026-08-24

## Scope and authority

This contract defines the shared v1 upload and storage boundary required by Company logos. It refines the master brief, domain rules, relational schema, application architecture, and design-system `FileUpload` contract without expanding v1 into general document or media storage.

The current production implementation uses private local storage. Domain and application code must depend only on Laravel's filesystem abstraction so a later move to an S3-compatible disk does not change Company, document, or rendering rules.

## Accepted Company-logo input

- Accept PNG, JPEG, and WebP raster images only.
- Reject SVG, GIF, animated formats, malformed files, and files whose content does not match an accepted image MIME type.
- The server-authoritative maximum is 5 MiB (`5 × 1024 × 1024` bytes).
- Width and height must each be positive and no greater than 4096 pixels.
- Validation uses detected file content and decoded image metadata. A browser filename, extension, `Content-Type`, preview, or client-side validation is never authoritative.
- Client-side checks may provide faster feedback but must use rules supplied to the shared `FileUpload` component and must never replace server validation.

The server derives the canonical MIME type and safe extension from the accepted content. Original filenames are neither storage keys nor authorization inputs.

## Private storage contract

- Company assets use the configured `COMPANY_ASSETS_DISK`; v1 defaults to the dedicated private local disk.
- The local disk root is outside `public/`, has no public symbolic link, and does not expose framework temporary-serving routes.
- Every object key is server generated from the Company UUID, asset UUID, and content-derived extension. It is opaque to the browser and never accepted as request input.
- Each stored asset records its Company, purpose, disk, object key, detected MIME type, exact byte size, SHA-256 content hash, pixel dimensions, creator, creation time, and optional deletion time.
- Object bytes are written with private visibility. A write is accepted only after the stored size and SHA-256 hash match the validated upload.
- UUIDs, disk names, and object keys are identifiers, not access secrets.

`company_assets` is tenant owned, protected by forced PostgreSQL RLS, and linked through the normal same-Company key pattern. Asset metadata is immutable after creation except for the controlled `deleted_at` lifecycle marker.

## Application access and serving

No Company asset is served by a public storage URL.

- An authenticated internal preview/download must resolve a Company and asset by server-owned identifiers, authorize current membership and the required named ability, enter that Company's tenant context, load the asset through RLS, and stream it through the application.
- Public Quote/Invoice pages will resolve logo access only after the Phase 8 public-token bootstrap has authorized the owning document and established its Company context. They must not reuse an internal authenticated route.
- PDF and email rendering read bytes through the backend storage boundary after the surrounding document operation has established tenant authorization.
- Responses use the stored canonical MIME type, `X-Content-Type-Options: nosniff`, and context-appropriate private or public cache policy. The browser never supplies a filesystem path or object key.

The internal serving endpoint belongs with the Phase 2 Company-logo workflow. Public serving belongs with the Phase 8 token workflow. This Phase 1 foundation deliberately exposes neither an orphan-producing upload endpoint nor a public asset endpoint.

## Replacement, removal, and cleanup

Replacing a logo never overwrites an existing object:

1. Validate and privately store a new immutable asset.
2. Create its `company_assets` row.
3. In the Company-settings mutation, atomically change the live logo reference and record the audit event.
4. Delete a failed pre-commit write on a best-effort basis.

Removing a logo clears the live Company-settings reference and records the settings change. An asset may receive `deleted_at` only after no live Company setting or retained document snapshot references it. Physical object deletion happens after commit through an idempotent cleanup path; a cleanup failure remains retryable and observable. Existing PDFs/documents may therefore retain an earlier logo asset for their approved retention period.

No HTTP request may perform irreversible object deletion before the referencing database transaction commits.

## Local-to-S3 migration

Do not add the S3 filesystem adapter until the move is scheduled. At that time:

1. Configure a new private S3-compatible disk and least-privilege credentials.
2. Copy each live object using its recorded disk/key.
3. Verify byte size and SHA-256 on the destination.
4. Update storage metadata only after verification, in bounded idempotent batches.
5. Keep both disks readable during the transition and remove the old copy only after the database points to the verified destination and restore evidence exists.

The Company-logo workflow, database identity, authorization, public-token rules, and UI do not change during this migration.

## Operations and tests

- Private local assets are included in the externally managed off-server backup and restore scope before public launch.
- Storage failures are reported through the approved operational logger using asset IDs and correlation IDs, never object bytes, original filenames, or secrets.
- Automated coverage must prove accepted/rejected types, size and dimension limits, content-derived metadata, private verified writes, cleanup after failed persistence, tenant isolation, immutable metadata, and no cross-Company access.
- The shared `FileUpload` component must cover idle, drag, selected, uploading, validation-error, success, replace, and remove states in English and Romanian without page-owned styling.

## Explicit v1 exclusions

- arbitrary attachments or document uploads
- product images, user avatars, signatures, stamps, or custom favicons
- SVG sanitization or animation support
- image cropping, filters, or responsive-image transformations
- browser-direct multipart uploads to object storage
- a general media library, folder browser, or public asset CDN
- antivirus infrastructure for the approved raster-logo boundary
