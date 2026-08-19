<template>
    <section class="special-coverage" :aria-labelledby="sectionTitleId">
        <div class="special-coverage__container">
            <div class="special-coverage__card">
                <div class="special-coverage__glow" aria-hidden="true"></div>

                <div class="special-coverage__content">
                    <span class="special-coverage__badge">
                        <span class="special-coverage__badge-dot" aria-hidden="true"></span>
                        {{ $t("homeAudit.specialCoverage.badge") }}
                    </span>

                    <h2 :id="sectionTitleId" class="special-coverage__title">
                        {{ $t("homeAudit.specialCoverage.title") }}
                    </h2>

                    <p class="special-coverage__description">
                        {{ $t("homeAudit.specialCoverage.description") }}
                    </p>

                    <button
                        ref="ctaButtonRef"
                        type="button"
                        class="special-coverage__cta"
                        @click="handleContactClick"
                    >
                        {{ $t("homeAudit.specialCoverage.contactUs") }}
                        <svg class="special-coverage__cta-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M5 12h14M14 7l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <Teleport to="body">
        <Transition name="special-coverage-modal">
            <div
                v-if="isModalOpen"
                class="special-coverage-modal__backdrop"
                @click.self="closeModal"
            >
                <div
                    class="special-coverage-modal"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="modalTitleId"
                >
                    <div class="special-coverage-modal__header">
                        <div>
                            <span class="special-coverage-modal__eyebrow">
                                {{ $t("homeAudit.specialCoverage.badge") }}
                            </span>
                            <h2 :id="modalTitleId" class="special-coverage-modal__title">
                                {{ $t("homeAudit.specialCoverage.modal.title") }}
                            </h2>
                        </div>

                        <button
                            type="button"
                            class="special-coverage-modal__close"
                            :aria-label="$t('homeAudit.specialCoverage.modal.close')"
                            @click="closeModal"
                        >
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <form class="special-coverage-modal__form" @submit.prevent>
                        <div class="special-coverage-modal__field">
                            <label for="special-coverage-event-name">
                                {{ $t("homeAudit.specialCoverage.modal.eventName") }}
                                <span class="special-coverage-modal__required" aria-hidden="true">*</span>
                            </label>
                            <input
                                id="special-coverage-event-name"
                                ref="eventNameInputRef"
                                v-model="eventName"
                                type="text"
                                required
                                :placeholder="$t('homeAudit.specialCoverage.modal.eventNamePlaceholder')"
                            />
                        </div>

                        <div class="special-coverage-modal__field">
                            <label for="special-coverage-event-description">
                                {{ $t("homeAudit.specialCoverage.modal.description") }}
                                <span class="special-coverage-modal__required" aria-hidden="true">*</span>
                            </label>
                            <textarea
                                id="special-coverage-event-description"
                                v-model="eventDescription"
                                rows="5"
                                required
                                :placeholder="$t('homeAudit.specialCoverage.modal.descriptionPlaceholder')"
                            ></textarea>
                        </div>

                        <div class="special-coverage-modal__actions">
                            <button type="button" class="special-coverage-modal__cancel" @click="closeModal">
                                {{ $t("homeAudit.specialCoverage.modal.cancel") }}
                            </button>
                            <button type="submit" class="special-coverage-modal__send">
                                {{ $t("homeAudit.specialCoverage.modal.send") }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";

const route = useRoute();
const router = useRouter();

const sectionTitleId = "special-coverage-title";
const modalTitleId = "special-coverage-modal-title";
const isModalOpen = ref(false);
const eventName = ref("");
const eventDescription = ref("");
const ctaButtonRef = ref(null);
const eventNameInputRef = ref(null);

let previousBodyOverflow = "";

const isAuthenticated = () => Boolean(localStorage.getItem("auth_token"));

const openModal = async () => {
    isModalOpen.value = true;
    await nextTick();
    eventNameInputRef.value?.focus();
};

const closeModal = async () => {
    isModalOpen.value = false;
    await nextTick();
    ctaButtonRef.value?.focus();
};

const handleContactClick = async () => {
    if (isAuthenticated()) {
        await openModal();
        return;
    }

    const lang = String(route.params.lang || localStorage.getItem("language") || "en").toLowerCase();
    const returnTo = router.resolve({
        path: route.path,
        query: {
            ...route.query,
            openSpecialCoverage: "1",
        },
        hash: route.hash,
    }).fullPath;

    await router.push({
        name: "auth",
        params: { lang },
        query: { redirect: returnTo },
    });
};

const openFromRoute = async () => {
    if (route.query.openSpecialCoverage !== "1" || !isAuthenticated()) return;

    await openModal();

    const { openSpecialCoverage: _openSpecialCoverage, ...query } = route.query;
    await router.replace({
        path: route.path,
        query,
        hash: route.hash,
    });
};

const handleEscape = (event) => {
    if (event.key === "Escape" && isModalOpen.value) {
        void closeModal();
    }
};

watch(isModalOpen, (isOpen) => {
    if (isOpen) {
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        return;
    }

    document.body.style.overflow = previousBodyOverflow;
});

watch(
    () => route.query.openSpecialCoverage,
    () => {
        void openFromRoute();
    }
);

onMounted(() => {
    document.addEventListener("keydown", handleEscape);
    void openFromRoute();
});

onUnmounted(() => {
    document.removeEventListener("keydown", handleEscape);
    document.body.style.overflow = previousBodyOverflow;
});
</script>

<style scoped>
.special-coverage {
    position: relative;
    padding: 48px 0;
    overflow: hidden;
    background: linear-gradient(180deg, var(--scemory-surface-soft) 0%, var(--scemory-surface) 100%);
}

.special-coverage__container {
    box-sizing: border-box;
    width: min(100%, 1320px);
    margin-inline: auto;
    padding-inline: 32px;
}

.special-coverage__card {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    border: 1px solid var(--scemory-border);
    border-radius: 28px;
    background:
        radial-gradient(circle at 88% 15%, rgba(48, 168, 255, 0.18), transparent 22rem),
        linear-gradient(135deg, var(--scemory-white) 0%, var(--scemory-surface) 58%, var(--scemory-surface-soft) 100%);
    box-shadow: var(--scemory-shadow);
}

.special-coverage__card::before {
    content: "";
    position: absolute;
    inset-block: 22%;
    inset-inline-start: 0;
    width: 4px;
    border-radius: 999px;
    background: linear-gradient(180deg, var(--scemory-blue), var(--scemory-light-blue));
}

.special-coverage__glow {
    position: absolute;
    z-index: -1;
    inset-inline-end: -70px;
    inset-block-end: -110px;
    width: 270px;
    height: 270px;
    border-radius: 50%;
    background: rgba(22, 119, 255, 0.08);
    filter: blur(4px);
}

.special-coverage__content {
    display: flex;
    min-height: 300px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 46px 32px;
    text-align: center;
}

.special-coverage__badge,
.special-coverage-modal__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    width: fit-content;
    color: var(--scemory-primary);
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.special-coverage__badge {
    border: 1px solid var(--scemory-border);
    border-radius: 999px;
    padding: 8px 13px;
    background: var(--scemory-bg-soft-blue);
}

.special-coverage__badge-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--scemory-light-blue);
    box-shadow: 0 0 0 4px rgba(48, 168, 255, 0.12);
}

.special-coverage__title {
    max-width: 760px;
    margin: 20px 0 0;
    color: var(--scemory-heading);
    font-size: clamp(1.8rem, 3vw, 2.65rem);
    font-weight: 800;
    line-height: 1.16;
}

.special-coverage__description {
    max-width: 680px;
    margin: 14px 0 0;
    color: var(--scemory-muted);
    font-size: 1rem;
    line-height: 1.75;
}

.special-coverage__cta {
    display: inline-flex;
    min-height: 48px;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 26px;
    border: 1px solid rgba(22, 119, 255, 0.3);
    border-radius: 999px;
    padding: 12px 25px;
    color: var(--scemory-white);
    background: linear-gradient(135deg, var(--scemory-primary), var(--scemory-blue));
    box-shadow: 0 10px 24px rgba(13, 77, 151, 0.2);
    font-size: 0.95rem;
    font-weight: 750;
    cursor: pointer;
    transition: var(--scemory-transition);
}

.special-coverage__cta:hover {
    transform: translateY(-2px);
    background: linear-gradient(135deg, var(--scemory-blue), var(--scemory-light-blue));
    box-shadow: 0 14px 28px rgba(13, 77, 151, 0.24);
}

.special-coverage__cta:focus-visible,
.special-coverage-modal button:focus-visible,
.special-coverage-modal input:focus-visible,
.special-coverage-modal textarea:focus-visible {
    outline: 3px solid rgba(48, 168, 255, 0.32);
    outline-offset: 3px;
}

.special-coverage__cta-icon {
    width: 18px;
    height: 18px;
}

:global([dir="rtl"]) .special-coverage__cta-icon {
    transform: scaleX(-1);
}

.special-coverage-modal__backdrop {
    position: fixed;
    inset: 0;
    z-index: 2147483100;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow-y: auto;
    padding: 24px;
    background: rgba(6, 20, 42, 0.62);
    backdrop-filter: blur(10px);
}

.special-coverage-modal {
    width: min(100%, 620px);
    max-height: calc(100vh - 48px);
    overflow-y: auto;
    border: 1px solid var(--scemory-border);
    border-radius: 24px;
    background: var(--scemory-white);
    box-shadow: 0 28px 80px rgba(2, 8, 23, 0.28);
}

.special-coverage-modal__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    padding: 28px 28px 20px;
    border-bottom: 1px solid var(--scemory-border-soft);
    background: linear-gradient(135deg, var(--scemory-white), var(--scemory-surface));
}

.special-coverage-modal__title {
    margin: 7px 0 0;
    color: var(--scemory-heading);
    font-size: 1.65rem;
    font-weight: 800;
    line-height: 1.2;
}

.special-coverage-modal__close {
    display: inline-flex;
    width: 40px;
    height: 40px;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--scemory-border);
    border-radius: 50%;
    color: var(--scemory-text);
    background: var(--scemory-control);
    font-size: 1.55rem;
    line-height: 1;
    cursor: pointer;
    transition: var(--scemory-transition);
}

.special-coverage-modal__close:hover {
    color: var(--scemory-primary);
    background: var(--scemory-hover);
    transform: rotate(4deg);
}

.special-coverage-modal__form {
    display: grid;
    gap: 22px;
    padding: 26px 28px 28px;
}

.special-coverage-modal__field {
    display: grid;
    gap: 9px;
}

.special-coverage-modal__field label {
    color: var(--scemory-heading);
    font-size: 0.91rem;
    font-weight: 750;
}

.special-coverage-modal__required {
    color: var(--scemory-blue);
}

.special-coverage-modal__field input,
.special-coverage-modal__field textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid var(--scemory-border);
    border-radius: 14px;
    padding: 12px 14px;
    color: var(--scemory-body);
    background: var(--scemory-surface);
    font: inherit;
    line-height: 1.55;
    transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
}

.special-coverage-modal__field textarea {
    min-height: 130px;
    resize: vertical;
}

.special-coverage-modal__field input::placeholder,
.special-coverage-modal__field textarea::placeholder {
    color: var(--scemory-muted);
    opacity: 0.78;
}

.special-coverage-modal__field input:focus,
.special-coverage-modal__field textarea:focus {
    border-color: var(--scemory-blue);
    background: var(--scemory-white);
    box-shadow: 0 0 0 4px rgba(22, 119, 255, 0.09);
}

.special-coverage-modal__actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 2px;
}

.special-coverage-modal__cancel,
.special-coverage-modal__send {
    min-height: 44px;
    border-radius: 999px;
    padding: 10px 21px;
    font-size: 0.92rem;
    font-weight: 750;
    cursor: pointer;
    transition: var(--scemory-transition);
}

.special-coverage-modal__cancel {
    border: 1px solid var(--scemory-border);
    color: var(--scemory-text);
    background: var(--scemory-control);
}

.special-coverage-modal__cancel:hover {
    color: var(--scemory-primary);
    background: var(--scemory-hover);
}

.special-coverage-modal__send {
    border: 1px solid rgba(22, 119, 255, 0.3);
    color: var(--scemory-white);
    background: linear-gradient(135deg, var(--scemory-primary), var(--scemory-blue));
    box-shadow: 0 8px 20px rgba(13, 77, 151, 0.18);
}

.special-coverage-modal__send:hover {
    transform: translateY(-1px);
    background: linear-gradient(135deg, var(--scemory-blue), var(--scemory-light-blue));
}

.special-coverage-modal-enter-active,
.special-coverage-modal-leave-active {
    transition: opacity 0.2s ease;
}

.special-coverage-modal-enter-active .special-coverage-modal,
.special-coverage-modal-leave-active .special-coverage-modal {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.special-coverage-modal-enter-from,
.special-coverage-modal-leave-to,
.special-coverage-modal-enter-from .special-coverage-modal,
.special-coverage-modal-leave-to .special-coverage-modal {
    opacity: 0;
}

.special-coverage-modal-enter-from .special-coverage-modal,
.special-coverage-modal-leave-to .special-coverage-modal {
    transform: translateY(12px) scale(0.98);
}

@media (max-width: 768px) {
    .special-coverage {
        padding: 36px 0;
    }

    .special-coverage__container {
        padding-inline: 20px;
    }

    .special-coverage__content {
        min-height: 280px;
        padding: 40px 24px;
    }
}

@media (max-width: 520px) {
    .special-coverage {
        padding: 28px 0;
    }

    .special-coverage__container {
        padding-inline: 14px;
    }

    .special-coverage__card {
        border-radius: 22px;
    }

    .special-coverage__content {
        min-height: 0;
        padding: 34px 18px;
    }

    .special-coverage__title {
        font-size: 1.7rem;
    }

    .special-coverage__description {
        font-size: 0.94rem;
    }

    .special-coverage-modal__backdrop {
        align-items: flex-end;
        padding: 12px;
    }

    .special-coverage-modal {
        max-height: calc(100vh - 24px);
        border-radius: 22px;
    }

    .special-coverage-modal__header {
        padding: 22px 20px 17px;
    }

    .special-coverage-modal__form {
        gap: 18px;
        padding: 22px 20px 24px;
    }

    .special-coverage-modal__actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }
}

@media (prefers-reduced-motion: reduce) {
    .special-coverage__cta,
    .special-coverage-modal *,
    .special-coverage-modal-enter-active,
    .special-coverage-modal-leave-active {
        transition: none !important;
    }
}
</style>
