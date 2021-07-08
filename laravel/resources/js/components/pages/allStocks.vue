<template>
  <v-simple-table dark v-if="started" fixed-header height="70vh">
    <template v-slot:default>
      <thead>
        <tr>
          <th class="text-left serial-no">S.No</th>
          <th class="text-left symbol-col">
            Symbol
          </th>
          <th class="text-left stock-col">
            Stock Name
          </th>
          <th class="text-left">
            Progress
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(stock, i) in processedStocksList" :key="stock.id">
          <td>{{ i + 1 }}</td>
          <td>{{ stock.symbol }}</td>
          <td>{{ stock.company_name }}</td>
          <td>
            <v-progress-linear color="green" rounded value="100">
            </v-progress-linear>
          </td>
        </tr>
        <tr v-for="(stock, i) in processingStocks" :key="stock.id">
          <td>{{ processedStocksList.length + i + 1 }}</td>
          <td>{{ stock.symbol }}</td>
          <td>{{ stock.company_name }}</td>
          <td>
            <v-progress-linear indeterminate rounded color="green"></v-progress-linear>
          </td>
        </tr>
        <tr v-for="(stock, i) in onHoldStocks" :key="stock.id">
          <td>
            {{
                            processedStocksList.length +
                                processingStocks.length +
                                i +
                                1
                        }}
          </td>
          <td>{{ stock.symbol }}</td>
          <td>{{ stock.company_name }}</td>
          <td>
            <v-progress-linear color="orange darken-2" buffer-value="0" stream rounded></v-progress-linear>
          </td>
        </tr>
      </tbody>
    </template>
  </v-simple-table>
</template>

<script>
export default {
  data() {
    return {
      started: false,
      processing: false,
      stocks: [],
      processingStocks: [],
      processedStocks: [],
      onHoldStocks: [],
      atATime: 3,
      timeTakenForEachResponse: [],
      lastSyncLog: {},
      currentSyncLog: {},
      totalTimeInSeconds: "",
    };
  },
  mounted() {
    this.getLastSyncLog();
  },
  methods: {
    getLastSyncLog() {
      axios.get("/getLastSyncLog").then((response) => {
        this.lastSyncLog = response.data;
      });
    },
    getAllStocks() {
      let data = {
        type: 1,
        operation_type: "create",
      };

      axios.post("/createSyncLog", data).then((response) => {
        this.currentSyncLog = response.data;
      });

      this.processing = true;
      this.started = true;

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
      let startTime = new Date();
      this.processingStocks = this.stocks.filter((a, i) => {
        return i >= from && i < to;
      });
      this.onHoldStocks = this.stocks.filter((a, i) => {
        return i >= to;
      });
      let symbols = [];
      this.processingStocks.forEach((stock) => {
        symbols.push(stock.symbol);
      });
      let data = {
        symbols: symbols,
      };
      axios.post("/pricehistory", data).then((response) => {
        let endTime = new Date();
        let timeForResponse = endTime - startTime;
        this.timeTakenForEachResponse.push(timeForResponse);
        this.processedStocks = this.processedStocks.concat(
          this.processingStocks
        );
        if (response && to < this.stocks.length) {
          this.startProcessing(to, to + this.atATime);
        } else {
          this.currentSyncLog.total_synced = this.processedStocks.length;
          this.currentSyncLog.total_time = this.totalTimeInSeconds;
          this.currentSyncLog.operation_type = "update";
          axios
            .post("/createSyncLog", this.currentSyncLog)
            .then((response) => {});
          this.processingStocks = [];
          this.processing = false;
        }
      });
    },
  },
  computed: {
    processedStocksList() {
      return this.processedStocks;
    },
    averageTimeInMilliseconds() {
      let averageTimeInMilliseconds =
        this.timeTakenForEachResponse.reduce((a, b) => a + b, 0) /
        this.timeTakenForEachResponse.length;
      return averageTimeInMilliseconds;
    },
    estimatedTime() {
      // averageTimeInMilliseconds is based on atATime stocks so divide total pending stocks by atATime
      let pendingRequests =
        (this.onHoldStocks.length + this.processingStocks.length) /
        this.atATime;
      let estimatedTimeInMilliSeconds =
        pendingRequests * this.averageTimeInMilliseconds;
      let estimatedTimeInSeconds = estimatedTimeInMilliSeconds / 1000;
      let estimatedTimeInMinutes = estimatedTimeInSeconds / 60;
      let scale = estimatedTimeInSeconds > 60 ? " Minutes" : " Seconds";
      let result =
        estimatedTimeInSeconds > 60
          ? estimatedTimeInMinutes
          : estimatedTimeInSeconds;

      return estimatedTimeInMinutes
        ? Math.round(result) + scale
        : "Calculating Estimation Time";
    },
    progress() {
      let progress =
        (this.processedStocksList.length / this.stocks.length) * 100;
      return progress;
    },
    totalTime() {
      let totalTime =
        this.timeTakenForEachResponse.reduce((a, b) => a + b, 0) / 1000;
      this.totalTimeInSeconds = totalTime;
      let result =
        totalTime > 60
          ? Math.round(totalTime / 60) + " Minutes"
          : Math.round(totalTime) + " Seconds";

      return result;
    },
  },
};
</script>
