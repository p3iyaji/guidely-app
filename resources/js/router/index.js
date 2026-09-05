import { createRouter, createWebHistory } from 'vue-router';
import AccessDenied from '../pages/AccessDenied.vue';
import HomePage from '../pages/HomePage.vue';
import LoginPage from '../pages/LoginPage.vue';

/**
 * Product SPA routes. Keep wireframe parent/LMS/clinician/EduConnect IA out of this table.
 */
const routes = [
    {
        path: '/',
        name: 'home',
        component: HomePage,
    },
    {
        path: '/login',
        name: 'login',
        component: LoginPage,
    },
    {
        path: '/access-denied',
        name: 'access-denied',
        component: AccessDenied,
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
export { routes };
