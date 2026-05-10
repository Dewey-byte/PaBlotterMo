<template>
  <div v-if="submitted" class="min-h-screen bg-gradient-to-br from-[#1E3A8A] via-[#2563EB] to-[#15803D] flex items-center justify-center px-4">
    <div class="bg-white/95 backdrop-blur rounded-3xl shadow-2xl max-w-md w-full p-8 text-center border border-white/60">
      <div class="bg-[#15803D] p-4 rounded-2xl w-16 h-16 mx-auto mb-4 flex items-center justify-center">
        <Check class="w-8 h-8 text-white" />
      </div>
      <h2 class="text-2xl font-bold text-gray-900 mb-2">Complaint Submitted!</h2>
      <p class="text-gray-600 mb-6">Your complaint has been successfully submitted to the Barangay office.</p>

      <div class="bg-blue-50 border-2 border-[#1E3A8A] rounded-lg p-4 mb-6">
        <p class="text-sm text-gray-600 mb-2">Your Tracking Number:</p>
        <p class="text-2xl font-bold text-[#1E3A8A]">{{ trackingNumber }}</p>
        <p class="text-xs text-gray-500 mt-2">Save this number to track your complaint status</p>
      </div>

      <div class="space-y-3">
        <button
          @click="handleNewComplaint"
          class="w-full bg-[#1E3A8A] text-white py-3 rounded-lg hover:bg-[#1e3a8ae6] transition font-medium shadow-lg"
        >
          Submit Another Complaint
        </button>
        <button
          @click="router.push('/')"
          class="w-full border-2 border-gray-300 text-gray-700 py-3 rounded-lg hover:bg-gray-50 transition font-medium"
        >
          Back to Home
        </button>
        <button
          @click="router.push({ path: '/track-complaint', query: { tracking: trackingNumber } })"
          class="w-full border-2 border-[#1E3A8A] text-[#1E3A8A] py-3 rounded-lg hover:bg-blue-50 transition font-medium"
        >
          Track This Complaint
        </button>
      </div>
    </div>
  </div>

  <div v-else class="min-h-screen bg-slate-50">
    <header class="bg-white/90 backdrop-blur shadow-sm border-b border-slate-200">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
        <button @click="router.push('/')" class="flex items-center space-x-2 text-gray-700 hover:text-[#1E3A8A] transition">
          <ArrowLeft class="w-5 h-5" />
          <span>Back to Home</span>
        </button>
        <RouterLink to="/admin/login" class="text-sm text-[#1E3A8A] hover:underline font-medium">Admin Login</RouterLink>
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden">
        <div class="bg-gradient-to-r from-[#1E3A8A] to-[#15803D] px-6 sm:px-8 py-6">
          <h1 class="text-2xl font-bold text-white">Submit New Complaint</h1>
          <p class="text-blue-100 mt-1">Provide details about your concern</p>
        </div>

        <form @submit.prevent="handleSubmit" class="px-6 sm:px-8 py-8 space-y-6">
          <div v-if="submitError" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            {{ submitError }}
          </div>

          <div>
            <label for="fullName" class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
            <div class="relative">
              <User class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
              <input
                id="fullName"
                v-model="formData.fullName"
                type="text"
                class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition"
                placeholder="Enter your full name"
                required
              />
            </div>
          </div>

          <div>
            <label for="contactNumber" class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
            <div class="relative">
              <Phone class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
              <input
                id="contactNumber"
                v-model="formData.contactNumber"
                type="tel"
                class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition"
                placeholder="09XX-XXX-XXXX"
                required
              />
            </div>
          </div>

          <div>
            <label for="category" class="block text-sm font-medium text-gray-700 mb-2">Complaint Category</label>
            <div class="relative">
              <Tag class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
              <select
                id="category"
                v-model="formData.category"
                class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition appearance-none bg-white"
                required
              >
                <option value="">Select a category</option>
                <option v-for="category in categories" :key="category" :value="category">{{ category }}</option>
              </select>
            </div>
          </div>

          <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Complaint Description</label>
            <div class="relative">
              <FileText class="absolute left-3 top-3 w-5 h-5 text-gray-400" />
              <textarea
                id="description"
                v-model="formData.description"
                rows="5"
                class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition resize-none"
                placeholder="Describe your complaint in detail..."
                required
              />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Upload Evidence (Optional)</label>
            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-[#1E3A8A] transition">
              <Upload class="w-8 h-8 text-gray-400 mx-auto mb-2" />
              <p class="text-sm text-gray-600 mb-2">{{ fileName || "Click to upload or drag and drop" }}</p>
              <p class="text-xs text-gray-500">PNG, JPG, PDF up to 10MB</p>
              <input id="file-upload" type="file" class="hidden" accept="image/*,.pdf" @change="handleFileChange" />
              <label for="file-upload" class="mt-4 inline-block px-4 py-2 bg-gray-100 text-gray-700 rounded-lg cursor-pointer hover:bg-gray-200 transition">
                Choose File
              </label>
            </div>
          </div>

          <div class="pt-4">
            <button
              type="submit"
              :disabled="isSubmitting"
              class="w-full px-6 py-3 bg-[#1E3A8A] text-white rounded-lg hover:bg-[#1e3a8ae6] transition font-medium shadow-lg"
            >
              {{ isSubmitting ? "Submitting..." : "Submit Complaint" }}
            </button>
          </div>
        </form>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import { useRouter, RouterLink } from "vue-router";
import type { ComplaintCategory } from "../data/mockData";
import { Upload, FileText, User, Phone, Tag, ArrowLeft, Check } from "lucide-vue-next";
import { apiRequest } from "../lib/api";

const router = useRouter();
const categories: ComplaintCategory[] = ["Noise", "Theft", "Domestic", "Property", "Others"];

const formData = reactive<{
  fullName: string;
  contactNumber: string;
  category: ComplaintCategory | "";
  description: string;
}>({
  fullName: "",
  contactNumber: "",
  category: "",
  description: "",
});

const fileName = ref("");
const submitted = ref(false);
const trackingNumber = ref("");
const submitError = ref("");
const isSubmitting = ref(false);

interface CreateComplaintResponse {
  message: string;
  complaint: {
    trackingNumber: string;
  };
}

const handleSubmit = async () => {
  if (isSubmitting.value) return;

  isSubmitting.value = true;
  submitError.value = "";

  try {
    const response = await apiRequest<CreateComplaintResponse>("/complaints", {
      method: "POST",
      body: JSON.stringify({
        fullName: formData.fullName,
        contactNumber: formData.contactNumber,
        category: formData.category,
        description: formData.description,
      }),
    });
    trackingNumber.value = response.complaint.trackingNumber;
    submitted.value = true;
  } catch (err) {
    submitError.value = err instanceof Error ? err.message : "Failed to submit complaint.";
  } finally {
    isSubmitting.value = false;
  }
};

const handleNewComplaint = () => {
  submitted.value = false;
  submitError.value = "";
  formData.fullName = "";
  formData.contactNumber = "";
  formData.category = "";
  formData.description = "";
  fileName.value = "";
  trackingNumber.value = "";
};

const handleFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  if (file) {
    fileName.value = file.name;
  }
};
</script>
