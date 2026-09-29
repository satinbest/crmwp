<template>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    :class="customClass"
    stroke="currentColor"
    stroke-width="1.5"
    stroke-linecap="round"
    stroke-linejoin="round"
  >
    <!-- Dashboard / Element-4 -->
    <g v-if="name === 'dashboard' || name === 'element-4'">
      <rect x="3" y="3" width="7" height="7" rx="2" />
      <rect x="14" y="3" width="7" height="7" rx="2" />
      <rect x="14" y="14" width="7" height="7" rx="2" />
      <rect x="3" y="14" width="7" height="7" rx="2" />
    </g>

    <!-- Shop / Store -->
    <g v-else-if="name === 'shop'">
      <path d="M3 9L4.5 4H19.5L21 9" />
      <path d="M3 9C3 10.66 4.34 12 6 12C7.66 12 9 10.66 9 9C9 10.66 10.34 12 12 12C13.66 12 15 10.66 15 9C15 10.66 16.34 12 18 12C19.66 12 21 10.66 21 9" />
      <path d="M5 12V20H19V12" />
      <path d="M10 20V15H14V20" />
    </g>

    <!-- Customers / Profile-2user -->
    <g v-else-if="name === 'customers' || name === 'profile-2user'">
      <path d="M17 21V19C17 17.34 15.66 16 14 16H10C8.34 16 7 17.34 7 19V21" />
      <circle cx="12" cy="9" r="4" />
      <path d="M21 21V19C21 17.65 20.15 16.5 19 16.14" />
      <path d="M16 5.14C17.15 5.5 18 6.65 18 8C18 9.35 17.15 10.5 16 10.86" />
    </g>

    <!-- Orders / Receipt -->
    <g v-else-if="name === 'orders' || name === 'receipt'">
      <path d="M4 2V22L7 20L10 22L13 20L16 22L19 20L22 22V2L19 4L16 2L13 4L10 2L7 4L4 2Z" />
      <line x1="8" y1="8" x2="16" y2="8" />
      <line x1="8" y1="12" x2="16" y2="12" />
      <line x1="8" y1="16" x2="13" y2="16" />
    </g>

    <!-- Products / Box-2 -->
    <g v-else-if="name === 'products' || name === 'box'">
      <path d="M21 8L12 3L3 8V16L12 21L21 16V8Z" />
      <path d="M12 3V21" />
      <path d="M3 8L12 13L21 8" />
    </g>

    <!-- Inventory / Archive -->
    <g v-else-if="name === 'inventory' || name === 'archive'">
      <rect x="3" y="4" width="18" height="4" rx="1" />
      <path d="M5 8V20H19V8" />
      <line x1="10" y1="12" x2="14" y2="12" />
    </g>

    <!-- CRM / Tag-2 -->
    <g v-else-if="name === 'tag'">
      <path d="M20.59 13.41L13.42 20.58C12.64 21.36 11.37 21.36 10.59 20.58L3 13V3H13L20.59 10.59C21.37 11.37 21.37 12.64 20.59 13.41Z" />
      <circle cx="7.5" cy="7.5" r="1.5" />
    </g>

    <!-- Tasks / Task-square -->
    <g v-else-if="name === 'task' || name === 'task-square'">
      <rect x="3" y="3" width="18" height="18" rx="4" />
      <path d="M8 12L11 15L16 9" />
    </g>

    <!-- Segments / Category -->
    <g v-else-if="name === 'segments' || name === 'category'">
      <circle cx="6" cy="6" r="3" />
      <circle cx="18" cy="6" r="3" />
      <circle cx="18" cy="18" r="3" />
      <circle cx="6" cy="18" r="3" />
    </g>

    <!-- Activities / Activity -->
    <g v-else-if="name === 'activity'">
      <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
    </g>

    <!-- Reports / Chart -->
    <g v-else-if="name === 'reports' || name === 'chart'">
      <line x1="18" y1="20" x2="18" y2="10" />
      <line x1="12" y1="20" x2="12" y2="4" />
      <line x1="6" y1="20" x2="6" y2="14" />
      <line x1="2" y1="20" x2="22" y2="20" />
    </g>

    <!-- Settings / Setting-2 -->
    <g v-else-if="name === 'settings'">
      <circle cx="12" cy="12" r="3" />
      <path d="M19.4 15A1.65 1.65 0 0 0 19.73 16.82L19.8 16.89C20.25 17.34 20.25 18.06 19.8 18.51L18.51 19.8C18.06 20.25 17.34 20.25 16.89 19.8L16.82 19.73A1.65 1.65 0 0 0 15 19.4V19.5A2.12 2.12 0 0 1 12.88 21.62H11.12A2.12 2.12 0 0 1 9 19.5V19.4A1.65 1.65 0 0 0 7.18 19.73L7.11 19.8C6.66 20.25 5.94 20.25 5.49 19.8L4.2 18.51C3.75 18.06 3.75 17.34 4.2 16.89L4.27 16.82A1.65 1.65 0 0 0 4.6 15H4.5A2.12 2.12 0 0 1 2.38 12.88V11.12A2.12 2.12 0 0 1 4.5 9H4.6A1.65 1.65 0 0 0 4.27 7.18L4.2 7.11C3.75 6.66 3.75 5.94 4.2 5.49L5.49 4.2C5.94 3.75 6.66 3.75 7.11 4.2L7.18 4.27A1.65 1.65 0 0 0 9 4.6V4.5A2.12 2.12 0 0 1 11.12 2.38H12.88A2.12 2.12 0 0 1 15 4.5V4.6A1.65 1.65 0 0 0 16.82 4.27L16.89 4.2C17.34 3.75 18.06 3.75 18.51 4.2L19.8 5.49C20.25 5.94 20.25 6.66 19.8 7.11L19.73 7.18A1.65 1.65 0 0 0 19.4 9H19.5A2.12 2.12 0 0 1 21.62 11.12V12.88A2.12 2.12 0 0 1 19.5 15H19.4Z" />
    </g>

    <!-- Users / Shield-tick -->
    <g v-else-if="name === 'users' || name === 'shield-tick'">
      <path d="M12 22S19 18 19 12V5L12 2L5 5V12C5 18 12 22 12 22Z" />
      <path d="M9 12L11 14L15 10" />
    </g>

    <!-- Shield Cross / Forbidden -->
    <g v-else-if="name === 'shield-cross'">
      <path d="M12 22S19 18 19 12V5L12 2L5 5V12C5 18 12 22 12 22Z" />
      <line x1="9.5" y1="9.5" x2="14.5" y2="14.5" />
      <line x1="14.5" y1="9.5" x2="9.5" y2="14.5" />
    </g>

    <!-- Lock -->
    <g v-else-if="name === 'lock'">
      <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
      <path d="M7 11V7a5 5 0 0 1 10 0v4" />
    </g>

    <!-- Key -->
    <g v-else-if="name === 'key'">
      <path d="M21 2l-2 2m-1.5 1.5L14 9a5 5 0 1 0 3 3l3.5-3.5 2 2 1.5-1.5-2-2 1.5-1.5L21 2z" />
    </g>

    <!-- Copy -->
    <g v-else-if="name === 'copy'">
      <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
      <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
    </g>

    <!-- Search / Search-normal -->
    <g v-else-if="name === 'search'">
      <circle cx="11" cy="11" r="8" />
      <line x1="21" y1="21" x2="16.65" y2="16.65" />
    </g>

    <!-- Sun (Light Mode) -->
    <g v-else-if="name === 'sun'">
      <circle cx="12" cy="12" r="5" />
      <line x1="12" y1="1" x2="12" y2="3" />
      <line x1="12" y1="21" x2="12" y2="23" />
      <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
      <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
      <line x1="1" y1="12" x2="3" y2="12" />
      <line x1="21" y1="12" x2="23" y2="12" />
      <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
      <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
    </g>

    <!-- Moon (Dark Mode) -->
    <g v-else-if="name === 'moon'">
      <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" />
    </g>

    <!-- Bell / Notification -->
    <g v-else-if="name === 'notification' || name === 'bell'">
      <path d="M18 8A6 6 0 0 0 6 8C6 15 3 17 3 17H21S18 15 18 8Z" />
      <path d="M13.73 21A2 2 0 0 1 10.27 21" />
    </g>

    <!-- Logout -->
    <g v-else-if="name === 'logout'">
      <path d="M9 21H5A2 2 0 0 1 3 19V5A2 2 0 0 1 5 3H9" />
      <polyline points="16 17 21 12 16 7" />
      <line x1="21" y1="12" x2="9" y2="12" />
    </g>

    <!-- Command / Key -->
    <g v-else-if="name === 'command'">
      <path d="M18 3A3 3 0 0 0 15 6V18A3 3 0 0 0 18 21A3 3 0 0 0 21 18A3 3 0 0 0 18 15H6A3 3 0 0 0 3 18A3 3 0 0 0 6 21A3 3 0 0 0 9 18V6A3 3 0 0 0 6 3A3 3 0 0 0 3 6A3 3 0 0 0 6 9H18A3 3 0 0 0 21 6A3 3 0 0 0 18 3Z" />
    </g>

    <!-- Check / Success -->
    <g v-else-if="name === 'check'">
      <polyline points="20 6 9 17 4 12" />
    </g>

    <!-- Close / Cross -->
    <g v-else-if="name === 'close' || name === 'x'">
      <line x1="18" y1="6" x2="6" y2="18" />
      <line x1="6" y1="6" x2="18" y2="18" />
    </g>

    <!-- Refresh / Reload -->
    <g v-else-if="name === 'refresh'">
      <path d="M23 4V10H17" />
      <path d="M1 20V14H7" />
      <path d="M3.51 9A9 9 0 0 1 18.36 5.64L23 10M1 14L5.64 18.36A9 9 0 0 0 20.49 15" />
    </g>

    <!-- Chevron Down -->
    <g v-else-if="name === 'chevron-down'">
      <polyline points="6 9 12 15 18 9" />
    </g>

    <!-- Chevron Left (Forward in RTL) -->
    <g v-else-if="name === 'chevron-left'">
      <polyline points="15 18 9 12 15 6" />
    </g>

    <!-- Chevron Right (Back in RTL) -->
    <g v-else-if="name === 'chevron-right'">
      <polyline points="9 18 15 12 9 6" />
    </g>

    <!-- Add / Plus -->
    <g v-else-if="name === 'add' || name === 'plus'">
      <line x1="12" y1="5" x2="12" y2="19" />
      <line x1="5" y1="12" x2="19" y2="12" />
    </g>

    <!-- Edit -->
    <g v-else-if="name === 'edit'">
      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
    </g>

    <!-- Trash / Delete -->
    <g v-else-if="name === 'trash'">
      <polyline points="3 6 5 6 21 6" />
      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
    </g>

    <!-- Eye / View -->
    <g v-else-if="name === 'eye'">
      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
      <circle cx="12" cy="12" r="3" />
    </g>

    <!-- Filter -->
    <g v-else-if="name === 'filter'">
      <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
    </g>

    <!-- Phone -->
    <g v-else-if="name === 'phone'">
      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
    </g>

    <!-- Mail / SMS -->
    <g v-else-if="name === 'mail' || name === 'sms'">
      <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
      <polyline points="22,6 12,13 2,6" />
    </g>

    <!-- Calendar -->
    <g v-else-if="name === 'calendar'">
      <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
      <line x1="16" y1="2" x2="16" y2="6" />
      <line x1="8" y1="2" x2="8" y2="6" />
      <line x1="3" y1="10" x2="21" y2="10" />
    </g>

    <!-- Bulk / Layers -->
    <g v-else-if="name === 'bulk' || name === 'layers'">
      <polygon points="12 2 2 7 12 12 22 7 12 2" />
      <polyline points="2 17 12 22 22 17" />
      <polyline points="2 12 12 17 22 12" />
    </g>

    <!-- Warning / Alert Triangle -->
    <g v-else-if="name === 'warning' || name === 'danger'">
      <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
      <line x1="12" y1="9" x2="12" y2="13" />
      <line x1="12" y1="17" x2="12.01" y2="17" />
    </g>

    <!-- Percent -->
    <g v-else-if="name === 'percent'">
      <line x1="19" y1="5" x2="5" y2="19" />
      <circle cx="6.5" cy="6.5" r="2.5" />
      <circle cx="17.5" cy="17.5" r="2.5" />
    </g>

    <!-- Menu / Hamburger -->
    <g v-else-if="name === 'menu'">
      <line x1="3" y1="12" x2="21" y2="12" />
      <line x1="3" y1="6" x2="21" y2="6" />
      <line x1="3" y1="18" x2="21" y2="18" />
    </g>

    <!-- Fallback / Circle -->
    <g v-else>
      <circle cx="12" cy="12" r="9" />
      <line x1="12" y1="8" x2="12" y2="12" />
      <line x1="12" y1="16" x2="12.01" y2="16" />
    </g>
  </svg>
</template>

<script setup>
defineProps({
  name: {
    type: String,
    required: true,
  },
  size: {
    type: [Number, String],
    default: 20,
  },
  customClass: {
    type: String,
    default: '',
  },
});
</script>
