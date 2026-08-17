import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("../../resources/js/services/CommentService/CommentService", () => ({
    default: {
        reactToComment: vi.fn(),
    },
    extractErrorMessage: vi.fn(() => "Reaction failed"),
}));

import CommentService from "../../resources/js/services/CommentService/CommentService";
import { useCommentReactions } from "../../resources/js/composables/useCommentReactions";

describe("useCommentReactions", () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it("switches reactions using authoritative response counts", async () => {
        const comment = {
            id: 42,
            current_user_reaction: null,
            support_count: 0,
            neutral_count: 0,
            exhibitions_count: 0,
        };
        const state = useCommentReactions();
        state.initializeReactions([comment]);

        for (const [type, counts] of [
            ["support", [1, 0, 0]],
            ["neutral", [0, 1, 0]],
            ["exhibitions", [0, 0, 1]],
            ["support", [1, 0, 0]],
        ]) {
            CommentService.reactToComment.mockResolvedValueOnce({
                data: {
                    data: {
                        current_user_reaction: type,
                        support_count: counts[0],
                        neutral_count: counts[1],
                        exhibitions_count: counts[2],
                    },
                },
            });

            await state.setReaction(comment, type);

            expect(state.reactions.value[comment.id]).toBe(type);
            expect([
                comment.support_count,
                comment.neutral_count,
                comment.exhibitions_count,
            ]).toEqual(counts);
        }

        await state.setReaction(comment, "support");
        expect(CommentService.reactToComment).toHaveBeenCalledTimes(4);
    });

    it("rolls back failed optimistic state and ignores rapid clicks while pending", async () => {
        const comment = {
            id: 9,
            current_user_reaction: "support",
            support_count: 1,
            neutral_count: 0,
            exhibitions_count: 0,
        };
        const state = useCommentReactions();
        state.initializeReactions([comment]);
        let rejectRequest;

        CommentService.reactToComment.mockImplementationOnce(() => new Promise((resolve, reject) => {
            rejectRequest = reject;
        }));

        const pending = state.setReaction(comment, "neutral");
        await state.setReaction(comment, "exhibitions");
        rejectRequest(new Error("Network error"));
        await pending;

        expect(CommentService.reactToComment).toHaveBeenCalledTimes(1);
        expect(state.reactions.value[comment.id]).toBe("support");
        expect(comment.support_count).toBe(1);
        expect(comment.neutral_count).toBe(0);
        expect(comment.exhibitions_count).toBe(0);
        expect(state.reactionErrors.value[comment.id]).toBe("Reaction failed");
    });
});
