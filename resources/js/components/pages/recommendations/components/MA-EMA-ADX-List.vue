<template>
  <v-row>
    <v-col cols="12">
      <v-simple-table>
        <template v-slot:default>
          <thead>
            <tr>
              <th class="text-left">Stock</th>
              <th class="text-left">Price</th>
              <th class="text-left">EMA High</th>
              <th class="text-left" width="12%"></th>
              <th class="text-left">ADX</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(stock, symbol, i) in by_MA_EMA_ADX" :key="i">
              <td :title="stock.stock.company_name">{{ symbol }}</td>
              <td>
                {{ stock.close_today }}
              </td>
              <td>
                <div class="font-weight-normal caption">
                  {{ stock.reverse_EMA_high[2] }}
                  <v-icon v-if="stock.reverse_EMA_high[2] > stock.reverse_EMA_high[1]">mdi-arrow-bottom-right-thick</v-icon>
                  <v-icon v-else>mdi-arrow-top-right-thick</v-icon>
                  {{ stock.reverse_EMA_high[1] }}
                  <v-icon>mdi-arrow-top-right-thick</v-icon>
                  {{ stock.reverse_EMA_high[0] }}
                </div>
              </td>
              <td>
                <v-sparkline :value="stock.ADX" :gradient="sparkline.gradient" :smooth="sparkline.radius || false" :padding="sparkline.padding" :line-width="sparkline.width" :stroke-linecap="sparkline.lineCap" :gradient-direction="sparkline.gradientDirection" :fill="sparkline.fill" :type="sparkline.type" :auto-line-width="sparkline.autoLineWidth" auto-draw></v-sparkline>
              </td>
              <td>
                <div class="font-weight-normal">
                  {{ stock.reverse_ADX[2] }}
                  <v-icon v-if="stock.reverse_ADX[2] > stock.reverse_ADX[1]">mdi-arrow-bottom-right-thick</v-icon>
                  <v-icon v-else>mdi-arrow-top-right-thick</v-icon>
                  {{ stock.reverse_ADX[1] }}
                  <v-icon>mdi-arrow-top-right-thick</v-icon>
                  {{ stock.reverse_ADX[0] }}
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
  name: "RsiAdxList",
  components: {},
  props: {
    by_MA_EMA_ADX: { type: Object, required: true },
    sparkline: { type: Object, required: true },
  },
  data() {
    return {};
  },
  mounted() {},
  methods: {},
};
</script>
