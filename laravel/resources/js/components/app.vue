<template>
  <v-app>
    <!-- <v-navigation-drawer v-model="drawernot" app class="pt-4" mini-variant>
      <v-avatar color="grey darken" size="36" class="d-block text-center mx-auto mb-9"></v-avatar>
      <v-avatar color="grey lighten-1" size="20" class="d-block text-center mx-auto mb-9"></v-avatar>
      <v-avatar color="grey lighten-1" size="20" class="d-block text-center mx-auto mb-9"></v-avatar>
      <v-avatar color="grey lighten-1" size="20" class="d-block text-center mx-auto mb-9"></v-avatar>
      <v-avatar color="grey lighten-1" size="20" class="d-block text-center mx-auto mb-9"></v-avatar>
      <v-avatar color="grey lighten-1" size="20" class="d-block text-center mx-auto mb-9"></v-avatar>
      <v-avatar color="grey lighten-1" size="20" class="d-block text-center mx-auto mb-9"></v-avatar>
    </v-navigation-drawer> -->
    <v-navigation-drawer app :mini-variant="drawer">
      <v-list-item>
        <v-list-item-icon>
          <v-app-bar-nav-icon @click="drawer = !drawer"></v-app-bar-nav-icon>
        </v-list-item-icon>
      </v-list-item>

      <v-divider></v-divider>

      <v-list dense nav>
        <v-list-item v-for="(item, index) in items" :key="index" :to="item.path">
          <v-list-item-icon>
            <v-icon>mdi-account-circle</v-icon>
          </v-list-item-icon>
          <v-list-item-content>
            <v-list-item-title v-html="item.title"></v-list-item-title>
          </v-list-item-content>
        </v-list-item>
      </v-list>
    </v-navigation-drawer>

    <v-app-bar app>
      <v-btn-toggle v-if="routeName == 'recommendations'" :value="recommendationView" shaped mandatory>
        <v-btn value="tile" @click="changeRecommendationView('tile')">
          <v-icon>mdi-view-module</v-icon>
        </v-btn>
        <v-btn value="list" @click="changeRecommendationView('list')">
          <v-icon>mdi-format-list-bulleted-square</v-icon>
        </v-btn>
      </v-btn-toggle>
    </v-app-bar>
    <v-main>
      <v-container>
        <v-layout justify-center align-center>
          <router-view></router-view>
        </v-layout>
      </v-container>
    </v-main>

    <v-footer app>
      <!-- -->
    </v-footer>
  </v-app>
</template>


<script>
export default {
  components: {},
  data() {
    return {
      drawer: false,
      items: [
        {
          path: "/dashboard/daily-recommendation",
          title: "Recommendations",
        },
        {
          path: "/dashboard/sync-price-history",
          title: "Sync Price History",
        },
      ],
    };
  },
  mounted() {},
  methods: {
    changeRecommendationView(view) {
      this.$store.commit("changeRecommendationView", view);
    },
  },
  computed: {
    recommendationView() {
      return this.$store.state.recommendationView;
    },
    routeName() {
      return this.$route.name;
    },
  },
};
</script>
