# KPFC Fleet Management — Development TODO

> Based on [FLEETDOC.MD](FLEETDOC.MD)

---

## Phase 1 — Existing Foundation Review

- [ ] Inspect current migrations
- [ ] Inspect `VehicleDeployment` model
- [ ] Inspect deployment controller
- [ ] Inspect `Vehicle` model and its existing relationships
- [ ] Inspect existing deployment statuses
- [ ] Confirm current database schema
- [ ] Inspect current `shops` table
- [ ] Confirm existing shop relationships
- [ ] Identify how the existing 44 shops are currently seeded
- [ ] Decide: does `vehicle_deployments` become the trip entity, get renamed into trips, or remain as an assignment layer beneath trips?

---

## Phase 2 — Shop Reference Preparation

- [ ] Keep existing `shops` table (do not delete existing shop records)
- [ ] Add `external_shop_id` column to `shops` table
- [ ] Determine how existing shops map to Business/Finance shop IDs
- [ ] Add `synced_at` and other synchronization metadata fields to `shops`
- [ ] Add Fleet-specific fields: `geofence_radius`, `operational_notes`, `status`
- [ ] Preserve existing vehicle/shop relationships
- [ ] Verify location-resolution waterfall still uses local `shops` table for geofencing

---

## Phase 3 — Transport Domain

- [ ] Create `transport_requests` migration and model
  - [ ] Fields: `request_number`, `request_type`, `source_system`, `source_type`, `source_id`, `source_reference`, `requested_by_external_user_id`, `requested_at`, `requested_for_date`, `priority`, `status`, `title`, `description`, `special_instructions`, `pickup_location`, `vehicle_requirements`
  - [ ] Request types: `lpo_collection`, `invoice_delivery`, `dispatch_invoice_to_shop`, `custom_transport`
- [ ] Create `transport_request_stops` migration and model
  - [ ] Fields: `transport_request_id`, `sequence`, `stop_type`, `external_shop_id`, `shop_id`, `location_name`, `address`, `latitude`, `longitude`, `contact_phone`, `delivery_instructions`, `source_reference`
- [ ] Create `trips` migration and model
  - [ ] Fields: `transport_request_id`, `vehicle_id`, `external_user_id` (driver), `planned_start`, `actual_start`, `planned_end`, `actual_end`, `starting_mileage`, `ending_mileage`, `trip_mileage`, `status`, `planned_route`, `actual_route`
- [ ] Create `trip_stops` migration and model
  - [ ] Fields: `trip_id`, `sequence`, `stop_type`, `shop_id`, `external_shop_id`, `location_name`, `address`, `latitude`, `longitude`, `contact_phone`, `delivery_instructions`, `status`, `arrived_at`, `departed_at`
  - [ ] Stop types: `pickup`, `delivery`, `return`, `waypoint`
  - [ ] Enforce sequential stop completion logic
- [ ] Establish relationships between `trips`, `vehicles`, `shops`, external users
- [ ] Write factories and seeders for transport domain models
- [ ] Write feature tests for transport domain

---

## Phase 4 — Integration API

- [ ] Design and document the full API contract between Business and Fleet apps
- [ ] Create `POST /api/integration/shops/sync` — shop synchronization endpoint
- [ ] Create `GET /api/integration/shops/{external_shop_id}`
- [ ] Create `POST /api/integration/transport-requests`
- [ ] Create `GET /api/integration/transport-requests/{id}`
- [ ] Create `PATCH /api/integration/transport-requests/{id}`
- [ ] Create `POST /api/integration/transport-requests/{id}/cancel`
- [ ] Create `GET /api/integration/vehicles`
- [ ] Create `GET /api/integration/vehicles/{vehicle}`
- [ ] Create `GET /api/integration/vehicles/{vehicle}/availability`
- [ ] Create `GET /api/integration/vehicles/{vehicle}/location`
- [ ] Implement integration API authentication (inspect Business/Finance auth before deciding: Sanctum / Passport / signed tokens)
- [ ] Write feature tests for integration API

---

## Phase 5 — Fleet Manager Operations

- [ ] Transport request queue view (list unassigned requests)
- [ ] Trip creation from transport request
- [ ] Vehicle assignment to trip
- [ ] Driver (external user) assignment to trip
- [ ] Add/remove/reorder trip stops
- [ ] Route calculation via OSRM for multi-stop trips (origin → stop 1 → stop 2 → … → base)
- [ ] Route recalculation on stop sequence change
- [ ] Vehicle availability views: `available_at_base`, `en_route`, `assigned`, `unavailable`, `maintenance`, `out_of_service`, `provisionally_available`
- [ ] Provisional availability logic derived from current trip, location, and expected return time
- [ ] Trip scheduling

---

## Phase 6 — Driver Operations

- [x] Driver API: get active trip
- [x] Driver API: get upcoming trips
- [x] Driver API: get assigned vehicle
- [x] Driver API: manually start a trip (records actual start time, starting mileage, starting location)
- [x] Driver API: get ordered stops for current trip
- [x] Driver API: mark stop as arrived
- [x] Driver API: submit return-to-base request
- [x] Fleet Manager API: review and approve/deny return-to-base requests
  - [x] Record: driver, trip, reason, current location, undelivered stops, request time, decision, decision maker, decision time, manager comments
- [x] Driver API: complete trip

---

## Phase 7 — Delivery Verification

- [ ] Create `delivery_confirmations` migration and model
  - [ ] Fields: `trip_stop_id`, `verification_method`, `verified_at`, `verified_by`, `override_reason`
- [ ] SMS trigger when vehicle approaches/arrives at destination
- [ ] Generate and store secure verification code (hashed, not plaintext)
- [ ] Code validation endpoint (driver submits code received from customer)
- [ ] Manual override for cases where SMS verification fails
- [ ] Audit trail for every delivery confirmation and override
- [ ] Write feature tests for verification flow

---

## Phase 8 — Mileage & Auditing

- [ ] Pull mileage/odometer from Protrack365 telemetry on trip start
- [ ] Pull mileage/odometer from Protrack365 telemetry on trip completion
- [ ] Calculate `trip_mileage = ending_odometer - starting_odometer`
- [ ] Update cumulative vehicle mileage
- [ ] Create `audit_logs` migration and model
  - [ ] Fields: `actor_external_user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `timestamp`, `metadata`
- [ ] Hook audit logging into all operationally important actions:
  - [ ] Vehicle assignment
  - [ ] Driver assignment
  - [ ] Trip creation / modification / cancellation
  - [ ] Stop completion
  - [ ] Delivery override
  - [ ] Return request and approval
  - [ ] Maintenance changes
  - [ ] Inventory changes
  - [ ] Tool assignment

---

## Phase 9 — Maintenance

- [x] Create `maintenance_schedules` and `maintenance_alerts` migrations/models
- [x] Mileage-based service interval alerting (alert when approaching next service mileage)
- [x] Create `maintenance_tickets` migration and model
  - [x] Ticket types: `repair`, `inspection`, `service`, `diagnostic`
  - [x] Statuses: `open`, `prioritized`, `in_progress`, `awaiting_parts`, `completed`, `closed`
- [x] Create `maintenance_job_cards` migration and model
  - [x] Fields: vehicle, mileage, reported problem, diagnosis, work performed, parts used, tools used, mechanic (external user), start/end time, notes, completion status
- [x] Create `checklist_templates` and `checklist_items` migrations/models
- [x] Create `job_card_checklists` and `job_card_checklist_items` (snapshot of template at job card creation time)
- [x] Create `vehicle_repairs` migration and model
- [x] Create `vehicle_replacements` migration and model
- [x] Mechanic Dashboard API endpoints
- [x] Write feature tests for maintenance domain

---

## Phase 10 — Inventory

- [x] Create `inventory_categories` migration and model
- [x] Create `inventory_parts` migration and model (mechanical & cosmetic/body categories)
  - [x] Fields: part, category, quantity, minimum_quantity, location
- [x] Create `inventory_movements` migration and model (stock movements and usage)
- [x] Create `job_card_parts` migration and model (parts consumed per job card)
- [x] Create `tools` migration and model
  - [x] Fields: tool, category, availability, condition
- [x] Create `tool_assignments` migration and model
  - [x] Fields: tool_id, mechanic (external user), assigned_at, returned_at
- [x] Low-stock alerts when `quantity < minimum_quantity`
- [x] Write feature tests for inventory domain

---

## Phase 11 — Reporting & Analytics

- [ ] Fleet utilization report
- [ ] Vehicle mileage report
- [ ] Maintenance history report
- [ ] Service compliance report
- [ ] Trip history report
- [ ] Delivery performance report
- [ ] Driver execution report
- [ ] Parts consumption report
- [ ] Tool utilization report
- [ ] Operational analytics dashboard data

---

## Cross-Cutting Concerns

- [ ] Confirm authentication integration mechanism with Business/Finance app (Sanctum / Passport / signed tokens)
- [ ] Ensure Fleet never writes directly to the Business/Finance database
- [ ] Ensure `external_user_id` is stored on all records that need to identify the actor
- [ ] Run `vendor/bin/pint --dirty` after any PHP file changes
- [ ] Run `php artisan test --compact` after each phase
- [ ] Document API contracts before implementation begins for each phase
