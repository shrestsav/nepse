export type SyncLogSummary = {
    id: number;
    type: string | null;
    typeLabel: string | null;
    status: string | null;
    start?: string | null;
    end?: string | null;
    batchId?: string | null;
    totalTime: number | null;
    totalSynced: number;
    totalStocks: number;
    processedStocks: number;
    errorSummary?: string | null;
    isRunning?: boolean;
};

export type RecommendationMetric = {
    recent: number[];
    series: number[];
    latest: number;
};

export type RecommendationEntry = {
    symbol: string;
    companyName: string;
    sector: string | null;
    asOfDate: string | null;
    closeOnDate: number | null;
    closeToday: number | null;
    stopLoss: number | null;
    tradedSharePercent: number | null;
    metrics: Record<string, RecommendationMetric>;
    deltas: Record<string, number | null>;
};

export type RecommendationSignalGroup = {
    buy: RecommendationEntry[];
    sell: RecommendationEntry[];
};

export type RecommendationGroups = {
    rsiAdx: RecommendationSignalGroup;
    rsiMacd: RecommendationSignalGroup;
    maEmaAdx: RecommendationSignalGroup;
};

export type SyncModeOption = {
    value: string;
    label: string;
};

export type BrokerOption = {
    brokerNo: string;
    brokerName: string;
};

export type FloorsheetRow = {
    id: number;
    transaction: string;
    symbol: string;
    buyerBrokerNo: string | null;
    buyerBrokerName: string | null;
    sellerBrokerNo: string | null;
    sellerBrokerName: string | null;
    quantity: number;
    rate: number;
    amount: number;
};

export type FloorsheetFilters = {
    date: string;
    symbol: string | null;
    buyer: string | null;
    seller: string | null;
    quantityRange: 'all' | '0-10' | '10-100' | '100-1k';
};

export type StockIndexItem = {
    id: number;
    symbol: string;
    companyName: string;
    sector: string | null;
    priceHistoryCount: number;
    latestDate: string | null;
    latestSyncedAt: string | null;
    latestClose: number | null;
};

export type SectorOption = {
    id: number;
    name: string;
    stockCount: number;
};

export type WatchStockOption = {
    id: number;
    symbol: string;
    companyName: string;
    sector: string | null;
};

export type WatchStockQuote = {
    stockId: number;
    symbol: string;
    companyName: string;
    sector: string | null;
    marketDate: string;
    recordedAt: string;
    latestSyncedAt: string;
    price: number;
    change: number;
    changePercent: number;
    previousClose: number;
    high: number;
    low: number;
    open: number;
    volume: number;
};

export type StockPriceHistoryItem = {
    id: number;
    date: string | null;
    close: number | null;
    high: number | null;
    low: number | null;
    change: number | null;
    changePercent: number | null;
    previousClose: number | null;
    volume: number | null;
    transactions: number | null;
    amount: number | null;
};

export type StockDetailItem = {
    id: number;
    symbol: string;
    companyName: string;
    sector: string | null;
};

export type StockPriceRangeSummary = {
    matchingRecords: number;
    shownRecords: number;
    firstDate: string | null;
    lastDate: string | null;
    lowPrice: number | null;
    highPrice: number | null;
    earliestClose: number | null;
    latestClose: number | null;
    closeChange: number | null;
    closeChangePercent: number | null;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from?: number | null;
    to?: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type BacktestRunSummary = {
    id: number;
    strategy: string | null;
    strategyLabel: string | null;
    status: string | null;
    statusLabel: string | null;
    startDate: string | null;
    endDate: string | null;
    startedAt: string | null;
    finishedAt: string | null;
    durationSeconds: number | null;
    eligibleStockCount: number;
    totalTrades: number;
    wins: number;
    losses: number;
    averageProfitRate: number | null;
    averageLossRate: number | null;
    successRate: number | null;
    errorSummary: string | null;
    isRunning: boolean;
};

export type BacktestTrade = {
    id: number;
    stockId: number | null;
    symbol: string;
    companyName: string | null;
    buyDate: string | null;
    buyPrice: number;
    sellDate: string | null;
    sellPrice: number;
    stopLoss: number | null;
    exitReason: string;
    percentageReturn: number;
    holdingDays: number;
    indicatorSnapshot: Record<string, number | null>;
};

export type BacktestStrategyOption = {
    value: string;
    label: string;
};

export type StrategyListItem = {
    slug: string;
    name: string;
    summary: string;
    url: string;
};

export type StrategyDetail = {
    slug: string;
    name: string;
    summary: string;
    thesis: string;
    howComputed: string[];
    entryRules: string[];
    riskControls: string[];
    backtestPlan: string[];
};

export type StrategyShowFilters = {
    date: string | null;
    minTurnover: number;
    limit: number;
};

export type StrategyShowSummary = {
    symbolsScanned: number;
    symbolsPassingTurnover: number;
    buyCandidates: number;
    sellCandidates: number;
    neutral: number;
};

export type StrategyBrokerAmount = {
    brokerNo: string;
    amount: number;
};

export type StrategyCandidateRow = {
    symbol: string;
    stockId: number | null;
    close: number | null;
    changePercent: number | null;
    turnover: number;
    netFlowTop5: number;
    netFlowRatio: number;
    buyerBrokers: number;
    sellerBrokers: number;
    dominanceRatio: number;
    signal: 'buy' | 'sell' | 'neutral';
    topBuyers: StrategyBrokerAmount[];
    topSellers: StrategyBrokerAmount[];
};
