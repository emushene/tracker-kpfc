/**
 * Global Dashboard State Store
 */
export const state = {
  shops: [],
  vehicles: [],
  selectedVehicleId: null,
  currentPage: 1,
  vehiclesPerPage: 10,
  sortKey: "vehicle",
  sortDirection: "asc",
  searchQuery: "",
  activeVehicleForModal: null,
};

/**
 * Get the currently selected vehicle object or null.
 */
export function getSelectedVehicle() {
  if (!state.selectedVehicleId) return null;
  return state.vehicles.find(v => v.id === state.selectedVehicleId) || null;
}

/**
 * Find a vehicle by ID.
 */
export function getVehicleById(id) {
  return state.vehicles.find(v => v.id === id) || null;
}

/**
 * Find a shop by ID.
 */
export function getShopById(id) {
  return state.shops.find(s => s.id === id) || null;
}
