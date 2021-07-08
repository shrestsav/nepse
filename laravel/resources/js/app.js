require('./bootstrap');

window.Vue = require('vue').default;

Vue.component('app', require('./components/app.vue').default);

import Vuetify from 'vuetify';
import { router } from './config/router'
import {store} from './config/store'

Vue.use(Vuetify);

import VueMoment from 'vue-moment'

Vue.use(VueMoment);

export default new Vuetify({
    theme: { dark: true },
})

const app = new Vue({
    el: '#app',
    vuetify: new Vuetify(),
    store,
    router
});