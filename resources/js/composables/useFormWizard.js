import { nextTick, ref } from "vue";

export function useFormWizard({ totalPhases = 3, afterPhaseChange = null } = {}) {
    const currentPhase = ref(1);

    const goToPhase = async (phase) => {
        const nextPhase = Math.max(1, Math.min(totalPhases, Number(phase) || 1));

        currentPhase.value = nextPhase;
        await nextTick();

        if (typeof afterPhaseChange === "function") {
            afterPhaseChange(nextPhase);
        }
    };

    const nextPhase = () => goToPhase(currentPhase.value + 1);
    const previousPhase = () => goToPhase(currentPhase.value - 1);

    return {
        currentPhase,
        goToPhase,
        nextPhase,
        previousPhase,
    };
}
