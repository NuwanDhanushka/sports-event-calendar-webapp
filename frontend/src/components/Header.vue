<script setup>
import { useTheme } from "../composables/useTheme.js";
import { useRoute, RouterLink } from "vue-router";
const { theme, toggle, DARK } = useTheme();
const route = useRoute();
const isActive = (path) => route.path === path;
</script>

<template>
  <div class="drawer lg:drawer-open min-h-screen">
    <input id="app-drawer" type="checkbox" class="drawer-toggle" />

    <!-- Main area -->
    <div class="drawer-content flex flex-col">
      <!-- Top bar -->
      <div class="flex justify-between items-center px-10 py-5 bg-base-100 card-border border-base-300 border-l-0">
        <div class="flex items-center gap-3">
          <label for="app-drawer" aria-label="open sidebar" class="btn btn-ghost btn-circle lg:hidden">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </label>
          <span class="text-xl subpixel-antialiased font-bold">Sports Event Calendar</span>
        </div>

        <label class="swap swap-rotate">
          <input type="checkbox" :checked="theme === DARK" @change="toggle" aria-label="Toggle dark mode" />
          <svg class="swap-on h-5 w-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5.64,17l-.71.71a1,1,0,0,0,0,1.41,1,1,0,0,0,1.41,0l.71-.71A1,1,0,0,0,5.64,17ZM5,12a1,1,0,0,0-1-1H3a1,1,0,0,0,0,2H4A1,1,0,0,0,5,12Zm7-7a1,1,0,0,0,1-1V3a1,1,0,0,0-2,0V4A1,1,0,0,0,12,5ZM5.64,7.05a1,1,0,0,0,.7.29,1,1,0,0,0,.71-.29,1,1,0,0,0,0-1.41l-.71-.71A1,1,0,0,0,4.93,6.34Zm12,.29a1,1,0,0,0,.7-.29l.71-.71a1,1,0,1,0-1.41-1.41L17,5.64a1,1,0,0,0,0,1.41A1,1,0,0,0,17.66,7.34ZM21,11H20a1,1,0,0,0,0,2h1a1,1,0,0,0,0-2Zm-9,8a1,1,0,0,0-1,1v1a1,1,0,0,0,2,0V20A1,1,0,0,0,12,19ZM18.36,17A1,1,0,0,0,17,18.36l.71.71a1,1,0,0,0,1.41,0,1,1,0,0,0,0-1.41ZM12,6.5A5.5,5.5,0,1,0,17.5,12,5.51,5.51,0,0,0,12,6.5Zm0,9A3.5,3.5,0,1,1,15.5,12,3.5,3.5,0,0,1,12,15.5Z"/></svg>
          <svg class="swap-off h-5 w-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M21.64,13a1,1,0,0,0-1.05-.14,8.05,8.05,0,0,1-3.37.73A8.15,8.15,0,0,1,9.08,5.49a8.59,8.59,0,0,1,.25-2A1,1,0,0,0,8,2.36,10.14,10.14,0,1,0,22,14.05,1,1,0,0,0,21.64,13Zm-9.5,6.69A8.14,8.14,0,0,1,7.08,5.22v.27A10.15,10.15,0,0,0,17.22,15.63a9.79,9.79,0,0,0,2.1-.22A8.11,8.11,0,0,1,12.14,19.73Z"/></svg>
        </label>
      </div>

      <!-- CONTENT SLOT -->
      <div class="p-6">
        <slot></slot>
      </div>
    </div>

    <!-- Sidebar -->
    <div class="drawer-side is-drawer-close:overflow-visible">
      <label for="app-drawer" aria-label="close sidebar" class="drawer-overlay"></label>

      <div class="is-drawer-close:w-14 is-drawer-open:w-64 bg-base-100 flex flex-col items-start min-h-full border-r border-base-300">
        <ul class="menu w-full grow gap-y-1">
          <!-- Events (list) -->
          <li>
            <RouterLink
                to="/events"
                class="is-drawer-close:tooltip is-drawer-close:tooltip-right"
                data-tip="Events"
                :class="{ 'bg-base-200' : isActive('/events') }"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="inline-block size-4 my-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <path d="M16 2v4M8 2v4M3 10h18"></path>
              </svg>
              <span class="is-drawer-close:hidden">Events</span>
            </RouterLink>
          </li>

          <!-- Add Event (create) -->
          <li>
            <RouterLink
                to="/events/new"
                class="is-drawer-close:tooltip is-drawer-close:tooltip-right"
                data-tip="Add Event"
                :class="{ 'bg-base-200' : isActive('/events/new') }"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="inline-block size-4 my-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 5v14M5 12h14"></path>
              </svg>
              <span class="is-drawer-close:hidden">Add Event</span>
            </RouterLink>
          </li>
        </ul>

        <div class="m-2 is-drawer-close:tooltip is-drawer-close:tooltip-right" data-tip="Open">
          <label for="app-drawer" class="btn btn-ghost btn-circle drawer-button is-drawer-open:rotate-y-180">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-linejoin="round" stroke-linecap="round" stroke-width="2" fill="none" stroke="currentColor" class="inline-block size-4 my-1.5">
              <path d="M4 4m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z"></path>
              <path d="M9 4v16"></path>
              <path d="M14 10l2 2l-2 2"></path>
            </svg>
          </label>
        </div>
      </div>
    </div>
  </div>
</template>
