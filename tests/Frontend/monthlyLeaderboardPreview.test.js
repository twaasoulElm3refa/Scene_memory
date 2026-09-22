import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

const homeSource = readFileSync(
    resolve(process.cwd(), "resources/js/views/home/home.vue"),
    "utf8"
);
const componentSource = readFileSync(
    resolve(process.cwd(), "resources/js/views/home/components/MonthlyLeaderboardPreview.vue"),
    "utf8"
);
const serviceSource = readFileSync(
    resolve(process.cwd(), "resources/js/services/leaderboardService.js"),
    "utf8"
);

describe("monthly leaderboard preview", () => {
    it("sits between special coverage and the Scemory experience", () => {
        const specialCoverage = homeSource.indexOf("<SpecialCoverageSection />");
        const leaderboard = homeSource.indexOf("<MonthlyLeaderboardPreview />");
        const experience = homeSource.indexOf("<ScemoryExperienceTabs />");

        expect(specialCoverage).toBeGreaterThanOrEqual(0);
        expect(leaderboard).toBeGreaterThan(specialCoverage);
        expect(experience).toBeGreaterThan(leaderboard);
    });

    it("loads API data and provides loading, empty, and error states", () => {
        expect(serviceSource).toContain('baseURL: "/api"');
        expect(serviceSource).toContain('"/leaderboard/monthly-preview"');
        expect(componentSource).toContain("getMonthlyLeaderboardPreview");
        expect(componentSource).toContain('v-if="loading"');
        expect(componentSource).toContain('v-else-if="error"');
        expect(componentSource).toContain('v-else-if="users.length === 0"');
    });

    it("shows only five rows on mobile and links to the full leaderboard", () => {
        expect(componentSource).toMatch(/nth-child\(n \+ 6\)[^{]*\{[^}]*display:\s*none/s);
        expect(componentSource).toContain('href="/leaderboard"');
        expect(componentSource).toContain("View Full Leaderboard");
        expect(componentSource).toContain("View All");
    });
});
