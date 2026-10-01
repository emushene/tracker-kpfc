/**
 * KPFC Fleet Dashboard Application Coordinator
 */

import { state, getVehicleById } from "./state.js";
import * as api from "./api.js";
import { showToast } from "./toast.js";
import * as mapComp from "./map.js";
import * as statsComp from "./stats.js";
import * as tableComp from "./fleet-table.js";
import * as opsComp from "./operations.js";
import * as modalComp from "./vehicle-modal.js";

async function loadFleetData() {
  try {
    const vehicles = await api.fetchVehicles();
    state.vehicles = vehicles;

    // Refresh UI components
    statsComp.renderStats(state.vehicles);
    mapComp.updateVehicleMarkers(state.vehicles, state.selectedVehicleId, selectVehicle);
    tableComp.renderFleetTable();
    opsComp.updateOperationsPanel();

    showToast("Fleet Refreshed", `Loaded ${vehicles.length} vehicles.`);
  } catch (err) {
    console.error("Failed to load fleet:", err);
    showToast("Error", "Could not load fleet data. Check if backend is running.", true);
  }
}

function selectVehicle(id) {
  state.selectedVehicleId = id;
  const vehicle = getVehicleById(id);

  if (!vehicle) return;

  // Center map on vehicle coordinates if available
  const lat = vehicle.location_latitude || vehicle.latest_telemetry?.latitude;
  const lng = vehicle.location_longitude || vehicle.latest_telemetry?.longitude;

  if (lat && lng) {
    mapComp.flyToCoordinates(lat, lng, 12);
  }

  // Update map markers and UI panels
  mapComp.updateVehicleMarkers(state.vehicles, state.selectedVehicleId, selectVehicle);
  tableComp.renderFleetTable();
  opsComp.updateOperationsPanel();
}

function filterVehicles() {
  const searchInput = document.getElementById("search-input");
  state.searchQuery = searchInput ? searchInput.value : "";
  state.currentPage = 1;
  tableComp.renderFleetTable();
}

function sortVehicles(key) {
  tableComp.sortVehicles(key, () => {
    tableComp.renderFleetTable();
  });
}

function changeVehiclePage(delta) {
  tableComp.changeVehiclePage(delta, () => {
    tableComp.renderFleetTable();
  });
}

async function testProtrackConnection() {
  try {
    const res = await api.testProtrack();
    showToast("Protrack API Status", `Connected: ${res.status} | Token: ${res.token_received}`);
  } catch (err) {
    showToast("Protrack Offline", err.message, true);
  }
}

// Expose handlers globally for backwards-compatible inline HTML event attributes
Object.assign(window, {
  loadFleetData,
  selectVehicle,
  filterVehicles,
  sortVehicles,
  changeVehiclePage,
  testProtrackConnection,
  showToast,
  submitAssignShop: () => opsComp.submitAssignShop(async (id) => {
    await loadFleetData();
    selectVehicle(id);
  }),
  submitClearShop: () => opsComp.submitClearShop(async (id) => {
    await loadFleetData();
    selectVehicle(id);
  }),
  submitDispatch: () => opsComp.submitDispatch(async (id) => {
    await loadFleetData();
    selectVehicle(id);
  }),
  releaseDeployment: () => opsComp.releaseDeployment(async (id) => {
    await loadFleetData();
    selectVehicle(id);
  }),
  cancelDeployment: () => opsComp.cancelDeployment(async (id) => {
    await loadFleetData();
    selectVehicle(id);
  }),
  openCreateVehicleModal: modalComp.openCreateVehicleModal,
  openEditVehicleModal: modalComp.openEditVehicleModal,
  deleteVehicle: (id) => modalComp.deleteVehicle(id, async () => {
    if (state.selectedVehicleId === id) {
      state.selectedVehicleId = null;
    }
    await loadFleetData();
  }),
});

// Bootstrap application on page load
document.addEventListener("DOMContentLoaded", async () => {
  if (window.lucide && typeof window.lucide.createIcons === "function") {
    window.lucide.createIcons();
  }

  // Initialize Modal
  modalComp.initVehicleModal({
    onSaveSuccess: async () => {
      await loadFleetData();
      if (state.selectedVehicleId) {
        selectVehicle(state.selectedVehicleId);
      }
    },
  });

  try {
    // 1. Load Shops
    const shops = await api.fetchShops();
    state.shops = shops;

    // 2. Initialize Map & Geofences
    mapComp.initMap("map");
    mapComp.renderShopGeofences(state.shops);

    // 3. Populate dropdowns
    opsComp.populateShopDropdowns(state.shops);
    modalComp.populateVehicleModalShops(state.shops);

    // 4. Load Fleet Data
    await loadFleetData();
  } catch (err) {
    console.error("Initialization error:", err);
    showToast("Dashboard Error", err.message || "Failed to initialize dashboard.", true);
  }
});
