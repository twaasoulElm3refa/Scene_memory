<template>
    <div class="min-h-screen bg-gray-50/60 py-14 px-4">
        <div class="max-w-4xl mx-auto">

            <!-- Header -->
            <div class="mb-10 pb-8 border-b border-gray-200">
                <span class="text-xs font-medium tracking-widest text-gray-400 uppercase">Legal</span>
                <h1 class="text-3xl font-semibold text-gray-900 mt-2 mb-1">
                    {{ $t("terms.title") }}
                </h1>
                <p class="text-sm text-gray-400">
                    {{ $t("terms.last_updated") }}
                </p>
            </div>

            <!-- Body: Sidebar + Content -->
            <div class="flex gap-10">

                <!-- Sticky Sidebar Nav -->
                <aside class="hidden lg:flex flex-col gap-1 w-44 shrink-0 sticky top-8 self-start">
                    <p class="text-[11px] font-medium tracking-widest text-gray-400 uppercase mb-2">
                        Sections
                    </p>
                    <a v-for="(section, i) in sections" :key="i" :href="`#section-${i}`" :class="[
                        'text-sm px-2.5 py-1.5 rounded-lg transition-colors duration-150',
                        activeSection === i
                            ? 'text-gray-900 bg-gray-100'
                            : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100'
                    ]">
                        {{ $t(section.titleKey) }}
                    </a>
                </aside>

                <!-- Sections -->
                <div class="flex-1 flex flex-col divide-y divide-gray-100">
                    <section v-for="(section, i) in sections" :key="i" :id="`section-${i}`" class="py-8 first:pt-0">
                        <div class="flex items-center gap-3 mb-3">
                            <span
                                class="w-6 h-6 rounded-md text-xs font-medium flex items-center justify-center shrink-0"
                                :class="section.badge">
                                {{ i + 1 }}
                            </span>
                            <h2 class="text-base font-semibold text-gray-800">
                                {{ $t(section.titleKey) }}
                            </h2>
                        </div>

                        <p class="text-sm leading-relaxed pl-9"
                            :class="section.danger ? 'text-red-600 bg-red-50 rounded-lg p-3 pl-3' : 'text-gray-500'">
                            {{ $t(section.textKey) }}
                        </p>
                    </section>
                </div>
            </div>

        </div>
    </div>
</template>

<script setup>
const sections = [
    {
        titleKey: "terms.acceptance_title",
        textKey: "terms.acceptance_text",
        badge: "bg-blue-50 text-blue-500",
    },
    {
        titleKey: "terms.accounts_title",
        textKey: "terms.accounts_text",
        badge: "bg-blue-50 text-blue-500",
    },
    {
        titleKey: "terms.content_title",
        textKey: "terms.content_text",
        badge: "bg-blue-50 text-blue-500",
    },
    {
        titleKey: "terms.ai_title",
        textKey: "terms.ai_text",
        badge: "bg-violet-50 text-violet-500",
    },
    {
        titleKey: "terms.prohibited_title",
        textKey: "terms.prohibited_text",
        badge: "bg-red-50 text-red-400",
        danger: true,
    },
    {
        titleKey: "terms.liability_title",
        textKey: "terms.liability_text",
        badge: "bg-gray-100 text-gray-500",
    },
    {
        titleKey: "terms.changes_title",
        textKey: "terms.changes_text",
        badge: "bg-gray-100 text-gray-500",
    },
    {
        titleKey: "terms.contact_title",
        textKey: "terms.contact_text",
        badge: "bg-green-50 text-green-500",
    },
]
</script>
