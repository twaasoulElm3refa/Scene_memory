// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import { createI18n } from 'vue-i18n';
import { createMemoryHistory, createRouter } from 'vue-router';
import Preview from '../../resources/js/views/home/components/WeeklyLeaderboardPreview.vue';
import Stats from '../../resources/js/components/profile/WeeklyStatsCard.vue';
import translations from '../../resources/js/i18n/weeklyCompetition';
import { getWeeklyLeaderboardPreview, getWeeklyLeaderboard } from '../../resources/js/services/leaderboardService';

vi.mock('../../resources/js/services/leaderboardService', () => ({
    getWeeklyLeaderboardPreview: vi.fn(),
    getWeeklyLeaderboard: vi.fn(),
}));

let app;
const settle = async () => { for (let i = 0; i < 8; i++) await Promise.resolve(); await nextTick(); };
async function mount(component, props = {}, locale = 'en') {
    const target = document.createElement('div');
    document.body.append(target);
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:lang/leaderboard', component: { template: '<div />' } }] });
    await router.push(`/${locale}/leaderboard`);
    app = createApp(component, props);
    app.use(createI18n({ legacy: false, locale, messages: { [locale]: { weeklyCompetition: translations[locale] } } }));
    app.use(router);
    app.mount(target);
    await settle();
    return target;
}
const payload = (users = []) => ({ data: {
    week_start: '2026-10-17', week_end: '2026-10-23', timezone: 'Asia/Riyadh',
    ends_at: '2026-10-23T21:00:00+00:00', users,
} });

afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
    vi.clearAllMocks();
    vi.useRealTimers();
});

describe('weekly competition rendering', () => {
    it.each(Object.keys(translations))('renders weekly statistics in %s without missing translations', async (locale) => {
        expect(Object.keys(translations[locale])).toEqual(Object.keys(translations.en));
        expect(Object.values(translations[locale]).every((value) => typeof value === 'string' && value.length > 0)).toBe(true);
        const target = await mount(Stats, { stats: { weekly_points: 250, weekly_rank: 2, week_start: '2026-10-17', week_end: '2026-10-23' } }, locale);
        expect(target.textContent).toContain(translations[locale].thisWeek);
        expect(target.textContent).toContain(translations[locale].points);
        expect(target.textContent).toContain('#2');
        expect(target.textContent).not.toContain('weeklyCompetition.');
    });

    it('renders a weekly empty state and localized full leaderboard link', async () => {
        getWeeklyLeaderboardPreview.mockResolvedValue(payload());
        const target = await mount(Preview, {}, 'ar');
        expect(target.textContent).toContain(translations.ar.empty);
        expect(target.querySelector('a').getAttribute('href')).toBe('/ar/leaderboard');
        expect(target.textContent).toContain('Asia/Riyadh');
    });

    it('shows an error and recovers on retry', async () => {
        getWeeklyLeaderboardPreview.mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce(payload());
        const target = await mount(Preview);
        expect(target.querySelector('[role=alert]')).not.toBeNull();
        target.querySelector('button').click();
        await settle();
        expect(target.querySelector('[role=alert]')).toBeNull();
        expect(target.textContent).toContain(translations.en.empty);
    });

    it('refreshes at the server-provided Saturday boundary', async () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-10-23T20:59:59Z'));
        getWeeklyLeaderboardPreview.mockResolvedValueOnce(payload([{ id: 1, rank: 1, points: 250, name: 'Memory Author', badge: 'Memory Master' }]))
            .mockResolvedValueOnce({ data: { ...payload().data, week_start: '2026-10-24', week_end: '2026-10-30', ends_at: '2026-10-30T21:00:00Z' } });
        const target = await mount(Preview);
        expect(target.textContent).toContain('250');
        await vi.advanceTimersByTimeAsync(1101);
        await settle();
        expect(getWeeklyLeaderboardPreview).toHaveBeenCalledTimes(2);
        expect(target.textContent).not.toContain('Memory Author');
        expect(target.textContent).toContain(translations.en.empty);
    });

    it('paginates the full leaderboard through the weekly API', async () => {
        getWeeklyLeaderboard.mockResolvedValueOnce({ data: { ...payload().data, current_page: 1, last_page: 2 } })
            .mockResolvedValueOnce({ data: { ...payload().data, current_page: 2, last_page: 2 } });
        const target = await mount(Preview, { full: true });
        const buttons = target.querySelectorAll('button');
        expect(buttons[0].disabled).toBe(true);
        buttons[1].click();
        await settle();
        expect(getWeeklyLeaderboard).toHaveBeenLastCalledWith(2);
        expect(getWeeklyLeaderboardPreview).not.toHaveBeenCalled();
    });
});
