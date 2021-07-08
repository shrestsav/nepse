<template>
  <v-container>
    <v-row class="d-flex">
      <v-dialog v-model="dialog" persistent max-width="400">
        <v-card>
          <v-card-title class="text-h5">
            Choose Sync Preference
          </v-card-title>
          <v-card-text>
            <div class="text-center">
              <v-chip class="ma-2" color="primary" @click="getAllStocks(3)">
                <v-icon left>mdi-server-plus</v-icon> LIVE
              </v-chip>
              <v-chip class="ma-2" color="secondary" @click="getAllStocks(2)">
                <v-icon left>mdi-server-plus</v-icon> SMART
              </v-chip>
              <v-chip class="ma-2" color="red" text-color="white" @click="getAllStocks(1)">
                <v-icon left>mdi-server-plus</v-icon> ALL TIME
              </v-chip>
            </div>
          </v-card-text>
          <v-card-actions>
            <v-spacer></v-spacer>
            <v-btn color="green darken-1" text @click="dialog = false">
              Cancel
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
      <v-col cols="12" sm="4">
        <v-chip>
          <v-icon>mdi-clock</v-icon>
          &nbsp; Last Synced On:
          {{ Object.keys(lastSyncLog).length === 0 ? "Not Available" : $moment(lastSyncLog.end).format("YYYY-MM-DD HH:mm") }}
        </v-chip>
        <br />
        <br />
        <v-chip v-if="started">
          <v-icon>mdi-clock</v-icon> &nbsp;
          <template v-if="processing">
            Estimated Time: {{ estimatedTime }}
          </template>
          <template v-else>Total Time: {{ totalTime }} </template>
        </v-chip>
      </v-col>
      <v-col cols="12" sm="4">
        <v-row class="justify-center">
          <v-progress-circular :rotate="180" :size="100" :width="15" :value="progress" color="green">
            <div v-if="processing">
              {{ processedStocksList.length }} /
              {{ stocks.length }}
            </div>
            <v-btn v-else color="success" fab x-large dark @click="dialog = true">
              <v-icon>mdi-cached</v-icon>
            </v-btn>
          </v-progress-circular>
        </v-row>
      </v-col>
    </v-row>
    <br />
    <v-simple-table dark v-if="started" fixed-header height="70vh">
      <template v-slot:default>
        <thead>
          <tr>
            <th class="text-left serial-no">
              S.No
            </th>
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
              <v-progress-linear color="green" rounded value="100"></v-progress-linear>
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
              {{processedStocksList.length + processingStocks.length + i + 1}}
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
  </v-container>
</template>

<script>
export default {
  data() {
    return {
      dialog: false,
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
      axios.get("/api/getLastSyncLog").then((response) => {
        this.lastSyncLog = response.data;
      });
    },
    reset() {
      this.dialog = false;
      this.stocks = [];
      this.processingStocks = [];
      this.processedStocks = [];
      this.onHoldStocks = [];
      this.timeTakenForEachResponse = [];
      this.currentSyncLog = {};
      this.totalTimeInSeconds = "";
    },
    getAllStocks(type) {
      this.reset();

      // Type 1 - All time, 2 - Smart, 3 - Live
      let data = {
        type: type,
        operation_type: "create",
      };

      axios.post("/api/createSyncLog", data).then((response) => {
        this.currentSyncLog = response.data;
      });

      this.processing = true;
      this.started = true;

      let startTime = new Date();
      if (type == 3) {
        axios.get("/api/merolagani/livePrice").then((response) => {
          let endTime = new Date();
          let timeForResponse = endTime - startTime;
          this.timeTakenForEachResponse.push(timeForResponse);
          this.processedStocks = this.processedStocks.concat(
            this.processingStocks
          );

          this.currentSyncLog.total_synced = this.processedStocks.length;
          this.currentSyncLog.total_time = this.totalTimeInSeconds;
          this.currentSyncLog.operation_type = "update";

          axios
            .post("/api/createSyncLog", this.currentSyncLog)
            .then((response) => {});

          this.processingStocks = [];
          this.processing = false;
          this.dialog = false;

          this.getLastSyncLog();
        });

        return;
      }

      axios
        .get("/api/getAllStocks")
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
      axios.post("/api/nepalipaisa/pricehistory", data).then((response) => {
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
            .post("/api/createSyncLog", this.currentSyncLog)
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
