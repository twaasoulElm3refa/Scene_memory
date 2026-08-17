import { ref } from "vue";
import CommentService, { extractErrorMessage } from "../services/CommentService/CommentService";

const REACTION_TYPES = new Set(["support", "neutral", "exhibitions"]);

const normalizeReaction = (type) => {
    if (!type) return null;

    const normalized = String(type).toLowerCase();
    return REACTION_TYPES.has(normalized) ? normalized : null;
};

const counterKey = (type) => `${type}_count`;

export function useCommentReactions() {
    const reactions = ref({});
    const reactionLoading = ref({});
    const reactionErrors = ref({});

    const initializeReactions = (comments = []) => {
        const next = { ...reactions.value };

        comments.forEach((comment) => {
            if (!comment?.id) return;
            next[comment.id] = normalizeReaction(comment.current_user_reaction);
        });

        reactions.value = next;
    };

    const setReaction = async (comment, type) => {
        const commentId = comment?.id;
        const nextReaction = normalizeReaction(type);

        if (!commentId || !nextReaction || reactionLoading.value[commentId]) return;

        const previousReaction = normalizeReaction(reactions.value[commentId]);

        // The product convention is idempotent selection, not click-again removal.
        if (previousReaction === nextReaction) return;

        const snapshot = {
            support_count: Number(comment.support_count || 0),
            neutral_count: Number(comment.neutral_count || 0),
            exhibitions_count: Number(comment.exhibitions_count || 0),
        };

        reactions.value[commentId] = nextReaction;

        if (previousReaction) {
            const previousCounter = counterKey(previousReaction);
            comment[previousCounter] = Math.max(0, snapshot[previousCounter] - 1);
        }

        const nextCounter = counterKey(nextReaction);
        comment[nextCounter] = snapshot[nextCounter] + 1;
        reactionLoading.value[commentId] = true;
        reactionErrors.value[commentId] = "";

        try {
            const response = await CommentService.reactToComment(commentId, nextReaction);
            const data = response?.data?.data || {};

            reactions.value[commentId] = normalizeReaction(data.current_user_reaction) || nextReaction;
            comment.current_user_reaction = reactions.value[commentId];
            comment.support_count = Number(data.support_count ?? comment.support_count ?? 0);
            comment.neutral_count = Number(data.neutral_count ?? comment.neutral_count ?? 0);
            comment.exhibitions_count = Number(data.exhibitions_count ?? comment.exhibitions_count ?? 0);
        } catch (error) {
            reactions.value[commentId] = previousReaction;
            comment.current_user_reaction = previousReaction;
            Object.assign(comment, snapshot);
            reactionErrors.value[commentId] = extractErrorMessage(error);

            globalThis.setTimeout(() => {
                reactionErrors.value[commentId] = "";
            }, 5000);
        } finally {
            reactionLoading.value[commentId] = false;
        }
    };

    return {
        reactions,
        reactionLoading,
        reactionErrors,
        initializeReactions,
        setReaction,
    };
}
