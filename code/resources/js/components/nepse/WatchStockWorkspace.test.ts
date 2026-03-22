import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import WatchStockWorkspace from '@/components/nepse/WatchStockWorkspace.vue';
import type { WatchStockOption } from '@/types';

const stocks: WatchStockOption[] = [
    {
        id: 1,
        symbol: 'NABIL',
        companyName: 'Nabil Bank Limited',
        sector: 'Commercial Bank',
    },
    {
        id: 2,
        symbol: 'ADBL',
        companyName: 'Agricultural Development Bank Limited',
        sector: 'Commercial Bank',
    },
];

describe('WatchStockWorkspace', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('starts and stops polling for the selected stock', async () => {
        const fetchMock = vi.fn()
            .mockResolvedValueOnce(jsonResponse({ quote: buildQuote(1, 810.25, '2026-03-22T12:00:00+05:45') }))
            .mockResolvedValueOnce(jsonResponse({ quote: buildQuote(1, 811.5, '2026-03-22T12:00:05+05:45') }));
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(WatchStockWorkspace, {
            props: { stocks, pollIntervalMs: 5000 },
        });

        await wrapper.get('[data-testid="stock-select"]').setValue('1');
        await wrapper.get('[data-testid="watch-button"]').trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(wrapper.get('[data-testid="watch-status"]').text()).toContain('Watching');

        await vi.advanceTimersByTimeAsync(5000);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);

        await wrapper.get('[data-testid="stop-button"]').trigger('click');
        await vi.advanceTimersByTimeAsync(10000);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(wrapper.get('[data-testid="watch-status"]').text()).toContain('Idle');
    });

    it('resets the session when the selected stock changes', async () => {
        const fetchMock = vi.fn()
            .mockResolvedValueOnce(jsonResponse({ quote: buildQuote(1, 810.25, '2026-03-22T12:00:00+05:45') }));
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(WatchStockWorkspace, {
            props: { stocks, pollIntervalMs: 5000 },
        });

        await wrapper.get('[data-testid="stock-select"]').setValue('1');
        await wrapper.get('[data-testid="watch-button"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-testid="current-price"]').text()).toContain('810.25');
        expect(wrapper.get('[data-testid="session-point-count"]').text()).toContain('1 point');

        await wrapper.get('[data-testid="stock-select"]').setValue('2');
        await flushPromises();

        expect(wrapper.get('[data-testid="watch-status"]').text()).toContain('Idle');
        expect(wrapper.get('[data-testid="current-price"]').text()).toContain('—');
        expect(wrapper.get('[data-testid="session-point-count"]').text()).toContain('0 points');
    });

    it('renders the quote cards from the fetched payload', async () => {
        const fetchMock = vi.fn()
            .mockResolvedValueOnce(jsonResponse({ quote: buildQuote(1, 810.25, '2026-03-22T12:00:00+05:45') }));
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(WatchStockWorkspace, {
            props: { stocks },
        });

        await wrapper.get('[data-testid="stock-select"]').setValue('1');
        await wrapper.get('[data-testid="watch-button"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-testid="current-price"]').text()).toContain('810.25');
        expect(wrapper.get('[data-testid="price-change"]').text()).toContain('+10.25');
        expect(wrapper.text()).toContain('Nabil Bank Limited');
        expect(wrapper.text()).toContain('12,345');
        expect(wrapper.text()).toContain('2026-03-22');
    });

    it('caps the session chart at 60 points', async () => {
        let tick = 0;

        const fetchMock = vi.fn().mockImplementation(async () => {
            tick += 1;

            return jsonResponse({
                quote: buildQuote(1, 800 + tick, `2026-03-22T12:${String(tick % 60).padStart(2, '0')}:00+05:45`),
            });
        });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(WatchStockWorkspace, {
            props: { stocks, pollIntervalMs: 1000, maxPoints: 60 },
        });

        await wrapper.get('[data-testid="stock-select"]').setValue('1');
        await wrapper.get('[data-testid="watch-button"]').trigger('click');
        await flushPromises();

        for (let count = 0; count < 64; count += 1) {
            await vi.advanceTimersByTimeAsync(1000);
            await flushPromises();
        }

        expect(wrapper.get('[data-testid="session-point-count"]').text()).toContain('60 points');
    });
});

function jsonResponse(body: unknown): Response {
    return {
        ok: true,
        status: 200,
        json: async () => body,
    } as Response;
}

function buildQuote(stockId: number, price: number, recordedAt: string) {
    return {
        stockId,
        symbol: stockId === 1 ? 'NABIL' : 'ADBL',
        companyName: stockId === 1 ? 'Nabil Bank Limited' : 'Agricultural Development Bank Limited',
        sector: 'Commercial Bank',
        marketDate: '2026-03-22',
        recordedAt,
        latestSyncedAt: recordedAt,
        price,
        change: 10.25,
        changePercent: 1.28,
        previousClose: 800,
        high: 815,
        low: 805.5,
        open: 806,
        volume: 12345,
    };
}
