<template>
    <v-container>
        <v-toolbar>
            <v-toolbar-title>Buy Recommendation</v-toolbar-title>
        </v-toolbar>
        <v-overlay :value="!loaded">
            <v-progress-circular
                :size="70"
                :width="7"
                color="purple"
                indeterminate
            >
            </v-progress-circular>
        </v-overlay>
        <v-row justify="space-around">
            <v-card
                width="400"
                v-for="(stock, symbol, i) in by_rsi_adx"
                :key="i"
            >
                <v-sparkline
                    :value="stock.RSI"
                    :gradient="gradient"
                    :smooth="radius || false"
                    :padding="padding"
                    :line-width="width"
                    :stroke-linecap="lineCap"
                    :gradient-direction="gradientDirection"
                    :fill="fill"
                    :type="type"
                    :auto-line-width="autoLineWidth"
                    auto-draw
                ></v-sparkline>
                <v-sparkline
                    :value="stock.ADX"
                    :gradient="gradient"
                    :smooth="radius || false"
                    :padding="padding"
                    :line-width="width"
                    :stroke-linecap="lineCap"
                    :gradient-direction="gradientDirection"
                    :fill="fill"
                    :type="type"
                    :auto-line-width="autoLineWidth"
                    auto-draw
                ></v-sparkline>
                <v-card-title>
                    {{ symbol }}
                </v-card-title>
                <v-card-subtitle>
                    {{ stock.stock.company_name }}
                </v-card-subtitle>
                <v-card-text>
                    <div class="font-weight-bold ml-8 mb-2">
                        Today
                    </div>
                    <v-timeline align-top dense>
                        <v-timeline-item color="green" small>
                            <div>
                                <div class="font-weight-normal">
                                    <strong>RSI: &nbsp;</strong>
                                    {{ stock.reverse_RSI[2] }}
                                    <v-icon>mdi-forward</v-icon>
                                    {{ stock.reverse_RSI[1] }}
                                    <v-icon>mdi-forward</v-icon>
                                    {{ stock.reverse_RSI[0] }}
                                </div>
                            </div>
                        </v-timeline-item>
                        <v-timeline-item color="green" small>
                            <div>
                                <div class="font-weight-normal">
                                    <strong>ADX: &nbsp;</strong>
                                    {{ stock.reverse_ADX[2] }}
                                    <v-icon>mdi-forward</v-icon>
                                    {{ stock.reverse_ADX[1] }}
                                    <v-icon>mdi-forward</v-icon>
                                    {{ stock.reverse_ADX[0] }}
                                </div>
                            </div>
                        </v-timeline-item>
                    </v-timeline>
                </v-card-text>
            </v-card>
        </v-row>
    </v-container>
</template>

<script>
const gradients = [
    ["#222"],
    ["#42b3f4"],
    ["red", "orange", "yellow"],
    ["purple", "violet"],
    ["#00c6ff", "#F0F", "#FF0"],
    ["#f72047", "#ffd200", "#1feaea"]
];
export default {
    components: {},
    data() {
        return {
            width: 2,
            radius: 10,
            padding: 8,
            lineCap: "round",
            gradient: gradients[5],
            gradientDirection: "top",
            gradients,
            fill: false,
            type: "trend",
            autoLineWidth: false,
            messages: [
                {
                    from: "You",
                    message: `Sure, I'll see you later.`,
                    time: "10:42am",
                    color: "deep-purple lighten-1"
                },
                {
                    from: "John Doe",
                    message: "Yeah, sure. Does 1:00pm work?",
                    time: "10:37am",
                    color: "green"
                },
                {
                    from: "You",
                    message: "Did you still want to grab lunch today?",
                    time: "9:47am",
                    color: "deep-purple lighten-1"
                }
            ],
            loaded: false,
            by_rsi_adx: {}
        };
    },
    created() {},
    mounted() {
        this.getRecommendationsByRsiNAdx();
    },
    methods: {
        getRecommendationsByRsiNAdx() {
            axios
                .get("/api/get_recommendations_by_rsi_n_adx")
                .then(response => {
                    let recommendations = response.data;

                    Object.keys(recommendations).forEach(symbol => {
                        let reverse_RSI = recommendations[symbol].reverse_RSI;
                        let reverse_ADX = recommendations[symbol].reverse_ADX;
                        let ten_reverse_RSI = reverse_RSI.filter((a, i) => {
                            return i >= 0 && i <= 15;
                        });
                        let ten_reverse_ADX = reverse_ADX.filter((a, i) => {
                            return i >= 0 && i <= 15;
                        });

                        let RSI = ten_reverse_RSI.reverse();
                        let ADX = ten_reverse_ADX.reverse();

                        recommendations[symbol].RSI = RSI;
                        recommendations[symbol].ADX = ADX;
                    });
                    this.by_rsi_adx = recommendations;

                    this.loaded = true;
                });
        }
    }
};
</script>
