import { createRouter, createWebHistory } from 'vue-router'
import EventsPage from "./pages/Events.vue";
import AddEventPage from "./pages/AddEventPage.vue";

/**
 * Routes
 */
const routes = [
    { path: '/', redirect: '/events' },
    { path: '/events', name: 'events.index', component: EventsPage, meta: { title: 'Events' } },
    { path: '/events/new', name: 'events.create', component: AddEventPage, meta: { title: 'Add Event' } },
]

export default createRouter({ history: createWebHistory(), routes })
