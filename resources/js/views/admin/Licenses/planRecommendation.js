// Helpers for the Create Plan optimizer panel. The API is authoritative: these
// only copy its numbers onto the form and turn them into display strings.

const STATUS_KEYS = {
    target_met: 'statusMet',
    target_not_met: 'statusNotMet',
    no_safe_quota: 'statusNoQuota',
    unconfigured: 'statusUnconfigured',
    search_limit: 'statusSearchLimit',
    invalid_price: 'statusInvalid',
};

const OPTION_TITLE_KEYS = {
    recommended: 'optionRecommended',
    closest_safe: 'optionClosestSafe',
    max_value: 'optionMaxValue',
    max_profit: 'optionMaxProfit',
    balanced: 'optionBalanced',
    price_alternative: 'optionPriceAlternative',
};

export const eligibleTiers = (tiers) => (tiers || []).filter((tier) => tier.is_active && tier.is_plan_eligible);

/**
 * Form fields for an API option. Every eligible tier gets a row; tiers the
 * option leaves out get zero ceilings, exactly as the API validated them.
 */
export function formStateFromOption(option, tiers) {
    return {
        photo_quota: option.photo_quota,
        video_quota: option.video_quota,
        media_tier_id: option.media_tier_id,
        tier_limits: eligibleTiers(tiers).map((tier) => {
            const limit = option.tier_limits.find((item) => item.media_tier_id === tier.id);
            return {
                media_tier_id: tier.id,
                photo_maximum: limit ? limit.photo_maximum : 0,
                video_maximum: limit ? limit.video_maximum : 0,
            };
        }),
        recommendation_value_target_bps: option.recommendation_value_target_bps ?? null,
    };
}

export const primaryOption = (result) => result?.recommendation || result?.closest_safe_configuration || null;

export function displayedOptions(result) {
    const primary = primaryOption(result);
    const alternatives = result?.alternatives || [];

    return primary ? [primary, ...alternatives] : [...alternatives];
}

export const optionIdentity = (option) => [
    option.key,
    option.price_minor,
    option.photo_quota,
    option.video_quota,
    JSON.stringify(option.tier_limits),
].join(':');

export const statusKey = (result) => `planOptimizer.${STATUS_KEYS[result?.status] || 'statusInvalid'}`;

export const optionTitleKey = (option) => `planOptimizer.${OPTION_TITLE_KEYS[option.key] || 'optionRecommended'}`;

export function createFormatters(locale, currency) {
    const money = new Intl.NumberFormat(locale || 'en', { style: 'currency', currency: currency || 'USD' });
    const number = new Intl.NumberFormat(locale || 'en', { maximumFractionDigits: 2 });
    const share = new Intl.NumberFormat(locale || 'en', { style: 'percent', maximumFractionDigits: 2 });

    return {
        money: (minor) => money.format(Number(minor || 0) / 100),
        // Bare number for messages that place their own percent sign.
        percent: (bps) => number.format(Number(bps || 0) / 100),
        share: (bps) => share.format(Number(bps || 0) / 10000),
    };
}

const mediaLabel = (t, media) => t(media === 'video' ? 'planOptimizer.mediaVideo' : 'planOptimizer.mediaPhoto');

const methodLabel = (t, method) => {
    if (method === 'paypal') return t('planOptimizer.methodPaypal');
    if (method === 'wallet') return t('planOptimizer.methodWallet');

    return method || '';
};

/**
 * Profit-safe budget breakdown; every amount comes from the API proof.
 */
export function budgetRows(proof, fmt, t) {
    if (!proof) return [];

    return [
        { key: 'planPrice', value: fmt.money(proof.price_minor) },
        { key: 'paymentFee', params: { method: methodLabel(t, proof.payment_fee_method) }, value: fmt.money(-proof.payment_processing_cost_minor) },
        { key: 'tax', value: fmt.money(-proof.tax_cost_minor) },
        { key: 'operatingCost', value: fmt.money(-proof.operating_cost_minor) },
        { key: 'riskReserve', value: fmt.money(-proof.risk_reserve_minor) },
        { key: 'requiredProfit', params: { margin: fmt.percent(proof.minimum_margin_bps) }, value: fmt.money(-proof.required_profit_minor) },
        { key: 'liabilityBudget', value: fmt.money(proof.liability_budget_minor), emphasis: true },
        { key: 'valueTarget', value: fmt.money(proof.target_minor) },
        { key: 'maxSafeValue', value: fmt.money(proof.max_profit_safe_value_minor), emphasis: true },
    ];
}

/**
 * The reasoning behind the result, as i18n keys with formatted parameters.
 */
export function proofLines(result, fmt, t) {
    const proof = result?.proof;
    if (!proof) return [];
    const lines = [];
    const best = proof.best_value_credit;

    if (best) {
        lines.push({
            key: 'proofBestCredit',
            params: {
                tier: best.tier_name,
                media: mediaLabel(t, best.media_type),
                cap: fmt.money(best.payout_cap_minor),
                maxPrice: fmt.money(best.max_price_minor),
                ratio: fmt.percent(best.payout_to_value_bps),
            },
        });
    }
    if (proof.value_upper_bound_minor !== null && proof.value_upper_bound_minor !== undefined) {
        lines.push({
            key: 'proofUpperBound',
            params: {
                budget: fmt.money(Math.max(0, proof.liability_budget_minor)),
                value: fmt.money(proof.value_upper_bound_minor),
            },
        });
    }
    if (!proof.target_feasible_at_price) {
        lines.push({ key: 'proofShortfall', params: { amount: fmt.money(proof.target_shortfall_minor) } });
        if (!proof.target_feasible_at_some_price) {
            lines.push({
                key: 'proofImpossible',
                params: {
                    share: fmt.percent(proof.creator_share_ceiling_bps),
                    costs: fmt.percent(proof.variable_cost_bps),
                    margin: fmt.percent(proof.minimum_margin_bps),
                    ratio: fmt.percent(proof.asymptotic_value_ratio_bps),
                    required: fmt.percent(proof.required_payout_to_value_bps),
                },
            });
        } else if (proof.guaranteed_target_price_minor) {
            lines.push({ key: 'proofReachable', params: { price: fmt.money(proof.guaranteed_target_price_minor) } });
        }
        if (proof.standard_plan_cap_blocks_value) {
            lines.push({ key: 'proofGap', params: { price: fmt.money(proof.price_minor), target: fmt.money(proof.target_minor) } });
        }
    }
    if (proof.minimum_price_for_one_credit_minor) {
        lines.push({ key: 'proofMinimumPrice', params: { price: fmt.money(proof.minimum_price_for_one_credit_minor) } });
    }

    return lines;
}

export function optionMetrics(option, fmt) {
    return [
        { key: 'maxPayout', value: fmt.money(option.max_creator_liability_minor) },
        { key: 'worstCaseProfit', value: fmt.money(option.worst_case_profit_minor) },
        { key: 'margin', value: fmt.share(option.worst_case_margin_bps) },
        {
            key: 'potentialValue',
            value: `${fmt.money(option.potential_cart_value_minor)} (${fmt.share(option.potential_cart_value_ratio_bps)})`,
        },
        ...(option.distribution_fit_bps === null || option.distribution_fit_bps === undefined
            ? []
            : [{ key: 'distributionFit', value: fmt.share(option.distribution_fit_bps) }]),
    ];
}

export function ceilingRows(option, tiers) {
    return (option.tier_limits || []).map((limit) => ({
        media_tier_id: limit.media_tier_id,
        name: (tiers || []).find((tier) => tier.id === limit.media_tier_id)?.name || `#${limit.media_tier_id}`,
        photos: limit.photo_maximum,
        videos: limit.video_maximum,
    }));
}
