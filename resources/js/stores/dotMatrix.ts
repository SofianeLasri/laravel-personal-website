import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useDotMatrixStore = defineStore('dotMatrix', () => {
    // Check localStorage for saved preference
    const savedPreference = localStorage.getItem('dotMatrixEnabled');
    const isEnabled = ref(savedPreference !== 'false'); // Default to true unless explicitly disabled

    const toggleEnabled = () => {
        isEnabled.value = !isEnabled.value;
        localStorage.setItem('dotMatrixEnabled', isEnabled.value.toString());
    };

    const setEnabled = (value: boolean) => {
        isEnabled.value = value;
        localStorage.setItem('dotMatrixEnabled', value.toString());
    };

    return {
        isEnabled,
        toggleEnabled,
        setEnabled,
    };
});