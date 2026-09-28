import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

const readSource = (path) => readFileSync(resolve(process.cwd(), path), "utf8");

describe("authenticated user monthly statistics", () => {
    it("uses the same statistics component on profile and timeline", () => {
        const profile = readSource("resources/js/views/home/profile.vue");
        const timeline = readSource("resources/js/views/home/profileTimeline.vue");

        expect(profile).toContain("<MonthlyStatsCard");
        expect(timeline).toContain("<MonthlyStatsCard");
        expect(profile).toContain("res?.monthly_statistics");
        expect(timeline).toContain("payload.monthly_statistics");
    });

    it("renders points, rank, month, and the unranked fallback", () => {
        const component = readSource("resources/js/components/profile/MonthlyStatsCard.vue");

        expect(component).toContain("stats.monthly_points");
        expect(component).toContain("stats.monthly_rank == null");
        expect(component).toContain("stats.month ||");
        expect(component).toContain("`#${props.stats.monthly_rank}`");
    });
});
