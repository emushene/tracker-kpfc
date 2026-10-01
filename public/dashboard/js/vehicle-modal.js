/**
 * Vehicle CRUD Modal Component
 */

import { state, getVehicleById } from "./state.js";
import { createVehicle, updateVehicle, deleteVehicle as apiDeleteVehicle } from "./api.js";
import { showToast } from "./toast.js";

export function initVehicleModal({ onSaveSuccess, onDeleteSuccess } = {}) {
  const modal = document.getElementById("vehicle-modal");
  const form = document.getElementById("vehicle-form");
  const closeBtn = document.getElementById("vehicle-modal-close");
  const cancelBtn = document.getElementById("vehicle-modal-cancel");

  if (!modal || !form) return;

  function closeModal() {
    modal.classList.add("hidden");
    form.reset();
    document.getElementById("vehicle-id").value = "";
    document.getElementById("vehicle-modal-error").classList.add("hidden");
    document.getElementById("vehicle-modal-error").innerText = "";
  }

  if (closeBtn) closeBtn.onclick = closeModal;
  if (cancelBtn) cancelBtn.onclick = closeModal;

  modal.onclick = (e) => {
    if (e.target === modal) {
      closeModal();
    }
  };

  form.onsubmit = async (e) => {
    e.preventDefault();
    const errorEl = document.getElementById("vehicle-modal-error");
    const submitBtn = document.getElementById("vehicle-modal-submit");

    errorEl.classList.add("hidden");
    errorEl.innerText = "";
    submitBtn.disabled = true;
    submitBtn.innerText = "Saving...";

    const id = document.getElementById("vehicle-id").value;
    const imeiVal = document.getElementById("vehicle-imei").value.trim();
    const plateVal = document.getElementById("vehicle-plate").value.trim();
    const nameVal = document.getElementById("vehicle-name").value.trim();
    const typeVal = document.getElementById("vehicle-type").value.trim();
    const simVal = document.getElementById("vehicle-sim").value.trim();
    const shopVal = document.getElementById("vehicle-shop").value;
    const activeVal = document.getElementById("vehicle-active").checked;

    const payload = {
      plate_number: plateVal || null,
      device_name: nameVal || null,
      device_type: typeVal || null,
      simcard: simVal || null,
      assigned_shop_id: shopVal ? parseInt(shopVal) : null,
      active: activeVal,
    };

    if (imeiVal) {
      payload.imei = imeiVal;
    }

    try {
      if (id) {
        // Update existing vehicle
        const res = await updateVehicle(id, payload);
        showToast("Vehicle Updated", res.message || "Vehicle updated successfully.");
      } else {
        // Create new vehicle
        const res = await createVehicle(payload);
        showToast("Vehicle Created", `Vehicle created with IMEI: ${res.vehicle?.imei || "generated"}`);
      }

      closeModal();
      if (typeof onSaveSuccess === "function") {
        await onSaveSuccess();
      }
    } catch (err) {
      errorEl.innerText = err.message || "Failed to save vehicle.";
      errorEl.classList.remove("hidden");
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerText = id ? "Update Vehicle" : "Create Vehicle";
    }
  };
}

export function populateVehicleModalShops(shops) {
  const shopSelect = document.getElementById("vehicle-shop");
  if (!shopSelect) return;

  shopSelect.innerHTML = '<option value="">-- No Assigned Shop --</option>' +
    shops.map(s => `<option value="${s.id}">${s.name} (${s.code})</option>`).join("");
}

export function openCreateVehicleModal() {
  const modal = document.getElementById("vehicle-modal");
  const form = document.getElementById("vehicle-form");
  const title = document.getElementById("vehicle-modal-title");
  const submitBtn = document.getElementById("vehicle-modal-submit");
  const imeiHint = document.getElementById("vehicle-imei-hint");

  if (!modal || !form) return;

  form.reset();
  document.getElementById("vehicle-id").value = "";
  document.getElementById("vehicle-active").checked = true;
  document.getElementById("vehicle-modal-error").classList.add("hidden");

  if (title) title.innerText = "Add New Vehicle";
  if (submitBtn) submitBtn.innerText = "Create Vehicle";
  if (imeiHint) {
    imeiHint.innerText = "Optional: If left blank, a unique FLEET-XXXXXXXXXX placeholder will be auto-generated.";
  }

  modal.classList.remove("hidden");
}

export function openEditVehicleModal(vehicleId) {
  const targetId = vehicleId || state.selectedVehicleId;
  const vehicle = getVehicleById(targetId);
  if (!vehicle) return;

  const modal = document.getElementById("vehicle-modal");
  const form = document.getElementById("vehicle-form");
  const title = document.getElementById("vehicle-modal-title");
  const submitBtn = document.getElementById("vehicle-modal-submit");
  const imeiHint = document.getElementById("vehicle-imei-hint");

  if (!modal || !form) return;

  form.reset();
  document.getElementById("vehicle-modal-error").classList.add("hidden");

  document.getElementById("vehicle-id").value = vehicle.id;
  document.getElementById("vehicle-imei").value = vehicle.imei || "";
  document.getElementById("vehicle-plate").value = vehicle.plate_number || "";
  document.getElementById("vehicle-name").value = vehicle.device_name || "";
  document.getElementById("vehicle-type").value = vehicle.device_type || "";
  document.getElementById("vehicle-sim").value = vehicle.simcard || "";
  document.getElementById("vehicle-shop").value = vehicle.assigned_shop_id || "";
  document.getElementById("vehicle-active").checked = Boolean(vehicle.active);

  if (title) title.innerText = `Edit Vehicle: ${vehicle.plate_number || vehicle.imei}`;
  if (submitBtn) submitBtn.innerText = "Update Vehicle";
  if (imeiHint) {
    imeiHint.innerText = vehicle.imei?.startsWith("FLEET-")
      ? "Currently using placeholder IMEI. Update with real tracker IMEI when installed."
      : "Assigned hardware tracker IMEI.";
  }

  modal.classList.remove("hidden");
}

export async function deleteVehicle(vehicleId, onSuccess) {
  const targetId = vehicleId || state.selectedVehicleId;
  const vehicle = getVehicleById(targetId);
  if (!targetId) return;

  const identifier = vehicle ? (vehicle.plate_number || vehicle.imei) : `Vehicle #${targetId}`;

  if (!confirm(`Are you sure you want to delete ${identifier}? This action cannot be undone.`)) {
    return;
  }

  try {
    const res = await apiDeleteVehicle(targetId);
    showToast("Vehicle Deleted", res.message || `${identifier} deleted.`);
    if (typeof onSuccess === "function") {
      await onSuccess(targetId);
    }
  } catch (err) {
    showToast("Delete Failed", err.message, true);
  }
}
