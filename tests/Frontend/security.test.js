// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vitest';
import { escapeHtml, safeMediaUrl } from '../../resources/js/services/safeContent';
import { getStorageUrl } from '../../resources/js/services/EventService/eventMedia';
import { toMediaUrl } from '../../resources/js/services/EventService/eventSearchHelpers';
import MapService from '../../resources/js/services/MapService/MapService';

vi.mock('../../resources/js/services/mapLibre', () => ({ default: { getRTLTextPluginStatus: () => 'loaded' } }));
vi.mock('../../resources/js/services/ApiClient', () => ({ default: {} }));

describe('map content safety', () => {
    it('escapes text and HTML attribute delimiters without changing stored content', () => {
        const title = `<img src=x onerror="alert('fixture')"> &`;
        const host = document.createElement('div');
        host.innerHTML = `<h3>${escapeHtml(title)}</h3>`;
        expect(host.querySelector('img')).toBeNull();
        expect(host.textContent).toBe(title);
    });
    it('rejects active URL schemes and credentials', () => {
        for (const value of ['javascript:alert(1)', 'data:text/html,<script>test</script>', 'file:///secret', 'https://user:password@example.test/pic']) expect(safeMediaUrl(value)).toBe('');
        expect(safeMediaUrl('https://example.test/pic.jpg')).toBe('https://example.test/pic.jpg');
    });
    it('renders hostile event titles and image URLs safely in the actual popup builder', () => {
        const title = '<img src=x onerror="alert(1)">';
        const html = MapService.prototype._buildPopupHTML({ image_url: 'javascript:alert(1)' }, title, false);
        const host = document.createElement('div');
        host.innerHTML = html;
        expect(host.textContent).toContain(title);
        expect(host.querySelector('[onerror], [onload], script')).toBeNull();
        expect(html).not.toContain('javascript:');
    });
});

describe('controlled media and localization contracts', () => {
    it('preserves signed preview URLs rather than treating them as storage paths', () => {
        const url = 'https://example.test/api/v1/media/42/preview?expires=123&signature=fixture';
        expect(getStorageUrl(url)).toBe(url);
        expect(toMediaUrl(url)).toBe(url);
        expect(getStorageUrl('/api/v1/media/42/preview')).not.toContain('/storage/api/');
        expect(toMediaUrl('/api/v1/media/42/preview')).not.toContain('/storage/api/');
    });
    it('provides every security message in precisely the existing thirteen locales', () => {
        const files = import.meta.glob('../../resources/js/i18n/*.json', { eager: true, import: 'default' });
        const messages = Object.fromEntries(Object.entries(files).map(([path, value]) => [path.split('/').pop().replace('.json', ''), value.security]));
        expect(Object.keys(messages).sort()).toEqual(['ar', 'en', 'fr', 'de', 'ru', 'es', 'it', 'hi', 'ja', 'fa', 'zh', 'ur', 'tr'].sort());
        const keys = Object.keys(messages.en).sort();
        for (const [locale, values] of Object.entries(messages)) {
            expect(Object.keys(values).sort(), locale).toEqual(keys);
            for (const [key, value] of Object.entries(values)) {
                expect(value.trim().length, `${locale}.${key}`).toBeGreaterThan(0);
                if (locale !== 'en') expect(value, `${locale}.${key}`).not.toBe(messages.en[key]);
            }
        }
    });
});
