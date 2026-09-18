# KPFC Fleet Management & Telematics System
## Development TODO List

**Document Status:** Implementation Roadmap  
**Based On:** KPFC Fleet Management & Telematics System — Architecture & Design Specification  
**Primary System:** KPFC Fleet Management & Telematics Application  
**External System:** KPFC Business / Finance Application  

---

## 1. Implementation Principles

- [ ] Inspect the existing Fleet application before creating new migrations/models.
- [ ] Preserve all currently working Protrack365, location, routing, Google Sheets, vehicle, shop, and deployment functionality.
- [ ] Do not create duplicate tables, models, services, or relationships.
- [ ] Fleet must not directly access the Business / Finance database.
- [ ] Fleet ↔ Business / Finance communication must use authenticated APIs.
- [ ] Financial/commercial ownership remains with Business / Finance.
- [ ] Operational fleet execution remains with Fleet.
- [ ] Use external IDs/references to map records between systems.
- [ ] Preserve historical operational snapshots.
- [ ] Build in controlled phases.
- [ ] Test each phase before moving to the next.
- [ ] Keep `vehicle_deployments` during the transition to the richer Trip domain.

---

# Phase 1 — Existing Foundation Review

## Database

- [ ] Inspect all existing migrations.
- [ ] Inspect current database schema.
- [ ] Inspect `vehicles`.
- [ ] Inspect `shops`.
- [ ] Inspect `locations`.
- [ ] Inspect `vehicle_deployments`.
- [ ] Inspect `vehicle_positions`.
- [ ] Inspect `vehicle_mileages`.
- [ ] Inspect `vehicle_events`.
- [ ] Inspect `location_cache`.
- [ ] Inspect any existing maintenance/inventory tables.
- [ ] Review indexes.
- [ ] Review foreign keys.
- [ ] Confirm migration history.
- [ ] Identify duplicate or obsolete fields.

## Models

- [ ] Inspect `Vehicle`.
- [ ] Inspect `Shop`.
- [ ] Inspect `Location`.
- [ ] Inspect `VehicleDeployment`.
- [ ] Verify all relationships.
- [ ] Find stale relationships.
- [ ] Check `Location::deployments()` for the obsolete `destination_location_id` reference.
- [ ] Check all callers before changing that relationship.
- [ ] Do not add `Shop::deployments()` unless actually required.

## API

- [ ] Verify `GET /api/shops`.
- [ ] Verify `GET /api/vehicles`.
- [ ] Verify `GET /api/vehicles/{vehicle}`.
- [ ] Verify vehicle → shop assignment.
- [ ] Verify deployment creation.
- [ ] Verify deployment dispatch.
- [ ] Verify deployment completion/release.
- [ ] Verify deployment cancellation.
- [ ] Verify route recalculation.
- [ ] Keep production routes in `routes/api.php`.
- [ ] Keep diagnostic/development routes separate.

## Existing Integrations

- [ ] Verify Protrack365 authentication.
- [ ] Verify position synchronization.
- [ ] Verify location resolution.
- [ ] Verify shop geofencing.
- [ ] Verify geographic location cache.
- [ ] Verify LocationIQ fallback.
- [ ] Verify OSRM routing.
- [ ] Verify Google Sheets integration.
- [ ] Verify Google service-account authentication.
- [ ] Verify scheduled jobs.
- [ ] Document scheduler/queue requirements.

---

# Phase 2 — Local Fleet Shop + Business Reference

Fleet will retain its own operational `shops` table.

## Local Shop Data

- [ ] Keep Fleet `shops` table.
- [ ] Keep Fleet shop records available even without Business DB access.
- [ ] Keep shop name.
- [ ] Keep address.
- [ ] Keep latitude/longitude.
- [ ] Keep geofence radius.
- [ ] Keep operational status.
- [ ] Keep operational notes where required.
- [ ] Allow Fleet to maintain operational shop information.

## Business / Finance Reference

- [ ] Confirm how Business / Finance identifies shops.
- [ ] Determine whether the external identifier is numeric, UUID, code, or another reference.
- [ ] Add `external_shop_id` or the agreed equivalent.
- [ ] Index the external reference.
- [ ] Decide whether the external reference must be unique.
- [ ] Map existing Fleet shops to Business / Finance shops.
- [ ] Create a process for unmapped shops.
- [ ] Never write directly to the Business database.
- [ ] Do not make Business → Fleet synchronization a prerequisite for Fleet operation.
- [ ] If a Business API becomes available, optionally support read/synchronization later.
- [ ] Ensure local Fleet shop changes do not automatically overwrite Business records.

## Existing 44 Shops

- [ ] Review the existing 44-shop seeder.
- [ ] Preserve existing shop IDs where vehicles/operational records depend on them.
- [ ] Do not blindly delete/recreate shops.
- [ ] Validate names.
- [ ] Validate addresses.
- [ ] Validate GPS coordinates.
- [ ] Validate geofence radii.
- [ ] Populate Business / Finance references when known.

---

# Phase 3 — Business / Finance Integration Foundation

## API Configuration

- [ ] Confirm Business / Finance API base URL.
- [ ] Define Fleet integration configuration.
- [ ] Store credentials securely.
- [ ] Do not commit secrets.
- [ ] Define request timeout.
- [ ] Define retry policy.
- [ ] Define error handling.
- [ ] Define integration logging.

## Authentication

- [ ] Inspect Business / Finance authentication.
- [ ] Determine whether Sanctum is available.
- [ ] Determine whether Passport/OAuth2 is available.
- [ ] Determine whether signed service-to-service authentication is available.
- [ ] Select the agreed authentication mechanism.
- [ ] Implement authenticated Fleet → Business requests.
- [ ] Implement authorization.
- [ ] Handle authentication failures.
- [ ] Audit integration failures.

## External References

- [ ] Standardize `source_system`.
- [ ] Standardize `source_type`.
- [ ] Standardize `source_id`.
- [ ] Standardize `source_reference`.
- [ ] Standardize `external_user_id` / equivalent.
- [ ] Document record ownership by system.

---

# Phase 4 — Transport Request Domain

## Transport Requests

- [ ] Create `transport_requests`.
- [ ] Add request number.
- [ ] Add request type.
- [ ] Add source system.
- [ ] Add source type.
- [ ] Add source ID.
- [ ] Add source reference.
- [ ] Add requested-by external user ID.
- [ ] Add requested timestamp.
- [ ] Add requested-for date.
- [ ] Add priority.
- [ ] Add status.
- [ ] Add title.
- [ ] Add description.
- [ ] Add special instructions.
- [ ] Add pickup information.
- [ ] Add vehicle requirements.
- [ ] Add timestamps.
- [ ] Add indexes.
- [ ] Add foreign keys where appropriate.

## Request Types

- [ ] `lpo_collection`
- [ ] `invoice_delivery`
- [ ] `dispatch_invoice_to_shop`
- [ ] `custom_transport`

## Dispatch Invoice to Shop

Treat this as a specific process.

- [ ] Define request structure.
- [ ] Support warehouse pickup.
- [ ] Support one or more shop destinations.
- [ ] Store invoice/business reference.
- [ ] Store shop references.
- [ ] Store delivery instructions.
- [ ] Store contacts.
- [ ] Store required date.
- [ ] Store priority.
- [ ] Preserve source-system references.

## Custom Transport

- [ ] Build structured configurable fields.
- [ ] Support configurable pickup.
- [ ] Support configurable destinations.
- [ ] Support vehicle requirements.
- [ ] Support instructions.
- [ ] Avoid an unrestricted JSON-only design.
- [ ] Allow future request types without redesigning the core model.

## Transport Request API

- [ ] Create/import request.
- [ ] Retrieve request.
- [ ] Update request.
- [ ] Cancel request.
- [ ] Validate request status transitions.
- [ ] Prevent duplicate external requests.
- [ ] Add audit logging.
- [ ] Add integration logging.

---

# Phase 5 — Operational Snapshots

Historical execution data must not change when master data changes.

- [ ] Define location snapshot structure.
- [ ] Store destination name.
- [ ] Store address.
- [ ] Store latitude.
- [ ] Store longitude.
- [ ] Store contact phone.
- [ ] Store delivery instructions.
- [ ] Store local Fleet shop ID where applicable.
- [ ] Store external shop reference where applicable.
- [ ] Define snapshot creation rules.
- [ ] Ensure completed trips remain historically stable.

---

# Phase 6 — Trip Domain

## Trips

- [ ] Create `trips`.
- [ ] Link trip to transport request.
- [ ] Link trip to vehicle.
- [ ] Link trip to driver/external driver identity.
- [ ] Add trip number.
- [ ] Add status.
- [ ] Add planned start/end.
- [ ] Add actual start/end.
- [ ] Add planned route.
- [ ] Add actual route information.
- [ ] Add starting mileage.
- [ ] Add ending mileage.
- [ ] Add calculated trip mileage.
- [ ] Add operational metadata.
- [ ] Add indexes.

## Trip Status

- [ ] Planned.
- [ ] Assigned.
- [ ] Ready.
- [ ] In progress.
- [ ] Completed.
- [ ] Cancelled.
- [ ] Define valid transitions.
- [ ] Prevent invalid transitions.

## Trip Stops

Create `trip_stops`.

- [ ] Add `trip_id`.
- [ ] Add sequence.
- [ ] Add stop type.
- [ ] Add location.
- [ ] Add local shop reference where applicable.
- [ ] Add external shop reference where applicable.
- [ ] Add historical snapshots.
- [ ] Add status.
- [ ] Add arrival timestamp.
- [ ] Add departure timestamp.
- [ ] Add delivery information.
- [ ] Add instructions.
- [ ] Add contact information.
- [ ] Add indexes.

## Stop Types

- [ ] Pickup.
- [ ] Delivery.
- [ ] Return.
- [ ] Waypoint.

Initial scope:

- [ ] Implement pickup.
- [ ] Implement delivery.
- [ ] Add return/waypoint support as needed.

## Stop Execution

Implement:

`Upcoming → En Route → Arrived → Verification → Completed`

- [ ] Enforce sequential completion.
- [ ] Allow Fleet Manager override.
- [ ] Audit overrides.
- [ ] Record arrival.
- [ ] Record departure.
- [ ] Prevent unauthorized stop skipping.

---

# Phase 7 — Multi-Stop OSRM Routing

## Route Calculation

- [ ] Extend existing OSRM implementation.
- [ ] Support:
  `Origin → Stop 1 → Stop 2 → ... → Stop N → Destination/Base`
- [ ] Calculate road distance.
- [ ] Calculate duration.
- [ ] Store route geometry.
- [ ] Store leg distances.
- [ ] Store leg durations.
- [ ] Store route calculation timestamp.

## Recalculation

Recalculate when:

- [ ] Stop is added.
- [ ] Stop is removed.
- [ ] Stop order changes.
- [ ] Destination changes.
- [ ] Vehicle location changes significantly.
- [ ] Fleet Manager manually requests recalculation.
- [ ] Route becomes stale.

## Route Editing

- [ ] Reorder stops.
- [ ] Add stops.
- [ ] Remove stops.
- [ ] Recalculate after changes.
- [ ] Audit route changes.

---

# Phase 8 — Vehicle & Driver Assignment

## Vehicle Assignment

- [ ] Assign vehicle to trip.
- [ ] Check availability.
- [ ] Prevent conflicting active trips.
- [ ] Record assignment timestamp.
- [ ] Record assigning user.
- [ ] Audit assignment changes.

## Driver Assignment

- [ ] Define driver identity/reference.
- [ ] Assign driver to trip.
- [ ] Check driver availability.
- [ ] Prevent conflicting assignments.
- [ ] Record assignment timestamp.
- [ ] Audit driver changes.

---

# Phase 9 — Vehicle Availability

Do not rely only on a boolean `is_available`.

Implement/derive:

- [ ] Available at Base.
- [ ] En Route.
- [ ] Assigned.
- [ ] Unavailable.
- [ ] Maintenance.
- [ ] Out of Service.
- [ ] Provisional Availability.

Availability logic should consider:

- [ ] Current trip.
- [ ] Current location.
- [ ] Assignment.
- [ ] Return route.
- [ ] Expected return.
- [ ] Maintenance.
- [ ] Operational restrictions.

---

# Phase 10 — Driver Operations

## Driver Mobile App

- [ ] Active trip.
- [ ] Upcoming trip.
- [ ] Assigned vehicle.
- [ ] Ordered stops.
- [ ] Stop instructions.
- [ ] Navigation.
- [ ] Stop status updates.
- [ ] Arrival confirmation.
- [ ] Delivery confirmation.
- [ ] Return-to-base request.
- [ ] Trip completion.

## Trip Start

Driver manually starts the trip.

- [ ] Record actual start time.
- [ ] Capture starting mileage.
- [ ] Capture starting GPS location.
- [ ] Validate vehicle.
- [ ] Validate driver.
- [ ] Record trip-start event.

---

# Phase 11 — Delivery Verification

## SMS Code

- [ ] Define secure code generation.
- [ ] Send SMS when vehicle approaches/arrives.
- [ ] Hash codes.
- [ ] Set code expiry.
- [ ] Limit invalid attempts.
- [ ] Record verification attempts.
- [ ] Validate customer code.
- [ ] Mark stop verified after successful validation.

## Manual Override

- [ ] Restrict override to authorized Fleet Manager.
- [ ] Require reason.
- [ ] Record authorizing user.
- [ ] Record timestamp.
- [ ] Record affected stop.
- [ ] Audit override.

---

# Phase 12 — Return-to-Base

- [ ] Create return-to-base request.
- [ ] Record driver.
- [ ] Record trip.
- [ ] Record reason.
- [ ] Record current GPS location.
- [ ] Record undelivered stops.
- [ ] Record request time.
- [ ] Record decision.
- [ ] Record decision maker.
- [ ] Record decision time.
- [ ] Record comments.

Workflow:

- [ ] Driver submits request.
- [ ] Fleet Manager receives request.
- [ ] Fleet Manager approves/denies.
- [ ] Record decision.
- [ ] Recalculate route if approved.
- [ ] Update remaining stops.
- [ ] Notify Business / Finance where required.

---

# Phase 13 — Mileage & Telemetry

## Protrack365

- [ ] Keep Protrack365 as telemetry source.
- [ ] Continue position synchronization.
- [ ] Continue odometer/mileage synchronization.
- [ ] Continue vehicle event synchronization.
- [ ] Continue location resolution.
- [ ] Verify telemetry → Fleet vehicle mapping.

## Trip Mileage

At trip start:

- [ ] Capture starting odometer.

At trip completion:

- [ ] Capture ending odometer.
- [ ] Calculate trip mileage.
- [ ] Compare against telemetry.
- [ ] Flag anomalies.
- [ ] Preserve audit data.

## Mileage Audit

- [ ] Detect missing starting mileage.
- [ ] Detect missing ending mileage.
- [ ] Detect abnormal mileage.
- [ ] Detect telemetry gaps.
- [ ] Allow authorized corrections.
- [ ] Audit corrections.

---

# Phase 14 — Fleet Manager Dashboard

## Request Queue

- [ ] Pending requests.
- [ ] Priority.
- [ ] Requested date.
- [ ] Request type.
- [ ] Source reference.
- [ ] Status.
- [ ] Assignment status.

## Trip Planning

- [ ] Create trip from request.
- [ ] Select vehicle.
- [ ] Select driver.
- [ ] Add stops.
- [ ] Reorder stops.
- [ ] Calculate route.
- [ ] Review distance.
- [ ] Review duration.
- [ ] Dispatch trip.

## Live Operations

- [ ] Active vehicles.
- [ ] Active trips.
- [ ] Vehicle locations.
- [ ] Current stops.
- [ ] Routes.
- [ ] Delivery status.
- [ ] Exceptions.
- [ ] Return-to-base requests.

## Approvals

- [ ] Return-to-base approvals.
- [ ] Delivery verification overrides.
- [ ] Stop sequence overrides.
- [ ] Other operational overrides.

---

# Phase 15 — Maintenance

## Maintenance Schedules

- [ ] Create `maintenance_schedules`.
- [ ] Mileage-based schedules.
- [ ] Time-based schedules.
- [ ] Service intervals.
- [ ] Vehicle-specific requirements.

## Maintenance Alerts

- [ ] Create `maintenance_alerts`.
- [ ] Mileage alerts.
- [ ] Time-based alerts.
- [ ] Alert status.
- [ ] Notifications.

## Maintenance Tickets

- [ ] Create `maintenance_tickets`.
- [ ] Link vehicle.
- [ ] Record problem.
- [ ] Record mileage.
- [ ] Record priority.
- [ ] Record status.
- [ ] Record requester.
- [ ] Track resolution.

## Job Cards

- [ ] Create `maintenance_job_cards`.
- [ ] Link ticket.
- [ ] Link vehicle.
- [ ] Assign mechanic.
- [ ] Record work performed.
- [ ] Record start/end.
- [ ] Record mileage.
- [ ] Record parts.
- [ ] Record tools.
- [ ] Record inspection result.

## Checklists

- [ ] Create checklist templates.
- [ ] Create checklist items.
- [ ] Create job-card checklist instances.
- [ ] Record results.
- [ ] Record failed checks.
- [ ] Record corrective actions.

## Repairs & Replacements

- [ ] Create `vehicle_repairs`.
- [ ] Create `vehicle_replacements`.
- [ ] Record repair history.
- [ ] Record replaced components.
- [ ] Record mileage.
- [ ] Record dates.
- [ ] Keep financial ownership external where appropriate.

---

# Phase 16 — Fleet Inventory

## Parts

- [ ] Create `inventory_categories`.
- [ ] Create `inventory_parts`.
- [ ] Define part numbers.
- [ ] Define descriptions.
- [ ] Track quantities.
- [ ] Define reorder thresholds.

## Inventory Movements

- [ ] Create `inventory_movements`.
- [ ] Record receipts.
- [ ] Record issues.
- [ ] Record adjustments.
- [ ] Link issues to job cards.
- [ ] Audit stock changes.

## Job Card Parts

- [ ] Create `job_card_parts`.
- [ ] Record part.
- [ ] Record quantity.
- [ ] Record job card.
- [ ] Record issue date.
- [ ] Link inventory movement.

## Tools

- [ ] Create `tools`.
- [ ] Create `tool_assignments`.
- [ ] Track availability.
- [ ] Track assigned mechanic.
- [ ] Track condition.
- [ ] Track returns.

---

# Phase 17 — Audit Logging

Create central `audit_logs`.

- [ ] Record external user ID.
- [ ] Record action.
- [ ] Record entity type.
- [ ] Record entity ID.
- [ ] Record old values.
- [ ] Record new values.
- [ ] Record timestamp.
- [ ] Record metadata.
- [ ] Never log secrets.
- [ ] Protect audit records from unauthorized modification.

Audit at minimum:

- [ ] Vehicle assignment.
- [ ] Driver assignment.
- [ ] Trip creation.
- [ ] Trip changes.
- [ ] Stop changes.
- [ ] Delivery overrides.
- [ ] Return-to-base decisions.
- [ ] Mileage corrections.
- [ ] Maintenance changes.
- [ ] Inventory changes.
- [ ] Integration changes.

---

# Phase 18 — Business / Finance Integration

## Fleet → Business Events

- [ ] Transport request accepted.
- [ ] Trip created.
- [ ] Vehicle assigned.
- [ ] Trip started.
- [ ] Delivery completed.
- [ ] Partial delivery.
- [ ] Return approved.
- [ ] Trip completed.
- [ ] Trip cancelled.

## Business → Fleet

- [ ] Dispatch Invoice to Shop.
- [ ] LPO Collection.
- [ ] Invoice Delivery.
- [ ] Custom Transport.
- [ ] Request updates.
- [ ] Request cancellation.

## Idempotency

- [ ] Prevent duplicate transport requests.
- [ ] Prevent duplicate events.
- [ ] Use source-system identifiers.
- [ ] Make retries safe.
- [ ] Define reconciliation process.

---

# Phase 19 — Google Sheets Export

Google Sheets remains an operational export, not the primary database.

- [ ] Keep Protrack365 worksheet.
- [ ] Match vehicles by IMEI.
- [ ] Write current human-readable location.
- [ ] Keep Fleet DB as operational source of truth.
- [ ] Log failed updates.
- [ ] Add retry handling.
- [ ] Do not make core workflows dependent on Sheets availability.

---

# Phase 20 — Notifications

- [ ] Create notification abstraction.
- [ ] SMS delivery verification.
- [ ] Maintenance alerts.
- [ ] Return-to-base alerts.
- [ ] Trip assignment notifications.
- [ ] Integration failure notifications.
- [ ] Fleet Manager operational alerts.

---

# Phase 21 — Reporting & Analytics

## Transport

- [ ] Requests by type.
- [ ] Requests by status.
- [ ] Completed trips.
- [ ] Cancelled trips.
- [ ] Outstanding requests.
- [ ] Trip duration.
- [ ] Delivery duration.

## Vehicles

- [ ] Vehicle utilization.
- [ ] Vehicle availability.
- [ ] Mileage.
- [ ] Distance by period.
- [ ] Trips per vehicle.
- [ ] Maintenance history.
- [ ] Downtime.

## Deliveries

- [ ] Successful deliveries.
- [ ] Partial deliveries.
- [ ] Failed deliveries.
- [ ] Verification failures.
- [ ] Manual overrides.
- [ ] Return-to-base requests.

## Maintenance

- [ ] Upcoming maintenance.
- [ ] Overdue maintenance.
- [ ] Maintenance by vehicle.
- [ ] Repair history.
- [ ] Parts consumption.
- [ ] Tool assignments.

---

# Phase 22 — Security

- [ ] HTTPS for external communication.
- [ ] Authenticated Fleet ↔ Business communication.
- [ ] Secure integration credentials.
- [ ] Hashed delivery codes.
- [ ] API authorization.
- [ ] Audit privileged actions.
- [ ] Protect customer information.
- [ ] Protect vehicle assignment operations.
- [ ] Protect delivery overrides.
- [ ] Validate external API input.
- [ ] Apply rate limiting where appropriate.
- [ ] Review logs for sensitive information.
- [ ] Perform production security review.

---

# Phase 23 — Testing

## Unit Tests

- [ ] Transport request validation.
- [ ] Request status transitions.
- [ ] Trip status transitions.
- [ ] Stop status transitions.
- [ ] Vehicle availability.
- [ ] Route calculation.
- [ ] Mileage calculation.
- [ ] Delivery verification.
- [ ] Return-to-base.
- [ ] Maintenance calculations.

## API / Feature Tests

- [ ] Create transport request.
- [ ] Retrieve transport request.
- [ ] Update transport request.
- [ ] Cancel transport request.
- [ ] Create trip.
- [ ] Assign vehicle.
- [ ] Assign driver.
- [ ] Add stops.
- [ ] Reorder stops.
- [ ] Start trip.
- [ ] Complete stop.
- [ ] Complete trip.
- [ ] Submit return-to-base.
- [ ] Approve return-to-base.
- [ ] Verify delivery code.
- [ ] Perform authorized override.

## Integration Tests

- [ ] Business → Fleet request.
- [ ] Fleet → Business status update.
- [ ] Duplicate request.
- [ ] Authentication failure.
- [ ] Business API unavailable.
- [ ] Timeout.
- [ ] Retry.
- [ ] Protrack failure.
- [ ] Google Sheets failure.

---

# Phase 24 — First End-to-End Test

Primary first workflow:

**Business / Finance → Dispatch Invoice to Shop → Fleet → Trip → Warehouse → Shop A → Shop B → Shop C → Verification → Completion → Business / Finance**

- [ ] Business creates Dispatch Invoice to Shop request.
- [ ] Business assigns external reference.
- [ ] Fleet receives request.
- [ ] Fleet stores transport request.
- [ ] Fleet validates shop references.
- [ ] Fleet Manager sees request.
- [ ] Fleet Manager selects vehicle.
- [ ] Fleet Manager selects driver.
- [ ] Fleet Manager confirms warehouse pickup.
- [ ] Fleet Manager adds Shop A.
- [ ] Fleet Manager adds Shop B.
- [ ] Fleet Manager adds Shop C.
- [ ] Fleet calculates multi-stop route.
- [ ] Fleet dispatches trip.
- [ ] Driver sees trip.
- [ ] Driver starts trip.
- [ ] Starting mileage is captured.
- [ ] Warehouse pickup is completed.
- [ ] Shop A delivery is verified.
- [ ] Shop B delivery is verified.
- [ ] Shop C delivery is verified.
- [ ] Final stop is completed.
- [ ] Ending mileage is captured.
- [ ] Trip mileage is calculated.
- [ ] Trip is completed.
- [ ] Fleet sends completion status to Business / Finance.
- [ ] Business / Finance updates its workflow.
- [ ] Full audit trail is available.

---

# Phase 25 — Existing Deployment Transition

Do not remove `vehicle_deployments` immediately.

- [ ] Document current deployment usage.
- [ ] Identify API consumers.
- [ ] Identify UI consumers.
- [ ] Identify routing dependencies.
- [ ] Keep existing deployment endpoints working.
- [ ] Introduce Trips alongside deployments.
- [ ] Keep Trip functionality independent of unnecessary deployment-specific fields.
- [ ] Identify duplicated functionality.
- [ ] Decide which deployment functionality becomes legacy.
- [ ] Migrate only after Trip workflows are stable.
- [ ] Deprecate old functionality only after dependency analysis.

---

# Phase 26 — Production Readiness

- [ ] Review database indexes.
- [ ] Review foreign keys.
- [ ] Review API authorization.
- [ ] Review validation.
- [ ] Review logging.
- [ ] Review audit logging.
- [ ] Review scheduled jobs.
- [ ] Review queues/workers.
- [ ] Review integration retries.
- [ ] Review Protrack synchronization.
- [ ] Review OSRM availability.
- [ ] Review SMS provider.
- [ ] Review Google Sheets integration.
- [ ] Review backups.
- [ ] Test database restoration.
- [ ] Review monitoring.
- [ ] Review alerting.
- [ ] Review security.
- [ ] Document deployment procedure.
- [ ] Document rollback procedure.

---

# Recommended Implementation Order

1. [ ] Foundation/schema audit.
2. [ ] Local Fleet shops + external Business references.
3. [ ] Business / Finance integration foundation.
4. [ ] Transport Requests.
5. [ ] Dispatch Invoice to Shop.
6. [ ] Operational snapshots.
7. [ ] Trips.
8. [ ] Trip Stops.
9. [ ] Multi-stop routing.
10. [ ] Vehicle/driver assignment.
11. [ ] Vehicle availability.
12. [ ] Driver operations.
13. [ ] Delivery verification.
14. [ ] Return-to-base.
15. [ ] Mileage and telemetry audit.
16. [ ] Fleet Manager dashboard.
17. [ ] Business / Finance status integration.
18. [ ] Maintenance.
19. [ ] Inventory.
20. [ ] Audit/reporting.
21. [ ] Production hardening.

---

# Immediate Next Actions

Before creating production migrations:

- [ ] Inspect current Fleet database schema.
- [ ] Inspect current models.
- [ ] Inspect current controllers.
- [ ] Inspect current services.
- [ ] Inspect current API routes.
- [ ] Inspect the 44 existing shops.
- [ ] Confirm Business / Finance shop identifiers.
- [ ] Decide the exact external shop reference field.
- [ ] Inspect Business / Finance API capabilities.
- [ ] Confirm authentication mechanism.
- [ ] Confirm Dispatch Invoice to Shop request payload.
- [ ] Confirm first end-to-end test data.
- [ ] Create only the first required migration after the above review.

---

# Definition of Done

The first production-oriented transport workflow is ready when:

- [ ] Fleet maintains its local operational shop data.
- [ ] Fleet shops can be mapped to Business / Finance shops.
- [ ] Fleet does not require direct Business DB access.
- [ ] Business / Finance can submit transport requirements.
- [ ] Fleet can create Trips.
- [ ] Trips support multiple ordered stops.
- [ ] Vehicles and drivers can be safely assigned.
- [ ] OSRM supports multi-stop routing.
- [ ] Drivers can execute trips.
- [ ] Delivery verification works.
- [ ] Verification overrides are audited.
- [ ] Return-to-base is controlled.
- [ ] Mileage is captured and audited.
- [ ] Protrack365 remains the telemetry source.
- [ ] Required operational statuses reach Business / Finance.
- [ ] Existing deployments remain stable during transition.
- [ ] Critical workflows have automated tests.
- [ ] Audit records exist for privileged actions.
- [ ] Dispatch Invoice to Shop works end-to-end.
