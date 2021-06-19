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
                        <!-- <v-list-item-title> -->
                        <router-link to="/sync-price-history">Go to Foo</router-link>
                        <!-- </v-list-item-title> -->
                    </v-list-item-content>
                </v-list-item>
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
                        <v-list-item-title>Dashboard</v-list-item-title>
                    </v-list-item-content>
                </v-list-item>
            </v-list>
        </v-navigation-drawer>

        <v-app-bar app>
            <!-- -->
        </v-app-bar>
        <v-main>
            <v-container fluid>
                <router-view></router-view>
            </v-container>
        </v-main>

        <v-footer app>
            <!-- -->
        </v-footer>
    </v-app>
    <!-- <div class="container"> -->
    <!-- App.vue -->

    <!-- App.vue -->

    <!-- <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Example Component</div>

                    <div class="card-body">I'm an example comsponent.</div>
                    <div v-for="stock in processedStocksList" :key="stock.id">
                        <strong>{{ stock.symbol }}</strong> Done
                    </div>
                    <div v-for="stock in processingStocks" :key="stock.id">
                        <strong>{{ stock.symbol }}</strong> Processing
                        ................................
                    </div>
                    <button @click="getAllStocks">Sync</button>
                </div>
            </div>
        </div> -->
    <!-- </div> -->
</template>

<script>
export default {
    data() {
        return {
            stocks: [],
            processingStocks: [],
            processedStocks: [],
            atATime: 3,
        };
    },
    mounted() {},
    methods: {
        getAllStocks() {
            axios
                .get("/getAllStocks")
                .then((response) => {
                    this.stocks = response.data;
                })
                .finally(() => {
                    this.startProcessing(0, this.atATime);
                });
        },
        startProcessing(from, to) {
            this.processingStocks = this.stocks.filter((a, i) => {
                return i >= from && i < to;
            });
            let symbols = [];
            this.processingStocks.forEach((stock) => {
                symbols.push(stock.symbol);
            });
            let data = {
                symbols: symbols,
            };
            axios.post("/pricehistory", data).then((response) => {
                console.log(response.data);
                this.processedStocks = this.processedStocks.concat(
                    this.processingStocks
                );
                if (response && to < this.stocks.length) {
                    this.startProcessing(to, to + this.atATime);
                } else {
                    this.processingStocks = [];
                }
            });
        },
    },
    computed: {
        processedStocksList() {
            return this.processedStocks;
        },
    },
};
</script>
