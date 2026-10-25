// @vitest-environment jsdom

import { beforeEach, describe, expect, it, vi } from 'vitest';

const { get, auth } = vi.hoisted(() => ({ get: vi.fn(), auth: { token: 'buyer-one' } }));
vi.mock('../../resources/js/services/ApiClient', () => ({ default: { get } }));
vi.mock('../../resources/js/services/authTokenStorage', () => ({ getAuthToken: () => auth.token }));

import { accountState, clearAccountState, refreshAccount } from '../../resources/js/services/accountState';

const response = (id, amount) => ({ data: { data: { user: { id, wallet: { amount } } } } });

describe('account balance state', () => {
  beforeEach(() => {
    clearAccountState();
    get.mockReset();
    auth.token = 'buyer-one';
  });

  it('shares one fresh profile request and balance across consumers', async () => {
    get.mockResolvedValue(response(1, '25.34'));
    await Promise.all([refreshAccount(), refreshAccount()]);
    expect(get).toHaveBeenCalledTimes(1);
    expect(accountState.user.wallet.amount).toBe('25.34');
  });

  it('ignores an older response after confirmed payment starts a forced refresh', async () => {
    let resolveOld;
    get.mockImplementationOnce(() => new Promise((resolve) => { resolveOld = resolve; }));
    get.mockResolvedValueOnce(response(1, '30.00'));
    const old = refreshAccount();
    await refreshAccount({ force: true });
    resolveOld(response(1, '10.00'));
    await old;
    expect(accountState.user.wallet.amount).toBe('30.00');
  });

  it('drops financial data on logout or account change', async () => {
    get.mockResolvedValue(response(1, '30.00'));
    await refreshAccount();
    clearAccountState();
    expect(accountState.user).toBeNull();

    let resolveOther;
    get.mockImplementationOnce(() => new Promise((resolve) => { resolveOther = resolve; }));
    const pending = refreshAccount();
    auth.token = 'buyer-two';
    resolveOther(response(1, '30.00'));
    await pending;
    expect(accountState.user).toBeNull();
  });
});
