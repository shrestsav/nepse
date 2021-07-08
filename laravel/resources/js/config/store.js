import Vue from "vue";
import Vuex from "vuex";

Vue.use(Vuex);

export const store = new Vuex.Store({
    state: {
        recommendationView: "list"
    },
    mutations: {
        changeRecommendationView(state, recommendationView) {
            state.recommendationView = recommendationView;
        }
    },
    actions: {}
});
