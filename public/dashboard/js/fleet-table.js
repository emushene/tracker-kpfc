/**
 * Fleet Vehicles Table Component
 */

import { state } from "./state.js";

function getVehicleSortValue(vehicle, sortKey) {
  switch (sortKey) {
    case "status":
      return vehicle.status || "";
    case "location":
      return vehicle.location_name || "";
    case "homebase":
      return vehicle.homebase_distance?.distance_km ?? Number.POSITIVE_INFINITY;
    case "mission":
      return vehicle.routing?.distance_km ?? Number.POSITIVE_INFINITY;
    case "vehicle":
    default:
      return vehicle.plate_number || vehicle.imei || "";
  }
}

function compareVehicles(firstVehicle, secondVehicle) {
  const firstValue = getVehicleSortValue(firstVehicle, state.sortKey);
  const secondValue = getVehicleSortValue(secondVehicle, state.sortKey);
  let comparison = 0;

  if (typeof firstValue === "number" && typeof secondValue === "number") {
    comparison = firstValue - secondValue;
  } else {
    comparison = String(firstValue).localeCompare(String(secondValue), undefined, {
      numeric: true,
      sensitivity: "base",
    });
  }

  return state.sortDirection === "asc" ? comparison : -comparison;
}

export function getFilteredAndSortedVehicles() {
  const query = (state.searchQuery || "").trim().toLowerCase();
  const filter = state.statusFilter || "all";

  const filtered = state.vehicles.filter(v => {
    // 1. Text Search Filter
    const text = `${v.plate_number || ""} ${v.imei || ""} ${v.device_name || ""}`.toLowerCase();
    if (!text.includes(query)) {
      return false;
    }

    // 2. Status Pill Filter
    if (filter === "moving") return v.status === "moving";
    if (filter === "deployed") return Boolean(v.active_deployment);
    if (filter === "parked") return v.status === "parked";
    if (filter === "no-tracker") return Boolean(v.imei && v.imei.startsWith("FLEET-"));

    return true;
  });

  return filtered.sort(compareVehicles);
}

export function setStatusFilter(filter, onRender) {
  state.statusFilter = filter;
  state.currentPage = 1;

  // Update visual pill active states
  const pills = document.querySelectorAll(".filter-pill");
  pills.forEach(pill => {
    const pillFilter = pill.getAttribute("data-filter");
    if (pillFilter === filter) {
      pill.className = "filter-pill px-2.5 py-1 rounded-md text-xs font-semibold bg-[#2563eb] text-white shadow-sm transition";
    } else {
      pill.className = "filter-pill px-2.5 py-1 rounded-md text-xs font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-200/70 bg-slate-100 transition";
    }
  });

  if (typeof onRender === "function") {
    onRender();
  }
}

export function updateSortIndicators() {
  ["vehicle", "status", "location", "homebase", "mission"].forEach(key => {
    const indicator = document.getElementById(`sort-${key}`);
    if (indicator) {
      indicator.innerText = key === state.sortKey
        ? (state.sortDirection === "asc" ? "↑" : "↓")
        : "↕";
    }
  });
}

export function updateVehiclePagination(totalVehicles, totalPages, firstVehicleIndex) {
  const paginationInfo = document.getElementById("pagination-info");
  const paginationPage = document.getElementById("pagination-page");
  const previousPage = document.getElementById("previous-page");
  const nextPage = document.getElementById("next-page");

  if (!paginationInfo || !paginationPage || !previousPage || !nextPage) return;

  const lastVehicleIndex = Math.min(firstVehicleIndex + state.vehiclesPerPage, totalVehicles);

  paginationInfo.innerText = totalVehicles === 0
    ? "No vehicles found"
    : `Showing ${firstVehicleIndex + 1}-${lastVehicleIndex} of ${totalVehicles} vehicles`;

  paginationPage.innerText = `Page ${state.currentPage} of ${totalPages}`;
  previousPage.disabled = state.currentPage === 1;
  nextPage.disabled = state.currentPage >= totalPages;
}

export function renderFleetTable() {
  const tbody = document.getElementById("vehicle-table-body");
  if (!tbody) return;

  const sortedVehicles = getFilteredAndSortedVehicles();
  const totalPages = Math.max(1, Math.ceil(sortedVehicles.length / state.vehiclesPerPage));
  state.currentPage = Math.min(state.currentPage, totalPages);

  const firstVehicleIndex = (state.currentPage - 1) * state.vehiclesPerPage;
  const pagedVehicles = sortedVehicles.slice(firstVehicleIndex, firstVehicleIndex + state.vehiclesPerPage);

  if (pagedVehicles.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="5" class="text-center py-10 text-slate-500">
          <div class="flex flex-col items-center justify-center space-y-1">
            <span class="text-sm font-medium">No matching vehicles found</span>
            <span class="text-xs text-slate-400">Try adjusting your search query or filter selection.</span>
          </div>
        </td>
      </tr>
    `;
    updateVehiclePagination(0, 1, 0);
    return;
  }

  tbody.innerHTML = pagedVehicles.map(v => {
    const isSelected = state.selectedVehicleId === v.id;
    const deploymentDestination = v.active_deployment?.destination?.name || null;

    const statusBadge = v.status === "moving"
      ? '<span class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200/70 px-2 py-0.5 rounded-full text-[10px] font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>Moving</span>'
      : (v.status === "deployed"
        ? `<span class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-700 border border-amber-200/70 px-2 py-0.5 rounded-full text-[10px] font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Deployed</span><div class="text-[10px] text-amber-600 mt-0.5 font-medium truncate max-w-[120px]">To: ${deploymentDestination || "--"}</div>`
        : '<span class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-600 border border-slate-200 px-2 py-0.5 rounded-full text-[10px] font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Parked</span>');

    const routingInfo = v.routing?.distance_km
      ? `<span class="text-indigo-600 font-mono font-medium">${v.routing.distance_km} km</span> <span class="text-slate-400 text-[11px]">(~${v.routing.duration_minutes}m)</span>`
      : '<span class="text-slate-400">--</span>';

    const homebaseInfo = v.assigned_shop
      ? `<span class="font-medium text-slate-800">${v.assigned_shop.name}</span><br><span class="text-[10px] text-slate-500">${v.homebase_distance?.distance_km ?? "--"} km away</span>`
      : '<span class="text-slate-400 italic">Unassigned</span>';

    const isAutoPlaceholderImei = v.imei && v.imei.startsWith("FLEET-");

    const trackerIndicator = isAutoPlaceholderImei
      ? `<span class="inline-flex items-center gap-1 text-[10px] font-medium text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded mt-0.5" title="No hardware tracker yet. Placeholder auto-generated.">
           <svg class="w-3 h-3 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
           Pending Tracker
         </span>`
      : `<span class="text-[10px] font-normal font-mono text-slate-500">${v.imei}</span>`;

    return `
      <tr class="hover:bg-blue-50/40 transition cursor-pointer ${
        isSelected
          ? "bg-blue-50 border-l-4 border-[#2563eb] shadow-sm ring-1 ring-blue-500/10"
          : "border-l-4 border-transparent"
      }" onclick="window.selectVehicle(${v.id})" data-vehicle-id="${v.id}" title="Click to view full operations and telemetry">
        <td class="py-3 px-3">
          <div class="font-bold text-[#172033] text-sm tracking-tight flex items-center gap-1.5">
            ${v.plate_number || '<span class="text-slate-400 italic font-normal text-xs">No Plate</span>'}
            ${isSelected ? '<span class="bg-[#2563eb] text-white text-[9px] uppercase px-1.5 py-0.2 rounded font-bold">Selected</span>' : ''}
          </div>
          <div>${trackerIndicator}</div>
        </td>
        <td class="py-3 px-3">${statusBadge}</td>
        <td class="py-3 px-3 max-w-[170px] truncate text-slate-700" title="${v.location_name || ""}">
          <span class="font-medium text-slate-800">${v.location_name || '<span class="text-slate-400">In Transit</span>'}</span>
          ${v.latest_telemetry?.speed ? `<br><span class="text-[10px] text-emerald-600 font-medium">${v.latest_telemetry.speed} km/h</span>` : ''}
        </td>
        <td class="py-3 px-3 text-slate-700">${homebaseInfo}</td>
        <td class="py-3 px-3 text-xs">${routingInfo}</td>
      </tr>
    `;
  }).join("");

  updateVehiclePagination(sortedVehicles.length, totalPages, firstVehicleIndex);
  updateSortIndicators();
}

export function sortVehicles(sortKey, onRender) {
  if (state.sortKey === sortKey) {
    state.sortDirection = state.sortDirection === "asc" ? "desc" : "asc";
  } else {
    state.sortKey = sortKey;
    state.sortDirection = "asc";
  }

  state.currentPage = 1;
  if (typeof onRender === "function") {
    onRender();
  }
}

export function changeVehiclePage(delta, onRender) {
  const sortedVehicles = getFilteredAndSortedVehicles();
  const totalPages = Math.max(1, Math.ceil(sortedVehicles.length / state.vehiclesPerPage));

  state.currentPage = Math.max(1, Math.min(state.currentPage + delta, totalPages));

  if (typeof onRender === "function") {
    onRender();
  }

  const tableContainer = document.getElementById("fleet-vehicles");
  if (tableContainer) {
    tableContainer.scrollIntoView({ behavior: "smooth", block: "start" });
  }
}
