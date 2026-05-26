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
          class="w-full bg-[#1E3A8A] text-white py-3 rounded-lg hover:bg-[#1e3a8ae6] transition-colors shadow-lg font-medium disabled:opacity-60"
        >
          {{ isSubmitting ? "Signing In..." : "Sign In" }}
        </button>
      </form>

      <div class="mt-4 text-center">
        <button
          type="button"
          @click="openForgotPasswordModal"
          class="text-sm text-[#1E3A8A] hover:underline font-medium"
        >
          Forgot Password?
        </button>
      </div>
    </div>

    <Teleport to="body">
      <Transition name="forgot-modal">
        <div
          v-if="showForgotPassword"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
          role="dialog"
          aria-modal="true"
          aria-labelledby="forgot-password-title"
        >
          <button
            type="button"
            class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
            aria-label="Close password reset"
            @click="closeForgotPasswordModal"
          />

          <div
            class="forgot-modal-panel relative w-full max-w-lg max-h-[min(92vh,720px)] overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/10 flex flex-col"
            @click.stop
          >
            <div class="relative shrink-0 overflow-hidden bg-gradient-to-br from-[#1E3A8A] via-[#2563EB] to-[#15803D] px-6 pt-6 pb-8 text-white">
              <div class="absolute -right-6 -top-6 h-28 w-28 rounded-full bg-white/10 blur-2xl"></div>
              <div class="absolute -bottom-10 left-1/3 h-24 w-24 rounded-full bg-emerald-300/20 blur-2xl"></div>

              <button
                type="button"
                class="absolute right-4 top-4 rounded-full p-2 text-white/80 transition hover:bg-white/15 hover:text-white"
                aria-label="Close"
                @click="closeForgotPasswordModal"
              >
                <X class="h-5 w-5" />
              </button>

              <div class="relative flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur">
                  <KeyRound class="h-6 w-6" />
                </div>
                <div>
                  <h2 id="forgot-password-title" class="text-xl font-bold tracking-tight">Reset password</h2>
                  <p class="mt-1 text-sm text-blue-100">Verify your admin email with a one-time code</p>
                </div>
              </div>

              <div class="relative mt-6 flex items-center gap-2">
                <div
                  class="flex flex-1 items-center gap-2 rounded-xl px-3 py-2 text-xs font-medium transition"
                  :class="forgotPhase === 'send' ? 'bg-white/20 text-white' : 'bg-white/10 text-blue-100'"
                >
                  <span
                    class="flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-bold"
                    :class="forgotPhase === 'send' ? 'bg-white text-[#1E3A8A]' : 'bg-white/20'"
                  >1</span>
                  Send OTP
                </div>
                <ChevronRight class="h-4 w-4 shrink-0 text-white/50" />
                <div
                  class="flex flex-1 items-center gap-2 rounded-xl px-3 py-2 text-xs font-medium transition"
                  :class="forgotPhase === 'reset' ? 'bg-white/20 text-white' : 'bg-white/10 text-blue-100'"
                >
                  <span
                    class="flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-bold"
                    :class="forgotPhase === 'reset' ? 'bg-white text-[#1E3A8A]' : otpSent ? 'bg-emerald-400/90 text-white' : 'bg-white/20'"
                  >2</span>
                  New password
                </div>
              </div>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
              <div v-if="forgotError" class="flex gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <CircleAlert class="h-5 w-5 shrink-0 text-red-500" />
                <p>{{ forgotError }}</p>
              </div>
              <div v-if="forgotMessage" class="flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                <CircleCheck class="h-5 w-5 shrink-0 text-emerald-500" />
                <p>{{ forgotMessage }}</p>
              </div>
              <div v-if="devOtp" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Dev OTP: {{ devOtp }}</p>
                <p class="mt-1 text-xs text-amber-700">Shown only in local/debug mode.</p>
              </div>

              <section class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 space-y-4">
                <div class="flex items-center gap-2">
                  <Mail class="h-4 w-4 text-[#1E3A8A]" />
                  <h3 class="text-sm font-semibold text-slate-900">Admin email</h3>
                </div>
                <div class="relative">
                  <input
                    id="forgotEmail"
                    v-model="forgotEmail"
                    type="email"
                    autocomplete="email"
                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-[#1E3A8A] focus:ring-2 focus:ring-[#1E3A8A]/20"
                    placeholder="admin@example.com"
                  />
                </div>
                <button
                  type="button"
                  :disabled="forgotLoading"
                  class="flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-900/20 transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                  @click="requestOtp"
                >
                  <Loader2 v-if="forgotLoading && forgotPhase === 'send'" class="h-4 w-4 animate-spin" />
                  <Send v-else class="h-4 w-4" />
                  {{ forgotLoading && forgotPhase === "send" ? "Sending..." : "Send OTP to email" }}
                </button>
              </section>

              <section
                class="rounded-2xl border p-4 space-y-4 transition"
                :class="otpSent ? 'border-[#1E3A8A]/30 bg-blue-50/40' : 'border-slate-200 bg-white opacity-90'"
              >
                <div class="flex items-center justify-between gap-2">
                  <div class="flex items-center gap-2">
                    <ShieldCheck class="h-4 w-4 text-[#15803D]" />
                    <h3 class="text-sm font-semibold text-slate-900">Verify & set password</h3>
                  </div>
                  <span v-if="!otpSent" class="text-[10px] font-medium uppercase tracking-wide text-slate-400">After OTP sent</span>
                </div>

                <div>
                  <label for="otpCode" class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-500">
                    6-digit code
                  </label>
                  <input
                    id="otpCode"
                    v-model="otpCode"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-center font-mono text-2xl font-semibold tracking-[0.45em] text-slate-900 shadow-sm outline-none transition focus:border-[#1E3A8A] focus:ring-2 focus:ring-[#1E3A8A]/20"
                    placeholder="000000"
                    @input="onOtpInput"
                  />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                  <div>
                    <label for="newPassword" class="mb-1.5 block text-xs font-medium text-slate-600">New password</label>
                    <div class="relative">
                      <input
                        id="newPassword"
                        v-model="newPassword"
                        :type="showNewPassword ? 'text' : 'password'"
                        autocomplete="new-password"
                        class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-3 pr-10 text-sm outline-none focus:border-[#1E3A8A] focus:ring-2 focus:ring-[#1E3A8A]/20"
                      />
                      <button
                        type="button"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                        :aria-label="showNewPassword ? 'Hide password' : 'Show password'"
                        @click="showNewPassword = !showNewPassword"
                      >
                        <EyeOff v-if="showNewPassword" class="h-4 w-4" />
                        <Eye v-else class="h-4 w-4" />
                      </button>
                    </div>
                  </div>
                  <div>
                    <label for="confirmPassword" class="mb-1.5 block text-xs font-medium text-slate-600">Confirm</label>
                    <div class="relative">
                      <input
                        id="confirmPassword"
                        v-model="newPasswordConfirmation"
                        :type="showConfirmPassword ? 'text' : 'password'"
                        autocomplete="new-password"
                        class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-3 pr-10 text-sm outline-none focus:border-[#1E3A8A] focus:ring-2 focus:ring-[#1E3A8A]/20"
                      />
                      <button
                        type="button"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                        :aria-label="showConfirmPassword ? 'Hide password' : 'Show password'"
                        @click="showConfirmPassword = !showConfirmPassword"
                      >
                        <EyeOff v-if="showConfirmPassword" class="h-4 w-4" />
                        <Eye v-else class="h-4 w-4" />
                      </button>
                    </div>
                  </div>
                </div>

                <button
                  type="button"
                  :disabled="forgotLoading"
                  class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#15803D] to-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-600/25 transition hover:from-[#166534] hover:to-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                  @click="resetPasswordWithOtp"
                >
                  <Loader2 v-if="forgotLoading && forgotPhase === 'reset'" class="h-4 w-4 animate-spin" />
                  <Check v-else class="h-4 w-4" />
                  {{ forgotLoading && forgotPhase === "reset" ? "Updating..." : "Reset password" }}
                </button>
              </section>
            </div>

            <div class="shrink-0 border-t border-slate-100 bg-slate-50/80 px-6 py-4">
              <button
                type="button"
                class="w-full rounded-xl py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-200/60 hover:text-slate-900"
                @click="closeForgotPasswordModal"
              >
                Back to sign in
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from "vue";
import { useRouter, useRoute, RouterLink } from "vue-router";
import {
  Building2,
  Lock,
  Mail,
  Eye,
  EyeOff,
  X,
  KeyRound,
  ChevronRight,
  Send,
  ShieldCheck,
  CircleAlert,
  CircleCheck,
  Loader2,
  Check,
} from "lucide-vue-next";
import { useAuth } from "../composables/useAuth";
import { apiRequest } from "../lib/api";

const router = useRouter();
const route = useRoute();
const { login, logout } = useAuth();

const email = ref("");
const password = ref("");
const error = ref("");
const isSubmitting = ref(false);
const showForgotPassword = ref(false);
const forgotEmail = ref("");
const forgotError = ref("");
const forgotMessage = ref("");
const forgotLoading = ref(false);
const forgotPhase = ref<"send" | "reset">("send");
const otpSent = ref(false);
const otpCode = ref("");
const newPassword = ref("");
const newPasswordConfirmation = ref("");
const showPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);
const devOtp = ref("");

const onEscapeKey = (event: KeyboardEvent) => {
  if (event.key === "Escape" && showForgotPassword.value) {
    closeForgotPasswordModal();
  }
};

onMounted(() => {
  document.addEventListener("keydown", onEscapeKey);
});

onUnmounted(() => {
  document.removeEventListener("keydown", onEscapeKey);
});

watch(showForgotPassword, (open) => {
  document.body.style.overflow = open ? "hidden" : "";
});

const onOtpInput = (event: Event) => {
  const target = event.target as HTMLInputElement;
  otpCode.value = target.value.replace(/\D/g, "").slice(0, 6);
};

const handleSubmit = async () => {
  if (isSubmitting.value) return;

  isSubmitting.value = true;
  error.value = "";
  try {
    const user = await login(email.value, password.value);
    if (user.role !== "admin") {
      error.value = "Only administrators can login. Residents can submit complaints directly from the home page.";
      await logout();
      return;
    }
    const redirect = route.query.redirect;
    const nextPath =
      typeof redirect === "string" && redirect.startsWith("/") && !redirect.startsWith("//") ? redirect : "/admin";
    router.push(nextPath);
  } catch (err) {
    error.value = err instanceof Error ? err.message : "Unable to login right now.";
  } finally {
    isSubmitting.value = false;
  }
};

const openForgotPasswordModal = () => {
  forgotError.value = "";
  forgotMessage.value = "";
  devOtp.value = "";
  if (!forgotEmail.value) {
    forgotEmail.value = email.value;
  }
  showForgotPassword.value = true;
};

const closeForgotPasswordModal = () => {
  showForgotPassword.value = false;
  forgotPhase.value = "send";
};

const requestOtp = async () => {
  if (!forgotEmail.value) {
    forgotError.value = "Please enter your admin email first.";
    return;
  }

  forgotLoading.value = true;
  forgotPhase.value = "send";
  forgotError.value = "";
  forgotMessage.value = "";
  devOtp.value = "";

  try {
    const response = await apiRequest<{ message: string; otp?: string }>("/admin/password/forgot/request-otp", {
      method: "POST",
      body: JSON.stringify({ email: forgotEmail.value }),
    });
    otpSent.value = true;
    forgotPhase.value = "reset";
    forgotMessage.value = response.message;
    if (response.otp) {
      devOtp.value = response.otp;
      otpCode.value = response.otp;
    }
  } catch (err) {
    const message = err instanceof Error ? err.message : "Failed to send OTP.";
    forgotError.value = message;
    if (message.toLowerCase().includes("wait before requesting")) {
      otpSent.value = true;
      forgotPhase.value = "reset";
      forgotMessage.value =
        "If you already received an OTP, enter it below. Otherwise wait about a minute and send again.";
    }
  } finally {
    forgotLoading.value = false;
  }
};

const resetPasswordWithOtp = async () => {
  if (!forgotEmail.value || !otpCode.value || !newPassword.value || !newPasswordConfirmation.value) {
    forgotError.value = "Please complete all fields.";
    forgotPhase.value = "reset";
    return;
  }

  forgotLoading.value = true;
  forgotPhase.value = "reset";
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
    otpSent.value = false;
    forgotPhase.value = "send";
    setTimeout(() => {
      closeForgotPasswordModal();
    }, 1800);
  } catch (err) {
    forgotError.value = err instanceof Error ? err.message : "Failed to reset password.";
  } finally {
    forgotLoading.value = false;
  }
};
</script>

<style scoped>
.forgot-modal-enter-active,
.forgot-modal-leave-active {
  transition: opacity 0.2s ease;
}

.forgot-modal-enter-active .forgot-modal-panel,
.forgot-modal-leave-active .forgot-modal-panel {
  transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease;
}

.forgot-modal-enter-from,
.forgot-modal-leave-to {
  opacity: 0;
}

.forgot-modal-enter-from .forgot-modal-panel,
.forgot-modal-leave-to .forgot-modal-panel {
  opacity: 0;
  transform: scale(0.96) translateY(8px);
}
</style>
