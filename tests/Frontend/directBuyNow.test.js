// @vitest-environment jsdom

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { createApp, h, nextTick } from "vue";
import { createI18n } from "vue-i18n";
import { createMemoryHistory, createRouter } from "vue-router";

import DiscoveryMediaCard from "../../resources/js/views/home/components/DiscoveryMediaCard.vue";
import discoverySearchMessages from "../../resources/js/i18n/discoverySearch";

// Mock services
const mockPay = vi.fn();
vi.mock("@/services/PaymentService/PaymentService", () => ({
    PaymentService: {
        pay: (...args) => mockPay(...args),
    },
}));

vi.mock("@/services/CartService/CartService", () => ({
    CartService: {
        addToCart: vi.fn().mockResolvedValue({}),
    },
}));

let mockToken = null;
vi.mock("@/services/authTokenStorage", () => ({
    getAuthToken: () => mockToken,
}));

vi.mock("@/services/ApiClient", () => ({
    default: { get: vi.fn(), post: vi.fn() },
    showSafeToast: vi.fn(),
}));

vi.mock("@/services/runtimeUrls", () => ({
    isMobileRuntime: () => false,
    toAssetUrl: (v) => v,
}));

const existing = import.meta.glob("../../resources/js/i18n/*.json", {
    eager: true,
    import: "default",
});
const messages = Object.fromEntries(
    Object.entries(existing).map(([file, value]) => [
        file.split("/").pop().replace(".json", ""),
        value,
    ])
);

// Merge discovery translations exactly like resources/js/app.js does
Object.entries(discoverySearchMessages).forEach(([lang, discovery]) => {
    if (!messages[lang]) messages[lang] = {};
    messages[lang].discovery = discovery;
});

const languages = [
    "ar", "de", "en", "es", "fa", "fr", "hi", "it", "ja", "ru", "tr", "ur", "zh",
];

let app;
const settle = async () => {
    for (let i = 0; i < 4; i++) {
        await new Promise((resolve) => setTimeout(resolve, 0));
        await nextTick();
    }
};

async function mountCard(props = {}, lang = "en") {
    const target = document.createElement("div");
    document.body.append(target);

    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: "/:lang", name: "home", component: { render: () => h("div") } },
            { path: "/:lang/auth", name: "auth", component: { render: () => h("div") } },
            { path: "/:lang/downloads", name: "downloads", component: { render: () => h("div") } },
            { path: "/:pathMatch(.*)*", component: { render: () => h("div") } },
        ],
    });

    await router.push(`/${lang}`);

    const i18n = createI18n({
        legacy: false,
        locale: lang,
        fallbackLocale: "en",
        messages,
    });

    app = createApp(DiscoveryMediaCard, {
        result: {
            id: 101,
            media_id: 101,
            result_type: "image",
            price: "15.00",
            image_url: "/uploads/sample.jpg",
            ...props.result,
        },
        index: 0,
        ...props,
    });

    app.use(router).use(i18n);
    app.mount(target);
    await settle();

    return { target, router };
}

afterEach(() => {
    app?.unmount();
    document.body.innerHTML = "";
    mockToken = null;
    vi.clearAllMocks();
    sessionStorage.clear();
    localStorage.clear();
});

describe("Direct Buy Now Localization", () => {
    it("provides directPurchase translations for all 13 supported languages", () => {
        expect(Object.keys(messages)).toEqual(expect.arrayContaining(languages));

        languages.forEach((lang) => {
            const langMessages = messages[lang];
            expect(langMessages, `Missing messages for ${lang}`).toBeDefined();
            expect(
                langMessages.directPurchase,
                `Missing directPurchase section in ${lang}.json`
            ).toBeDefined();

            const dp = langMessages.directPurchase;
            expect(dp.buyNow, `Missing buyNow in ${lang}`).toBeTruthy();
            expect(dp.processing, `Missing processing in ${lang}`).toBeTruthy();
            expect(dp.alreadyOwned, `Missing alreadyOwned in ${lang}`).toBeTruthy();
            expect(dp.loginToBuy, `Missing loginToBuy in ${lang}`).toBeTruthy();
        });
    });

    it("verifies Arabic direct purchase translations including 'اشترِ الآن'", () => {
        const ar = messages.ar.directPurchase;
        expect(ar.buyNow).toBe("اشترِ الآن");
        expect(ar.processing).toBe("جارٍ معالجة الدفع...");
        expect(ar.alreadyOwned).toBe("تم الشراء مسبقاً");
        expect(ar.viewPurchased).toBe("عرض الوسائط المشتراة");
    });

    it("verifies discoverySearch.js includes buyNow across all languages", () => {
        expect(discoverySearchMessages.en.media.buyNow).toBe("Buy Now");
        expect(discoverySearchMessages.en.media.processing).toBe("Processing payment...");

        languages.forEach((lang) => {
            expect(
                discoverySearchMessages[lang]?.media?.buyNow,
                `discoverySearch.js missing buyNow for ${lang}`
            ).toBeTruthy();
        });
        expect(discoverySearchMessages.ar.media.buyNow).toBe("اشترِ الآن");
    });
});

describe("DiscoveryMediaCard Buy Now Component", () => {
    it("renders both Add to Cart and Buy Now buttons side-by-side", async () => {
        const { target } = await mountCard();

        const actions = target.querySelector(".discovery-media-card__actions");
        expect(actions).not.toBeNull();

        const cartBtn = target.querySelector(".discovery-media-card__cart");
        expect(cartBtn).not.toBeNull();
        expect(cartBtn.textContent.toLowerCase()).toContain("add to cart");

        const buyNowBtn = target.querySelector(".discovery-media-card__buy-now");
        expect(buyNowBtn).not.toBeNull();
        expect(buyNowBtn.textContent).toContain("Buy Now");
    });

    it("renders Arabic labels for Buy Now ('اشترِ الآن') in ar locale", async () => {
        const { target } = await mountCard({}, "ar");

        const buyNowBtn = target.querySelector(".discovery-media-card__buy-now");
        expect(buyNowBtn).not.toBeNull();
        expect(buyNowBtn.textContent).toContain("اشترِ الآن");
    });

    it("redirects unauthenticated users to auth and saves pending buy-now in sessionStorage", async () => {
        mockToken = null;
        const { target, router } = await mountCard({
            result: { id: 77, media_id: 77, price: "18.00" },
        }, "en");

        const buyNowBtn = target.querySelector(".discovery-media-card__buy-now");
        buyNowBtn.click();
        await settle();

        // 1. Session storage has pending media
        const pending = JSON.parse(sessionStorage.getItem("scemory_pending_buy_now"));
        expect(pending).not.toBeNull();
        expect(pending.mediaId).toBe(77);

        // 2. Redirected to /en/auth
        expect(router.currentRoute.value.path).toBe("/en/auth");
    });

    it("initiates direct PayPal single_media checkout when authenticated", async () => {
        mockToken = "valid-buyer-token";
        mockPay.mockResolvedValue({
            data: {
                approval_url: "https://www.sandbox.paypal.com/checkoutnow?token=BUYNOW-TEST",
                order_id: 999,
            },
        });

        // Mock window.location
        delete window.location;
        window.location = { href: "" };

        const { target } = await mountCard({
            result: { id: 55, media_id: 55, price: "20.00" },
        }, "en");

        const buyNowBtn = target.querySelector(".discovery-media-card__buy-now");
        buyNowBtn.click();
        await settle();

        expect(mockPay).toHaveBeenCalledWith({
            type: "single_media",
            media_id: 55,
            idempotency_key: expect.any(String),
        });

        expect(window.location.href).toBe("https://www.sandbox.paypal.com/checkoutnow?token=BUYNOW-TEST");
    });

    it("navigates to downloads when media is already owned (409 conflict)", async () => {
        mockToken = "valid-buyer-token";
        mockPay.mockRejectedValue({
            response: {
                status: 409,
                data: { message: "One or more media items are already owned." },
            },
        });

        const { target, router } = await mountCard({
            result: { id: 88, media_id: 88, price: "12.00" },
        }, "en");

        const buyNowBtn = target.querySelector(".discovery-media-card__buy-now");
        buyNowBtn.click();
        await settle();

        expect(router.currentRoute.value.path).toBe("/en/downloads");
    });
});
