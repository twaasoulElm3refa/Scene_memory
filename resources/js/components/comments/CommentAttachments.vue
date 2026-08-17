<script setup>
defineProps({
    images: { type: Array, default: () => [] },
    resolveUrl: { type: Function, required: true },
});
</script>

<template>
    <div v-if="images.length" class="comment-attachments" :class="{ 'has-one': images.length === 1 }">
        <a
            v-for="image in images.slice(0, 2)"
            :key="image.id || resolveUrl(image)"
            :href="resolveUrl(image)"
            target="_blank"
            rel="noopener noreferrer"
            class="comment-attachment"
        >
            <img
                :src="resolveUrl(image)"
                :alt="$t('commentsPage.attachmentAlt')"
                loading="lazy"
            />
        </a>
    </div>
</template>

<style scoped>
.comment-attachments {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 190px));
    gap: 0.65rem;
    width: fit-content;
    max-width: 100%;
    margin: 0.85rem 0 1rem;
}

.comment-attachments.has-one {
    grid-template-columns: minmax(0, 210px);
}

.comment-attachment {
    display: block;
    width: 100%;
    aspect-ratio: 3 / 2;
    overflow: hidden;
    border: 1px solid #dce8f5;
    border-radius: 12px;
    background: #f1f5f9;
}

.comment-attachment img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.2s ease;
}

.comment-attachment:hover img {
    transform: scale(1.025);
}

@media (max-width: 480px) {
    .comment-attachments,
    .comment-attachments.has-one {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        width: 100%;
    }

    .comment-attachments.has-one {
        max-width: 210px;
    }
}
</style>
