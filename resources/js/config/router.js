import Vue from 'vue'
import VueRouter from 'vue-router'

Vue.use(VueRouter)

import syncPriceHistory from '../components/pages/syncPriceHistory.vue'
import recommendations from '../components/pages/recommendations.vue'


const routes = [
    { name: 'recommendations', path: '/dashboard/daily-recommendation', component: recommendations },
    { name: 'syncPriceHistory', path: '/dashboard/sync-price-history', component: syncPriceHistory },
];

export const router = new VueRouter({
    mode: 'history',
    routes
});
