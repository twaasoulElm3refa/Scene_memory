// @vitest-environment jsdom

import { beforeEach, describe, expect, it, vi } from "vitest";
import { clearIdempotencyKey, getOrCreateIdempotencyKey } from "../../resources/js/services/PaymentService/checkoutSession";
import { pollOrderStatus } from "../../resources/js/services/PaymentService/pollOrderStatus";

describe("payment idempotency session", () => {
  beforeEach(() => sessionStorage.clear());

  it("reuses one UUID for double-clicks and rotates when the cart changes", () => {
    const first = getOrCreateIdempotencyKey("cart:paypal", "1-2");
    const doubleClick = getOrCreateIdempotencyKey("cart:paypal", "1-2");
    const changedCart = getOrCreateIdempotencyKey("cart:paypal", "1-2-3");

    expect(doubleClick).toBe(first);
    expect(changedCart).not.toBe(first);
  });

  it("clears a completed checkout key", () => {
    const first = getOrCreateIdempotencyKey("cart:wallet", "1");
    clearIdempotencyKey("cart:wallet");
    expect(getOrCreateIdempotencyKey("cart:wallet", "1")).not.toBe(first);
  });
});

describe("sequential order polling", () => {
  it("treats only completed as success", async () => {
    const fetchStatus = vi.fn().mockResolvedValueOnce("approved").mockResolvedValueOnce("completed");
    await expect(pollOrderStatus({ fetchStatus, delay: 1, maxAttempts: 3 }))
      .resolves.toEqual({ outcome: "completed" });
    expect(fetchStatus).toHaveBeenCalledTimes(2);
  });

  it("returns failed for a definitive failure", async () => {
    await expect(pollOrderStatus({ fetchStatus: async () => "failed", delay: 1 }))
      .resolves.toEqual({ outcome: "failed" });
  });

  it("does not mislabel timeout as payment failure", async () => {
    await expect(pollOrderStatus({ fetchStatus: async () => "approved", delay: 1, maxAttempts: 2 }))
      .resolves.toEqual({ outcome: "timeout" });
  });

  it("retries 429 and server errors without overlapping requests", async () => {
    let inFlight = 0;
    let maximumInFlight = 0;
    let calls = 0;
    const fetchStatus = async () => {
      inFlight += 1;
      maximumInFlight = Math.max(maximumInFlight, inFlight);
      calls += 1;
      await new Promise((resolve) => setTimeout(resolve, 1));
      inFlight -= 1;
      if (calls === 1) throw { response: { status: 429 } };
      if (calls === 2) throw { response: { status: 503 } };
      return "completed";
    };

    await expect(pollOrderStatus({ fetchStatus, delay: 1, maxAttempts: 4 }))
      .resolves.toEqual({ outcome: "completed" });
    expect(maximumInFlight).toBe(1);
  });

  it.each([401, 403, 404])("stops on terminal HTTP %s", async (statusCode) => {
    const fetchStatus = vi.fn().mockRejectedValue({ response: { status: statusCode } });
    await expect(pollOrderStatus({ fetchStatus, delay: 1 }))
      .resolves.toEqual({ outcome: "terminal_error", statusCode });
    expect(fetchStatus).toHaveBeenCalledOnce();
  });

  it("aborts cleanly when the component unmounts", async () => {
    const controller = new AbortController();
    const polling = pollOrderStatus({
      signal: controller.signal,
      fetchStatus: async () => "approved",
      delay: 1000,
      maxAttempts: 30,
    });
    controller.abort();
    await expect(polling).resolves.toEqual({ outcome: "aborted" });
  });
});
