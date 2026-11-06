# Manifest Loading Status

This document describes the implemented manifest-loading work. The validated
business rules remain in [domain-decisions.md](domain-decisions.md).

Last refreshed: 2026-10-26, against `main` at `351d5d3` plus the icon-rail branch (PR #11).

## Implemented

### Scan and audit

- A loader can scan a uniquely barcoded handling unit onto an open manifest.
- The scan creates one current `manifest_items` assignment and marks the pallet
  as loaded.
- Repeat scans of the same pallet on the same manifest are idempotent. They do
  not create another assignment or overwrite the original loader and time.
- Scans against closed manifests are rejected.
- A destination mismatch can be acknowledged and is recorded as a warning.
- A consignment split can be acknowledged when another pallet from the same
  consignment is already on another manifest.
- An acknowledged pallet conflict moves the pallet's current assignment to the
  selected manifest and records the conflicting manifest.
- Successful loads create an immutable `operational_events` record with the
  device event time, server receipt time, actor, pallet, event type, and
  manifest metadata.
- Database uniqueness and a transaction protect current assignments and client
  event IDs during concurrent scans.

### Manifest synchronization

- The `ManifestSource` contract isolates the upstream system.
  `FixtureManifestSource` is the bound implementation (`AppServiceProvider`).
- `SyncManifests` imports idempotently by source plus external ID, and
  `SyncManifestsCommand` runs it from the console.
- The fictional DepotApp simulator has not been built (see below).

### Tablet UI

- Touch-first kiosk layout with the freightOPS branding, user menu, light theme
  by default and dark theme opt-in.
- Loading flow: home, depot, manifest and scan screens
  (`resources/js/pages/loading/`).
- Manifest page is the scan card plus a departure chip. Everything else sits in
  a left icon rail (a scrolling row on phones): consignments (`n/m` badge),
  trailer type, DG map, equipment, Finish and Close (scaler roles). Each opens
  an existing dialog or sheet; equipment has its own dialog. The clash banner
  and a DG/food strip show only when there is something aboard.
- The consignment board (`ManifestConsignmentBoard`) colours each consignment:
  green all in bay, amber part missing, orange held in a holding lane, blue
  split onto another manifest, grey complete on this trailer. Filters: all,
  ready, not in bay, complete, split. Each piece shows where it is, using the
  arrival rule (a pallet has arrived once a cuber or runner scans it).
- Manifest summary and consignment progress endpoints feed the confirmation
  screens.
- Camera barcode scanning (`use-barcode-camera`, `barcode-scanner`) and a
  simulated-scan route for development.

### Offline

- Scan submission goes through a client outbox (`resources/js/lib/outbox.ts`,
  covered by Vitest) with `client_event_id` idempotency and an online-status
  indicator.

### Trailers, equipment and finishing

- Trailers with type, owner and status, assignable to a manifest.
- Trailer positions for pallets and DG pallet positions.
- Manifest equipment counts with an adjust endpoint.
- Finishing a manifest's loading, and reopening it, with a
  `ManifestLoadingFinished` notification.
- Closing a manifest (`CloseManifest`, scaler roles only). Outstanding
  consignments or an unfinished loading sign-off warn and need an
  acknowledgement; the result is recorded as `closed_by` and a `close_summary`
  snapshot. A manifest closed here is not reopened by a later sync.
- Closing writes one `manifest_closed` `operational_events` row per pallet on
  the manifest (id derived from manifest + pallet, so a retry cannot duplicate
  it), shown in each pallet's trace.
- The loader's finish is only a signal; the scaler's close is the official one.
  Once a manifest is closed, finish and reopen answer `manifest_not_open` (409)
  and the Reopen button is hidden. A scaler-side reopen is not built.
- Notification bell with read and read-all actions.
- Admin-settable consignment hold-back flag with its own audit table
  (`consignment_hold_back_events`).

### Authentication

- PIN login for shared tablets (`PinLoginController`), with username and PIN
  on users, a PIN settings page and an artisan command to set a PIN
  (`SetUserPin`).
- Password login remains as a fallback at `login/password`.
- Expired sessions are handled in the client (`session/ping`,
  `resources/js/lib/session.ts`).
- Loading routes require `auth` and `verified`.

### Unload and bay direction

- A runner scans a pallet at `/unload`; `BayDirector` returns the bay and a
  stated reason. The destination depot's bay is the default (e.g. SYD02).
  A sender with an active `holding_rules` row goes to the holding bay instead
  (Settings: sender, max hold days, reason).
- Nothing is recorded until the runner taps Confirm. The confirm endpoint
  re-checks the bay and answers `direction_changed` if the rules moved on.
- `MoveHandlingUnit` updates the pallet's current location and status and writes
  one immutable `operational_events` row (`staged` for the first placement,
  `moved` after that), idempotent on `client_event_id`. Pallets already on a
  trailer are refused.
- Holding lanes: five lanes (HOLD1-HOLD5; 1 leaves soonest, 5 can wait longest).
  A holding rule's `base_lane` is where a standard-service pallet goes; EXPRESS
  shifts it two lanes toward 1, ECONOMY one lane later, clamped to 1-5.
- When the suggested lane is full the runner picks another lane. The move is
  recorded with `override_reason: lane_full` and the suggested lane. Only held
  pallets can be overridden, and only into an active holding lane.
- A "Simulate seeded scan" button (local/testing only) picks an unplaced pallet.
- The home depot comes from `config('freight.home_depot_code')` (`MEL`).
- Movements show in the consignment sheet's pallet trace.
- After a drop the runner sees how much of the consignment is placed
  (`ConsignmentBayProgress`): "2 of 5 in bay", then "All 5 items placed".
  Pallets in a holding lane are counted as held, not missing, and pallets
  already on a trailer are ignored. The scan response carries the same
  `progress` data.
- The Confirm tap is not queued in the offline outbox yet: it retries safely
  with the same event ID but needs a connection.

### Seeded development data

- `SydneyFreightSeeder` builds seven MEL-to-SYD manifests numbered
  `MEL-SYD-{yymmdd}-{run}` from the service date (the same shape as upstream).
  The last one is already departed: loading finished, closed by a scaler, with
  an empty clean `close_summary` (nothing is ever built onto a closed manifest).
- Seed the freight data alone with `php artisan db:seed --class=SydneyFreightSeeder`;
  the full `DatabaseSeeder` also resets the dev users' PINs.

## Main code locations

- `app/Actions/Loading/LoadHandlingUnit.php` contains the scan decision tree
  and transaction.
- `app/Actions/Loading/SyncManifests.php` and `app/ManifestSources/` hold the
  import path.
- `app/Actions/Loading/SetConsignmentHoldBack.php` handles hold-back changes.
- `app/Http/Controllers/Loading/` contains the loading endpoints and pages.
- `app/Support/Loading/` builds the manifest summary, consignment board and
  equipment counts.
- `app/Models/` includes `Manifest`, `ManifestItem`, `Trailer`,
  `WarningAcknowledgement`, `OperationalEvent` and
  `ConsignmentHoldBackEvent`.
- `tests/Feature/Actions/Loading/`, `tests/Feature/Http/Loading/` and
  `tests/Feature/Concurrency/LoadingConcurrencyTest.php` cover the scan
  outcomes, endpoints and concurrent loading.

## Verification

On `main`, with `DB_HOST=127.0.0.1 DB_PORT=3307` for the host-run suite:

- Full PHP suite: 200 tests, 200 passing (812 assertions).
- Vitest: 12 tests, 12 passing.
- Pint, PHPStan, `tsc --noEmit` and ESLint all pass.

## Still to build

- A real upstream connector. The mechanism is still undecided (see open
  discoveries in domain-decisions.md).
- The fictional DepotApp simulator (create, update, close and dispatch
  manifests), to be built only when needed to validate synchronization.
- Dispatch and paperwork workflows. Closing is covered by the close flow
  above, but dispatch itself is not built.
- Dangerous-goods document processing.
- Weight, cube and trailer-capacity calculations.
- Split-avoidance recommendations and reporting.
- Receiving and check-weight workflows, more bay codes (only SYD02 and the
  holding bay are seeded) and an offline queue for runner confirms.
- Offline conflict UI beyond server idempotency.
- Later blueprint slices: operational priority, equipment sessions,
  exceptions, real-time updates (Reverb) and reporting.
