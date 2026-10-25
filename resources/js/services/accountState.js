import { reactive } from 'vue';
import api from './ApiClient';
import { getAuthToken } from './authTokenStorage';

export const accountState = reactive({
  user: null,
  weeklyStatistics: null,
});

let requestVersion = 0;
let inFlight = null;

export function clearAccountState() {
  requestVersion += 1;
  inFlight = null;
  accountState.user = null;
  accountState.weeklyStatistics = null;
}

export async function refreshAccount({ force = false } = {}) {
  const token = getAuthToken();
  if (!token) {
    clearAccountState();
    return null;
  }
  if (inFlight && !force) return inFlight;

  const version = ++requestVersion;
  const pending = api.get('/users/profile', { suppressGlobalErrorToast: true }).then((response) => {
    if (version !== requestVersion || token !== getAuthToken()) return null;
    const data = response.data?.data;
    if (!data?.user) return null;
    accountState.user = data.user;
    accountState.weeklyStatistics = data.weekly_statistics || null;
    return data;
  });
  inFlight = pending;
  try {
    return await pending;
  } finally {
    if (inFlight === pending) inFlight = null;
  }
}
