<template>
    <div class="gift-support">
        <button v-if="recipient?.id && Number(recipient.id) !== Number(currentUserId)" type="button" class="support-button" @click="open">✦ {{ t('gifts.sendSupport') }}</button>
        <span v-if="count" class="support-total">{{ t('gifts.giftCount', { count }) }}</span>
        <VirtualGiftModal v-if="visible" :event-id="Number(eventId)" :comment-id="commentId ? Number(commentId) : null" :recipient="recipient" @close="visible = false" @delivered="refresh" />
    </div>
</template>
<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import VirtualGiftModal from './VirtualGiftModal.vue';
import { giftRequest } from '@/services/virtualGiftService';
const props = defineProps({ eventId: [Number, String], commentId: [Number, String], recipient: Object, authenticated: Boolean, currentUserId: [Number, String] });
const { t, locale } = useI18n(); const router = useRouter(); const visible = ref(false), summary = ref([]);
const count = computed(() => summary.value.reduce((sum, item) => sum + Number(item.quantity), 0));
function open() { if (!props.authenticated) router.push(`/${locale.value}/auth`); else visible.value = true; }
async function refresh() {
    try { summary.value = await giftRequest('get', props.commentId ? `/comments/${props.commentId}/gifts/summary` : `/events/${props.eventId}/gifts/summary`); }
    catch { summary.value = []; }
}
onMounted(refresh);
</script>
<style scoped>
.gift-support { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-block: 8px; } .support-button { border: 1px solid #059669; border-radius: 12px; background: #ecfdf5; padding: 8px 12px; color: #047857; font-weight: 700; font-size: .8rem; } .support-total { font-size: .75rem; color: #64748b; }
</style>
