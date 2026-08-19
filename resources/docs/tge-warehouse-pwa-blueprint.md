# TGE Warehouse Ops — Complete PWA Blueprint

## 1. Project Overview

A progressive web app built with Laravel 12, Inertia.js, and React that reimagines warehouse tablet operations for a national logistics company. Designed for Android tablets, installable via Chrome, works offline, and demonstrates deep domain expertise from real warehouse floor experience.

**What this replaces:** A sluggish C#/.NET tablet system with too many taps, no offline resilience, zero priority visibility, and no dangerous goods mapping.

**What this proves in your portfolio:**
- Laravel architecture at scale (services, events, queues, policies, observers)
- Inertia + React as a cohesive full-stack approach
- PWA offline-first patterns (service workers, IndexedDB, background sync)
- Real domain expertise — not a tutorial project
- Business value thinking — quantified time savings and cost reduction

---

## 2. Stack Decision & Justification

| Layer | Choice | Why |
|-------|--------|-----|
| **Backend** | Laravel 12 | Your primary framework. Security support through Feb 2027. Mature ecosystem. |
| **Frontend** | React via Inertia.js | Consistent with DingoBet and BubblyPups. SPA feel without a separate API. |
| **Styling** | Tailwind CSS | Rapid UI development, utility-first, great tablet responsive support. |
| **Database** | MySQL (dev/production) | Your standard stack. Supports the relational schema this app needs. |
| **Offline storage** | IndexedDB (via Dexie.js) | Client-side structured storage for offline queue and cached data. |
| **Service worker** | Workbox (via Vite PWA plugin) | Google's battle-tested service worker toolkit. Handles caching strategies. |
| **Barcode scanning** | Barcode Detection API + QuaggaJS fallback | Native Chrome Android support. QuaggaJS polyfill for unsupported browsers. |
| **Real-time** | Laravel Echo + Pusher (or Soketi) | WebSocket notifications for bay status, priority alerts, trailer updates. |
| **Auth** | Laravel Breeze (customised) | PIN-based auth for warehouse speed. Session-based, no token complexity. |
| **Testing** | Pest PHP + Vitest | Pest for backend (Laravel-native). Vitest for React components. |
| **Dev tools** | Vite, Laravel Sail (Docker) | Hot reload, consistent dev environment. |

### Why React + Inertia Over Other Options

**Not Vue:** You're building career momentum with React. Vue is Laravel's "natural" pairing but switching frameworks mid-career-build splits your learning and muddies your portfolio story. Every project should reinforce the same stack narrative.

**Not Livewire/Blade:** Great for server-rendered apps but doesn't demonstrate frontend architecture skills. Interviewers asking about your React experience want to see React, not "I used Blade for one project."

**Not standalone React SPA:** Would require building a separate REST/GraphQL API. Inertia eliminates this — you get SPA behaviour with Laravel controllers and routing. Less code, same result, and you can talk intelligently about the tradeoff in interviews.

### Why PWA Over NativePHP

**Not NativePHP:** The critical warehouse features (barcode scanning, biometrics, push notifications) are all premium plugins ($$$). NativePHP v3 is still early with ~18k total installs. A PWA uses your primary stack, is dramatically easier to demo ("open this URL"), and the Barcode Detection API works natively on Chrome Android — exactly what warehouse tablets run.

**The honest tradeoff:** NativePHP gives deeper offline (SQLite on-device) and true native feel. But for a portfolio project where the architecture and domain thinking are what impress, PWA wins on practicality, demoability, and stack consistency.

---

## 3. PWA Architecture

### Service Worker Strategy

The app uses a layered caching strategy via Workbox:

```
┌─────────────────────────────────────────────┐
│                 App Shell                    │
│  (HTML, JS, CSS — cached on install)         │
│  Strategy: Cache First                       │
├─────────────────────────────────────────────┤
│              API Responses                   │
│  (manifests, bay data, user lists)           │
│  Strategy: Stale While Revalidate            │
├─────────────────────────────────────────────┤
│            Write Operations                  │
│  (scans, status updates, bay assignments)    │
│  Strategy: Network First → Offline Queue     │
├─────────────────────────────────────────────┤
│              Static Assets                   │
│  (icons, images, fonts)                      │
│  Strategy: Cache First, long TTL             │
└─────────────────────────────────────────────┘
```

### Offline Queue (The Outbox Pattern)

When the tablet loses connectivity, write operations don't fail — they queue.

```
1. User scans a consignment barcode
2. App checks navigator.onLine
3. IF online  → POST to Laravel API normally via Inertia
4. IF offline → Write to IndexedDB "outbox" table with timestamp
5. Service worker listens for "sync" event
6. When connectivity returns → replay queued operations in order
7. Server responds with any conflicts → surface to user
```

This is the most architecturally interesting part of the project. The outbox table in IndexedDB stores:
- The intended API endpoint
- The request payload
- A timestamp (for ordering and conflict resolution)
- A retry count
- A status (pending / syncing / failed / synced)

### Conflict Resolution Strategy

Keep it simple — **last write wins with notification**. When the sync replays and the server detects a conflict (e.g., another user already moved that consignment), the server accepts the latest write but returns a conflict flag. The app surfaces a toast: "Consignment X was already moved to Bay H2 by [user]. Your update applied."

For the portfolio, this is sufficient. In interviews, you can discuss more sophisticated strategies (vector clocks, CRDTs, operational transforms) and explain why last-write-wins was the pragmatic choice for this domain.

### IndexedDB Schema (Client-Side via Dexie.js)

```
outbox:        ++id, endpoint, payload, timestamp, status, retryCount
cached_manifests: consignmentId, data, cachedAt
cached_bays:   bayId, data, cachedAt
user_session:  key, value
```

Dexie.js wraps IndexedDB with a clean Promise-based API. It's lightweight, well-maintained, and avoids the pain of raw IndexedDB transactions.

### PWA Manifest & Installation

The Vite PWA plugin generates the manifest and service worker registration. On Android tablets, Chrome prompts "Add to Home Screen" — the app then launches in standalone mode (no browser chrome), which feels native.

Key manifest settings for tablet use:
- `display: "standalone"` — no URL bar
- `orientation: "landscape"` — warehouse tablets are landscape-mounted
- `theme_color` / `background_color` — branded splash screen on launch

---

## 4. Complete Database Schema

### Core Tables

```
users
├── id
├── name
├── email (unique)
├── pin_hash (bcrypt — 4-6 digit PIN for fast tablet auth)
├── role (enum: driver, unloader, loader, supervisor, ops_manager, admin)
├── depot_id (FK → depots)
├── is_active (boolean)
├── last_login_at (nullable, timestamp)
├── password (standard Laravel, for web admin access)
├── remember_token
└── timestamps

depots
├── id
├── name (e.g. "Melbourne South")
├── code (e.g. "MLBS" — short code for display)
├── address
├── timezone (e.g. "Australia/Melbourne")
├── is_active (boolean)
└── timestamps

shifts
├── id
├── depot_id (FK)
├── name (e.g. "PM Sort", "Night Load", "AM Dispatch")
├── start_time (time)
├── end_time (time)
├── is_active (boolean)
└── timestamps

shift_sessions
├── id
├── user_id (FK)
├── shift_id (FK)
├── depot_id (FK)
├── clock_in_at (timestamp)
├── clock_out_at (nullable, timestamp)
├── notes (nullable, text — handover notes)
└── timestamps
```

### Trailer & Run Tables

```
trailers
├── id
├── trailer_number (string, unique — e.g. "TR-4521")
├── trailer_type (enum: rigid, semi, b_double, container)
├── max_weight_kg (integer)
├── max_volume_m3 (decimal)
├── depot_id (FK — home depot)
├── current_status (enum: available, loading, loaded, dispatched, in_transit, at_dock)
├── current_bay_id (nullable, FK → dock_bays)
└── timestamps

runs
├── id
├── run_number (string — e.g. "MEL-SYD-0422")
├── trailer_id (FK)
├── depot_id (FK — origin)
├── destination_depot_id (nullable, FK)
├── scheduled_departure (datetime)
├── actual_departure (nullable, datetime)
├── cutoff_time (datetime — hard deadline for loading)
├── status (enum: planning, loading, ready, departed, cancelled)
├── assigned_loader_id (nullable, FK → users)
└── timestamps

dock_bays
├── id
├── depot_id (FK)
├── bay_number (string — e.g. "D1", "D2")
├── bay_type (enum: inbound, outbound, flex)
├── is_active (boolean)
└── timestamps
```

### Consignment & Scanning Tables

```
consignments
├── id
├── connote_number (string, unique — the barcode value)
├── sender_name (string)
├── sender_account (nullable, string)
├── receiver_name (string)
├── receiver_address (text)
├── receiver_suburb (string)
├── receiver_postcode (string)
├── receiver_state (string)
├── item_count (integer)
├── total_weight_kg (decimal)
├── total_volume_m3 (nullable, decimal)
├── service_code (string — e.g. "OVN", "EXP", "STD", "ECO")
├── service_description (string — e.g. "Overnight Express")
├── dangerous_goods (boolean, default false)
├── dg_class (nullable, string — ADG Code class)
├── dg_un_number (nullable, string)
├── special_instructions (nullable, text)
├── manifest_id (nullable, FK → manifests)
├── origin_depot_id (FK)
├── destination_depot_id (FK)
├── received_at (nullable, timestamp — when first scanned into warehouse)
├── dispatched_at (nullable, timestamp)
└── timestamps

manifests
├── id
├── manifest_number (string, unique)
├── run_id (nullable, FK)
├── depot_id (FK)
├── direction (enum: inbound, outbound)
├── total_consignments (integer)
├── total_items (integer)
├── total_weight_kg (decimal)
├── status (enum: pending, receiving, complete, dispatched)
├── created_by (FK → users)
└── timestamps

scans
├── id
├── consignment_id (FK)
├── user_id (FK)
├── scan_type (enum: receive, load, unload, bay_assign, bay_remove, dispatch, exception)
├── location_context (nullable, string — e.g. "Dock Bay D3", "Holding Bay H1")
├── notes (nullable, text)
├── scanned_at (timestamp — device time, may differ from created_at if offline)
├── synced_at (nullable, timestamp — when the offline scan was synced)
├── is_offline_scan (boolean, default false)
└── timestamps
```

### Freight Priority & Holding Bay Tables

```
priority_tiers (seeder — rarely changes)
├── id
├── code (string — P1, P2, P3, P4)
├── name (string — CRITICAL, STANDARD, FLEX, HOLD)
├── colour (string — hex code: #EF4444, #F59E0B, #3B82F6, #6B7280)
├── max_hold_hours (nullable, integer — P1: 0, P2: 12, P3: 48, P4: null)
├── sort_order (integer)
└── timestamps

priority_rules
├── id
├── rule_type (enum: service_code, customer_account, weight_threshold, dg_class, manual)
├── match_field (string — the field on consignments to check)
├── match_operator (enum: equals, contains, greater_than, less_than, in_list)
├── match_value (string — the value(s) to match against)
├── assigned_tier_id (FK → priority_tiers)
├── priority_order (integer — rules evaluated in this order, first match wins)
├── description (string — human-readable: "Overnight express → P1")
├── is_active (boolean)
├── created_by (FK → users)
└── timestamps

holding_bays
├── id
├── depot_id (FK)
├── name (string — e.g. "H1 — Bunnings Hold")
├── code (string — short display code: "H1", "H2")
├── default_tier_id (FK → priority_tiers)
├── max_pallet_spaces (integer)
├── current_count (integer — denormalised, updated via observer)
├── zone_description (nullable, text — "North wall, past racking row 12")
├── is_active (boolean)
└── timestamps

consignment_priorities
├── id
├── consignment_id (FK, unique)
├── priority_tier_id (FK)
├── auto_assigned_tier_id (FK — what the rules engine originally assigned)
├── was_manually_overridden (boolean, default false)
├── override_by (nullable, FK → users)
├── override_reason (nullable, text)
├── holding_bay_id (nullable, FK)
├── checked_into_bay_at (nullable, timestamp)
├── checked_out_of_bay_at (nullable, timestamp)
├── loaded_onto_run_id (nullable, FK → runs)
├── loaded_at (nullable, timestamp)
├── sla_deadline (nullable, datetime — calculated from service code + receive time)
└── timestamps
```

### Dangerous Goods Tables

```
dg_classes (seeder)
├── id
├── class_number (string — "1", "2.1", "2.2", "3", "4.1", etc.)
├── class_name (string — "Explosives", "Flammable Gases", etc.)
├── placard_colour (string — hex)
├── placard_symbol (string — description or icon reference)
├── storage_requirements (text)
└── timestamps

dg_segregation_rules (seeder — from ADG Code)
├── id
├── class_a_id (FK → dg_classes)
├── class_b_id (FK → dg_classes)
├── rule (enum: compatible, separated, segregated, incompatible)
├── min_separation_metres (nullable, decimal)
├── notes (nullable, text)
└── timestamps

dg_consignment_details
├── id
├── consignment_id (FK)
├── dg_class_id (FK)
├── un_number (string)
├── proper_shipping_name (string)
├── packing_group (nullable, enum: I, II, III)
├── quantity (decimal)
├── quantity_unit (string — e.g. "kg", "L")
├── emergency_contact (nullable, string)
├── erg_guide_number (nullable, string)
└── timestamps
```

### Notification Tables

```
warehouse_notifications
├── id
├── event_type (enum: p1_received, p1_stale, bay_near_capacity, bay_full,
│     freight_age_warning, freight_age_critical, priority_promoted,
│     trailer_cutoff_warning, run_ready, shift_handover)
├── severity (enum: info, warning, critical)
├── title (string)
├── body (text)
├── consignment_id (nullable, FK)
├── holding_bay_id (nullable, FK)
├── run_id (nullable, FK)
├── target_role (nullable, enum — role-based targeting)
├── target_user_id (nullable, FK — specific user targeting)
├── depot_id (FK)
├── acknowledged_at (nullable, timestamp)
├── acknowledged_by (nullable, FK → users)
├── escalated (boolean, default false)
├── escalated_at (nullable, timestamp)
└── timestamps
```

### Loadsheet & Trailer Loading Tables

```
loadsheets
├── id
├── run_id (FK)
├── trailer_id (FK)
├── loader_id (FK → users)
├── status (enum: draft, loading, complete, signed_off)
├── total_items_loaded (integer, default 0)
├── total_weight_loaded_kg (decimal, default 0)
├── total_volume_loaded_m3 (nullable, decimal)
├── estimated_axle_weight_front_kg (nullable, decimal)
├── estimated_axle_weight_rear_kg (nullable, decimal)
├── notes (nullable, text)
├── signed_off_by (nullable, FK → users)
├── signed_off_at (nullable, timestamp)
└── timestamps

loadsheet_items
├── id
├── loadsheet_id (FK)
├── consignment_id (FK)
├── load_position (nullable, string — e.g. "front-left", "rear-centre")
├── loaded_at (timestamp)
├── loaded_by (FK → users)
├── sequence_number (integer — order loaded)
└── timestamps
```

### Reporting & Metrics Tables

```
daily_metrics (aggregated by scheduled job)
├── id
├── depot_id (FK)
├── date (date)
├── total_consignments_received (integer)
├── total_consignments_dispatched (integer)
├── p1_count (integer)
├── p2_count (integer)
├── p3_count (integer)
├── p4_count (integer)
├── priority_inversions (integer — P3 left before P1/P2)
├── avg_p1_hold_minutes (decimal)
├── avg_p3_hold_minutes (decimal)
├── sla_breaches (integer)
├── bay_utilisation_pct (decimal)
└── timestamps
```

---

## 5. Laravel Architecture

### Directory Structure (What Matters)

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   └── PinAuthController.php
│   │   ├── DashboardController.php
│   │   ├── ConsignmentController.php
│   │   ├── ScanController.php
│   │   ├── ManifestController.php
│   │   ├── HoldingBayController.php
│   │   ├── FreightPriorityController.php
│   │   ├── LoadsheetController.php
│   │   ├── RunController.php
│   │   ├── TrailerController.php
│   │   ├── NotificationController.php
│   │   ├── DangerousGoodsController.php
│   │   ├── ReportController.php
│   │   └── Admin/
│   │       ├── DepotController.php
│   │       ├── UserController.php
│   │       ├── PriorityRuleController.php
│   │       └── BayConfigController.php
│   ├── Middleware/
│   │   ├── EnsureUserHasRole.php
│   │   └── TrackLastActivity.php
│   └── Requests/
│       ├── StoreScanRequest.php
│       ├── OverridePriorityRequest.php
│       ├── StoreLoadsheetItemRequest.php
│       └── ... (form request per write operation)
├── Models/
│   ├── User.php
│   ├── Depot.php
│   ├── Consignment.php
│   ├── ConsignmentPriority.php
│   ├── HoldingBay.php
│   ├── PriorityRule.php
│   ├── PriorityTier.php
│   ├── Scan.php
│   ├── Manifest.php
│   ├── Run.php
│   ├── Trailer.php
│   ├── Loadsheet.php
│   ├── LoadsheetItem.php
│   ├── DgClass.php
│   ├── DgSegregationRule.php
│   ├── DgConsignmentDetail.php
│   ├── WarehouseNotification.php
│   ├── Shift.php
│   ├── ShiftSession.php
│   ├── DockBay.php
│   └── DailyMetric.php
├── Services/
│   ├── FreightClassifier.php          ← Priority rule engine
│   ├── LoadPriorityBuilder.php        ← Smart load queue generation
│   ├── BayCapacityManager.php         ← Holding bay operations
│   ├── SlaCalculator.php              ← Deadline calculation from service codes
│   ├── AxleWeightEstimator.php        ← Physics-based weight distribution
│   ├── DgSegregationChecker.php       ← ADG Code compliance validation
│   ├── NotificationDispatcher.php     ← Route notifications to correct roles
│   ├── MetricsAggregator.php          ← Daily reporting calculations
│   └── OfflineSyncResolver.php        ← Conflict detection for synced scans
├── Events/
│   ├── ConsignmentReceived.php
│   ├── ConsignmentLoadedOntoRun.php
│   ├── FreightPriorityChanged.php
│   ├── HoldingBayCapacityWarning.php
│   ├── TrailerCutoffApproaching.php
│   └── SlaBreachImminent.php
├── Listeners/
│   ├── ClassifyConsignmentPriority.php
│   ├── AssignRecommendedBay.php
│   ├── UpdateBayCount.php
│   ├── SendPriorityNotification.php
│   ├── CheckTrailerCutoffs.php
│   └── LogMetricEvent.php
├── Observers/
│   ├── ConsignmentPriorityObserver.php  ← Update bay counts on change
│   └── ScanObserver.php                 ← Trigger events on scan creation
├── Policies/
│   ├── ConsignmentPolicy.php
│   ├── HoldingBayPolicy.php
│   ├── LoadsheetPolicy.php
│   ├── PriorityRulePolicy.php
│   └── RunPolicy.php
├── Enums/
│   ├── UserRole.php
│   ├── ScanType.php
│   ├── ConsignmentService.php
│   ├── TrailerStatus.php
│   ├── RunStatus.php
│   ├── NotificationSeverity.php
│   └── SegregationRule.php
└── Console/
    └── Commands/
        ├── CheckStalePriorityFreight.php   ← Scheduled: find P1 sitting too long
        ├── CheckBayCapacity.php            ← Scheduled: warn on near-full bays
        ├── AggregateDailyMetrics.php       ← Scheduled: nightly reporting rollup
        └── CheckTrailerCutoffs.php         ← Scheduled: warn 30 min before cutoff
```

### Key Laravel Patterns to Master

This project is designed to exercise the Laravel features that matter most in professional work:

**Service Classes** — All business logic lives in `/Services`, not in controllers. Controllers are thin: validate, call service, return Inertia response. This is the #1 pattern interviewers look for.

**Form Requests** — Every write operation gets a dedicated FormRequest class. Validation rules, authorisation checks, and custom messages all live here.

**Events & Listeners** — The scan-to-notification pipeline is entirely event-driven. `ConsignmentReceived` fires → `ClassifyConsignmentPriority` listener runs → `FreightPriorityChanged` fires → `SendPriorityNotification` listener runs. This decouples everything.

**Observers** — `ConsignmentPriorityObserver` watches for bay assignment changes and updates the denormalised `current_count` on `holding_bays`. Discuss why you denormalised (performance on the bay status board) and the tradeoff (potential drift, mitigated by the observer).

**Policies** — Role-based access. Loaders can't modify priority rules. Unloaders can't sign off loadsheets. Supervisors can do both. `EnsureUserHasRole` middleware for route-level checks, Policies for model-level checks.

**Enums** — PHP 8.1+ backed enums for every finite set. Type safety, IDE autocomplete, no magic strings.

**Scheduled Commands** — The console commands run on `schedule:run`. `CheckStalePriorityFreight` runs every 5 minutes. `AggregateDailyMetrics` runs at midnight. This demonstrates understanding of background processing.

**Queued Jobs** — Notification dispatch and metrics aggregation run on queues, not synchronously. Use Laravel's database queue driver for simplicity.

---

## 6. React Frontend Architecture

### Page Structure (Inertia Pages)

```
resources/js/
├── Pages/
│   ├── Auth/
│   │   └── PinLogin.jsx               ← Numeric keypad, large touch targets
│   ├── Dashboard/
│   │   └── Index.jsx                   ← Role-based: shows relevant widgets
│   ├── Unloading/
│   │   ├── ScanReceive.jsx             ← Camera barcode scanner + consignment display
│   │   └── BayAssignment.jsx           ← Recommended bay + override option
│   ├── Loading/
│   │   ├── TrailerSelect.jsx           ← Pick your assigned trailer/run
│   │   ├── PriorityQueue.jsx           ← Smart load queue — THE key screen
│   │   └── LoadsheetBuilder.jsx        ← Scan-to-load with position tracking
│   ├── Bays/
│   │   ├── StatusBoard.jsx             ← Full-screen bay overview (dock TV display)
│   │   ├── BayDetail.jsx               ← Single bay — all consignments listed
│   │   └── BulkReclassify.jsx          ← Supervisor multi-select tier change
│   ├── DangerousGoods/
│   │   ├── TrailerMap.jsx              ← Visual DG placement with segregation
│   │   └── SegregationCheck.jsx        ← Compatibility checker
│   ├── Runs/
│   │   ├── Index.jsx                   ← All runs for today, status overview
│   │   └── Detail.jsx                  ← Single run: manifest, loadsheet, timeline
│   ├── Handover/
│   │   └── ShiftHandover.jsx           ← Outgoing/incoming shift notes
│   ├── Reports/
│   │   ├── OperationalDashboard.jsx    ← Metrics, charts, KPIs
│   │   └── PriorityInversions.jsx      ← When P3 left before P1/P2
│   ├── Admin/
│   │   ├── PriorityRules.jsx           ← CRUD priority classification rules
│   │   ├── BayConfig.jsx               ← Manage holding bays per depot
│   │   ├── UserManagement.jsx          ← Create/edit users, assign roles
│   │   └── DepotConfig.jsx             ← Depot settings
│   └── Notifications/
│       └── Index.jsx                   ← Notification centre with acknowledge
├── Components/
│   ├── Scanner/
│   │   ├── BarcodeScanner.jsx          ← Barcode Detection API wrapper
│   │   └── ScanResult.jsx              ← Post-scan consignment card
│   ├── Priority/
│   │   ├── TierBadge.jsx              ← Colour-coded P1/P2/P3/P4 badge
│   │   ├── SlaCountdown.jsx           ← Time-to-breach countdown
│   │   ├── PriorityQueueItem.jsx      ← Single item in the load queue
│   │   └── OverrideModal.jsx          ← Tier change with reason capture
│   ├── Bays/
│   │   ├── BayCard.jsx                ← Single bay status card with fill bar
│   │   ├── BayGrid.jsx               ← Grid of BayCards for status board
│   │   └── CapacityBar.jsx           ← Visual fill level indicator
│   ├── Loading/
│   │   ├── LoadQueueList.jsx          ← Ordered priority list for trailer
│   │   ├── LoadsheetRow.jsx           ← Single loaded item with position
│   │   └── WeightGauge.jsx           ← Visual weight/capacity indicator
│   ├── DangerousGoods/
│   │   ├── TrailerLayoutCanvas.jsx    ← Interactive 2D trailer map
│   │   ├── DgBadge.jsx               ← DG class placard badge
│   │   └── SegregationAlert.jsx      ← Incompatibility warning
│   ├── Notifications/
│   │   ├── NotificationToast.jsx      ← Pop-up alert with acknowledge
│   │   └── NotificationBell.jsx       ← Header bell icon with count
│   ├── Layout/
│   │   ├── TabletLayout.jsx           ← Main layout: header, sidebar, content
│   │   ├── Header.jsx                 ← User info, shift, depot, notifications
│   │   ├── Sidebar.jsx                ← Role-based navigation
│   │   └── OfflineIndicator.jsx       ← Connection status banner
│   └── Shared/
│       ├── LargeButton.jsx            ← Touch-friendly button (min 48px target)
│       ├── NumericKeypad.jsx          ← For PIN entry and quantity inputs
│       ├── ConfirmDialog.jsx          ← Touch-friendly confirmation modal
│       ├── DataTable.jsx              ← Sortable, filterable table
│       └── EmptyState.jsx             ← Friendly empty state illustrations
├── Hooks/
│   ├── useBarcodeScan.js              ← Barcode Detection API hook
│   ├── useOnlineStatus.js             ← navigator.onLine + event listeners
│   ├── useOfflineQueue.js             ← Dexie.js outbox operations
│   ├── useSyncStatus.js               ← Pending sync count + last synced
│   └── useNotifications.js            ← Echo channel subscription
├── Lib/
│   ├── offlineDb.js                   ← Dexie.js database setup
│   ├── syncManager.js                 ← Outbox replay logic
│   └── priorityHelpers.js            ← Tier colour/label utilities
└── Layouts/
    ├── AuthLayout.jsx                 ← Minimal layout for login
    └── AppLayout.jsx                  ← Full app layout with sidebar
```

### Tablet UI Design Principles

These tablets are used by people wearing gloves, standing at docks, in variable lighting:

- **Minimum touch target: 48px** (Android Material Design guideline) — most buttons should be larger
- **High contrast colours** — P1 red, P2 amber, P3 blue, P4 grey on dark or white backgrounds
- **Large text** — minimum 16px body, 24px+ for scanned consignment data
- **One-hand operation where possible** — primary actions on the right side (most people are right-handed)
- **No hover states** — everything is tap/press
- **Landscape orientation** — warehouse tablets are mounted landscape
- **Dark mode option** — night shift workers in brightly-lit warehouses benefit from reduced glare

---

## 7. Feature Modules (Build Order)

### Phase 1: Foundation (Weeks 1–2)

**Goal:** Auth, layout, basic navigation, PWA shell.

**1A: Project Setup**
- `laravel new tge-warehouse`
- Install Breeze with React + Inertia stack
- Configure Tailwind, Vite PWA plugin (vite-plugin-pwa)
- Set up Sail for local Docker development
- Configure Pest for testing
- Seed depots, shifts, priority tiers, DG classes

**1B: PIN Authentication**
- Custom `PinAuthController` that authenticates via PIN instead of email/password
- `NumericKeypad` component — large buttons, tactile feedback (CSS active states)
- Session-based auth (no tokens — these are shared depot tablets)
- Role stored in session for middleware checks
- Auto-logout after 15 minutes of inactivity (configurable per depot)

**1C: Tablet Layout Shell**
- `TabletLayout` with collapsible sidebar
- Role-based navigation (unloader sees different menu than loader)
- `OfflineIndicator` banner — green dot when online, yellow "Offline — changes will sync" when not
- `NotificationBell` in header
- PWA manifest + service worker registration via Vite plugin

**1D: Testing Foundation**
- Feature test for PIN auth flow
- Unit test for role-based middleware
- Vitest setup for React components
- Test `TierBadge` renders correct colour per tier

---

### Phase 2: Scanning & Receiving (Weeks 3–4)

**Goal:** Unloaders can scan incoming freight and see consignment details.

**2A: Barcode Scanner**
- `useBarcodeScan` hook wrapping the Barcode Detection API
- Camera stream via `getUserMedia({ video: { facingMode: 'environment' } })`
- Continuous frame analysis against `BarcodeDetector`
- QuaggaJS fallback for browsers without native support
- Scan sound/vibration feedback on successful read
- Debounce to prevent double-scans

**2B: Consignment Receiving**
- `ScanReceive` page: camera viewfinder + result card below
- On scan → lookup consignment by connote number
- Display: customer, items, weight, service code, DG flag
- Create `scans` record with type `receive`
- If consignment not found in DB → show "Unknown consignment" with manual entry option

**2C: Manifest Management**
- `ManifestController` — list inbound manifests for today
- Manifest detail page showing all consignments with received/pending status
- Progress bar: "42/58 items received"
- Manifests seeded from demo data (in production, these would come from TGE's TMS)

**2D: Offline Scanning**
- `useOfflineQueue` hook — detect offline, write scan to IndexedDB
- Visual indicator on scan result: "Saved offline — will sync when connected"
- `syncManager` — background sync on reconnection
- Test: disconnect network, perform scan, reconnect, verify sync

---

### Phase 3: Freight Priority System (Weeks 5–7)

**Goal:** Auto-classification, holding bays, priority dashboard, notifications.

**3A: Priority Classification Engine**
- `FreightClassifier` service — evaluate rules in priority_order, first match wins
- Triggered by `ConsignmentReceived` event → `ClassifyConsignmentPriority` listener
- `SlaCalculator` service — compute deadline from service code + receive time
- Create `consignment_priorities` record with auto-assigned tier
- Test: seed rules, run classifier against sample consignments, verify correct tiers

**3B: Holding Bay Assignment**
- After classification, `AssignRecommendedBay` listener suggests a bay
- Logic: find bays matching the tier with available capacity, prefer the bay with most space
- `BayAssignment` page: shows recommended bay with one-tap confirm + override dropdown
- On confirm → create scan record (type `bay_assign`), update `consignment_priorities`
- `BayCapacityManager` service — increment/decrement counts, check thresholds

**3C: Bay Status Board**
- `StatusBoard` page — designed for wall-mounted dock displays
- `BayGrid` renders all active bays as `BayCard` components
- Each `BayCard` shows: name, fill bar, consignment count, oldest item age, dominant tier colour
- Auto-refresh via polling (every 30 seconds) or Echo WebSocket for real-time
- Pulsing red border on any bay holding P1 freight
- Amber warning on bays exceeding 80% capacity

**3D: Loader Priority Queue**
- `PriorityQueue` page — THE most important screen
- Loader selects their run → `LoadPriorityBuilder` service generates the queue
- Queue ordering: P1 sorted by SLA deadline (earliest first) → P2 by scheduled time → P3 by age (oldest first) → P4 never auto-included
- Each `PriorityQueueItem` shows: tier badge, customer, connote, items, weight, SLA countdown, current bay location
- P1 items have pulsing red SLA countdown
- "Load Next" button on each item → opens scan-to-verify flow

**3E: Priority Override & Bulk Reclassify**
- `OverrideModal` — supervisor taps a consignment, selects new tier, enters reason
- `BulkReclassify` page — multi-select consignments, apply new tier in one action
- All overrides logged: who, when, from-tier, to-tier, reason
- `FreightPriorityChanged` event fires → updates dashboard in real-time

**3F: Notification System**
- `NotificationDispatcher` service — creates `warehouse_notifications` records
- Routes to correct users based on `target_role` and `depot_id`
- `NotificationToast` component — slides in from top-right
- Acknowledge action — tap to dismiss + record acknowledgement
- Escalation: if P1 freight sits > 30 min unacknowledged → escalate to ops manager
- `CheckStalePriorityFreight` scheduled command runs every 5 minutes

**3G: Priority Rules Admin**
- `PriorityRules` admin page — CRUD interface for classification rules
- Each rule: type, match criteria, assigned tier, priority order
- Drag-and-drop reordering of rule priority
- "Test Rule" button — enter a sample consignment, see which tier it gets
- Only accessible to admin and ops_manager roles

---

### Phase 4: Loading & Loadsheets (Weeks 8–9)

**Goal:** Loaders can build trailers using the priority queue, generate digital loadsheets.

**4A: Run Management**
- `RunController` — list today's runs, filter by status
- Run detail page: assigned trailer, scheduled departure, cutoff countdown, manifest, loadsheet
- Cutoff warning: `CheckTrailerCutoffs` command fires `TrailerCutoffApproaching` event at 30 min before

**4B: Loadsheet Builder**
- `LoadsheetBuilder` page — the digital replacement for paper loadsheets
- Loader scans each item from the priority queue → consignment verified against the run's manifest
- Each scan creates a `loadsheet_items` record with position and sequence
- Running totals: items loaded, weight loaded, estimated capacity remaining
- `WeightGauge` component — visual indicator approaching max weight

**4C: Axle Weight Estimation**
- `AxleWeightEstimator` service — basic lever arm calculation
- Input: total weight, load position (front/centre/rear), trailer type
- Output: estimated front and rear axle weights
- Warning if either axle exceeds legal limit
- This is a portfolio differentiator — applied physics in software

**4D: Loadsheet Sign-Off**
- Supervisor reviews completed loadsheet
- Sign-off records: who approved, timestamp
- Status changes: loading → complete → signed_off
- Dispatched consignments get `dispatched_at` timestamp

---

### Phase 5: Dangerous Goods (Weeks 10–11)

**Goal:** DG mapping, segregation checking, compliance validation.

**5A: DG Data & Segregation Rules**
- Seed `dg_classes` and `dg_segregation_rules` from ADG Code
- 9 primary classes + subdivisions
- Segregation matrix: compatible / separated / segregated / incompatible

**5B: Segregation Checker**
- `DgSegregationChecker` service — given a list of DG items on a trailer, check all pairwise combinations
- Returns: list of conflicts with severity and required separation
- `SegregationCheck` page — enter or scan DG items, get instant compliance report
- `SegregationAlert` component — red warning with specific incompatibility

**5C: Trailer DG Map**
- `TrailerLayoutCanvas` — 2D top-down view of trailer
- DG items placed visually with class placard badges
- Colour-coded zones showing safe placement areas
- Real-time incompatibility warnings as items are placed
- This feature doesn't exist in TGE's current system — pure value-add

---

### Phase 6: Reporting & Polish (Week 12)

**Goal:** Operational metrics, deployment, final testing.

**6A: Operational Dashboard**
- `OperationalDashboard` page — charts and KPIs
- Priority inversion rate (trending toward zero)
- Average hold time by tier
- Bay utilisation over time
- SLA breach rate
- Use Recharts or Chart.js for visualisations

**6B: Shift Handover**
- `ShiftHandover` page — outgoing shift documents current state
- Open P1 freight, bay status, in-progress loadsheets, known issues
- Incoming shift acknowledges handover
- Creates audit trail

**6C: Demo Data & Seeding**
- Comprehensive seeders: realistic depot, users, consignments, manifests
- Bunnings-style bulk freight seeded as P3
- Overnight express seeded as P1
- Pre-loaded holding bays with varied states
- DG consignments with segregation scenarios

**6D: Testing**
- Feature tests for every controller action
- Unit tests for all services (FreightClassifier, AxleWeightEstimator, DgSegregationChecker)
- React component tests for critical UI (BarcodeScanner, PriorityQueueItem, TierBadge)
- Offline queue test: mock offline → scan → mock reconnect → verify sync

---

## 8. Key Route Structure

```php
// Auth
Route::get('/login', [PinAuthController::class, 'show']);
Route::post('/login', [PinAuthController::class, 'authenticate']);
Route::post('/logout', [PinAuthController::class, 'logout']);

// Authenticated routes
Route::middleware(['auth', 'track.activity'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Scanning & Receiving
    Route::get('/scan/receive', [ScanController::class, 'receiveForm']);
    Route::post('/scan', [ScanController::class, 'store']);
    Route::get('/manifests', [ManifestController::class, 'index']);
    Route::get('/manifests/{manifest}', [ManifestController::class, 'show']);

    // Holding Bays
    Route::get('/bays', [HoldingBayController::class, 'index']);          // Status board
    Route::get('/bays/{holdingBay}', [HoldingBayController::class, 'show']);
    Route::post('/bays/{holdingBay}/assign', [HoldingBayController::class, 'assignConsignment']);
    Route::post('/bays/{holdingBay}/remove', [HoldingBayController::class, 'removeConsignment']);

    // Freight Priority
    Route::get('/priority/queue/{run}', [FreightPriorityController::class, 'queue']);
    Route::post('/priority/{consignment}/override', [FreightPriorityController::class, 'override']);
    Route::post('/priority/bulk-reclassify', [FreightPriorityController::class, 'bulkReclassify']);

    // Loading & Loadsheets
    Route::get('/runs', [RunController::class, 'index']);
    Route::get('/runs/{run}', [RunController::class, 'show']);
    Route::get('/loadsheets/{loadsheet}', [LoadsheetController::class, 'show']);
    Route::post('/loadsheets/{loadsheet}/items', [LoadsheetController::class, 'addItem']);
    Route::post('/loadsheets/{loadsheet}/sign-off', [LoadsheetController::class, 'signOff']);

    // Dangerous Goods
    Route::get('/dg/check', [DangerousGoodsController::class, 'segregationForm']);
    Route::post('/dg/check', [DangerousGoodsController::class, 'checkSegregation']);
    Route::get('/dg/trailer-map/{run}', [DangerousGoodsController::class, 'trailerMap']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/acknowledge', [NotificationController::class, 'acknowledge']);

    // Reports (supervisor+ only)
    Route::middleware('role:supervisor,ops_manager,admin')->group(function () {
        Route::get('/reports/dashboard', [ReportController::class, 'dashboard']);
        Route::get('/reports/priority-inversions', [ReportController::class, 'priorityInversions']);
    });

    // Admin
    Route::middleware('role:admin,ops_manager')->prefix('admin')->group(function () {
        Route::resource('users', Admin\UserController::class);
        Route::resource('priority-rules', Admin\PriorityRuleController::class);
        Route::resource('bay-config', Admin\BayConfigController::class);
        Route::resource('depots', Admin\DepotController::class);
    });

    // Shift
    Route::get('/handover', [ShiftController::class, 'handoverForm']);
    Route::post('/handover', [ShiftController::class, 'submitHandover']);
});

// API routes for offline sync
Route::middleware('auth:sanctum')->prefix('api')->group(function () {
    Route::post('/sync/scans', [SyncController::class, 'syncScans']);
    Route::get('/sync/status', [SyncController::class, 'status']);
});
```

---

## 9. The Barcode Scanner Implementation

This deserves its own section because it's the core interaction pattern.

### Browser API Approach

```
1. Check: does window.BarcodeDetector exist?
   ├── YES → use native BarcodeDetector API (Chrome Android)
   └── NO  → load QuaggaJS as polyfill

2. Request camera: navigator.mediaDevices.getUserMedia({
     video: { facingMode: 'environment', width: 1280, height: 720 }
   })

3. Render video stream to a <video> element

4. On each animation frame:
   - Capture frame from video
   - Pass to BarcodeDetector.detect() or QuaggaJS
   - If barcode found:
     a. Play success sound (short beep via Web Audio API)
     b. Trigger haptic feedback (navigator.vibrate(200))
     c. Debounce (ignore same barcode for 2 seconds)
     d. Call onScan callback with barcode value

5. Look up consignment by connote_number
   ├── Found → display consignment card, proceed to action
   └── Not found → show "Unknown" state with manual entry fallback
```

### Performance Notes for Warehouse Use

- Use rear camera (`facingMode: 'environment'`) — warehouse barcodes are on boxes, not screens
- Request 720p minimum — standard 1D barcodes need reasonable resolution
- Process every 3rd frame (not every frame) to reduce CPU load on older tablets
- Torch/flashlight: `track.applyConstraints({ advanced: [{ torch: true }] })` for dark areas
- The native `BarcodeDetector` API is significantly faster than QuaggaJS — it runs on the device's ML hardware

---

## 10. Offline Strategy Deep Dive

### What Works Offline

| Feature | Offline Support | How |
|---------|----------------|-----|
| PIN Login | Yes (cached session) | Service worker caches auth state |
| Scan & receive | Yes (queued) | Scans write to IndexedDB outbox |
| View cached manifests | Yes (read-only) | Manifests cached in IndexedDB on last fetch |
| Bay assignment | Yes (queued) | Assignment writes to outbox, UI updates optimistically |
| View bay status | Partial (stale data) | Last-fetched bay state cached, shows "Last updated X min ago" |
| Priority queue | Partial (stale) | Cached queue data, but can't reflect other users' changes |
| Loadsheet building | Yes (queued) | Each "load item" scan queues, loadsheet built locally |
| Notifications | No | Requires server connection |
| Reports | No | Requires fresh data |
| Admin CRUD | No | Requires server connection |

### Sync Priority

When connection returns, the outbox replays in this order:
1. **Scans** — most critical, establishes chain of custody
2. **Bay assignments** — updates holding bay counts
3. **Loadsheet items** — completes the loading record
4. **Priority overrides** — supervisory actions

---

## 11. Demo Data Strategy

### Seed Scenario: "Tuesday PM Sort at Melbourne South"

Create a realistic snapshot that demonstrates every feature:

**Users:**
- 3 unloaders (PIN: 1111, 1112, 1113)
- 3 loaders (PIN: 2221, 2222, 2223)
- 1 supervisor (PIN: 3331)
- 1 ops manager (PIN: 4441)
- 1 admin (PIN: 9999)

**Inbound manifests (being received):**
- Sydney → Melbourne: 58 consignments, mixed priority
- Brisbane → Melbourne: 34 consignments, includes DG items
- Adelaide → Melbourne: 22 consignments, heavy Bunnings bulk

**Outbound runs (being loaded):**
- MEL → SYD Overnight Express: cutoff 18:30, P1 heavy
- MEL → GEE Standard: cutoff 20:00, P2 mixed
- MEL → BEN Economy: cutoff 22:00, P3 acceptable

**Holding bays:**
- H1 "Bunnings Hold" — 12/20 pallet spaces, all P3 Bunnings
- H2 "Standard Overflow" — 8/15 spaces, mixed P2
- H3 "DG Staging" — 3/10 spaces, DG consignments awaiting segregation check
- H4 "Exceptions" — 2/10 spaces, address query + damaged item

**Pre-seeded scenarios to demonstrate:**
- 2 P1 consignments sitting in H2 that should have been loaded (triggers stale P1 alert)
- Bunnings consignment manually overridden from P3 → P1 (store opening stock)
- DG Class 3 (flammable liquid) and Class 5.1 (oxidiser) both on Melbourne-Sydney run (segregation conflict)
- One consignment scanned offline 20 minutes ago, pending sync

---

## 12. Testing Strategy

### What to Test (Priority Order)

**1. Services (Unit Tests — Pest)**
- `FreightClassifier` — given rules + consignment, returns correct tier
- `SlaCalculator` — given service code + receive time, returns correct deadline
- `LoadPriorityBuilder` — given consignments, returns correctly ordered queue
- `DgSegregationChecker` — given DG items, returns correct conflicts
- `AxleWeightEstimator` — given weight + position, returns correct axle loads
- `BayCapacityManager` — increment, decrement, threshold warnings

**2. Feature Tests (HTTP Tests — Pest)**
- PIN auth: valid PIN → authenticated, invalid PIN → rejected
- Scan store: creates scan record, fires ConsignmentReceived event
- Bay assignment: updates consignment_priorities, increments bay count
- Priority override: requires supervisor role, logs override reason
- Loadsheet sign-off: requires supervisor, updates status

**3. Event/Listener Tests**
- ConsignmentReceived → ClassifyConsignmentPriority runs → priority record created
- FreightPriorityChanged → correct notification dispatched to correct role

**4. React Component Tests (Vitest + Testing Library)**
- `TierBadge` renders correct colour and label for each tier
- `SlaCountdown` shows correct time remaining, turns red under 2 hours
- `BarcodeScanner` calls onScan callback with barcode value
- `PriorityQueueItem` displays consignment data in correct order
- `OfflineIndicator` shows correct state for online/offline

**5. Integration / E2E (optional stretch)**
- Full scan → classify → assign bay → load onto trailer flow
- Offline scan → reconnect → sync → verify server state

---

## 13. Interview Talking Points

### The Elevator Pitch

"I work afternoon shifts at a national logistics warehouse. I watched our tablet system cost the company money every day — important overnight express freight getting buried behind bulk Bunnings deliveries because nobody had visibility into what was urgent. I built a progressive web app replacement using Laravel 12, Inertia.js, and React that auto-classifies freight by priority, directs it to designated holding bays, and gives loaders a smart queue that ensures critical freight always loads first. It includes dangerous goods segregation mapping that doesn't exist in the current system, an offline-first architecture for unreliable warehouse WiFi, and barcode scanning via the browser's native detection API."

### Prepared Questions & Answers

**"Why a PWA instead of a native app?"**
The warehouse tablets run Chrome on Android. The Barcode Detection API gives me native-speed scanning without an app store. Service workers handle offline caching. PWA install makes it feel native — no URL bar, splash screen, home screen icon. And it keeps the project in my primary stack: Laravel + Inertia + React. I evaluated NativePHP Mobile but the features I needed — barcode scanning, biometrics, push notifications — were all premium plugins. PWA gave me 90% of the capability at zero additional cost, using tools I already know deeply.

**"Walk me through the priority system architecture."**
When a consignment is scanned in, a `ConsignmentReceived` event fires. A listener calls the `FreightClassifier` service, which evaluates a table of configurable rules in priority order — first match wins. The result creates a `consignment_priorities` record with the auto-assigned tier. A second listener recommends a holding bay based on tier and available capacity. When a loader opens their trailer's load queue, the `LoadPriorityBuilder` service generates an ordered list: P1 sorted by SLA deadline, P2 by scheduled time, P3 by age. Everything is event-driven and decoupled — the classifier doesn't know about bays, bays don't know about loadsheets.

**"How does offline work?"**
I use the outbox pattern. All write operations check `navigator.onLine`. If offline, the operation writes to an IndexedDB "outbox" table via Dexie.js. The UI updates optimistically. When connectivity returns, a background sync manager replays queued operations in order — scans first, then bay assignments, then loadsheet items. The server detects conflicts via timestamps and returns a conflict flag. I chose last-write-wins for conflict resolution because in this domain, the most recent physical action is almost always correct — if two people scanned the same consignment, the second scan reflects reality.

**"Why are tests important in this project?"**
The `FreightClassifier` is a rules engine — without tests, a rule change could silently misroute overnight express freight to a holding bay. The `DgSegregationChecker` validates compliance with the Australian Dangerous Goods Code — getting that wrong has real safety implications. Testing isn't optional when the software makes decisions that affect physical operations. I test services with unit tests, controllers with feature tests, and critical React components with Vitest.

**"What's the hardest technical challenge?"**
The offline sync. Not the happy path — that's straightforward. The hard part is conflict resolution when multiple tablets are operating offline simultaneously. Two unloaders scan the same consignment. One assigns it to Bay H1, the other to Bay H2. When both sync, which wins? I implemented timestamp-based resolution where the server accepts the latest write, but surfaces a conflict notification so the supervisor can verify the physical location. In a production system I'd consider event sourcing for a complete audit trail, but for this scope, last-write-wins with notifications is the pragmatic choice.

**"How would this scale to multiple depots?"**
Each depot is a tenant — users, bays, runs, and manifests are all scoped to a depot via foreign keys. Priority rules can be national (default) or depot-specific overrides. The schema already supports this via the `depot_id` on every operational table. For true multi-tenancy at scale, I'd add a global middleware that scopes all queries to the authenticated user's depot.

**"What would you add with more time?"**
Voice commands for hands-free scanning — the Web Speech API could handle "scan," "load," "next" commands. Real-time WebSocket updates so the bay status board refreshes instantly when any tablet scans. Integration with the company's TMS for live manifest data instead of seeded demo data. And a reporting export pipeline — PDF loadsheets, CSV metric exports — so management can pull data into their existing BI tools.

---

## 14. Legal & Ethical Reminders

- **Do not use TGE branding, logos, or actual operational data anywhere**
- **Use generic warehouse scenarios** with realistic but fictional demo data
- **Frame as:** "Inspired by warehouse operations experience at a national logistics company"
- **Do not claim this is built for TGE** or that TGE endorsed/commissioned it
- **Customer names in seed data:** Use fictional companies (e.g. "Castlewood Hardware" not "Bunnings")
- **If pitching to TGE later:** That's a separate conversation, outside the portfolio context
- **The domain expertise is yours** — your observations, your workflow analysis, your ideas. That's not proprietary. The specific company data and systems are.

---

## 15. Development Workflow Reminder

Your deliberate practice protocol applies to every feature:

```
1. Read this blueprint section for the feature
2. Attempt implementation solo (minimum 30 minutes)
3. When stuck, write pseudocode for what you think should happen
4. Compare your approach against Claude's scaffolding
5. Identify the gaps — WHY did you miss something?
6. Create Anki cards from the differences
7. Write tests FIRST for the next feature (TDD where possible)
8. Commit with descriptive messages that explain WHY, not just WHAT
```

This project should produce 50+ Anki cards on Laravel patterns, 20+ on React/PWA patterns, and a comprehensive Git history that demonstrates professional development practices.

---

## 16. Git Strategy

### Branch Structure
```
main              ← production-ready, tagged releases
├── develop       ← integration branch
├── feature/auth-pin-login
├── feature/barcode-scanner
├── feature/freight-priority-engine
├── feature/holding-bay-system
├── feature/loader-priority-queue
├── feature/loadsheet-builder
├── feature/dg-segregation
├── feature/offline-sync
├── feature/notifications
├── feature/reporting-dashboard
└── feature/admin-panel
```

### Commit Convention
```
feat(priority): implement FreightClassifier service with rule engine
test(priority): add unit tests for tier assignment edge cases
fix(scanner): debounce duplicate barcode reads within 2s window
refactor(bays): extract BayCapacityManager from controller
docs(readme): add PWA installation instructions
```

Every feature branch gets a PR (even solo) with a description of what it does, why, and how to test it. This is portfolio evidence of professional workflow.

---

## 17. Quick Reference: Setup Commands

```bash
# Create project
laravel new tge-warehouse
cd tge-warehouse

# Install Breeze with React + Inertia
composer require laravel/breeze --dev
php artisan breeze:install react --typescript

# Install additional dependencies
composer require laravel/echo
npm install dexie workbox-window @niconi/quagga2 recharts

# Vite PWA plugin
npm install -D vite-plugin-pwa

# Testing
composer require pestphp/pest --dev
composer require pestphp/pest-plugin-laravel --dev
php artisan pest:install

# Development
php artisan sail:install
./vendor/bin/sail up -d
npm run dev

# Database
php artisan migrate:fresh --seed
```

---

## 18. Deployment: Coolify + Hetzner

### Why This Stack
- **Coolify** — free, self-hosted PaaS. Push-to-deploy, auto SSL, database management, Docker under the hood.
- **Hetzner** — cheapest quality VPS in the game. ~€4.50/mo for a CX22 (2 vCPU, 4GB RAM) gets you more than enough.
- Total cost: **~$5–7/mo**. Interview story: "I self-host on a VPS using Docker via Coolify."

### Setup Steps (One-Time, ~2 Hours)

**1. Hetzner VPS**
- Sign up at hetzner.com/cloud → create a CX22 server (Ubuntu 24.04, Falkenstein or Helsinki)
- Add your SSH key during creation
- Note the IP address

**2. Install Coolify**
```bash
ssh root@YOUR_IP
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
```
- Access Coolify UI at `http://YOUR_IP:8000`
- Create admin account, add localhost as a server

**3. Connect GitHub**
- Coolify dashboard → Sources → Add GitHub App
- Authorise access to your `tge-warehouse` repo

**4. Create Resources**
- New Project → New Resource → select your repo
- Coolify auto-detects Laravel via Nixpacks
- Add a MySQL database resource separately
- Copy the generated DB credentials into your app's environment variables

**5. Environment Variables**
Set these in Coolify's UI for your app:
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
DB_CONNECTION=mysql
DB_HOST=(from Coolify)
DB_DATABASE=(from Coolify)
DB_USERNAME=(from Coolify)
DB_PASSWORD=(from Coolify)
```

**6. Domain & SSL**
- Point your domain's A record to your Hetzner IP
- Set the domain in Coolify → automatic Let's Encrypt SSL

**7. Deploy**
- Push to `main` → Coolify auto-deploys
- First deploy runs `composer install`, `npm run build`, `php artisan migrate`

### Post-Deploy Checklist
- Run `php artisan migrate --force` (Coolify can do this automatically via Nixpacks config)
- Verify service worker registers and PWA is installable
- Test on an actual Android tablet — Chrome → "Add to Home Screen"
- Set up a scheduled task in Coolify for `php artisan schedule:run`
- Configure a queue worker for notification dispatch

### nixpacks.toml (Root of Repo)
```toml
[phases.setup]
nixPkgs = ["nodejs_20"]

[phases.build]
cmds = ["npm ci", "npm run build", "php artisan migrate --force"]

[start]
cmd = "php artisan serve --host=0.0.0.0 --port=8000"
```

You'll likely need to tweak this as you go — Coolify's docs and the Nixpacks Laravel examples are your friends here. The point is to get it deployed early (even Phase 1 — just the login screen) so deployment isn't a scary last-minute task.

---

*This blueprint is your complete roadmap. Every section maps to real Laravel patterns you'll use professionally. Build it module by module, test as you go, and document your decisions in your dev log. The architecture and domain thinking here is what separates this from tutorial projects — interviewers will see someone who solves real problems, not someone who follows instructions.*
