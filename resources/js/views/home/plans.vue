<template>
  <div class="min-h-screen bg-white py-16 px-4">
    <!-- Header -->
    <div class="text-center mb-14">
      <h1 class="text-4xl font-bold text-black mb-3 tracking-tight">Choose Your Plan</h1>
      <p class="text-gray-600 text-lg">Simple, transparent pricing for everyone</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center items-center py-20">
      <div class="w-12 h-12 border-4 border-black border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="max-w-md mx-auto text-center py-16">
      <div class="text-red-600 text-5xl mb-4">⚠️</div>
      <p class="text-red-600 text-lg">{{ error }}</p>
      <button
        @click="fetchPlans"
        class="mt-6 px-6 py-2 bg-black hover:bg-gray-800 text-white rounded-lg transition"
      >
        Try Again
      </button>
    </div>

    <!-- Plans Grid -->
    <div v-else class="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-8">
      <div
        v-for="(plan, index) in plans"
        :key="plan.id"
        :class="[
          'relative rounded-3xl p-8 flex flex-col transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl cursor-pointer border',
          index === 1
            ? 'bg-black border-black shadow-xl scale-105 text-white'
            : 'bg-white border-gray-200 hover:border-gray-400'
        ]"
      >
        <!-- Popular Badge -->
        <div
          v-if="index === 1"
          class="absolute -top-4 left-1/2 -translate-x-1/2 bg-black text-white text-xs font-bold px-5 py-1.5 rounded-full uppercase tracking-widest"
        >
          Most Popular
        </div>

        <!-- Plan Icon -->
        <div
          :class="[
            'w-14 h-14 rounded-2xl flex items-center justify-center text-3xl mb-6',
            index === 1 ? 'bg-white/20 text-white' : 'bg-gray-100 text-black'
          ]"
        >
          {{ planIcons[index] }}
        </div>

        <!-- Name -->
        <h2
          :class="[
            'text-2xl font-bold mb-2',
            index === 1 ? 'text-white' : 'text-black'
          ]"
        >
          {{ plan.translation?.name || plan.name }}
        </h2>

        <!-- Price -->
        <div class="mt-2 mb-8">
          <span
            :class="[
              'text-5xl font-extrabold',
              index === 1 ? 'text-white' : 'text-black'
            ]"
          >
            {{ plan.price === '0.00' ? 'Free' : '$' + parseFloat(plan.price).toFixed(0) }}
          </span>
          <span
            v-if="plan.price !== '0.00'"
            class="text-sm ml-1 text-gray-500"
            :class="index === 1 ? 'text-white/70' : ''"
          >
            / mo
          </span>
        </div>

        <!-- Divider -->
        <div :class="['border-t mb-6', index === 1 ? 'border-white/30' : 'border-gray-200']"></div>

        <!-- Advantages -->
        <ul class="flex-1 space-y-3 mb-8">
          <li
            v-if="plan.advantges?.length === 0"
            :class="['text-sm italic', index === 1 ? 'text-white/70' : 'text-gray-500']"
          >
            Basic access included
          </li>
          <li
            v-for="adv in plan.advantges"
            :key="adv.id"
            :class="['flex items-center gap-3 text-base', index === 1 ? 'text-white' : 'text-gray-700']"
          >
            <span class="text-green-500 text-xl flex-shrink-0">✓</span>
            {{ adv.name }}
          </li>
        </ul>

        <!-- CTA Button -->
        <button
          :class="[
            'w-full py-4 rounded-2xl font-semibold text-base transition-all duration-200',
            index === 1
              ? 'bg-white text-black hover:bg-gray-100'
              : 'bg-black hover:bg-gray-900 text-white border border-black'
          ]"
        >
          {{ plan.price === '0.00' ? 'Get Started Free' : 'Get Started' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/ApiClient';

const plans = ref([]);
const loading = ref(false);
const error = ref(null);

const planIcons = ['🌱', '⚡', '🚀'];

const fetchPlans = async () => {
  loading.value = true;
  error.value = null;

  try {
    const response = await api.get('/plans/all');
    plans.value = response.data.data;
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load plans. Please try again.';
  } finally {
    loading.value = false;
  }
};

onMounted(fetchPlans);
</script>
