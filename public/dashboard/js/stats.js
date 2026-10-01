/**
 * Fleet Overview Stats Component
 */

export function renderStats(vehicles) {
  let movingCount = 0;
  let assignedCount = 0;
  let deployedCount = 0;

  vehicles.forEach(v => {
    if (v.status === "moving") movingCount++;
    if (v.assigned_shop_id) assignedCount++;
    if (v.active_deployment) deployedCount++;
  });

  const totalEl = document.getElementById("stat-total");
  const movingEl = document.getElementById("stat-moving");
  const assignedEl = document.getElementById("stat-assigned");
  const deployedEl = document.getElementById("stat-deployed");

  if (totalEl) totalEl.innerText = vehicles.length;
  if (movingEl) movingEl.innerText = movingCount;
  if (assignedEl) assignedEl.innerText = assignedCount;
  if (deployedEl) deployedEl.innerText = deployedCount;
}
