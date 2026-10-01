/**
 * Vehicle Operations & Command Center Component
 */

import { state, getSelectedVehicle } from "./state.js";
import { assignHomeShop, dispatchDeployment, updateDeploymentAction } from "./api.js";
import { showToast } from "./toast.js";
import { flyToCoordinates } from "./map.js";

export function populateShopDropdowns(shops) {
  const assignSelect = document.getElementById("assign-shop-select");
  const deploySelect = document.getElementById("deploy-shop-select");

  if (assignSelect) {
    assignSelect.innerHTML = '<option value="">-- Choose Home Base --</option>' +
      shops.map(s => `<option value="${s.id}">${s.name} (${s.code})</option>`).join("");
  }

  if (deploySelect) {
    deploySelect.innerHTML = '<option value="">-- Choose Destination --</option>' +
      shops.map(s => `<option value="${s.id}">${s.name} (${s.code})</option>`).join("");
  }
}

export function updateOperationsPanel() {
  const emptyState = document.getElementById("operations-empty-state");
  const activeContent = document.getElementById("operations-active-content");

  const vehicle = getSelectedVehicle();

  if (!emptyState || !activeContent) return;

  if (!vehicle) {
    emptyState.classList.remove("hidden");
    activeContent.classList.add("hidden");
    return;
  }

  emptyState.classList.add("hidden");
  activeContent.classList.remove("hidden");

  // 1. Vehicle Profile Header
  const titlePlate = document.getElementById("op-vehicle-plate");
  const titleName = document.getElementById("op-vehicle-name");
  const statusBadge = document.getElementById("op-vehicle-status");
  const imeiTag = document.getElementById("op-vehicle-imei");
  const locationTag = document.getElementById("op-vehicle-location");

  if (titlePlate) titlePlate.innerText = vehicle.plate_number || "No License Plate";
  if (titleName) titleName.innerText = vehicle.device_name || vehicle.device_type || "Commercial Fleet Asset";

  if (statusBadge) {
    statusBadge.innerHTML = vehicle.status === "moving"
      ? '<span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-full text-xs font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>Moving</span>'
      : (vehicle.status === "deployed"
        ? '<span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-full text-xs font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Deployed</span>'
        : '<span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 border border-slate-200 px-2 py-0.5 rounded-full text-xs font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Parked</span>');
  }

  const isPlaceholder = vehicle.imei && vehicle.imei.startsWith("FLEET-");
  if (imeiTag) {
    imeiTag.innerHTML = isPlaceholder
      ? `<span class="bg-amber-50 text-amber-800 border border-amber-200 px-2 py-0.5 rounded text-[11px] font-mono flex items-center gap-1" title="Tracker placeholder auto-generated. Update with hardware IMEI once installed.">
           <svg class="w-3.5 h-3.5 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
           No Tracker (${vehicle.imei})
         </span>`
      : `<span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-[11px] font-mono">IMEI: ${vehicle.imei}</span>`;
  }

  if (locationTag) {
    locationTag.innerText = vehicle.location_name || "Resolving GPS position...";
  }

  // 2. Telemetry Metrics
  const speedEl = document.getElementById("op-telemetry-speed");
  const ignitionEl = document.getElementById("op-telemetry-ignition");
  const batteryEl = document.getElementById("op-telemetry-battery");
  const odometerEl = document.getElementById("op-telemetry-odometer");

  const telemetry = vehicle.latest_telemetry;
  if (speedEl) speedEl.innerText = telemetry?.speed ? `${telemetry.speed} km/h` : "0 km/h";
  if (ignitionEl) {
    const isIgnitionOn = Boolean(telemetry?.ignition_on);
    ignitionEl.innerHTML = isIgnitionOn
      ? '<span class="text-emerald-600 font-semibold">ON</span>'
      : '<span class="text-slate-400">OFF</span>';
  }
  if (batteryEl) batteryEl.innerText = telemetry?.battery ? `${telemetry.battery} V` : "--";
  if (odometerEl) odometerEl.innerText = telemetry?.odometer ? `${Number(telemetry.odometer).toLocaleString()} km` : "--";

  // 3. Deployment & Mission Card
  const deployActiveContainer = document.getElementById("op-deployment-active");
  const deployFormContainer = document.getElementById("op-deployment-form");
  const deployStatus = document.getElementById("op-deployment-status");
  const deployDestination = document.getElementById("op-deployment-destination");
  const deployPurpose = document.getElementById("op-deployment-purpose");
  const deployCancelBtn = document.getElementById("cancel-deployment-button");

  const deployment = vehicle.active_deployment;

  if (deployment) {
    if (deployActiveContainer) deployActiveContainer.classList.remove("hidden");
    if (deployFormContainer) deployFormContainer.classList.add("hidden");
    if (deployDestination) deployDestination.innerText = deployment.destination?.name || "Branch Destination";
    if (deployPurpose) deployPurpose.innerText = deployment.purpose || "Urgent Branch Delivery";
    if (deployStatus) {
      deployStatus.innerText = deployment.status.toUpperCase();
      deployStatus.className = `text-[10px] font-bold px-2 py-0.5 rounded uppercase ${
        deployment.status === "in_progress" ? "bg-emerald-100 text-emerald-800" : "bg-amber-100 text-amber-800"
      }`;
    }
    if (deployCancelBtn) {
      deployCancelBtn.classList.toggle("hidden", deployment.status === "in_progress");
    }
  } else {
    if (deployActiveContainer) deployActiveContainer.classList.add("hidden");
    if (deployFormContainer) deployFormContainer.classList.remove("hidden");
  }

  // 4. Home Base Card
  const assignSelect = document.getElementById("assign-shop-select");
  const homebaseCurrent = document.getElementById("op-homebase-current");
  const homebaseDistance = document.getElementById("op-homebase-distance");

  if (assignSelect) {
    assignSelect.value = vehicle.assigned_shop_id || "";
  }

  if (homebaseCurrent) {
    homebaseCurrent.innerText = vehicle.assigned_shop?.name || "No permanent home base assigned";
  }

  if (homebaseDistance) {
    const km = vehicle.homebase_distance?.distance_km;
    homebaseDistance.innerText = km !== null && km !== undefined
      ? `${km} km road distance to base`
      : "";
  }
}

export function locateSelectedVehicle() {
  const vehicle = getSelectedVehicle();
  if (!vehicle) return;

  const lat = vehicle.location_latitude || vehicle.latest_telemetry?.latitude;
  const lng = vehicle.location_longitude || vehicle.latest_telemetry?.longitude;

  if (lat && lng) {
    flyToCoordinates(lat, lng, 14);
    const mapSection = document.getElementById("live-map");
    if (mapSection) {
      mapSection.scrollIntoView({ behavior: "smooth", block: "center" });
    }
    showToast("Map Centered", `Focused on ${vehicle.plate_number || vehicle.imei}`);
  } else {
    showToast("No Coordinates", "This vehicle does not have valid GPS coordinates yet.", true);
  }
}

export async function submitAssignShop(onSuccess) {
  const vehicle = getSelectedVehicle();
  if (!vehicle) return;

  const assignSelect = document.getElementById("assign-shop-select");
  const shopId = assignSelect ? assignSelect.value : null;

  try {
    const res = await assignHomeShop(vehicle.id, shopId);
    showToast("Home Base Assigned", res.message || "Updated successfully");
    if (typeof onSuccess === "function") {
      await onSuccess(vehicle.id);
    }
  } catch (err) {
    showToast("Assignment Failed", err.message, true);
  }
}

export async function submitClearShop(onSuccess) {
  const assignSelect = document.getElementById("assign-shop-select");
  if (assignSelect) {
    assignSelect.value = "";
  }
  await submitAssignShop(onSuccess);
}

export async function submitDispatch(onSuccess) {
  const vehicle = getSelectedVehicle();
  if (!vehicle) return;

  const deploySelect = document.getElementById("deploy-shop-select");
  const purposeInput = document.getElementById("deploy-purpose");

  const destinationId = deploySelect ? deploySelect.value : null;
  const purpose = purposeInput ? purposeInput.value.trim() : "";

  if (!destinationId) {
    alert("Please select a destination shop.");
    return;
  }

  try {
    await dispatchDeployment(vehicle.id, destinationId, purpose);
    showToast("Vehicle Dispatched!", "Mission destination set.");
    if (purposeInput) purposeInput.value = "";
    if (typeof onSuccess === "function") {
      await onSuccess(vehicle.id);
    }
  } catch (err) {
    showToast("Dispatch Failed", err.message, true);
  }
}

export async function releaseDeployment(onSuccess) {
  await handleDeploymentAction("release", "Vehicle Released", "Mission completed and vehicle returned to normal base routing.", onSuccess);
}

export async function cancelDeployment(onSuccess) {
  await handleDeploymentAction("cancel", "Deployment Cancelled", "The mission deployment was cancelled.", onSuccess);
}

async function handleDeploymentAction(action, title, successMessage, onSuccess) {
  const vehicle = getSelectedVehicle();
  if (!vehicle) return;

  const deploymentId = vehicle.active_deployment?.id;
  if (!deploymentId) {
    showToast("No Active Deployment", "This vehicle has no active deployment.", true);
    return;
  }

  try {
    await updateDeploymentAction(vehicle.id, deploymentId, action);
    showToast(title, successMessage);
    if (typeof onSuccess === "function") {
      await onSuccess(vehicle.id);
    }
  } catch (err) {
    showToast(`${title} Failed`, err.message, true);
  }
}
