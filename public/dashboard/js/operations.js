/**
 * Vehicle Operations & Command Center Component
 */

import { state, getSelectedVehicle } from "./state.js";
import { assignHomeShop, dispatchDeployment, updateDeploymentAction, updateDeploymentJourneyState } from "./api.js";
import { showToast } from "./toast.js";
import { flyToCoordinates } from "./map.js";

export function populateShopDropdowns(shops) {
  const assignSelect = document.getElementById("assign-shop-select");
  if (assignSelect) {
    assignSelect.innerHTML = '<option value="">-- Choose Home Base --</option>' +
      shops.map(s => `<option value="${s.id}">${s.name} (${s.code})</option>`).join("");
  }
}

export function populateDestinationDropdowns(destinations) {
  const deploySelect = document.getElementById("deploy-shop-select");
  if (!deploySelect) return;

  const shops = destinations?.shops || [];
  const locations = destinations?.locations || [];

  let html = '<option value="">-- Choose Destination (Branch or Customer Site) --</option>';

  if (shops.length > 0) {
    html += '<optgroup label="🏢 Internal Branches & Shops">';
    shops.forEach(s => {
      html += `<option value="shop:${s.id}" data-type="shop" data-id="${s.id}">Branch: ${s.name} (${s.code || 'Shop'})</option>`;
    });
    html += '</optgroup>';
  }

  if (locations.length > 0) {
    html += '<optgroup label="📍 Customer Sites, Depots & Hubs">';
    locations.forEach(l => {
      const addr = l.address ? ` - ${l.address}` : '';
      html += `<option value="location:${l.id}" data-type="location" data-id="${l.id}">${l.name} (${l.code || 'Site'})${addr}</option>`;
    });
    html += '</optgroup>';
  }

  deploySelect.innerHTML = html;
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
  const deployDirection = document.getElementById("op-deployment-direction");
  const deployTypeBadge = document.getElementById("op-deployment-type-badge");
  const deployDestination = document.getElementById("op-deployment-destination");
  const deployAddress = document.getElementById("op-deployment-address");
  const deployPurpose = document.getElementById("op-deployment-purpose");
  const deployDriverContainer = document.getElementById("op-deployment-driver");
  const deployDriverName = document.getElementById("op-driver-name");
  const deployDriverPhone = document.getElementById("op-driver-phone");
  const deployCancelBtn = document.getElementById("cancel-deployment-button");

  const deployment = vehicle.active_deployment;
  const mission = vehicle.active_mission || {};
  const hasActiveMission = Boolean(deployment || mission.has_active_mission);

  if (hasActiveMission) {
    if (deployActiveContainer) deployActiveContainer.classList.remove("hidden");
    if (deployFormContainer) deployFormContainer.classList.add("hidden");

    const dest = mission.destination || deployment?.destination || {};
    const destName = dest.name || "Destination";
    const destType = dest.type === "location" ? "Customer Site / Depot" : "Branch Shop";
    const destAddr = dest.address || (dest.latitude && dest.longitude ? `GPS: ${Number(dest.latitude).toFixed(4)}, ${Number(dest.longitude).toFixed(4)}` : "");
    const direction = mission.direction || deployment?.journey_state || "going";

    if (deployDestination) deployDestination.innerText = destName;
    if (deployTypeBadge) {
      deployTypeBadge.innerText = destType.toUpperCase();
      deployTypeBadge.className = `px-1.5 py-0.2 rounded text-[10px] font-bold ${dest.type === "location" ? "bg-amber-100 text-amber-800" : "bg-blue-100 text-blue-800"}`;
    }
    if (deployAddress) deployAddress.innerText = destAddr || "No street address recorded";
    if (deployPurpose) deployPurpose.innerText = deployment?.purpose || mission.status || "Operational Fleet Mission";

    if (deployDirection) {
      if (direction === "going_back") {
        deployDirection.innerText = "⬅️ RETURNING TO BASE";
        deployDirection.className = "text-[10px] font-bold px-2 py-0.5 rounded uppercase bg-indigo-100 text-indigo-800";
      } else if (direction === "at_stop") {
        deployDirection.innerText = "📍 AT STOP / SITE";
        deployDirection.className = "text-[10px] font-bold px-2 py-0.5 rounded uppercase bg-amber-100 text-amber-800";
      } else {
        deployDirection.innerText = "➡️ GOING (OUTBOUND)";
        deployDirection.className = "text-[10px] font-bold px-2 py-0.5 rounded uppercase bg-emerald-100 text-emerald-800";
      }
    }

    if (deployStatus) {
      const statusText = (deployment?.status || mission.status || "ACTIVE").toUpperCase();
      deployStatus.innerText = statusText;
      deployStatus.className = `text-[10px] font-bold px-2 py-0.5 rounded uppercase ${
        statusText === "IN_PROGRESS" ? "bg-emerald-100 text-emerald-800" : "bg-amber-100 text-amber-800"
      }`;
    }

    const driver = mission.driver || deployment?.driver || {};
    if (deployDriverContainer) {
      if (driver.name || driver.external_id) {
        deployDriverContainer.classList.remove("hidden");
        if (deployDriverName) deployDriverName.innerText = driver.name || driver.external_id;
        if (deployDriverPhone) deployDriverPhone.innerText = driver.phone || "No phone";
      } else {
        deployDriverContainer.classList.add("hidden");
      }
    }

    if (deployCancelBtn) {
      deployCancelBtn.classList.toggle("hidden", deployment?.status === "in_progress" || !deployment);
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
  const driverNameInput = document.getElementById("deploy-driver-name");
  const driverPhoneInput = document.getElementById("deploy-driver-phone");

  const selectedValue = deploySelect ? deploySelect.value : null;
  const purpose = purposeInput ? purposeInput.value.trim() : "";
  const driverName = driverNameInput ? driverNameInput.value.trim() : "";
  const driverPhone = driverPhoneInput ? driverPhoneInput.value.trim() : "";

  if (!selectedValue) {
    alert("Please select a destination (branch shop or customer site).");
    return;
  }

  let destinationType = "shop";
  let destinationId = selectedValue;

  if (String(selectedValue).includes(":")) {
    const parts = String(selectedValue).split(":");
    destinationType = parts[0];
    destinationId = parseInt(parts[1], 10);
  } else {
    destinationId = parseInt(selectedValue, 10);
  }

  try {
    await dispatchDeployment(vehicle.id, destinationType, destinationId, purpose, { driverName, driverPhone });
    showToast("Vehicle Dispatched!", "Mission destination and driver route set.");
    if (purposeInput) purposeInput.value = "";
    if (driverNameInput) driverNameInput.value = "";
    if (driverPhoneInput) driverPhoneInput.value = "";
    if (typeof onSuccess === "function") {
      await onSuccess(vehicle.id);
    }
  } catch (err) {
    showToast("Dispatch Failed", err.message, true);
  }
}

export async function submitUpdateDeploymentJourneyState(journeyState, onSuccess) {
  const vehicle = getSelectedVehicle();
  if (!vehicle) return;

  const deploymentId = vehicle.active_deployment?.id;
  if (!deploymentId) {
    showToast("No Active Deployment", "This vehicle has no active deployment.", true);
    return;
  }

  try {
    await updateDeploymentJourneyState(vehicle.id, deploymentId, journeyState);
    const label = journeyState === "going_back" ? "Returning to Base" : (journeyState === "at_stop" ? "At Stop" : "En Route (Going)");
    showToast("Journey Updated", `Driver state set to: ${label}`);
    if (typeof onSuccess === "function") {
      await onSuccess(vehicle.id);
    }
  } catch (err) {
    showToast("Journey Update Failed", err.message, true);
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
