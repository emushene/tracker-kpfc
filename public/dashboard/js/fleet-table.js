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

  const filtered = state.vehicles.filter(v => {
    const text = `${v.plate_number || ""} ${v.imei || ""} ${v.device_name || ""}`.toLowerCase();
    return text.includes(query);
  });

  return filtered.sort(compareVehicles);
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

export function renderFleetTable({ onSelect, onEdit, onDelete } = {}) {
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
        <td colspan="6" class="text-center py-8 text-slate-500">
          No vehicles match the search criteria.
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
      ? '<span class="bg-emerald-500/10 text-emerald-600 px-2 py-0.5 rounded text-[10px] font-semibold">Moving</span>'
      : (v.status === "deployed"
        ? `<span class="bg-amber-500/10 text-amber-600 px-2 py-0.5 rounded text-[10px] font-semibold">Deployed</span><br><span class="text-[10px] text-amber-600">To: ${deploymentDestination || "--"}</span>`
        : '<span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded text-[10px] font-semibold">Parked</span>');

    const routingInfo = v.routing?.distance_km
      ? `<span class="text-indigo-600 font-mono font-medium">${v.routing.distance_km} km</span> <span class="text-slate-400">(~${v.routing.duration_minutes}m)</span>`
      : '<span class="text-slate-400">--</span>';

    const homebaseInfo = v.assigned_shop
      ? `<span class="font-medium text-slate-800">${v.assigned_shop.name}</span><br><span class="text-[10px] text-slate-500">${v.homebase_distance?.distance_km ?? "--"} km away</span>`
      : '<span class="text-slate-400">Unassigned</span>';

    const isAutoPlaceholderImei = v.imei && v.imei.startsWith("FLEET-");

    return `
      <tr class="hover:bg-slate-50 transition cursor-pointer ${isSelected ? "bg-blue-50/70 border-l-4 border-[#2563eb]" : ""}" data-vehicle-id="${v.id}">
        <td class="py-3 px-3 font-semibold text-[#172033]" onclick="window.selectVehicle(${v.id})">
          ${v.plate_number || '<span class="text-slate-400 italic">No Plate</span>'}<br>
          <span class="text-[10px] font-normal font-mono ${isAutoPlaceholderImei ? "text-amber-600 bg-amber-50 px-1 rounded" : "text-slate-500"}" title="${isAutoPlaceholderImei ? "Auto-generated placeholder IMEI. Can be edited once tracker is assigned." : "Tracker IMEI"}">
            ${v.imei}
          </span>
        </td>
        <td class="py-3 px-3" onclick="window.selectVehicle(${v.id})">${statusBadge}</td>
        <td class="py-3 px-3 max-w-[150px] truncate text-slate-700" title="${v.location_name || ""}" onclick="window.selectVehicle(${v.id})">
          ${v.location_name || '<span class="text-slate-400">In Transit</span>'}
        </td>
        <td class="py-3 px-3 text-slate-700" onclick="window.selectVehicle(${v.id})">${homebaseInfo}</td>
        <td class="py-3 px-3 text-xs" onclick="window.selectVehicle(${v.id})">${routingInfo}</td>
        <td class="py-3 px-3 text-right">
          <div class="flex items-center justify-end gap-2">
            <button type="button" onclick="event.stopPropagation(); window.selectVehicle(${v.id})" class="text-xs text-[#2563eb] hover:text-[#1d4ed8] font-medium" title="Manage vehicle operations">
              Manage
            </button>
            <button type="button" onclick="event.stopPropagation(); window.openEditVehicleModal(${v.id})" class="text-xs text-slate-600 hover:text-slate-900 font-medium" title="Edit vehicle details">
              Edit
            </button>
            <button type="button" onclick="event.stopPropagation(); window.deleteVehicle(${v.id})" class="text-xs text-rose-600 hover:text-rose-800 font-medium" title="Delete vehicle">
              Delete
            </button>
          </div>
        </td>
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
