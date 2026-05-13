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
              :type="showPassword ? 'text' : 'password'"
              class="w-full pl-11 pr-12 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition"
              placeholder="Enter your password"
              required
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
              :aria-label="showPassword ? 'Hide password' : 'Show password'"
            >
              <EyeOff v-if="showPassword" class="w-5 h-5" />
              <Eye v-else class="w-5 h-5" />
            </button>
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

      <div class="mt-4 text-center">
        <button
          type="button"
          @click="toggleForgotPassword"
          class="text-sm text-[#1E3A8A] hover:underline font-medium"
        >
          {{ showForgotPassword ? "Hide Forgot Password" : "Forgot Password?" }}
        </button>
      </div>

      <div v-if="showForgotPassword" class="mt-6 border border-blue-100 bg-blue-50/60 rounded-xl p-4 space-y-4">
        <p class="text-sm font-semibold text-slate-900">Reset Admin Password</p>
        <p class="text-xs text-slate-600">
          We will send a one-time password (OTP) to this admin email address.
        </p>

        <div v-if="forgotError" class="bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded text-sm">
          {{ forgotError }}
        </div>
        <div v-if="forgotMessage" class="bg-green-50 border border-green-200 text-green-700 px-3 py-2 rounded text-sm">
          {{ forgotMessage }}
        </div>
        <div v-if="devOtp" class="bg-amber-50 border border-amber-200 text-amber-800 px-3 py-2 rounded text-sm">
          <p class="font-semibold">Testing OTP: {{ devOtp }}</p>
          <p class="text-xs mt-1">Visible only when backend is in local/debug mode.</p>
        </div>

        <div class="space-y-3">
          <div>
            <label for="forgotEmail" class="block text-sm font-medium text-gray-700 mb-1">Admin Email</label>
            <input
              id="forgotEmail"
              v-model="forgotEmail"
              type="email"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition"
              placeholder="admin@barangay.com"
            />
          </div>

          <button
            type="button"
            @click="requestOtp"
            :disabled="forgotLoading"
            class="w-full bg-slate-900 text-white py-2.5 rounded-lg hover:bg-black transition font-medium disabled:opacity-60"
          >
            {{ forgotLoading ? "Sending OTP..." : "Send OTP via Email" }}
          </button>
        </div>

        <div v-if="otpStep === 'verify'" class="pt-2 border-t border-blue-100 space-y-3">
          <div>
            <label for="otpCode" class="block text-sm font-medium text-gray-700 mb-1">OTP Code</label>
            <input
              id="otpCode"
              v-model="otpCode"
              type="text"
              maxlength="6"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition"
              placeholder="6-digit OTP"
            />
          </div>
          <div>
            <label for="newPassword" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
            <div class="relative">
              <input
                id="newPassword"
                v-model="newPassword"
                :type="showNewPassword ? 'text' : 'password'"
                class="w-full px-3 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition"
              />
              <button
                type="button"
                @click="showNewPassword = !showNewPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                :aria-label="showNewPassword ? 'Hide password' : 'Show password'"
              >
                <EyeOff v-if="showNewPassword" class="w-4 h-4" />
                <Eye v-else class="w-4 h-4" />
              </button>
            </div>
          </div>
          <div>
            <label for="confirmPassword" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
            <div class="relative">
              <input
                id="confirmPassword"
                v-model="newPasswordConfirmation"
                :type="showConfirmPassword ? 'text' : 'password'"
                class="w-full px-3 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition"
              />
              <button
                type="button"
                @click="showConfirmPassword = !showConfirmPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                :aria-label="showConfirmPassword ? 'Hide password' : 'Show password'"
              >
                <EyeOff v-if="showConfirmPassword" class="w-4 h-4" />
                <Eye v-else class="w-4 h-4" />
              </button>
            </div>
          </div>

          <button
            type="button"
            @click="resetPasswordWithOtp"
            :disabled="forgotLoading"
            class="w-full bg-[#15803D] text-white py-2.5 rounded-lg hover:bg-[#15803de6] transition font-medium disabled:opacity-60"
          >
            {{ forgotLoading ? "Resetting..." : "Reset Password" }}
          </button>
        </div>
      </div>

     
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { useRouter, RouterLink } from "vue-router";
import { Building2, Lock, Mail, Eye, EyeOff } from "lucide-vue-next";
import { useAuth } from "../composables/useAuth";
import { apiRequest } from "../lib/api";

const router = useRouter();
const { login } = useAuth();

const email = ref("");
const password = ref("");
const error = ref("");
const isSubmitting = ref(false);
const showForgotPassword = ref(false);
const forgotEmail = ref("");
const forgotError = ref("");
const forgotMessage = ref("");
const forgotLoading = ref(false);
const otpStep = ref<"request" | "verify">("request");
const otpCode = ref("");
const newPassword = ref("");
const newPasswordConfirmation = ref("");
const showPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);
const devOtp = ref("");

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

const toggleForgotPassword = () => {
  showForgotPassword.value = !showForgotPassword.value;
  forgotError.value = "";
  forgotMessage.value = "";
  devOtp.value = "";
  if (showForgotPassword.value && !forgotEmail.value) {
    forgotEmail.value = email.value;
  }
};

const requestOtp = async () => {
  if (!forgotEmail.value) {
    forgotError.value = "Please enter your admin email first.";
    return;
  }

  forgotLoading.value = true;
  forgotError.value = "";
  forgotMessage.value = "";
  devOtp.value = "";

  try {
    const response = await apiRequest<{ message: string; otp?: string }>("/admin/password/forgot/request-otp", {
      method: "POST",
      body: JSON.stringify({ email: forgotEmail.value }),
    });
    otpStep.value = "verify";
    forgotMessage.value = response.message;
    if (response.otp) {
      devOtp.value = response.otp;
      otpCode.value = response.otp;
    }
  } catch (err) {
    forgotError.value = err instanceof Error ? err.message : "Failed to send OTP.";
  } finally {
    forgotLoading.value = false;
  }
};

const resetPasswordWithOtp = async () => {
  if (!forgotEmail.value || !otpCode.value || !newPassword.value || !newPasswordConfirmation.value) {
    forgotError.value = "Please complete all fields.";
    return;
  }

  forgotLoading.value = true;
  forgotError.value = "";
  forgotMessage.value = "";

  try {
    const response = await apiRequest<{ message: string }>("/admin/password/forgot/reset", {
      method: "POST",
      body: JSON.stringify({
        email: forgotEmail.value,
        otp: otpCode.value,
        newPassword: newPassword.value,
        newPassword_confirmation: newPasswordConfirmation.value,
      }),
    });
    forgotMessage.value = response.message;
    otpCode.value = "";
    devOtp.value = "";
    newPassword.value = "";
    newPasswordConfirmation.value = "";
    otpStep.value = "request";
  } catch (err) {
    forgotError.value = err instanceof Error ? err.message : "Failed to reset password.";
  } finally {
    forgotLoading.value = false;
  }
};
</script>
