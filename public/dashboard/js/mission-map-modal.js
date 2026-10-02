/**
 * Mission Route Map Modal
 *
 * Opens a fullscreen Leaflet map popup showing the plotted OSRM road route
 * from the vehicle's current GPS position to its mission destination shop.
 */

import { getSelectedVehicle } from "./state.js";
import { showToast } from "./toast.js";

let modalMap = null;
let routeLayer = null;
let routeFenceLayer = null;
let originMarker = null;
let destMarker = null;

const OSRM_BASE = "https://router.project-osrm.org/route/v1/driving";

// ─── Modal HTML (injected once) ──────────────────────────────────────────────

function ensureModalExists() {
  if (document.getElementById("mission-map-modal")) return;

  const modal = document.createElement("div");
  modal.id = "mission-map-modal";
  modal.className =
    "fixed inset-0 z-[4000] hidden bg-slate-900/70 backdrop-blur-sm flex items-center justify-center p-3 sm:p-6";
  modal.setAttribute("role", "dialog");
  modal.setAttribute("aria-modal", "true");
  modal.setAttribute("aria-labelledby", "mission-map-title");

  modal.innerHTML = `
    <div class="relative bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-4xl overflow-hidden flex flex-col" style="height: min(88vh, 700px);">

      <!-- Header -->
      <div class="px-5 py-3.5 border-b border-slate-200 bg-white flex items-center justify-between shrink-0 z-10">
        <div class="flex items-center gap-2.5 min-w-0">
          <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500 text-white shrink-0">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M3 12h18M3 12l6-6m-6 6 6 6"/>
              <circle cx="19" cy="12" r="2"/>
            </svg>
          </span>
          <div class="min-w-0">
            <h3 id="mission-map-title" class="text-sm font-bold text-slate-900 truncate">Mission Route</h3>
            <p id="mission-map-subtitle" class="text-[11px] text-slate-500 truncate">Calculating road route…</p>
          </div>
        </div>

        <!-- Route stats -->
        <div id="mission-route-stats" class="hidden items-center gap-4 text-xs mx-4">
          <div class="text-center">
            <span class="block text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Distance</span>
            <span id="mission-stat-distance" class="font-bold text-slate-800">--</span>
          </div>
          <div class="w-px h-7 bg-slate-200"></div>
          <div class="text-center">
            <span class="block text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Est. Time</span>
            <span id="mission-stat-duration" class="font-bold text-slate-800">--</span>
          </div>
          <div class="w-px h-7 bg-slate-200"></div>
          <div class="text-center">
            <span class="block text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Purpose</span>
            <span id="mission-stat-purpose" class="font-bold text-slate-800 max-w-[120px] truncate block">--</span>
          </div>
        </div>

        <button
          id="mission-map-close"
          type="button"
          class="ml-auto shrink-0 text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 transition"
          aria-label="Close route map"
        >
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 6 6 18M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <!-- Map fills remaining space -->
      <div id="mission-map-container" class="flex-1 relative min-h-0" style="min-height: 280px;">
        <!-- Loading overlay -->
        <div id="mission-map-loading" class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-white gap-3">
          <div class="w-10 h-10 rounded-full border-4 border-amber-200 border-t-amber-500 animate-spin"></div>
          <p class="text-xs text-slate-500 font-medium">Fetching road route via OSRM…</p>
        </div>

        <!-- Error overlay -->
        <div id="mission-map-error" class="hidden absolute inset-0 z-10 flex flex-col items-center justify-center bg-white gap-3 px-6 text-center">
          <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
            </svg>
          </div>
          <div>
            <p class="text-sm font-bold text-slate-800">Route Unavailable</p>
            <p id="mission-map-error-msg" class="text-xs text-slate-500 mt-1">Could not compute a road route for this mission.</p>
          </div>
        </div>

        <div id="mission-map-leaflet" class="absolute inset-0"></div>
      </div>

      <!-- Footer legend -->
      <div class="px-5 py-2.5 border-t border-slate-100 bg-slate-50 shrink-0 flex items-center gap-4 text-[11px] text-slate-500">
        <span class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-full bg-[#2563eb] border-2 border-white shadow"></span> Current Position
        </span>
        <span class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-full bg-amber-500 border-2 border-white shadow"></span> Destination
        </span>
        <span class="flex items-center gap-1.5">
          <span class="block w-6 h-0.5 bg-amber-500 rounded"></span> Road Route
        </span>
        <span class="flex items-center gap-1.5">
          <span class="block w-6 h-2 bg-sky-300 rounded opacity-80"></span> 20m Route Fence
        </span>
        <span class="ml-auto text-[10px]">Route data © OSRM / OpenStreetMap contributors</span>
      </div>
    </div>
  `;

  document.body.appendChild(modal);

  // Close handlers
  document.getElementById("mission-map-close").addEventListener("click", closeMissionMapModal);
  modal.addEventListener("click", (e) => {
    if (e.target === modal) closeMissionMapModal();
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && !modal.classList.contains("hidden")) {
      closeMissionMapModal();
    }
  });
}

// ─── Leaflet Map init (recreated on each open to avoid zero-size container bug) ──

function destroyLeafletMap() {
  if (modalMap) {
    clearRouteOverlays();
    modalMap.remove();
    modalMap = null;
  }
}

function createLeafletMap() {
  modalMap = L.map("mission-map-leaflet", {
    zoomControl: true,
    preferCanvas: true,
  }).setView([-0.5, 36.5], 7);

  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19,
  }).addTo(modalMap);

  return modalMap;
}

function clearRouteOverlays() {
  if (!modalMap) return;
  if (routeFenceLayer) { modalMap.removeLayer(routeFenceLayer); routeFenceLayer = null; }
  if (routeLayer) { modalMap.removeLayer(routeLayer); routeLayer = null; }
  if (originMarker) { modalMap.removeLayer(originMarker); originMarker = null; }
  if (destMarker) { modalMap.removeLayer(destMarker); destMarker = null; }
}

function createRouteFencePolygon(latlngs, widthMeters = 20) {
  if (!Array.isArray(latlngs) || latlngs.length < 2) return null;

  const leftSide = [];
  const rightSide = [];
  const metersPerLatitude = 111_320;

  for (let i = 0; i < latlngs.length; i += 1) {
    const [lat, lng] = latlngs[i];
    const prev = latlngs[Math.max(0, i - 1)];
    const next = latlngs[Math.min(latlngs.length - 1, i + 1)];

    const [prevLat, prevLng] = prev;
    const [nextLat, nextLng] = next;

    const avgLat = (lat + prevLat + nextLat) / 3;
    const avgLatRad = (avgLat * Math.PI) / 180;
    const metersPerLongitude = 111_320 * Math.cos(avgLatRad);

    let dx = nextLng - prevLng;
    let dy = nextLat - prevLat;

    if (i === 0) {
      dx = nextLng - lng;
      dy = nextLat - lat;
    } else if (i === latlngs.length - 1) {
      dx = lng - prevLng;
      dy = lat - prevLat;
    }

    const segmentLength = Math.hypot(dx * metersPerLongitude, dy * metersPerLatitude) || 1;
    const normalX = -((dy * metersPerLatitude) / segmentLength);
    const normalY = ((dx * metersPerLongitude) / segmentLength);

    const latOffset = (normalY * widthMeters) / metersPerLatitude;
    const lngOffset = (normalX * widthMeters) / metersPerLongitude;

    leftSide.push([lat + latOffset, lng + lngOffset]);
    rightSide.push([lat - latOffset, lng - lngOffset]);
  }

  const polygon = [
    ...leftSide,
    ...[...rightSide].reverse(),
  ];

  if (polygon.length < 3) return null;

  return polygon;
}

// ─── OSRM Route Fetch ─────────────────────────────────────────────────────────

async function fetchOsrmRoute(fromLat, fromLng, toLat, toLng) {
  const url = `${OSRM_BASE}/${fromLng},${fromLat};${toLng},${toLat}?overview=full&geometries=geojson`;
  const res = await fetch(url);

  if (!res.ok) throw new Error(`OSRM responded with HTTP ${res.status}`);

  const data = await res.json();

  if (data.code !== "Ok" || !data.routes || data.routes.length === 0) {
    throw new Error(data.message || "No route found between these points.");
  }

  const route = data.routes[0];
  return {
    coordinates: route.geometry.coordinates, // [lng, lat] pairs
    distanceMeters: route.distance,
    durationSeconds: route.duration,
  };
}

// ─── Marker helpers ───────────────────────────────────────────────────────────

function createPinMarker(lat, lng, color, label) {
  const icon = L.divIcon({
    className: "",
    iconSize: [32, 32],
    iconAnchor: [16, 32],
    popupAnchor: [0, -32],
    html: `
      <div style="position:relative;width:32px;height:32px;">
        <div style="
          width:28px;height:28px;border-radius:50% 50% 50% 0;
          transform:rotate(-45deg);border:3px solid white;
          background:${color};box-shadow:0 2px 6px rgba(0,0,0,0.35);
          position:absolute;top:0;left:2px;
        "></div>
      </div>
    `,
  });

  return L.marker([lat, lng], { icon }).bindPopup(
    `<div class="text-xs font-semibold" style="min-width:120px">${label}</div>`,
    { closeButton: false }
  );
}

// ─── Formatting helpers ───────────────────────────────────────────────────────

function formatDistance(meters) {
  return meters >= 1000
    ? `${(meters / 1000).toFixed(1)} km`
    : `${Math.round(meters)} m`;
}

function formatDuration(seconds) {
  const h = Math.floor(seconds / 3600);
  const m = Math.round((seconds % 3600) / 60);
  if (h > 0) return `${h}h ${m}m`;
  return `${m} min`;
}

// ─── Open / Close ─────────────────────────────────────────────────────────────

export function closeMissionMapModal() {
  const modal = document.getElementById("mission-map-modal");
  if (modal) modal.classList.add("hidden");
  // Destroy the map so next open re-initialises in a correctly-sized container
  destroyLeafletMap();
}

/**
 * Open the mission route modal for a given vehicle + deployment.
 *
 * @param {object} vehicle    - Full vehicle object from state
 * @param {object} deployment - Active or freshly-created deployment object
 * @param {object} destShop   - Destination shop { name, lat, lng }
 */
export function openMissionMapModal(vehicle, deployment, destShop) {
  ensureModalExists();

  const modal = document.getElementById("mission-map-modal");
  const loading = document.getElementById("mission-map-loading");
  const errorDiv = document.getElementById("mission-map-error");
  const errorMsg = document.getElementById("mission-map-error-msg");
  const statsBar = document.getElementById("mission-route-stats");

  // 1. Tear down any previous map so we start fresh
  destroyLeafletMap();

  // 2. Reset UI overlays
  loading.classList.remove("hidden");
  errorDiv.classList.add("hidden");
  statsBar.classList.remove("flex");
  statsBar.classList.add("hidden");

  // 3. Set header text
  const plate = vehicle.plate_number || vehicle.imei || "Vehicle";
  const dest = destShop?.name || deployment?.destination?.name || "Destination";
  document.getElementById("mission-map-title").textContent = `Mission Route — ${plate}`;
  document.getElementById("mission-map-subtitle").textContent = `${plate} → ${dest}`;
  document.getElementById("mission-stat-purpose").textContent = deployment?.purpose || "Fleet Mission";

  // 4. Show the modal FIRST — browser must paint before Leaflet measures size
  modal.classList.remove("hidden");

  // 5. Defer Leaflet init + route fetch until after the browser has painted
  setTimeout(() => _initMapAndRoute(vehicle, deployment, destShop, plate, dest, loading, errorDiv, errorMsg, statsBar), 150);
}

async function _initMapAndRoute(vehicle, deployment, destShop, plate, dest, loading, errorDiv, errorMsg, statsBar) {
  // Create the map now that the container is visible and has real dimensions
  const map = createLeafletMap();
  requestAnimationFrame(() => map.invalidateSize());

  // Coordinates
  const fromLat = Number(vehicle.location_latitude || vehicle.latest_telemetry?.latitude);
  const fromLng = Number(vehicle.location_longitude || vehicle.latest_telemetry?.longitude);
  const toLat = Number(destShop?.lat || deployment?.destination?.latitude);
  const toLng = Number(destShop?.lng || deployment?.destination?.longitude);

  const hasOrigin = !isNaN(fromLat) && !isNaN(fromLng) && fromLat !== 0 && fromLng !== 0;
  const hasDest   = !isNaN(toLat)   && !isNaN(toLng)   && toLat  !== 0 && toLng  !== 0;

  if (!hasOrigin && !hasDest) {
    loading.classList.add("hidden");
    errorDiv.classList.remove("hidden");
    if (errorMsg) errorMsg.textContent = "Neither the vehicle position nor the destination have valid GPS coordinates.";
    return;
  }

  try {
    if (hasOrigin && hasDest) {
      // Full OSRM road route
      const route = await fetchOsrmRoute(fromLat, fromLng, toLat, toLng);

      // OSRM returns [lng, lat]; Leaflet needs [lat, lng]
      const latlngs = route.coordinates.map(([lng, lat]) => [lat, lng]);

      routeFenceLayer = L.polygon(createRouteFencePolygon(latlngs, 20), {
        color: "#0284c7",
        weight: 2,
        opacity: 1,
        fillColor: "#38bdf8",
        fillOpacity: 0.32,
      }).addTo(map);

      L.polyline(latlngs, {
        color: "#ffffff",
        weight: 10,
        opacity: 0.95,
        lineJoin: "round",
        lineCap: "round",
      }).addTo(map);

      routeLayer = L.polyline(latlngs, {
        color: "#dc2626",
        weight: 6,
        opacity: 1,
        lineJoin: "round",
        lineCap: "round",
      }).addTo(map);

      originMarker = createPinMarker(fromLat, fromLng, "#2563eb", `${plate}<br>Current Position`).addTo(map);
      destMarker   = createPinMarker(toLat,   toLng,   "#f59e0b", `${dest}<br>Mission Destination`).addTo(map);

      map.fitBounds(L.latLngBounds(latlngs), { padding: [48, 48] });

      document.getElementById("mission-stat-distance").textContent = formatDistance(route.distanceMeters);
      document.getElementById("mission-stat-duration").textContent = formatDuration(route.durationSeconds);
      statsBar.classList.remove("hidden");
      statsBar.classList.add("flex");

    } else if (hasOrigin) {
      map.setView([fromLat, fromLng], 13);
      originMarker = createPinMarker(fromLat, fromLng, "#2563eb", `${plate}<br>Current Position`).addTo(map);
      showToast("Partial Route", "Destination shop has no GPS coordinates — showing vehicle position only.", true);

    } else {
      map.setView([toLat, toLng], 13);
      destMarker = createPinMarker(toLat, toLng, "#f59e0b", `${dest}<br>Mission Destination`).addTo(map);
      showToast("Partial Route", "Vehicle has no GPS fix — showing destination only.", true);
    }

    loading.classList.add("hidden");

  } catch (err) {
    loading.classList.add("hidden");
    errorDiv.classList.remove("hidden");
    if (errorMsg) errorMsg.textContent = err.message;

    // Fallback — drop markers even if route failed
    if (hasOrigin) originMarker = createPinMarker(fromLat, fromLng, "#2563eb", `${plate} — No Route`).addTo(map);
    if (hasDest)   destMarker   = createPinMarker(toLat,   toLng,   "#f59e0b", `${dest}`).addTo(map);

    const points = [];
    if (hasOrigin) points.push([fromLat, fromLng]);
    if (hasDest)   points.push([toLat, toLng]);
    if (points.length === 2) {
      map.fitBounds(L.latLngBounds(points), { padding: [48, 48] });
    } else if (points.length === 1) {
      map.setView(points[0], 13);
    }
  }
}

/**
 * Convenience: open route modal for the currently selected vehicle's active deployment.
 * Reads vehicle + deployment from state.
 */
export function openActiveMissionRoute(shops) {
  const { getSelectedVehicle } = window._fleetState || {};
  const vehicle = typeof getSelectedVehicle === "function"
    ? getSelectedVehicle()
    : null;

  if (!vehicle) {
    showToast("No Vehicle", "Select a vehicle first.", true);
    return;
  }

  const deployment = vehicle.active_deployment;
  if (!deployment) {
    showToast("No Active Mission", "This vehicle has no active deployment to map.", true);
    return;
  }

  const destId = deployment.destination_id;
  const destShop = Array.isArray(shops)
    ? shops.find(s => s.id === destId)
    : null;

  const destCoords = destShop || {
    name: deployment.destination?.name,
    lat: deployment.destination?.latitude,
    lng: deployment.destination?.longitude,
  };

  openMissionMapModal(vehicle, deployment, destCoords);
}
