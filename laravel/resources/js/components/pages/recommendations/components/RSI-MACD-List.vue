<template>
  <v-row>
    <v-col cols="12">
      <v-simple-table>
        <template v-slot:default>
          <thead>
            <tr>
              <th class="text-left">Stock</th>
              <th class="text-left" width="12%"></th>
              <th class="text-left">RSI</th>
              <th class="text-left" width="12%"></th>
              <th class="text-left">MACD</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(stock, symbol, i) in by_rsi_macd" :key="i">
              <td :title="stock.stock.company_name">{{ symbol }}</td>
              <td>
                <v-sparkline :value="stock.RSI" :gradient="sparkline.gradient" :smooth="sparkline.radius || false" :padding="sparkline.padding" :line-width="sparkline.width" :stroke-linecap="sparkline.lineCap" :gradient-direction="sparkline.gradientDirection" :fill="sparkline.fill" :type="sparkline.type" :auto-line-width="sparkline.autoLineWidth" auto-draw>dasdas</v-sparkline>
              </td>
              <td>
                <div class="font-weight-normal caption">
                  {{ stock.reverse_RSI[2] }}
                  <v-icon v-if="stock.reverse_RSI[2] > stock.reverse_RSI[1]">mdi-arrow-bottom-right-thick</v-icon>
                  <v-icon v-else>mdi-arrow-top-right-thick</v-icon>
                  {{ stock.reverse_RSI[1] }}
                  <v-icon>mdi-arrow-top-right-thick</v-icon>
                  {{ stock.reverse_RSI[0] }}
                </div>
              </td>
              <td>
                <v-sparkline :value="stock.MACD" :gradient="sparkline.gradient" :smooth="sparkline.radius || false" :padding="sparkline.padding" :line-width="sparkline.width" :stroke-linecap="sparkline.lineCap" :gradient-direction="sparkline.gradientDirection" :fill="sparkline.fill" :type="sparkline.type" :auto-line-width="sparkline.autoLineWidth" auto-draw></v-sparkline>
              </td>
              <td>
                <div class="font-weight-normal caption">
                  {{ stock.reverse_MACD[2] }}
                  <v-icon v-if="stock.reverse_MACD[2] > stock.reverse_MACD[1]">mdi-arrow-bottom-right-thick</v-icon>
                  <v-icon v-else>mdi-arrow-top-right-thick</v-icon>
                  {{ stock.reverse_MACD[1] }}
                  <v-icon>mdi-arrow-top-right-thick</v-icon>
                  {{ stock.reverse_MACD[0] }}
                </div>
              </td>
            </tr>
          </tbody>
        </template>
      </v-simple-table>
    </v-col>
  </v-row>
</template>

<script>
export default {
  name: "RsiMacdList",
  components: {},
  props: {
    by_rsi_macd: { type: Object, required: true },
    sparkline: { type: Object, required: true },
  },
  data() {
    return {};
  },
  mounted() {},
  methods: {},
};
</script>
