# Manifest Loading Status

This document describes the implemented manifest-loading slice. The validated
business rules remain in [domain-decisions.md](domain-decisions.md).

## Implemented

- Manifests can be filtered by open status, destination, and service date.
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

## Main code locations

- `app/Actions/Loading/LoadHandlingUnit.php` contains the scan decision tree
  and transaction.
- `app/Models/Manifest.php` contains manifest loading filters.
- `app/Models/ManifestItem.php` represents the current pallet assignment.
- `app/Models/WarningAcknowledgement.php` stores acknowledged warnings.
- `app/Models/OperationalEvent.php` represents the operational audit trail.
- `tests/Feature/Actions/Loading/LoadHandlingUnitTest.php` covers the scan
  outcomes and warning paths.

## Verification

The current suite passes:

- Loading action tests: 10 tests, 10 passing.
- Full test suite: 58 tests, 58 passing.
- Pint formatting check passes.

## Still to build

- Manifest selection endpoint and tablet UI.
- Barcode scan endpoint and operator-facing confirmation or warning UI.
- Consignment progress response for the confirmation screen.
- `ManifestSource` contract and a fixture or approved upstream synchronizer.
- Authentication and authorization for loader operations.
- Closing, dispatch, paperwork, and offline outbox workflows.

## Working tree note

The operational event model, migration, factory, action changes, and related
test are currently uncommitted working-tree changes.
