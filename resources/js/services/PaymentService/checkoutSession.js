import { v4 as uuidv4 } from "uuid";

const prefix = "scemory_checkout:";

export function getOrCreateIdempotencyKey(scope, fingerprint = "default") {
  const storageKey = `${prefix}${scope}`;
  try {
    const current = JSON.parse(sessionStorage.getItem(storageKey) || "null");
    if (current?.fingerprint === fingerprint && current?.key) {
      return current.key;
    }
  } catch {
    // Replace malformed session data with a fresh UUID.
  }

  const key = uuidv4();
  sessionStorage.setItem(storageKey, JSON.stringify({ key, fingerprint }));
  return key;
}

export function clearIdempotencyKey(scope) {
  sessionStorage.removeItem(`${prefix}${scope}`);
}
