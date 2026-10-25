// @vitest-environment jsdom

import { afterEach, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import { createI18n } from 'vue-i18n';

const { getDownloads } = vi.hoisted(() => ({ getDownloads: vi.fn() }));
vi.mock('../../resources/js/services/downloadService/downloadService', () => ({
  default: { getDownloads },
}));

import Downloads from '../../resources/js/views/home/download.vue';

let app;
afterEach(() => {
  app?.unmount();
  document.body.innerHTML = '';
  getDownloads.mockReset();
});

it('renders entitled originals with authoritative media types and never uses previews', async () => {
  getDownloads.mockResolvedValue({ data: [
    { id: 1, type: 'image', full_url: '/original/1?signature=a', preview_url: '/preview/1', filename: 'a.jpg' },
    { id: 2, type: 'image', full_url: '/original/2?signature=b', preview_url: '/preview/2', filename: 'b.png' },
    { id: 3, type: 'image', full_url: '/original/3?signature=c', preview_url: '/preview/3', filename: 'c.webp' },
    { id: 4, type: 'video', full_url: '/original/4?signature=d', preview_url: '/preview/4', filename: 'd.mp4' },
  ] });
  const host = document.createElement('div');
  document.body.append(host);
  const i18n = createI18n({ legacy: false, locale: 'en', messages: { en: {
    downloads: { title: 'Downloads', fileCount: '{count}', imageType: 'IMG', videoType: 'VID', fileAlt: 'file', preview: 'View', download: 'Download' },
  } } });
  app = createApp(Downloads);
  app.use(i18n);
  app.mount(host);
  await Promise.resolve();
  await nextTick();
  await nextTick();

  expect(host.querySelectorAll('.media-card:not(.skeleton-card)')).toHaveLength(4);
  expect([...host.querySelectorAll('.media-card img')].map((el) => el.getAttribute('src')))
    .toEqual(['/original/1?signature=a', '/original/2?signature=b', '/original/3?signature=c']);
  expect(host.querySelector('video source').getAttribute('src')).toBe('/original/4?signature=d');
  expect([...host.querySelectorAll('.type-badge')].map((el) => el.textContent.trim()))
    .toEqual(['IMG', 'IMG', 'IMG', 'VID']);
  expect(host.innerHTML).not.toContain('/preview/');
});
