// require('./bootstrap');

window.Vue = require('vue').default;



Vue.component('example-component', require('./components/ExampleComponent.vue').default);

import Vuetify from 'vuetify';
import { router } from './config/router'

Vue.use(Vuetify);

export default new Vuetify({
    theme: { dark: true },
})

const app = new Vue({
    el: '#app',
    vuetify: new Vuetify(),
    router
});