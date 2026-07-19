const wait = (milliseconds, signal) => new Promise((resolve, reject) => {
  if (signal?.aborted) {
    reject(new DOMException("Polling aborted", "AbortError"));
    return;
  }
  const timer = setTimeout(resolve, milliseconds);
  signal?.addEventListener("abort", () => {
    clearTimeout(timer);
    reject(new DOMException("Polling aborted", "AbortError"));
  }, { once: true });
});

export async function pollOrderStatus({
  fetchStatus,
  signal,
  maxAttempts = 30,
  delay = 5000,
}) {
  for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
    if (signal?.aborted) return { outcome: "aborted" };

    try {
      const status = await fetchStatus(signal);
      if (status === "completed") return { outcome: "completed" };
      if (["failed", "cancelled"].includes(status)) return { outcome: "failed" };
    } catch (error) {
      if (error?.name === "AbortError" || signal?.aborted) return { outcome: "aborted" };
      const statusCode = error?.response?.status;
      if ([401, 403, 404].includes(statusCode)) {
        return { outcome: "terminal_error", statusCode };
      }
      // 409/422/429/5xx and network failures are transient while PayPal settles.
    }

    if (attempt < maxAttempts) {
      try {
        await wait(delay, signal);
      } catch (error) {
        if (error?.name === "AbortError" || signal?.aborted) {
          return { outcome: "aborted" };
        }
        throw error;
      }
    }
  }

  return { outcome: "timeout" };
}
