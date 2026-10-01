/**
 * API Service for KPFC Fleet Dashboard
 */

async function request(url, options = {}) {
  const defaultHeaders = {
    "Accept": "application/json",
  };

  if (options.body && typeof options.body === "string") {
    defaultHeaders["Content-Type"] = "application/json";
  }

  const response = await fetch(url, {
    ...options,
    headers: {
      ...defaultHeaders,
      ...options.headers,
    },
  });

  let data;
  try {
    data = await response.json();
  } catch {
    data = null;
  }

  if (!response.ok) {
    let errorMsg = data?.message || `Request failed with status ${response.status}`;
    if (data?.errors) {
      const fieldErrors = Object.values(data.errors).flat().join(" ");
      if (fieldErrors) {
        errorMsg += `: ${fieldErrors}`;
      }
    }
    throw new Error(errorMsg);
  }

  return data;
}

export async function fetchShops() {
  const json = await request("/api/shops");
  return (json.data || []).map(shop => ({
    id: shop.id,
    name: shop.name,
    code: shop.code,
    address: shop.address,
    lat: Number(shop.latitude),
    lng: Number(shop.longitude),
    radius: Number(shop.radius_meters || 500),
  }));
}

export async function fetchVehicles() {
  const json = await request("/api/vehicles?all=1");
  return json.data || [];
}

export async function assignHomeShop(vehicleId, shopId) {
  return request(`/api/vehicles/${vehicleId}/assign-shop`, {
    method: "PATCH",
    body: JSON.stringify({ shop_id: shopId ? parseInt(shopId) : null }),
  });
}

export async function dispatchDeployment(vehicleId, destinationId, purpose) {
  return request(`/api/vehicles/${vehicleId}/deployments`, {
    method: "POST",
    body: JSON.stringify({
      destination_type: "shop",
      destination_id: parseInt(destinationId),
      purpose: purpose || "Delivery Mission",
      status: "dispatched",
    }),
  });
}

export async function updateDeploymentAction(vehicleId, deploymentId, action) {
  return request(`/api/vehicles/${vehicleId}/deployments/${deploymentId}/${action}`, {
    method: "PATCH",
  });
}

export async function createVehicle(payload) {
  return request("/api/vehicles", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function updateVehicle(vehicleId, payload) {
  return request(`/api/vehicles/${vehicleId}`, {
    method: "PATCH",
    body: JSON.stringify(payload),
  });
}

export async function deleteVehicle(vehicleId) {
  return request(`/api/vehicles/${vehicleId}`, {
    method: "DELETE",
  });
}

export async function testProtrack() {
  return request("/protrack/test");
}
