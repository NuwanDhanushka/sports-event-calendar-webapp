import { createRouter, createWebHistory } from 'vue-router'
import Events from "./pages/events.vue";


const routes = [
    { path: '/', redirect: '/events' },
    { path: '/events', component: Events },
]

export default createRouter({ history: createWebHistory(), routes })
