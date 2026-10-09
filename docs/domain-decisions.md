# Freight Ops Domain Decisions

This document records validated operational decisions for Freight Ops. It is the
source of truth for terminology and Slice 1 behaviour. The broader blueprint is
directional; when the two disagree, this document wins.

To keep the portfolio safe, examples use fictional names and data. Production
integration with an employer-owned system must use an approved interface and
must not copy proprietary code, credentials, or operational data.

## Scope and terminology

- A **consignment** is a customer order containing one or more physical pallets.
- A **handling unit** is one physical pallet. Each handling unit has its own
  unique barcode. The loader-facing UI calls it **freight** or **pallet**.
- The UI calculates consignment progress from its handling units, for example
  `7 of 10`.
- A **manifest** is an externally created, destination-based load. It starts
  empty and always represents one trailer load.
- A manifest normally serves one destination, but a small number serve multiple
  destinations, such as Newcastle and Gosford.
- Freight is not allocated to a manifest before loading. A loader builds the
  manifest by scanning pallets.
- A **depot** owns the operational context. The first validated example is
  MEL05. Demo data must use fictional depot and business names.

## Current operational flow

1. Office staff create manifests in the upstream depot system in advance.
2. Those manifests become available on loading tablets automatically.
3. A loader signs in with an individual account and opens `Load PUD`.
4. The loader filters by receiving state and today, sometimes including
   yesterday when manifests were prepared early.
5. The loader selects an open manifest, identified primarily by manifest number
   and a display label such as `SYD FreightOps 1`.
6. The loader scans a pallet. A valid scan immediately means loaded; there is no
   second physical-confirmation step.
7. A short confirmation appears and dismisses automatically after a few
   seconds. An OK button permits immediate dismissal.
8. Several loaders may scan onto the same manifest concurrently.
9. A scaler closes and dispatches the finished manifest in the upstream depot
   system. Dangerous-goods paperwork and final dispatch reconciliation are
   outside the first Freight Ops slice.

## Slice 1 acceptance statement

> An authenticated loader selects a destination and an imported open manifest,
> scans a uniquely barcoded pallet, sees consignment progress, and either gets
> an immediate loaded confirmation or explicitly acknowledges a destination or
> split warning. Duplicate and concurrent requests never create duplicate
> current assignments.

### Manifest selection

- Filter manifests by depot, destination, and service date.
- Default to today and allow including yesterday.
- Display manifest number prominently.
- Trailer label and registration are optional display fields. Loading is not
  blocked when registration is absent.
- Closed or dispatched manifests are not normal loading targets.

### Successful scan

The confirmation shows at least:

- consignment number;
- pallet progress, such as `4 of 10`;
- manifest number and display label;
- loader;
- loaded time.

### Wrong destination

When the pallet destination is not served by the selected manifest, show the
pallet destination and manifest destination. The loader may cancel or load
anyway. Continuing records an audited warning acknowledgement without requiring
a reason.

### Same pallet already on the selected manifest

Show who previously scanned the pallet and when. Do not create a duplicate load
assignment or increment any count. This message helps a loader identify freight
that was scanned nearby but not yet physically placed on the trailer.

### Same pallet currently assigned to another manifest

Show the other manifest number, loader, and time. A confirmed override moves the
pallet's current assignment to the selected manifest while retaining immutable
history of both operations. This is a correction, not a consignment split.

### Consignment split

When other pallets from the same consignment are assigned to another manifest,
show:

- total pallets in the consignment;
- pallet counts grouped by existing manifest number;
- the count that will be on the selected manifest after this scan.

The loader may cancel or continue. Continuing records an audited acknowledgement
without asking for a split reason. A split is valid and should not be blocked.

## State and audit rules

- Every client operation carries a unique `client_event_id`.
- Every pallet has at most one current manifest assignment.
- Current assignment and immutable operational event are written in one database
  transaction.
- `occurred_at` records device time; `received_at` records server processing
  time.
- Warning acknowledgements record warning type, operator, pallet, selected
  manifest, relevant conflicting manifest, and timestamps.
- Counts are projections from current pallet assignments, not manually edited
  totals.
- Server-side uniqueness and idempotency protect concurrent loading by multiple
  operators.

## Manifest synchronization boundary

The upstream integration mechanism is not yet known. Freight Ops must isolate it
behind a `ManifestSource` contract rather than coupling tablet pages directly to
the upstream system.

Possible implementations include an approved API, file export, or database
integration. Until the real interface is known, a fictional fixture source will
support local development and automated tests.

Imported records retain:

- source name;
- external manifest identifier;
- manifest number;
- depot and destination data;
- service date and upstream status;
- optional trailer display name and registration;
- upstream update time and local synchronization time.

Synchronization is idempotent by source plus external identifier. Freight Ops
reads from its own database so tablets remain fast and can later work offline.

## DepotApp simulator boundary

A small fictional **DepotApp simulator** may be built to exercise workflows that
sit outside Freight Ops. Its purpose is to provide a realistic upstream system
for development and demonstrations, not to reproduce an employer's application.

The minimum useful simulator can:

- create and update destination-based manifests;
- publish manifests through the same `ManifestSource` boundary;
- update an optional trailer label or registration;
- close and dispatch a manifest;
- provide fictional consignments and uniquely barcoded pallets.

It should be built only when needed to validate synchronization. Slice 1 can use
fixtures first, which prevents the simulator becoming a second product before
the loading workflow works.

## Deferred capabilities

- Real upstream DepotApp connector, pending an approved integration mechanism
- Manifest closing, dispatch, and paperwork workflows
- Dangerous-goods document processing
- Registration automation or trailer barcodes
- Weight, cube, and trailer-capacity calculations
- Split-avoidance recommendations and reporting
- Receiving, check-weight/cubing, bay direction, and freight-runner workflows
- Full offline outbox and conflict UI beyond server idempotency

## Open discoveries

- Determine the approved upstream manifest interface: API, export, or another
  supported mechanism.
- Confirm the exact upstream manifest status values and whether closed manifests
  can reopen.
- Confirm how multi-destination manifests encode their destinations.
- Confirm whether moving the same pallet between manifests in the existing
  workflow changes the old manifest automatically; the current product decision
  is that it does.
- Confirm whether trailer registration and equipment identifiers become stable
  concepts worth modelling separately.
