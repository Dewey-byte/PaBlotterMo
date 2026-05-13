<template>
  <div class="min-h-screen bg-slate-50 flex items-center justify-center px-4">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-lg border border-slate-100 p-8 text-center">
      <h1 class="text-2xl font-bold text-slate-900">Page not found</h1>
      <p class="text-slate-600 mt-2">The page you are trying to open does not exist.</p>
      <button
        type="button"
        class="mt-6 px-5 py-3 bg-[#1E3A8A] text-white rounded-lg hover:bg-[#1e3a8ae6] transition"
        @click="router.push('/')"
      >
        Go to Home
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from "vue-router";

const router = useRouter();
</script>
