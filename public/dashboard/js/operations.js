/**
 * Vehicle Operations Component (Home Base Assignment & Mission Deployments)
 */

import { state, getSelectedVehicle } from "./state.js";
import { assignHomeShop, dispatchDeployment, updateDeploymentAction } from "./api.js";
import { showToast } from "./toast.js";

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
  const panel = document.getElementById("operations-panel");
  const title = document.getElementById("selected-vehicle-title");
  const assignSelect = document.getElementById("assign-shop-select");
  const actions = document.getElementById("deployment-actions");
  const statusEl = document.getElementById("deployment-status");
  const cancelButton = document.getElementById("cancel-deployment-button");

  const vehicle = getSelectedVehicle();

  if (!panel || !title) return;

  if (!vehicle) {
    panel.classList.add("opacity-40", "pointer-events-none");
    title.innerText = "Select a vehicle from the list to test assignments.";
    if (actions) actions.classList.add("hidden");
    return;
  }

  panel.classList.remove("opacity-40", "pointer-events-none");
  title.innerHTML = `Managing: <strong class="text-blue-700">${vehicle.plate_number || vehicle.imei}</strong>`;

  if (assignSelect) {
    assignSelect.value = vehicle.assigned_shop_id || "";
  }

  const deployment = vehicle.active_deployment;
  if (!actions || !statusEl) return;

  if (!deployment) {
    actions.classList.add("hidden");
  } else {
    actions.classList.remove("hidden");
    statusEl.innerText = `Active deployment: ${deployment.purpose || "Delivery Mission"} (${deployment.status})`;
    if (cancelButton) {
      cancelButton.classList.toggle("hidden", deployment.status === "in_progress");
    }
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
    alert("Please choose a destination shop.");
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
  await handleDeploymentAction("release", "Vehicle Released", "Deployment completed and vehicle returned to normal routing.", onSuccess);
}

export async function cancelDeployment(onSuccess) {
  await handleDeploymentAction("cancel", "Deployment Cancelled", "The deployment was cancelled.", onSuccess);
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
