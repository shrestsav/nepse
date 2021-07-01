<template>
    <v-app>
        <v-navigation-drawer app>
            <v-list-item>
                <v-list-item-content>
                    <v-list-item-title class="text-h6">
                        Shrestsav
                    </v-list-item-title>
                    <v-list-item-subtitle> </v-list-item-subtitle>
                </v-list-item-content>
            </v-list-item>

            <v-divider></v-divider>

            <v-list dense nav>
                <v-list-item link>
                    <v-list-item-icon>
                        <v-icon large color="green darken-2">
                            mdi-domain
                        </v-icon>
                    </v-list-item-icon>

                    <v-list-item-content>
                        <v-list-item-title>Dashboard</v-list-item-title>
                    </v-list-item-content>
                </v-list-item>
                <v-list-item link>
                    <v-list-item-icon>
                        <v-icon large color="green darken-2">
                            mdi-domain
                        </v-icon>
                    </v-list-item-icon>
                    <v-list-item-content>
                        <v-list-item-title>
                            <router-link to="/dashboard/sync-price-history">
                                Sync Price History
                            </router-link>
                        </v-list-item-title>
                    </v-list-item-content>
                </v-list-item>
                <v-list-item link>
                    <v-list-item-icon>
                        <v-icon large color="green darken-2">
                            mdi-domain
                        </v-icon>
                    </v-list-item-icon>

                    <v-list-item-content>
                        <v-list-item-title>
                            <router-link to="/dashboard/daily-recommendation">
                                Recommendations
                            </router-link>
                        </v-list-item-title>
                    </v-list-item-content>
                </v-list-item>
            </v-list>
        </v-navigation-drawer>

        <v-app-bar app>
            <!-- -->
        </v-app-bar>
        <v-main>
            <v-container>
                <router-view></router-view>
            </v-container>
        </v-main>

        <v-footer app>
            <!-- -->
        </v-footer>
    </v-app>
</template>
