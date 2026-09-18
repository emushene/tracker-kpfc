# KPFC Fleet Management & Telematics System

## Architecture & Design Specification

**Document Status:** Architecture Baseline
**Purpose:** Database, API and application development blueprint
**Primary System:** KPFC Fleet Management & Telematics Application
**External System:** KPFC Business / Finance Application

---

# 1. Purpose

The KPFC Fleet Management & Telematics System is the operational transport and fleet execution platform.

It manages:

* Vehicles
* Vehicle GPS telemetry
* Vehicle locations
* Mileage
* Vehicle availability
* Shops and operational shop locations
* Transport requests
* Trips
* Drivers and trip execution
* Multi-stop deliveries
* Delivery verification
* Return-to-base requests
* Routing
* Maintenance
* Job cards
* Vehicle repairs
* Parts inventory
* Tools inventory
* Fleet operational auditing

The system does **not** own financial or commercial business processes.

Those remain in the separate KPFC Business / Finance Application.

Fleet maintains its own operational copy of shop information because shop data is required for GPS, routing, geofencing, vehicle assignment and trip execution.

---

# 2. System Ownership

The two applications have clearly separated responsibilities.

## 2.1 Business / Finance Application

The external application remains the authoritative source for:

* Authentication
* Users
* User accounts
* Business permissions
* Customers
* Manufacturers / suppliers
* Shops / branches as business master records
* LPOs
* Invoices
* Financial information
* Warehouse/commercial records
* Business paperwork
* Commercial transport requirements

The Fleet application must **not directly access the Business/Finance database**.

Fleet does not require database credentials or database-level write access to the Business/Finance application.

Communication occurs through an authenticated HTTPS API.

---

## 2.2 Fleet Application

The Fleet application is the operational source of truth for:

* Vehicles
* GPS devices
* Vehicle positions
* Current vehicle location
* Mileage
* Vehicle availability
* Local operational shop data
* Transport execution
* Trips
* Trip stops
* Driver execution
* Delivery confirmation
* Return-to-base requests
* Routing
* Maintenance
* Job cards
* Repairs
* Parts
* Tools
* Operational audit records

Fleet may create and maintain its own local operational shop records.

However, Fleet does not become the business master for those shops.

---

# 3. Shop Data Architecture

Shops require special treatment because they are used extensively by Fleet operations.

The Business/Finance application owns the business shop record.

The Fleet application maintains a local operational representation of that shop.

For example:

```text
Business / Finance Application

Shop ID: 847
Name: Westlands Branch
Address: ...
Latitude: ...
Longitude: ...
```

may correspond to:

```text
Fleet Application

shops.id:           12
shops.external_shop_id: 847
shops.name:         Westlands Branch
shops.address:      ...
shops.latitude:     ...
shops.longitude:    ...
```

The two identifiers have different purposes.

### Fleet Shop ID

`shops.id`

This is the Fleet application's internal primary key.

It is used by Fleet relationships such as:

```text
vehicles.assigned_shop_id
trip_stops.shop_id
```

### External Shop ID

`shops.external_shop_id`

This identifies the corresponding shop in the Business/Finance application.

It is used when communicating between the two applications.

---

## 3.1 Shop Ownership Rule

The Business/Finance application remains authoritative for business-level shop information.

Fleet maintains its own operational copy.

Therefore:

```text
Business Shop
      │
      │ External reference
      ▼
Fleet Shop
      │
      ├── GPS coordinates
      ├── Geofence
      ├── Routing
      ├── Vehicle assignment
      ├── Proximity detection
      └── Trip execution
```

Fleet does not directly modify the Business application's shop record.

---

## 3.2 Why Fleet Maintains Its Own Shops

Fleet requires shop information for operational purposes including:

* Vehicle home/base assignment
* GPS proximity detection
* Shop geofencing
* Location resolution
* Routing
* Trip planning
* Navigation
* Delivery stops
* Vehicle availability
* Dispatch
* Historical trip records

It would be inefficient and unnecessarily dependent on the Business application to perform these operations through a live API call every time.

The Fleet database therefore maintains the information required for operational execution.

---

## 3.3 Proposed Shop Data

Conceptually:

```text
shops

id
external_shop_id
name
address
latitude
longitude
status
geofence_radius
operational_notes
synced_at
created_at
updated_at
```

The exact fields will be finalized after inspection of the existing schema.

The existing Fleet `shops` table should therefore **remain in place**.

The existing shop records do not need to be deleted simply because the Business application also contains shops.

---

## 3.4 Shop Synchronization

Shop synchronization can occur through the Business/Finance API.

Conceptually:

```text
Business / Finance
       │
       │ Shop information
       │
       ▼
Fleet Integration API
       │
       ▼
Fleet shops table
```

The synchronization process can create or update Fleet's local operational copy.

For example:

```text
Business Shop 847
        │
        ▼
Fleet shops.external_shop_id = 847
```

Fleet may then update its own local representation when synchronization occurs.

Fleet never needs write access to the Business database.

---

## 3.5 Fleet-Specific Shop Data

Fleet may maintain operational fields that are relevant only to Fleet.

Examples:

* Geofence radius
* Operational notes
* Fleet-specific routing information
* Operational status where appropriate
* Local synchronization timestamp

These fields belong to Fleet and do not need to be written back to the Business application.

---

# 4. High-Level Architecture

```text
┌──────────────────────────────────────────────────────────────┐
│                 KPFC BUSINESS / FINANCE APP                  │
│                                                              │
│ Authentication                                               │
│ Users                                                        │
│ Customers                                                    │
│ Manufacturers / Suppliers                                    │
│ Shops / Branches - Business Master                           │
│ LPOs                                                         │
│ Invoices                                                     │
│ Warehouse / Commercial Operations                            │
│ Financial Records                                            │
│ Business Paperwork                                           │
│                                                              │
│        Source of Business Requirements & Master Data         │
└─────────────────────────────┬────────────────────────────────┘
                              │
                              │ Authenticated HTTPS API
                              │
                    ┌─────────┴─────────┐
                    │                   │
                    ▼                   ▼
             Shop Synchronization   Transport Requests
                    │                   │
                    └─────────┬─────────┘
                              ▼
┌──────────────────────────────────────────────────────────────┐
│                    KPFC FLEET APPLICATION                    │
│                                                              │
│ Integration API                                              │
│                                                              │
│ Local Shop Operational Data                                  │
│                                                              │
│ Transport Requests                                           │
│        │                                                     │
│        ▼                                                     │
│ Trips                                                        │
│        │                                                     │
│        ├──────── Vehicle                                     │
│        ├──────── Driver / External User                      │
│        └──────── Trip Stops                                  │
│                       │                                      │
│                       ├── Pickup                             │
│                       ├── Delivery                           │
│                       ├── Verification                       │
│                       └── Return-to-Base                      │
│                                                              │
│ Fleet Management                                             │
│ Vehicle Availability                                         │
│ Mileage                                                      │
│ Routing                                                      │
│ Maintenance                                                  │
│ Parts / Tools                                                │
│ Auditing                                                     │
└──────────────┬──────────────────────┬────────────────────────┘
               │                      │
               ▼                      ▼
       ┌───────────────┐      ┌────────────────┐
       │  Protrack365  │      │ OSRM /         │
       │  GPS Platform │      │ LocationIQ     │
       └───────────────┘      └────────────────┘
               │
               ▼
       Vehicle Telemetry
```

---

# 5. Authentication Architecture

Authentication is **not implemented in the Fleet application**.

The external Business / Finance Application owns authentication and user accounts.

The Fleet application therefore must not create a second independent authentication system.

The exact integration mechanism is intentionally left open until the authentication implementation of the other Laravel application is inspected.

Possible mechanisms include:

* Laravel Sanctum
* Laravel Passport / OAuth2
* Signed service-to-service authentication
* Another agreed API authentication mechanism

The final mechanism must be determined from the actual external application.

---

# 6. External Identity

Although Fleet does not own users, Fleet needs to know who performed operational actions.

Therefore operational records may contain an external identity reference such as:

```text
external_user_id
external_user_reference
```

The Fleet application should not duplicate the complete user record unless a later integration requirement specifically requires a controlled local representation.

Audit records must be able to answer:

> Who performed this action?

without making Fleet responsible for authentication.

---

# 7. Business-to-Fleet Integration

The Business / Finance Application is responsible for creating business transport requirements.

It is also the authoritative source for business shop master records.

The Fleet application receives required information through an API.

The Business / Finance Application must **never write directly into Fleet's database**.

Likewise, Fleet must **never write directly into the Business/Finance database**.

The boundary is:

```text
Business Application
        │
        │ HTTPS API
        ▼
Fleet Integration Layer
        │
        ├── Shop synchronization
        │
        └── Transport requests
```

Fleet stores and operates on the received information locally.

---

# 8. Transport Request

A **Transport Request** represents a business requirement for transport.

It is not yet the physical execution of the transport.

Example:

> Deliver stock from the warehouse to Shop A, Shop B and Shop C tomorrow.

The request originates from the Business / Finance Application.

Fleet receives the information required to plan and execute the transport.

---

# 9. Transport Request Types

The initial request types are:

## 9.1 LPO Collection

A transport requirement associated with an LPO.

Typical operation:

```text
Manufacturer / Supplier
          ↓
       Vehicle
          ↓
       Warehouse
```

The LPO itself remains in the Business / Finance Application.

Fleet stores the external reference.

---

## 9.2 Invoice Delivery

A transport requirement associated with an invoice/customer delivery.

Typical operation:

```text
Warehouse / Origin
       ↓
    Customer
```

The invoice and financial information remain in the Business / Finance Application.

---

## 9.3 Dispatch Invoice to Shop

This is a specific business process identified by management.

It is **not** the generic transport request.

Internal type:

```text
dispatch_invoice_to_shop
```

Display name:

**Dispatch Invoice to Shop**

Typical operation:

```text
Warehouse
   ↓
Shop A
   ↓
Shop B
   ↓
Shop C
```

It may contain multiple shops/stops.

The associated paperwork remains in the Business / Finance Application.

Fleet uses the local shop records to execute the transport.

---

## 9.4 Custom Transport Request

A generic transport requirement for situations that do not fit the predefined business processes.

Internal type:

```text
custom_transport
```

Display name:

**Custom Transport Request**

It should be configurable enough to support:

* Custom title
* Description
* Pickup
* One or more destinations
* Requested date/time
* Priority
* Cargo description
* Quantity
* Special instructions
* Vehicle requirements
* External reference
* Supporting paperwork reference

The generic request should still have a structured database model rather than becoming an unrestricted JSON-only record.

---

# 10. Transport Request vs Trip

This distinction is fundamental.

### Transport Request

**Business requirement**

Something needs to be transported.

### Trip

**Operational execution**

This vehicle and driver will execute that transport requirement.

Therefore:

```text
Transport Request
       │
       ▼
      Trip
       │
       ├── Vehicle
       ├── Driver
       ├── Route
       ├── Stops
       └── Execution
```

A request may exist before a vehicle or driver is assigned.

---

# 11. Proposed Transport Request Data

Conceptually:

```text
transport_requests

id
request_number
request_type
source_system
source_type
source_id
source_reference
requested_by_external_user_id
requested_at
requested_for_date
priority
status
title
description
special_instructions
pickup_location information
vehicle_requirements
created_at
updated_at
```

The exact fields will be finalized before migration creation.

---

# 12. Transport Request Stops

Where a transport request contains multiple destinations, the request should support structured request stops.

Conceptually:

```text
transport_request_stops

id
transport_request_id
sequence
stop_type
external_shop_id
shop_id
location_name
address
latitude
longitude
contact_phone
delivery_instructions
source_reference
```

This allows a Business request such as:

```text
DIS-2026-00452

Warehouse
   ↓
Shop 847
   ↓
Shop 912
   ↓
Shop 1031
```

to be represented explicitly before Fleet creates the operational Trip.

For known shops:

```text
external_shop_id → Fleet shops.external_shop_id
```

Fleet can then resolve the local shop.

---

# 13. External References

Fleet must not duplicate complete LPOs, invoices or financial records.

Instead, Fleet stores references such as:

```text
source_system
source_type
source_id
source_reference
```

Example:

```text
source_system:    business_app
source_type:      dispatch_invoice_to_shop
source_id:        8472
source_reference: DIS-2026-00452
```

Similarly, a shop can contain:

```text
external_shop_id: 847
```

This allows Fleet to identify the originating business transaction or master record without becoming the financial or business master system.

---

# 14. Snapshot Data

Some information required during transport execution may need to exist inside Fleet.

For example:

* Destination name
* Destination address
* GPS coordinates
* Contact telephone number
* Delivery instructions
* Shop reference

The architecture should support receiving a **minimal operational snapshot** from the Business / Finance Application.

This avoids making a live external API call every time a driver needs basic delivery information.

It also provides a historical record of what information was supplied for that particular trip.

This does not constitute duplication of the complete customer/invoice/LPO record.

---

# 15. Historical Shop Snapshot

A Trip Stop should retain the information required to reconstruct what the driver was expected to visit at the time of the trip.

For example:

```text
trip_stops

shop_id
external_shop_id
location_name
address
latitude
longitude
contact_phone
delivery_instructions
```

This is important because a Business shop may later:

* Change its name
* Change address
* Change contact information
* Change GPS coordinates
* Become inactive

Historical trips must not be rewritten simply because the current shop record changed.

---

# 16. Trips

A trip represents an operational transport journey.

A trip may contain:

* One vehicle
* One driver or assigned external driver identity
* One transport request
* One or more stops
* Planned route
* Actual route
* Planned start/end
* Actual start/end
* Starting mileage
* Ending mileage
* Calculated trip mileage
* Trip status
* Operational events

---

# 17. Multi-Stop Trips

Trips must support multiple sequential stops.

Example:

```text
Warehouse
    │
    ▼
Shop A
    │
    ▼
Shop B
    │
    ▼
Shop C
    │
    ▼
Warehouse
```

Each stop has its own sequence number.

Conceptually:

```text
trip_stops

id
trip_id
sequence
stop_type
shop_id
location
status
arrival information
departure information
delivery information
```

This is important because the driver must complete stops sequentially.

The system should not allow:

```text
Stop 3 = completed
Stop 1 = pending
Stop 2 = pending
```

unless a Fleet Manager explicitly overrides the sequence according to defined business rules.

---

# 18. Pickup and Delivery Stops

A stop should support different operational purposes.

Examples:

* pickup
* delivery
* return
* waypoint

The initial implementation can focus on pickup and delivery while keeping the model extensible.

---

# 19. Driver Execution

The Driver Mobile Application will display:

* Active trip
* Upcoming trip
* Assigned vehicle
* Ordered stops
* Pickup instructions
* Delivery instructions
* Navigation
* Stop status
* Delivery confirmation
* Return-to-base process

The driver can manually start a trip.

The system records:

* Planned start
* Actual start
* Starting mileage
* Starting vehicle location

---

# 20. Stop Execution

For each stop:

```text
Upcoming
   ↓
En Route
   ↓
Arrived
   ↓
Verification
   ↓
Completed
```

The driver must confirm each stop before progressing to the next stop.

---

# 21. Delivery Verification

Delivery confirmation is performed per stop.

The system supports SMS verification.

When the vehicle approaches/arrives at the destination, the system can trigger customer communication.

A secure verification code can be sent to the customer's phone.

The customer provides the code to the driver.

Fleet validates the code.

## Manual Override

If SMS verification cannot be completed, an authorized user may perform a manual override.

The override must be audited.

The system should record:

* Verification method
* Verified at
* Verified by
* Override reason

Verification codes should be stored securely, preferably as hashes rather than plaintext.

---

# 22. Return-to-Base Requests

A driver may need to return to base while carrying undelivered consignments.

The driver submits a:

**Return-to-Base Request**

The Fleet Manager reviews it.

Possible flow:

```text
Driver
  │
  ▼
Return Request
  │
  ▼
Fleet Manager
  │
 ┌┴────────────┐
 ▼             ▼
Approve       Deny
 │
 ▼
Return-to-Base
```

The request must be linked to the relevant trip.

The system records:

* Driver
* Trip
* Reason
* Current location
* Undelivered stops
* Request time
* Decision
* Decision maker
* Decision time
* Manager comments

---

# 23. Vehicle Availability

Vehicle availability must distinguish between physical and operational availability.

Examples:

* Available at Base
* En Route
* Assigned
* Unavailable
* Maintenance
* Out of Service
* Provisionally Available

The Fleet Manager needs to know not only whether a vehicle is technically available, but whether it could realistically be used for another transport requirement.

---

# 24. Provisional Availability

A vehicle currently returning to base may potentially be used for another job.

Example:

```text
Vehicle A
Current Trip
Shop C
   │
   │ returning to base
   ▼
Warehouse
```

Fleet may indicate:

**Provisional Availability**

rather than simply:

**Available**

This allows the Fleet Manager to consider opportunistic assignments while making the distinction clear.

Availability should therefore be derived from:

* Current trip
* Current location
* Return route
* Expected return time
* Existing assignment
* Maintenance status
* Other operational constraints

A simplistic `is_available = true/false` field should not be the sole source of availability logic.

---

# 25. Mileage Management

The existing Protrack integration provides vehicle telemetry including mileage/odometer information.

Fleet uses this to maintain:

* Current mileage
* Starting trip mileage
* Ending trip mileage
* Trip mileage
* Cumulative vehicle mileage

For a completed trip:

```text
Trip Mileage =
Ending Odometer - Starting Odometer
```

Mileage should be audited against telemetry where possible.

---

# 26. Dynamic Routing

The existing OSRM routing capability should be extended from simple vehicle-to-home-shop routing into trip routing.

For multi-stop trips:

```text
Origin
  ↓
Stop 1
  ↓
Stop 2
  ↓
Stop 3
  ↓
Destination / Base
```

The routing service can calculate:

* Road distance
* Estimated duration
* Route geometry
* Leg-by-leg distances

The system should support trip editing by Fleet Managers.

Changing the stop sequence should cause route recalculation.

---

# 27. Protrack365 Integration

Protrack365 remains the telemetry source.

Fleet synchronizes:

* Device
* Vehicle
* GPS position
* Speed
* Sensors where available
* Odometer/mileage
* Online/offline status
* Timestamp

Existing telemetry functionality remains part of the architecture.

---

# 28. Location Resolution

The existing location-resolution architecture remains.

The current waterfall includes:

1. Shop geofence
2. Recent movement cache
3. Geographic bucket cache
4. LocationIQ fallback

The Fleet-local `shops` table remains essential to the first stage of this process because Fleet needs local shop coordinates and geofence information.

This allows the system to avoid unnecessary reverse-geocoding calls.

---

# 29. Road Routing

The existing OSRM integration remains.

It is used for:

* Road distance
* Estimated travel time
* Trip legs
* Dynamic routing
* Vehicle-to-destination calculations where required

The existing movement threshold can continue to control unnecessary recalculation.

---

# 30. Google Sheets Integration

The existing Google Sheets integration remains available for operational export.

The Protrack365 worksheet currently receives current vehicle location information.

Fleet remains the source of truth for fleet operational data.

Google Sheets is an operational/export destination, not the primary database.

---

# 31. Fleet Manager Dashboard

The Fleet Manager interface will provide:

## Deployment & Assignment

* View transport requests
* Review unassigned requests
* Create/manage trips
* Assign vehicles
* Assign drivers
* Schedule trips

## Dynamic Routing

* Create single-stop trips
* Create multi-stop trips
* Reorder stops
* Recalculate routes
* Edit active/planned trips where permitted

## Delivery Oversight

* Monitor trip progress
* Monitor stop sequence
* View completed deliveries
* View pending deliveries

## Approval Workflows

* Review return-to-base requests
* Approve
* Deny
* Record reasons

## Vehicle Availability

* Available at base
* En route
* Assigned
* Maintenance
* Unavailable
* Provisionally available

## Mileage Auditing

* Starting mileage
* Ending mileage
* Trip mileage
* Cumulative mileage

---

# 32. Driver Mobile Application

The Driver application will provide:

* Active trips
* Upcoming trips
* Assigned vehicle
* Manual trip start
* Navigation
* Stop sequence
* Arrival confirmation
* Delivery verification
* Manual delivery override where authorized
* Return-to-base request
* Return-to-base confirmation
* Trip completion

The driver should not be able to arbitrarily change the business transport requirement.

---

# 33. Mechanic Dashboard

The Mechanic Dashboard manages vehicle maintenance.

## Mileage Monitoring

The system monitors vehicle mileage and identifies upcoming service requirements.

Example:

```text
Current mileage: 148,200 km
Service interval: 10,000 km
Next service: 150,000 km
```

The system generates maintenance alerts.

---

# 34. Maintenance Tickets

Mechanics can create:

* Repair tickets
* Inspection tickets
* Service tickets
* Diagnostic tickets

Tickets should support:

* Open
* Prioritized
* In Progress
* Awaiting Parts
* Completed
* Closed

---

# 35. Digital Job Cards

Job cards contain:

* Vehicle
* Mileage
* Reported problem
* Diagnosis
* Work performed
* Checklist
* Parts used
* Tools used
* Mechanic
* Start/end time
* Notes
* Completion status

---

# 36. Maintenance Checklists

Checklist templates can be created for different maintenance activities.

Examples:

* Routine service
* Pre-service inspection
* Brake inspection
* Engine inspection
* Electrical inspection
* General vehicle inspection

A job card receives a copy/snapshot of the applicable checklist so historical records do not change when the template is later edited.

---

# 37. Repairs and Replacements

Fleet maintains operational records for:

## Repairs

* Problem
* Diagnosis
* Repair performed
* Cost/reference where applicable
* Mechanic
* Date
* Mileage

## Replacements

* Part replaced
* Old part
* New part
* Reason
* Mileage
* Date
* Job card

Financial costing remains with the Business / Finance Application where appropriate.

Fleet records the operational maintenance event.

---

# 38. Parts Inventory

Parts are divided conceptually into:

## Mechanical

Examples:

* Oil filters
* Air filters
* Brake pads
* Fluids
* Belts
* Batteries

## Cosmetic / Body

Examples:

* Mirrors
* Body panels
* Trim
* Lights
* Other body components

Inventory tracks:

* Part
* Category
* Quantity
* Minimum quantity
* Location
* Stock movements
* Usage
* Job-card allocation

---

# 39. Tools Inventory

The system maintains tools used by mechanics.

Examples:

* Diagnostic equipment
* Torque tools
* Workshop equipment
* Specialized vehicle tools

Tool records include:

* Tool
* Category
* Availability
* Condition
* Assignment
* Assigned mechanic
* Assignment time
* Return time

---

# 40. Audit Logging

Operationally important actions must be auditable.

Examples:

* Vehicle assignment
* Driver assignment
* Trip creation
* Trip modification
* Trip cancellation
* Stop completion
* Delivery override
* Return request
* Return approval
* Maintenance changes
* Inventory changes
* Tool assignment

Audit records should include:

```text
actor_external_user_id
action
entity_type
entity_id
old_values
new_values
timestamp
metadata
```

---

# 41. Core Database Domains

The database should be organized around these domains.

## Fleet

```text
vehicles
vehicle_positions
shops
locations
location_cache
vehicle_deployments [existing — to be reviewed]
```

The `shops` table remains a Fleet-owned operational table but contains an external reference to the corresponding Business/Finance shop.

## Transport

```text
transport_requests
transport_request_stops
trips
trip_stops
delivery_confirmations
return_requests
```

## Maintenance

```text
maintenance_schedules
maintenance_alerts
maintenance_tickets
maintenance_job_cards
checklist_templates
checklist_items
job_card_checklists
job_card_checklist_items
vehicle_repairs
vehicle_replacements
```

## Inventory

```text
inventory_categories
inventory_parts
inventory_movements
job_card_parts
tools
tool_assignments
```

## Auditing

```text
audit_logs
```

The exact table list will be finalized after inspection of the existing schema.

---

# 42. Existing Vehicle Deployment Model

The existing application already has:

```text
vehicle_deployments
```

and existing API operations for:

* Creating deployments
* Releasing deployments
* Cancelling deployments

The existing implementation must be inspected before deciding whether:

1. `vehicle_deployments` becomes the trip execution/assignment entity,
2. it is renamed/restructured into trips, or
3. it remains as an assignment layer beneath trips.

We should **not create a duplicate trips and vehicle_deployments model blindly**.

The current deployment implementation remains operational while the richer Trip domain is introduced.

---

# 43. API Architecture

There are two conceptual API areas.

## Operational API

Used by Fleet dashboards and operational clients.

Current examples:

```text
GET    /api/shops

GET    /api/vehicles

GET    /api/vehicles/{vehicle}

PATCH  /api/vehicles/{vehicle}/assign-shop

POST   /api/vehicles/{vehicle}/deployments

PATCH  /api/vehicles/{vehicle}/deployments/{deployment}/release

PATCH  /api/vehicles/{vehicle}/deployments/{deployment}/cancel
```

These existing APIs remain part of the operational Fleet API.

---

# 44. Integration API

A separate integration boundary should be created for the Business / Finance Application.

Conceptually:

```text
/api/integration/...
```

Potential endpoints include:

```text
POST /api/integration/shops/sync

GET  /api/integration/shops/{external_shop_id}

POST /api/integration/transport-requests

GET  /api/integration/transport-requests/{id}

PATCH /api/integration/transport-requests/{id}

POST /api/integration/transport-requests/{id}/cancel
```

Vehicle information exposed to the Business Application can be provided through integration endpoints such as:

```text
GET /api/integration/vehicles

GET /api/integration/vehicles/{vehicle}

GET /api/integration/vehicles/{vehicle}/availability

GET /api/integration/vehicles/{vehicle}/location
```

These are architectural examples and should become an explicit API contract before implementation.

---

# 45. Shop Integration API

Shop synchronization should follow the same database-boundary principle.

The Business/Finance application can provide shop information to Fleet through an authenticated API.

Conceptually:

```text
Business / Finance
        │
        │ Shop data
        ▼
Fleet Integration API
        │
        ▼
Fleet shops
```

The synchronization can support:

* Create local shop
* Update local shop
* Activate/deactivate local shop
* Update address
* Update coordinates
* Update contact information
* Update synchronization timestamp

The exact endpoint design will depend on the actual Business/Finance application's API capabilities.

---

# 46. Fleet-to-Business Communication

The integration should work in both directions where required.

```text
BUSINESS APP
     │
     │ Business Requirements
     │ Shop Master Data
     ▼
FLEET APP
     │
     │ Operational Status
     │ Trip Status
     │ Delivery Status
     │ Vehicle Information
     ▼
BUSINESS APP
```

Fleet may need to notify the Business Application when events occur such as:

* Request accepted
* Trip created
* Vehicle assigned
* Trip started
* Delivery completed
* Partial delivery
* Return approved
* Trip completed
* Trip cancelled

The exact webhook/callback contract will be defined during API design.

---

# 47. Data Flow: Shop Synchronization

The Business Application owns the master shop record.

Example:

```text
Business App

Shop ID: 847
Name: Westlands Branch
Address: ...
GPS: ...
        │
        │ HTTPS API
        ▼
Fleet Integration
        │
        ▼
Fleet shops

id: 12
external_shop_id: 847
name: Westlands Branch
address: ...
latitude: ...
longitude: ...
```

Fleet then uses `shops.id = 12` internally.

The external Business identifier remains available through:

```text
shops.external_shop_id = 847
```

No direct database write is performed against the Business application.

---

# 48. Data Flow: LPO Collection

```text
Business App
     │
     │ LPO
     ▼
Transport Request
     │
     │ type = lpo_collection
     ▼
Fleet Manager
     │
     ▼
Trip
     │
     ├── Vehicle
     ├── Driver
     ├── Manufacturer Pickup
     └── Warehouse Delivery
     │
     ▼
Driver Execution
     │
     ▼
Trip Completion
```

The LPO remains in the Business Application.

---

# 49. Data Flow: Dispatch Invoice to Shop

```text
Business App
     │
     │ Dispatch Invoice to Shop
     │ source_reference = DIS-2026-00452
     │
     ▼
Transport Request
     │
     │ type = dispatch_invoice_to_shop
     │
     ├── Shop external ID 847
     ├── Shop external ID 912
     └── Shop external ID 1031
     │
     ▼
Fleet resolves local shops
     │
     ├── Fleet Shop A
     ├── Fleet Shop B
     └── Fleet Shop C
     │
     ▼
Fleet Manager
     │
     ▼
Trip
     │
     ├── Warehouse
     ├── Shop A
     ├── Shop B
     └── Shop C
     │
     ▼
Driver
     │
     ├── Shop A verification
     ├── Shop B verification
     └── Shop C verification
     │
     ▼
Trip Completion
     │
     ▼
Business App notified
```

The Business application remains the owner of the commercial paperwork.

Fleet owns the physical execution.

---

# 50. Data Flow: Custom Transport

```text
Business App
     │
     │ Custom requirement
     ▼
Custom Transport Request
     │
     ▼
Fleet Manager
     │
     ▼
Trip
     │
     ├── Origin
     ├── Destination(s)
     ├── Vehicle
     └── Driver
     │
     ▼
Execution
```

---

# 51. Vehicle Lifecycle

```text
Vehicle Registered
       ↓
Available
       ↓
Assigned
       ↓
En Route
       ↓
Trip Completed
       ↓
Available
```

Maintenance can interrupt this lifecycle:

```text
Available
   ↓
Maintenance Required
   ↓
Maintenance
   ↓
Inspection
   ↓
Available
```

---

# 52. Overall Operational Lifecycle

```text
BUSINESS REQUIREMENT
        │
        ▼
TRANSPORT REQUEST
        │
        ▼
REQUEST REVIEW
        │
        ▼
TRIP PLANNING
        │
        ├── Vehicle
        ├── Driver
        └── Stops
        │
        ▼
DISPATCH
        │
        ▼
TRIP START
        │
        ▼
STOP 1
        │
        ▼
STOP 2
        │
        ▼
STOP N
        │
        ▼
RETURN / COMPLETE
        │
        ▼
MILEAGE AUDIT
        │
        ▼
TRIP COMPLETED
        │
        ▼
BUSINESS APP UPDATED
```

---

# 53. Important Architectural Principles

## Principle 1 — No duplicate authentication

Fleet does not create a competing user/password system.

## Principle 2 — No direct database integration

The Business Application communicates with Fleet through HTTPS APIs.

Fleet also does not connect directly to the Business/Finance database.

There are no shared database credentials or direct cross-database writes.

## Principle 3 — Business/Finance owns business master data

Business/Finance remains authoritative for:

* Shops/branches
* Customers
* LPOs
* Invoices
* Financial records
* Commercial paperwork
* Business users and permissions

## Principle 4 — Fleet maintains its own operational shop data

Fleet keeps a local `shops` table because shop information is required for:

* GPS
* Geofencing
* Routing
* Vehicle assignment
* Navigation
* Trip execution
* Historical records

Each Fleet shop references its corresponding Business shop using `external_shop_id`.

## Principle 5 — Fleet never writes to the Business database

Fleet communicates through APIs.

The Business application can expose data and integration endpoints without giving Fleet database access.

## Principle 6 — Financial ownership stays external

Invoices, LPOs, financial records and commercial paperwork remain in the Business Application.

## Principle 7 — Fleet owns execution

Vehicles, GPS, trips, routes, mileage, delivery execution and maintenance belong to Fleet.

## Principle 8 — Requests are not trips

A business requirement must be separated from its physical execution.

## Principle 9 — Specific business processes remain specific

`dispatch_invoice_to_shop` is a real request type and must not be hidden under custom.

## Principle 10 — Custom remains extensible

`custom_transport` handles requirements that do not yet have a dedicated business process.

## Principle 11 — Historical records must remain stable

Operational records should retain relevant snapshots so historical trips do not change when external business records change.

## Principle 12 — Every important operational action is auditable

The system must be able to determine who performed or approved an action.

## Principle 13 — Existing telemetry remains foundational

Protrack365 remains the vehicle telemetry source while Fleet becomes the operational system of record.

---

# 54. Development Strategy

We should build this in controlled phases.

## Phase 1 — Existing Foundation Review

Before modifying the database:

* Inspect current migrations
* Inspect VehicleDeployment model
* Inspect deployment controller
* Inspect vehicle model
* Inspect existing relationships
* Inspect existing deployment statuses
* Confirm current database schema
* Inspect current `shops` table
* Confirm existing shop relationships
* Identify how the existing 44 shops are currently seeded

**Goal:** avoid duplicating existing functionality.

---

## Phase 2 — Shop Reference Preparation

Before integration:

* Keep existing `shops` table
* Add `external_shop_id` where required
* Determine how existing shops map to Business shop IDs
* Add synchronization metadata where appropriate
* Preserve existing Fleet-specific shop fields
* Ensure vehicle/shop relationships continue working

**Goal:** establish the Business shop reference without breaking existing Fleet functionality.

---

## Phase 3 — Transport Domain

Build:

```text
transport_requests
transport_request_stops
trips
trip_stops
```

and establish their relationships with:

```text
vehicles
shops
external users
```

---

## Phase 4 — Integration API

Build the contract between:

```text
Business Application
        ↕
Fleet Application
```

starting with:

* Shop synchronization/reference
* Transport request creation
* Transport request update
* Transport request cancellation
* Vehicle availability
* Vehicle location

---

## Phase 5 — Fleet Manager Operations

Build:

* Request queue
* Trip planning
* Vehicle assignment
* Driver assignment
* Stop management
* Routing
* Availability

---

## Phase 6 — Driver Operations

Build:

* Active trip
* Trip start
* Arrival
* Stop sequence
* Delivery confirmation
* Return-to-base requests
* Trip completion

---

## Phase 7 — Delivery Verification

Build:

* SMS trigger
* Secure verification code
* Code validation
* Manual override
* Audit trail

---

## Phase 8 — Mileage & Auditing

Integrate:

* Protrack mileage
* Trip start mileage
* Trip completion mileage
* Trip mileage
* Operational audit

---

## Phase 9 — Maintenance

Build:

* Service schedules
* Alerts
* Maintenance tickets
* Job cards
* Checklists
* Repairs
* Replacements

---

## Phase 10 — Inventory

Build:

* Parts
* Categories
* Stock movements
* Job-card consumption
* Tools
* Tool assignments

---

## Phase 11 — Reporting & Analytics

Add:

* Fleet utilization
* Vehicle mileage
* Maintenance history
* Service compliance
* Trip history
* Delivery performance
* Driver execution
* Parts consumption
* Tool utilization
* Operational analytics

---

# 55. Final Target Architecture

```text
                 ┌───────────────────────────────┐
                 │ BUSINESS / FINANCE APP        │
                 │                               │
                 │ Authentication                │
                 │ Users                         │
                 │ Customers                     │
                 │ Manufacturers                 │
                 │ Shops / Branches              │
                 │ LPOs                          │
                 │ Invoices                      │
                 │ Warehouse                     │
                 │ Financial Records             │
                 │ Business Paperwork            │
                 │                               │
                 │ BUSINESS MASTER DATA          │
                 └──────────────┬────────────────┘
                                │
                                │ HTTPS API
                                │
                 ┌──────────────┴──────────────┐
                 │                             │
                 ▼                             ▼
          Shop Synchronization          Transport Requests
                 │                             │
                 └──────────────┬──────────────┘
                                ▼
┌─────────────────────────────────────────────────────────────┐
│                    FLEET APPLICATION                        │
│                                                             │
│  ┌──────────────────────┐                                   │
│  │ Integration API      │                                   │
│  └──────────┬───────────┘                                   │
│             │                                               │
│             ▼                                               │
│  ┌──────────────────────┐                                   │
│  │ Local Shops          │                                   │
│  │                      │                                   │
│  │ Fleet ID             │                                   │
│  │ External Shop ID     │                                   │
│  │ GPS                   │                                   │
│  │ Address               │                                   │
│  │ Geofence              │                                   │
│  └──────────┬───────────┘                                   │
│             │                                               │
│             ▼                                               │
│  ┌──────────────────────┐                                   │
│  │ Transport Requests   │                                   │
│  │                      │                                   │
│  │ LPO Collection       │                                   │
│  │ Invoice Delivery     │                                   │
│  │ Dispatch Invoice     │                                   │
│  │ to Shop              │                                   │
│  │ Custom Transport     │                                   │
│  └──────────┬───────────┘                                   │
│             │                                               │
│             ▼                                               │
│  ┌──────────────────────┐                                   │
│  │ Trips                │                                   │
│  └──────────┬───────────┘                                   │
│             │                                               │
│       ┌─────┼───────────────┐                               │
│       ▼     ▼               ▼                               │
│    Vehicle Driver       Trip Stops                          │
│                            │                                 │
│                  ┌─────────┼─────────┐                       │
│                  ▼         ▼         ▼                       │
│               Pickup    Delivery   Return                    │
│                              │                                │
│                              ▼                                │
│                        Verification                          │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ Fleet Operations                                     │   │
│  │ Availability │ Mileage │ Routing │ Deployments      │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ Maintenance                                          │   │
│  │ Schedules │ Alerts │ Tickets │ Job Cards │ Repairs  │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ Inventory                                            │   │
│  │ Parts │ Stock │ Movements │ Tools │ Assignments     │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ Audit & Reporting                                    │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                             │
└──────────────┬──────────────────────────┬───────────────────┘
               │                          │
               ▼                          ▼
       ┌───────────────┐          ┌────────────────┐
       │ Protrack365   │          │ OSRM /         │
       │ GPS Platform  │          │ LocationIQ     │
       └───────────────┘          └────────────────┘
```

---

# 56. Architecture Baseline

This document establishes the current design direction.

The most important decisions are:

1. **The Business / Finance Application owns authentication and financial/commercial data.**
2. **The Business / Finance Application is the authoritative owner of business shop/branch master records.**
3. **Fleet maintains its own `shops` table for operational use.**
4. **Fleet shops contain an external reference to the corresponding Business/Finance shop.**
5. **Fleet does not require direct database access to the Business/Finance application.**
6. **Fleet never writes directly to the Business/Finance database.**
7. **Business/Finance and Fleet communicate through authenticated HTTPS APIs.**
8. **LPO Collection, Invoice Delivery, Dispatch Invoice to Shop, and Custom Transport are transport-request types.**
9. **Dispatch Invoice to Shop is a specific business process, not custom.**
10. **Custom Transport is a separate generic request type.**
11. **Transport Request and Trip are separate entities.**
12. **Trips contain ordered Trip Stops.**
13. **Known shop stops reference the local Fleet shop and retain appropriate historical snapshot data.**
14. **Fleet owns operational execution and telemetry.**
15. **Delivery verification and return-to-base workflows belong to Fleet.**
16. **Maintenance, parts and tools belong to Fleet.**
17. **Financial details remain in the Business / Finance Application.**
18. **Existing `vehicle_deployments` must be inspected before restructuring the trip model.**
19. **Existing Fleet shop data should not be discarded simply because Business/Finance also owns shop master data.**
20. **The existing telemetry, location-resolution, routing and operational Fleet functionality remains foundational.**
