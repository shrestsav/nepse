import Vue from 'vue'
import VueRouter from 'vue-router'

Vue.use(VueRouter)

import syncPriceHistory from '../components/pages/syncPriceHistory.vue'


const routes = [
    { name: 'syncPriceHistory', path: '/sync-price-history', component: syncPriceHistory },
];

export const router = new VueRouter({
    mode: 'history',
    routes
});
