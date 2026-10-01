let toastTimeout = null;

/**
 * Display a toast notification.
 *
 * @param {string} title
 * @param {string} message
 * @param {boolean} isError
 */
export function showToast(title, message, isError = false) {
  const toast = document.getElementById("toast");
  const titleEl = document.getElementById("toast-title");
  const msgEl = document.getElementById("toast-message");
  const iconContainer = document.getElementById("toast-icon");

  if (!toast || !titleEl || !msgEl || !iconContainer) return;

  titleEl.innerText = title;
  msgEl.innerText = message;

  if (isError) {
    iconContainer.className = "p-1.5 rounded-lg bg-red-500/20 text-red-400";
    iconContainer.innerHTML = '<i data-lucide="alert-circle" class="w-5 h-5"></i>';
  } else {
    iconContainer.className = "p-1.5 rounded-lg bg-emerald-500/20 text-emerald-400";
    iconContainer.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5"></i>';
  }

  if (window.lucide && typeof window.lucide.createIcons === "function") {
    window.lucide.createIcons();
  }

  toast.classList.remove("translate-y-20", "opacity-0", "pointer-events-none");

  if (toastTimeout) {
    clearTimeout(toastTimeout);
  }

  toastTimeout = setTimeout(() => {
    toast.classList.add("translate-y-20", "opacity-0", "pointer-events-none");
  }, 4000);
}
