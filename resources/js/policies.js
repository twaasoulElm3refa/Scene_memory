// The existing public URLs remain authoritative; each policy has exactly one route.
export const policies = [
    { id: 'copyright', path: '/copyright-policy' },
    { id: 'terms', path: '/terms' },
    { id: 'privacy', path: '/privacy-policy' },
    { id: 'dataProtection', path: '/data-protection' },
    { id: 'cookies', path: '/cookie-policy' },
    { id: 'community', path: '/community-guidelines' },
    { id: 'contentRights', path: '/intellectual-property' },
    { id: 'reporting', path: '/copyright-reporting' },
    { id: 'marketplace', path: '/marketplace-policy' },
];

// No effective date is inferred from a deployment date. Set only after legal approval.
export const approvedPolicyDate = null;

const externalHosts = {
    facebook: ['facebook.com', 'www.facebook.com'],
    twitter: ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com'],
    instagram: ['instagram.com', 'www.instagram.com'],
    google_play: ['play.google.com'],
    app_store: ['apps.apple.com'],
};

export function verifiedFooterUrl(value, type) {
    try {
        const url = new URL(value);
        if (url.protocol !== 'https:' || url.username || url.password || url.port) return null;
        if (!externalHosts[type]?.includes(url.hostname.toLowerCase())) return null;
        // Reject the seed data, homepages and placeholder download destinations.
        if (!url.pathname.replace(/\//g, '').trim()) return null;
        if (type === 'google_play' && (url.pathname !== '/store/apps/details' || !url.searchParams.get('id'))) return null;
        if (type === 'app_store' && !/\/id\d+/.test(url.pathname)) return null;
        return url.href;
    } catch { return null; }
}
