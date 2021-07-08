<template>
  <v-container>
    <v-overlay :value="!loaded">
      <v-progress-circular :size="70" :width="7" color="purple" indeterminate></v-progress-circular>
    </v-overlay>

    <v-row>
      <v-app-bar dense dark>
        <v-toolbar-title>MA - EMA - ADX</v-toolbar-title>
      </v-app-bar>
    </v-row>

    <MA-EMA-ADX-List :by_MA_EMA_ADX="by_MA_EMA_ADX" :sparkline="sparkline"></MA-EMA-ADX-List>

    <v-row>
      <v-app-bar dense dark>
        <v-toolbar-title>RSI - ADX</v-toolbar-title>
      </v-app-bar>
    </v-row>

    <RSI-ADX-List v-if="recommendationView == 'list'" :by_RSI_ADX="by_RSI_ADX" :sparkline="sparkline"></RSI-ADX-List>

    <RSI-ADX-Tile v-if="recommendationView == 'tile'" :by_RSI_ADX="by_RSI_ADX" :sparkline="sparkline"></RSI-ADX-Tile>

    <v-row>
      <v-app-bar dense dark>
        <v-toolbar-title>RSI - MACD</v-toolbar-title>
      </v-app-bar>
    </v-row>

    <RSI-MACD-List v-if="recommendationView == 'list'" :by_rsi_macd="by_rsi_macd" :sparkline="sparkline"></RSI-MACD-List>

    <RSI-MACD-Tile v-if="recommendationView == 'tile'" :by_rsi_macd="by_rsi_macd" :sparkline="sparkline"></RSI-MACD-Tile>

  </v-container>
</template>

<script>
const gradients = [
  ["#222"],
  ["#42b3f4"],
  ["red", "orange", "yellow"],
  ["purple", "violet"],
  ["#00c6ff", "#F0F", "#FF0"],
  ["#f72047", "#ffd200", "#1feaea"],
];
import RSIADXList from "./components/RSI-ADX-List";
import RSIADXTile from "./components/RSI-ADX-Tile";
import RSIMACDList from "./components/RSI-MACD-List";
import RSIMACDTile from "./components/RSI-MACD-Tile";
import MAEMAADXList from "./components/MA-EMA-ADX-List";

export default {
  components: {
    RSIADXList,
    RSIADXTile,
    RSIMACDList,
    RSIMACDTile,
    MAEMAADXList,
  },
  data() {
    return {
      sparkline: {
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
      },
      loaded: false,
      by_RSI_ADX: {},
      by_rsi_macd: {},
      by_rsi_macd: {},
      by_MA_EMA_ADX: {},
    };
  },
  created() {},
  mounted() {
    this.getRecommendationsByRsiNAdx();
    this.getRecommendationsByRsiNMacd();
    this.getRecommendationsByMaEmaAdx();
  },
  methods: {
    getRecommendationsByMaEmaAdx() {
      axios.get("/api/get_recommendations_by_ma_ema_adx").then((response) => {
        let recommendations = response.data;
        console.log(response);
        Object.keys(recommendations).forEach((symbol) => {
          let reverse_ADX = recommendations[symbol].reverse_ADX;
          let reverse_EMA_high = recommendations[symbol].reverse_EMA_high;
          let reverse_EMA_hlc3 = recommendations[symbol].reverse_EMA_hlc3;
          let reverse_EMA_low = recommendations[symbol].reverse_EMA_low;

          let ten_reverse_ADX = reverse_ADX.filter((a, i) => {
            return i >= 0 && i <= 15;
          });
          let ten_reverse_EMA_high = reverse_EMA_high.filter((a, i) => {
            return i >= 0 && i <= 15;
          });
          let ten_reverse_EMA_hlc3 = reverse_EMA_hlc3.filter((a, i) => {
            return i >= 0 && i <= 15;
          });
          let ten_reverse_EMA_low = reverse_EMA_low.filter((a, i) => {
            return i >= 0 && i <= 15;
          });

          let ADX = ten_reverse_ADX.reverse();
          let EMA_high = ten_reverse_EMA_high.reverse();
          let EMA_hlc3 = ten_reverse_EMA_hlc3.reverse();
          let EMA_low = ten_reverse_EMA_low.reverse();

          recommendations[symbol].ADX = ADX;
          recommendations[symbol].EMA_high = EMA_high;
          recommendations[symbol].EMA_hlc3 = EMA_hlc3;
          recommendations[symbol].EMA_low = EMA_low;
        });
        this.by_MA_EMA_ADX = recommendations;

        this.loaded = true;
      });
    },
    getRecommendationsByRsiNAdx() {
      axios.get("/api/get_recommendations_by_rsi_n_adx").then((response) => {
        let recommendations = response.data;

        Object.keys(recommendations).forEach((symbol) => {
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
        this.by_RSI_ADX = recommendations;

        this.loaded = true;
      });
    },
    getRecommendationsByRsiNMacd() {
      axios.get("/api/get_recommendations_by_rsi_n_macd").then((response) => {
        let recommendations = response.data;

        Object.keys(recommendations).forEach((symbol) => {
          let reverse_RSI = recommendations[symbol].reverse_RSI;
          let reverse_MACD = recommendations[symbol].reverse_MACD;
          let ten_reverse_RSI = reverse_RSI.filter((a, i) => {
            return i >= 0 && i <= 15;
          });
          let ten_reverse_MACD = reverse_MACD.filter((a, i) => {
            return i >= 0 && i <= 15;
          });

          let RSI = ten_reverse_RSI.reverse();
          let MACD = ten_reverse_MACD.reverse();

          recommendations[symbol].RSI = RSI;
          recommendations[symbol].MACD = MACD;
        });
        this.by_rsi_macd = recommendations;

        this.loaded = true;
      });
    },
  },
  computed: {
    recommendationView() {
      return this.$store.state.recommendationView;
    },
  },
};
</script>
