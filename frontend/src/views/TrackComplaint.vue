<template>
  <div class="min-h-screen bg-slate-50">
    <header class="bg-white/90 backdrop-blur shadow-sm border-b border-slate-200">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <button
          @click="router.push('/')"
          class="flex items-center space-x-2 text-slate-700 hover:text-[#1E3A8A] transition"
        >
          <ArrowLeft class="w-5 h-5" />
          <span>Back to Home</span>
        </button>
      </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-8">
        <h1 class="text-2xl font-bold text-slate-900 mb-2 flex items-center gap-2">
          <ScanSearch class="w-6 h-6 text-[#1E3A8A]" />
          Track Complaint
        </h1>
        <p class="text-slate-600 mb-6">Enter your tracking number to check the latest complaint status.</p>

        <form @submit.prevent="handleTrack" class="space-y-4">
          <label for="trackingNumber" class="block text-sm font-medium text-slate-700">Tracking Number</label>
          <div class="flex gap-3">
            <input
              id="trackingNumber"
              v-model="trackingNumber"
              type="text"
              placeholder="BCM-12345678"
              class="flex-1 px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none"
              required
            />
            <button
              type="submit"
              :disabled="loading"
              class="px-6 py-3 bg-[#1E3A8A] text-white rounded-lg hover:bg-[#1e3a8ae6] transition font-medium disabled:opacity-60"
            >
              {{ loading ? "Checking..." : "Track" }}
            </button>
          </div>
        </form>

        <div v-if="error" class="mt-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
          {{ error }}
        </div>

        <div v-if="complaint" class="mt-8 border border-slate-200 rounded-xl p-6 bg-slate-50">
          <div class="flex items-center justify-between mb-4">
            <p class="text-sm text-slate-600">Tracking Number</p>
            <StatusBadge :status="complaint.status" />
          </div>
          <p class="text-xl font-bold text-[#1E3A8A] mb-4">{{ complaint.trackingNumber }}</p>

          <div class="grid sm:grid-cols-2 gap-4 text-sm">
            <div>
              <p class="text-slate-500">Resident Name</p>
              <p class="font-medium text-slate-900">{{ complaint.residentName }}</p>
            </div>
            <div>
              <p class="text-slate-500">Contact Number</p>
              <p class="font-medium text-slate-900">{{ complaint.contactNumber }}</p>
            </div>
            <div>
              <p class="text-slate-500">Category</p>
              <p class="font-medium text-slate-900">{{ complaint.category }}</p>
            </div>
            <div>
              <p class="text-slate-500">Date Submitted</p>
              <p class="font-medium text-slate-900">{{ formatDate(complaint.dateSubmitted) }}</p>
            </div>
          </div>

          <div class="mt-4">
            <p class="text-slate-500 text-sm">Description</p>
            <p class="text-slate-900 mt-1">{{ complaint.description }}</p>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { ArrowLeft, ScanSearch } from "lucide-vue-next";
import StatusBadge from "../components/StatusBadge.vue";
import type { Complaint } from "../data/mockData";
import { apiRequest } from "../lib/api";

const router = useRouter();
const route = useRoute();
const trackingNumber = ref("");
const complaint = ref<Complaint | null>(null);
const loading = ref(false);
const error = ref("");

const handleTrack = async () => {
  loading.value = true;
  error.value = "";
  complaint.value = null;

  try {
    const normalizedTracking = trackingNumber.value.trim().toUpperCase();
    const response = await apiRequest<Complaint>(`/complaints/track/${encodeURIComponent(normalizedTracking)}`);
    complaint.value = response;
    trackingNumber.value = normalizedTracking;
  } catch (err) {
    error.value = err instanceof Error ? err.message : "Unable to fetch complaint.";
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  const queryTracking = route.query.tracking;
  if (typeof queryTracking === "string" && queryTracking.trim().length > 0) {
    trackingNumber.value = queryTracking.trim().toUpperCase();
    void handleTrack();
  }
});

const formatDate = (date: string) =>
  new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
</script>
