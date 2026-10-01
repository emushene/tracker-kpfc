/**
 * Leaflet Live Map Component for Fleet & Geofences
 */

let mapInstance = null;
let mapMarkers = [];

export function initMap(containerId = "map") {
  if (mapInstance) {
    return mapInstance;
  }

  // Centered on Kenya (-0.5, 36.5)
  mapInstance = L.map(containerId).setView([-0.5, 36.5], 7);

  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19,
  }).addTo(mapInstance);

  return mapInstance;
}

export function renderShopGeofences(shops) {
  if (!mapInstance || !Array.isArray(shops)) return;

  shops.forEach(shop => {
    if (typeof shop.lat !== "number" || isNaN(shop.lat) || typeof shop.lng !== "number" || isNaN(shop.lng)) {
      return;
    }

    L.circle([shop.lat, shop.lng], {
      color: "#6366f1",
      fillColor: "#818cf8",
      fillOpacity: 0.2,
      radius: shop.radius || 500,
    }).addTo(mapInstance).bindPopup(`
      <div class="text-xs">
        <strong class="text-sm font-semibold">${shop.name}</strong><br>
        <span class="text-slate-500 font-mono">Code: ${shop.code}</span><br>
        <span>Radius: ${shop.radius}m</span>
      </div>
    `);
  });
}

export function updateVehicleMarkers(vehicles, selectedVehicleId, onSelectVehicle) {
  if (!mapInstance) return;

  // Clear previous vehicle markers
  mapMarkers.forEach(m => mapInstance.removeLayer(m));
  mapMarkers = [];

  vehicles.forEach(v => {
    const isSelected = selectedVehicleId === v.id;
    const lat = v.location_latitude || v.latest_telemetry?.latitude;
    const lng = v.location_longitude || v.latest_telemetry?.longitude;

    if (!lat || !lng) return;

    const markerColor = isSelected
      ? "#dc2626"
      : (v.status === "moving" ? "#10b981" : (v.status === "deployed" ? "#f59e0b" : "#64748b"));

    const marker = L.circleMarker([lat, lng], {
      color: markerColor,
      fillColor: markerColor,
      fillOpacity: isSelected ? 0.95 : 0.8,
      radius: isSelected ? 11 : 8,
      weight: isSelected ? 4 : 2,
      className: isSelected ? "selected-vehicle-marker" : "",
    }).addTo(mapInstance);

    if (isSelected) {
      marker.bringToFront();
    }

    marker.bindPopup(`
      <div class="text-xs space-y-1">
        <strong class="text-sm font-bold">${v.plate_number || v.imei}</strong><br>
        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase ${
          v.status === "moving" ? "bg-emerald-100 text-emerald-800" :
          (v.status === "deployed" ? "bg-amber-100 text-amber-800" : "bg-slate-100 text-slate-700")
        }">${v.status}</span><br>
        <span><b>Location:</b> ${v.location_name || "In Transit"}</span><br>
        <span><b>Speed:</b> ${v.latest_telemetry?.speed || 0} km/h</span><br>
        <span><b>Mission:</b> ${v.active_deployment?.destination?.name || "--"}</span><br>
        <span><b>Home Base:</b> ${v.assigned_shop?.name || "Unassigned"}</span><br>
        <span><b>Home Dist:</b> ${v.homebase_distance?.distance_km ?? "--"} km</span>
      </div>
    `);

    marker.on("click", () => {
      if (typeof onSelectVehicle === "function") {
        onSelectVehicle(v.id);
      }
    });

    mapMarkers.push(marker);
  });
}

export function flyToCoordinates(lat, lng, zoom = 13) {
  if (mapInstance && typeof lat === "number" && typeof lng === "number") {
    mapInstance.flyTo([lat, lng], zoom);
  }
}
