import { reactive } from 'vue';

// Updated only after the Navbar has verified the current user's profile.
export const navigationSession = reactive({ isLoggedIn: false, eventCount: 0 });

export const navigationGroups = {
    primary: [
        { labelKey: 'nav.home', path: '/home', active: 'home' },
        { labelKey: 'nav.scemoryGate', path: '/scemory-gate', active: 'scemory-gate' },
    ],
    plans: { labelKey: 'nav.plans', path: '/plans', active: 'plans' },
    specialCoverage: { labelKey: 'nav.specialCoverage', path: '/home', action: 'specialCoverage' },
    events: [
        { labelKey: 'nav.allEvents', path: '/all_events' },
        { labelKey: 'nav.addEvent', path: '/add_event', authenticated: true },
        { labelKey: 'nav.historical', path: '/historical', authenticated: true },
    ],
    more: [
        { labelKey: 'nav.favourites', path: '/WishList' },
        { labelKey: 'nav.contact', path: '/contact' },
    ],
    mobileMore: [
        { labelKey: 'nav.terms', path: '/terms' },
        { labelKey: 'nav.about', path: '/who' },
    ],
    account: [
        { labelKey: 'nav.profile', path: '/profile', authenticated: true },
        { labelKey: 'nav.timeline', path: '/profile/timeline', authenticated: true },
        { labelKey: 'nav.mediaCredits', path: '/profile?tab=credits', authenticated: true },
        { labelKey: 'nav.downloads', path: '/downloads', authenticated: true },
        { labelKey: 'nav.dashboard', path: '/creator/events', authenticated: true, creator: true },
    ],
    login: { labelKey: 'nav.login', path: '/auth', guest: true },
    cart: { labelKey: 'nav.cart', path: '/cart' },
};

export const visibleNavigation = (items, session) => items.filter(item =>
    (!item.authenticated || session.isLoggedIn) &&
    (!item.guest || !session.isLoggedIn) &&
    (!item.creator || session.eventCount > 0)
);

export const footerNavigation = session => visibleNavigation([
    ...navigationGroups.primary, navigationGroups.plans, navigationGroups.specialCoverage,
    ...navigationGroups.events, ...navigationGroups.more, ...navigationGroups.mobileMore,
    ...navigationGroups.account, navigationGroups.login, navigationGroups.cart,
], session);

export const localizedPath = (lang, path) => `/${lang}${path}`;

export function specialCoverageDestination(lang, authenticated, query = {}) {
    const path = localizedPath(lang, '/home');
    const destination = { path, query: { ...query, openSpecialCoverage: '1' } };
    return authenticated ? destination : {
        name: 'auth', params: { lang },
        query: { redirect: `${path}?${new URLSearchParams(destination.query)}` },
    };
}
