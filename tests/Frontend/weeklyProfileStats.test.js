import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

const readSource = (path) => readFileSync(resolve(process.cwd(), path), "utf8");

describe("authenticated user weekly statistics", () => {
    it("uses the same statistics component on profile and timeline", () => {
        const profile = readSource("resources/js/views/home/profile.vue");
        const timeline = readSource("resources/js/views/home/profileTimeline.vue");

        expect(profile).toContain("<WeeklyStatsCard");
        expect(timeline).toContain("<WeeklyStatsCard");
        expect(profile).toContain("res?.weekly_statistics");
        expect(timeline).toContain("payload.weekly_statistics");
    });

    it("renders points, rank, weekly dates, and the unranked fallback", () => {
        const component = readSource("resources/js/components/profile/WeeklyStatsCard.vue");

        expect(component).toContain("stats.weekly_points");
        expect(component).toContain("stats.weekly_rank == null");
        expect(component).toContain("stats.week_start ||");
        expect(component).toContain("`#${props.stats.weekly_rank}`");
    });
});
