<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-950 to-slate-900 py-16 px-4">
    <!-- Header -->
    <div class="text-center mb-14">
      <h1 class="text-4xl font-bold text-white mb-3 tracking-tight">Choose Your Plan</h1>
      <p class="text-slate-400 text-lg">Simple, transparent pricing for everyone</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center items-center py-20">
      <div class="w-12 h-12 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="max-w-md mx-auto text-center py-16">
      <div class="text-red-400 text-5xl mb-4">⚠️</div>
      <p class="text-red-400 text-lg">{{ error }}</p>
      <button
        @click="fetchPlans"
        class="mt-6 px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition"
      >
        Try Again
      </button>
    </div>

    <!-- Plans Grid -->
    <div v-else class="max-w-6xl mx-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <div
        v-for="(plan, index) in plans"
        :key="plan.id"
        :class="[
          'relative rounded-2xl p-6 flex flex-col transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl cursor-pointer border',
          index === 2
            ? 'bg-purple-600 border-purple-400 shadow-purple-500/40 shadow-xl scale-105'
            : 'bg-slate-800/60 border-slate-700 hover:border-purple-500 backdrop-blur-sm'
        ]"
      >
        <!-- Popular Badge -->
        <div
          v-if="index === 2"
          class="absolute -top-4 left-1/2 -translate-x-1/2 bg-yellow-400 text-slate-900 text-xs font-bold px-4 py-1 rounded-full uppercase tracking-widest"
        >
          Most Popular
        </div>

        <!-- Plan Icon -->
        <div
          :class="[
            'w-12 h-12 rounded-xl flex items-center justify-center text-2xl mb-5',
            index === 2 ? 'bg-white/20' : 'bg-purple-500/20'
          ]"
        >
          {{ planIcons[index] }}
        </div>

        <!-- Name -->
        <h2
          :class="[
            'text-xl font-bold mb-1 capitalize',
            index === 2 ? 'text-white' : 'text-slate-100'
          ]"
        >
          {{ plan.translation?.name || plan.name }}
        </h2>

        <!-- Price -->
        <div class="mt-3 mb-6">
          <span
            :class="[
              'text-4xl font-extrabold',
              index === 2 ? 'text-white' : 'text-purple-400'
            ]"
          >
            {{ plan.price === '0.00' ? 'Free' : '$' + parseFloat(plan.price).toFixed(0) }}
          </span>
          <span
            v-if="plan.price !== '0.00'"
            :class="['text-sm ml-1', index === 2 ? 'text-purple-200' : 'text-slate-400']"
          >
            / mo
          </span>
        </div>

        <!-- Divider -->
        <div :class="['border-t mb-5', index === 2 ? 'border-white/20' : 'border-slate-700']"></div>

        <!-- Advantages or placeholder -->
        <ul class="flex-1 space-y-2 mb-6">
          <li
            v-if="plan.advantges?.length === 0"
            :class="['text-sm italic', index === 2 ? 'text-purple-200' : 'text-slate-500']"
          >
            Basic access included
          </li>
          <li
            v-for="adv in plan.advantges"
            :key="adv.id"
            :class="['flex items-center gap-2 text-sm', index === 2 ? 'text-white' : 'text-slate-300']"
          >
            <span class="text-green-400">✓</span> {{ adv.name }}
          </li>
        </ul>

        <!-- CTA Button -->
        <button
          :class="[
            'w-full py-3 rounded-xl font-semibold text-sm transition-all duration-200',
            index === 2
              ? 'bg-white text-purple-700 hover:bg-purple-50 shadow-lg'
              : 'bg-purple-600/80 hover:bg-purple-600 text-white border border-purple-500'
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

const planIcons = ['🌱', '⚡', '🚀', '👑'];

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
