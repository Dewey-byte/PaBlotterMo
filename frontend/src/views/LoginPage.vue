<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#1E3A8A] via-[#2563EB] to-[#15803D] px-4 relative overflow-hidden">
    <div class="absolute -top-12 -right-10 w-72 h-72 bg-white/20 rounded-full blur-3xl"></div>
    <div class="absolute -bottom-16 -left-10 w-80 h-80 bg-emerald-200/20 rounded-full blur-3xl"></div>
    <div class="bg-white/95 backdrop-blur rounded-3xl shadow-2xl w-full max-w-md p-8 relative z-10 border border-white/60">
      <div class="flex flex-col items-center mb-8">
        <div class="bg-gradient-to-br from-[#1E3A8A] to-[#2563EB] p-4 rounded-2xl mb-4 shadow-lg">
          <Building2 class="w-12 h-12 text-white" />
        </div>
        <h1 class="text-2xl font-bold text-slate-900">Administrator Login</h1>
        <p class="text-slate-600 text-sm mt-1">PaBlotterMo Management System</p>
      </div>

      <div class="mb-6 text-center">
        <RouterLink to="/" class="text-sm text-[#1E3A8A] hover:underline font-medium">← Back to Submit Complaint</RouterLink>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-6">
        <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
          {{ error }}
        </div>

        <div>
          <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
          <div class="relative">
            <Mail class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
            <input
              id="email"
              v-model="email"
              type="email"
              class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition"
              placeholder="your.email@example.com"
              required
            />
          </div>
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
          <div class="relative">
            <Lock class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
            <input
              id="password"
              v-model="password"
              type="password"
              class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition"
              placeholder="Enter your password"
              required
            />
          </div>
        </div>

        <button
          type="submit"
          :disabled="isSubmitting"
          class="w-full bg-[#1E3A8A] text-white py-3 rounded-lg hover:bg-[#1e3a8ae6] transition-colors shadow-lg font-medium"
        >
          {{ isSubmitting ? "Signing In..." : "Sign In" }}
        </button>
      </form>

      <div class="mt-8 pt-6 border-t border-gray-200">
        <p class="text-xs text-gray-500 text-center mb-3">Demo Admin Credentials:</p>
        <div class="bg-gray-50 p-3 rounded text-xs text-gray-600">
          <strong>Email:</strong> admin@barangay.com<br />
          <strong>Password:</strong> admin123
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { useRouter, RouterLink } from "vue-router";
import { Building2, Lock, Mail } from "lucide-vue-next";
import { useAuth } from "../composables/useAuth";

const router = useRouter();
const { login } = useAuth();

const email = ref("");
const password = ref("");
const error = ref("");
const isSubmitting = ref(false);

const handleSubmit = async () => {
  if (isSubmitting.value) return;

  isSubmitting.value = true;
  error.value = "";
  try {
    const user = await login(email.value, password.value);
    if (user.role !== "admin") {
      error.value = "Only administrators can login. Residents can submit complaints directly from the home page.";
      return;
    }
    router.push("/admin");
  } catch (err) {
    error.value = err instanceof Error ? err.message : "Unable to login right now.";
  } finally {
    isSubmitting.value = false;
  }
};
</script>
