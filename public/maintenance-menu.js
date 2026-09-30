let maintenanceMenuCloseTimer;

function toggleMaintenanceMenu(trigger) {
  const menu = document.getElementById('maintenance-menu');
  if (trigger.getAttribute('aria-expanded') === 'true') {
    closeMaintenanceMenu(true);
    return;
  }

  clearTimeout(maintenanceMenuCloseTimer);
  menu.classList.remove('hidden');
  menu.classList.add('translate-y-1', 'opacity-0');
  positionMaintenanceMenu(trigger, menu);
  trigger.setAttribute('aria-expanded', 'true');
  requestAnimationFrame(() => menu.classList.remove('translate-y-1', 'opacity-0'));
  menu.querySelector('a')?.focus({ preventScroll: true });
}

function positionMaintenanceMenu(trigger, menu) {
  const triggerBounds = trigger.getBoundingClientRect();
  const left = Math.min(Math.max(8, triggerBounds.left), Math.max(8, window.innerWidth - menu.offsetWidth - 8));
  const below = triggerBounds.bottom + 8;
  const top = below + menu.offsetHeight <= window.innerHeight - 8
    ? below
    : Math.max(8, triggerBounds.top - menu.offsetHeight - 8);

  menu.style.left = `${left}px`;
  menu.style.top = `${top}px`;
}

function closeMaintenanceMenu(restoreFocus = false) {
  const menu = document.getElementById('maintenance-menu');
  const trigger = document.getElementById('maintenance-menu-trigger');
  if (menu.classList.contains('hidden')) return;

  clearTimeout(maintenanceMenuCloseTimer);
  menu.classList.add('translate-y-1', 'opacity-0');
  trigger.setAttribute('aria-expanded', 'false');
  maintenanceMenuCloseTimer = setTimeout(() => {
    menu.classList.add('hidden');
    menu.style.left = '';
    menu.style.top = '';
  }, 150);

  if (restoreFocus) trigger.focus({ preventScroll: true });
}

document.addEventListener('click', event => {
  const menu = document.getElementById('maintenance-menu');
  const trigger = document.getElementById('maintenance-menu-trigger');
  if (!menu.classList.contains('hidden') && !menu.contains(event.target) && !trigger.contains(event.target)) {
    closeMaintenanceMenu();
  }
});

document.addEventListener('keydown', event => {
  if (event.key === 'Escape') closeMaintenanceMenu(true);
});

window.addEventListener('resize', () => {
  const trigger = document.getElementById('maintenance-menu-trigger');
  const menu = document.getElementById('maintenance-menu');
  if (trigger.getAttribute('aria-expanded') === 'true') positionMaintenanceMenu(trigger, menu);
});
