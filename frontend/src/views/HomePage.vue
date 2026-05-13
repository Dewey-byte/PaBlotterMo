<template>
  <div class="min-h-screen bg-slate-50">
    <header class="bg-white/90 backdrop-blur shadow-sm border-b border-slate-200 sticky top-0 z-20">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center">
        <div class="flex items-center space-x-3">
          <div class="bg-gradient-to-br from-[#1E3A8A] to-[#2563EB] p-2.5 rounded-xl shadow-lg">
            <Building2 class="w-8 h-8 text-white" />
          </div>
          <div>
            <h1 class="text-xl font-bold text-slate-900">PaBlotterMo</h1>
            <p class="text-xs text-slate-500">Complaint Management System</p>
          </div>
        </div>
      </div>
    </header>

    <section class="relative overflow-hidden bg-gradient-to-br from-[#1E3A8A] via-[#2563EB] to-[#15803D] text-white py-20 px-4">
      <div class="absolute -top-16 -right-8 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-24 -left-10 w-72 h-72 bg-emerald-300/20 rounded-full blur-3xl"></div>
      <div class="max-w-4xl mx-auto text-center relative">
        <p class="inline-flex items-center gap-2 bg-white/15 border border-white/20 rounded-full px-4 py-1.5 text-sm mb-5">
          <BadgeCheck class="w-4 h-4" />
          Trusted community response platform
        </p>
        <h2 class="text-4xl md:text-5xl font-extrabold mb-6 tracking-tight">Your Voice Matters</h2>
        <p class="text-xl md:text-2xl text-blue-100 mb-8 max-w-3xl mx-auto">
          Submit complaints and concerns directly to your Barangay office. We're here to serve and protect our community.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
          <button
            @click="router.push('/submit-complaint')"
            class="bg-white text-[#1E3A8A] px-8 py-4 rounded-lg font-bold text-lg hover:bg-gray-100 transition shadow-xl inline-flex items-center justify-center space-x-2"
          >
            <span>Submit a Complaint</span>
            <ArrowRight class="w-5 h-5" />
          </button>
          <button
            @click="router.push('/track-complaint')"
            class="bg-white/10 border-2 border-white/50 text-white px-8 py-4 rounded-lg font-bold text-lg hover:bg-white hover:text-[#1E3A8A] transition shadow-xl inline-flex items-center justify-center space-x-2"
          >
            <Search class="w-5 h-5" />
            <span>Track Complaint</span>
          </button>
        </div>

        <form @submit.prevent="goToTracking" class="mt-8 max-w-2xl mx-auto">
          <div class="bg-white/10 backdrop-blur border border-white/20 rounded-2xl p-3 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-blue-100" />
              <input
                v-model="heroTrackingNumber"
                type="text"
                placeholder="Have a tracking number? e.g. BCM-12345678"
                class="w-full pl-10 pr-3 py-3 rounded-xl bg-white/95 text-slate-900 placeholder:text-slate-500 outline-none"
              />
            </div>
            <button
              type="submit"
              class="px-5 py-3 rounded-xl bg-[#0F172A] text-white font-semibold hover:bg-black transition"
            >
              Check Status
            </button>
          </div>
        </form>
      </div>
    </section>

    <section class="py-16 px-4">
      <div class="max-w-6xl mx-auto">
        <h3 class="text-3xl font-bold text-center text-slate-900 mb-12">How It Works</h3>
        <div class="grid md:grid-cols-3 gap-8">
          <div class="bg-white p-8 rounded-2xl shadow-md border border-slate-100 text-center hover:shadow-xl transition">
            <div class="bg-blue-50 w-16 h-16 rounded-xl flex items-center justify-center mx-auto mb-4">
              <FileText class="w-8 h-8 text-blue-700" />
            </div>
            <h4 class="text-xl font-bold text-slate-900 mb-3">1. Submit Your Complaint</h4>
            <p class="text-slate-600">Fill out a simple form with details about your concern. No account needed.</p>
          </div>
          <div class="bg-white p-8 rounded-2xl shadow-md border border-slate-100 text-center hover:shadow-xl transition">
            <div class="bg-indigo-50 w-16 h-16 rounded-xl flex items-center justify-center mx-auto mb-4">
              <ScanSearch class="w-8 h-8 text-indigo-700" />
            </div>
            <h4 class="text-xl font-bold text-slate-900 mb-3">2. Get a Tracking Number</h4>
            <p class="text-slate-600">Receive a unique tracking number to monitor your complaint in real-time.</p>
          </div>
          <div class="bg-white p-8 rounded-2xl shadow-md border border-slate-100 text-center hover:shadow-xl transition">
            <div class="bg-emerald-50 w-16 h-16 rounded-xl flex items-center justify-center mx-auto mb-4">
              <ShieldCheck class="w-8 h-8 text-emerald-700" />
            </div>
            <h4 class="text-xl font-bold text-slate-900 mb-3">3. We Take Action</h4>
            <p class="text-slate-600">Our team reviews and addresses your complaint promptly and professionally.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="py-16 px-4 bg-white">
      <div class="max-w-6xl mx-auto">
        <h3 class="text-3xl font-bold text-center text-slate-900 mb-4">What Can You Report?</h3>
        <p class="text-center text-slate-600 mb-12 max-w-2xl mx-auto">
          We handle various types of complaints to ensure the safety and well-being of our community.
        </p>
        <div class="grid md:grid-cols-5 gap-4">
          <div
            v-for="category in categories"
            :key="category.label"
            :class="[category.color, 'border rounded-2xl p-6 text-center hover:shadow-lg transition']"
          >
            <component :is="category.icon" class="w-8 h-8 mx-auto mb-3 text-slate-800" />
            <p class="font-semibold text-slate-900">{{ category.label }}</p>
          </div>
        </div>
      </div>
    </section>

    <section class="py-16 px-4">
      <div class="max-w-6xl mx-auto">
        <h3 class="text-3xl font-bold text-center text-slate-900 mb-12">Why Use Our System?</h3>
        <div class="grid md:grid-cols-3 gap-8">
          <div class="flex items-start space-x-4">
            <div class="bg-blue-50 p-3 rounded-xl flex-shrink-0">
              <Shield class="w-6 h-6 text-[#1E3A8A]" />
            </div>
            <div>
              <h4 class="font-bold text-slate-900 mb-2">Secure & Confidential</h4>
              <p class="text-slate-600">Your information is handled with strict confidentiality and security.</p>
            </div>
          </div>
          <div class="flex items-start space-x-4">
            <div class="bg-emerald-50 p-3 rounded-xl flex-shrink-0">
              <Zap class="w-6 h-6 text-[#15803D]" />
            </div>
            <div>
              <h4 class="font-bold text-slate-900 mb-2">Fast Response</h4>
              <p class="text-slate-600">Our team is committed to addressing complaints promptly and efficiently.</p>
            </div>
          </div>
          <div class="flex items-start space-x-4">
            <div class="bg-indigo-50 p-3 rounded-xl flex-shrink-0">
              <Users class="w-6 h-6 text-[#1E3A8A]" />
            </div>
            <div>
              <h4 class="font-bold text-slate-900 mb-2">Community Focused</h4>
              <p class="text-slate-600">We work together to make our barangay a better place for everyone.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-16 px-4 bg-gradient-to-r from-[#1E3A8A] to-[#15803D]">
      <div class="max-w-4xl mx-auto text-center">
        <h3 class="text-3xl font-bold text-white mb-4">Ready to Make a Difference?</h3>
        <p class="text-xl text-blue-100 mb-8">Your complaint helps us improve our community. Submit or track in seconds.</p>
        <button
          @click="router.push('/submit-complaint')"
          class="bg-white text-[#1E3A8A] px-8 py-4 rounded-lg font-bold text-lg hover:bg-gray-100 transition shadow-xl inline-flex items-center space-x-2"
        >
          <span>File a Complaint Now</span>
          <ArrowRight class="w-5 h-5" />
        </button>
      </div>
    </section>

    <footer class="bg-gray-900 text-gray-300 py-8 px-4">
      <div class="max-w-6xl mx-auto text-center">
        <div class="flex items-center justify-center space-x-3 mb-4">
          <div class="bg-[#1E3A8A] p-2 rounded-lg">
            <Building2 class="w-6 h-6 text-white" />
          </div>
          <span class="font-bold text-white">Barangay Portal</span>
        </div>
        <p class="text-sm text-gray-400 mb-4">Serving our community with transparency and accountability.</p>
        <p class="text-xs text-gray-500">© 2026 Barangay Complaint Management System. All rights reserved.</p>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { useRouter } from "vue-router";
import {
  Building2,
  FileText,
  BadgeCheck,
  Shield,
  Users,
  ArrowRight,
  Search,
  ScanSearch,
  ShieldCheck,
  Zap,
  Volume2,
  Siren,
  Home,
  Landmark,
  CircleAlert,
} from "lucide-vue-next";

const router = useRouter();
const heroTrackingNumber = ref("");

const goToTracking = () => {
  const normalizedTracking = heroTrackingNumber.value.trim().toUpperCase();
  if (!normalizedTracking) {
    router.push("/track-complaint");
    return;
  }
  router.push({
    path: "/track-complaint",
    query: { tracking: normalizedTracking },
  });
};

const categories = [
  { icon: Volume2, label: "Noise Complaints", color: "bg-blue-50 border-blue-100" },
  { icon: Siren, label: "Theft & Security", color: "bg-red-50 border-red-100" },
  { icon: Home, label: "Domestic Issues", color: "bg-purple-50 border-purple-100" },
  { icon: Landmark, label: "Property Disputes", color: "bg-green-50 border-green-100" },
  { icon: CircleAlert, label: "Other Concerns", color: "bg-gray-50 border-gray-100" },
];
</script>
