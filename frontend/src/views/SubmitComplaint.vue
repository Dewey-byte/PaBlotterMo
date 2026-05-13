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
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden">
        <div class="bg-gradient-to-r from-[#1E3A8A] to-[#15803D] px-6 sm:px-8 py-6">
          <h1 class="text-2xl font-bold text-white">Submit New Complaint</h1>
          <p class="text-blue-100 mt-1">Provide details about your concern</p>
        </div>

        <form @submit.prevent="handleSubmit" class="px-6 sm:px-8 py-8 space-y-6">
          <div class="bg-blue-50 border border-blue-200 text-blue-900 px-4 py-3 rounded-lg">
            <p class="text-sm font-semibold">Your privacy is protected.</p>
            <p class="text-sm mt-1">
              Complaints are recorded anonymously. Your contact details are only used for updates on your case.
            </p>
          </div>

          <div v-if="submitError" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            {{ submitError }}
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Preferred Contact Method</label>
            <div class="grid sm:grid-cols-2 gap-3">
              <button
                type="button"
                @click="setContactMethod('phone')"
                :disabled="!isPhoneContactAvailable"
                :class="[
                  'px-4 py-3 rounded-lg border text-left transition disabled:cursor-not-allowed',
                  formData.contactMethod === 'phone'
                    ? 'border-[#1E3A8A] bg-blue-50 text-[#1E3A8A]'
                    : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50',
                  !isPhoneContactAvailable ? 'opacity-50' : '',
                ]"
              >
                <span class="inline-flex items-center gap-2 font-medium">
                  <Phone class="w-4 h-4" />
                  {{ isPhoneContactAvailable ? "Phone Number" : "Phone Number (Unavailable)" }}
                </span>
              </button>
              <button
                type="button"
                @click="setContactMethod('email')"
                :class="[
                  'px-4 py-3 rounded-lg border text-left transition',
                  formData.contactMethod === 'email'
                    ? 'border-[#1E3A8A] bg-blue-50 text-[#1E3A8A]'
                    : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50',
                ]"
              >
                <span class="inline-flex items-center gap-2 font-medium">
                  <Mail class="w-4 h-4" />
                  Email Address
                </span>
              </button>
            </div>
            <p v-if="!isPhoneContactAvailable" class="mt-2 text-xs text-amber-700">
              Phone updates are temporarily unavailable. Please use email for complaint updates.
            </p>
          </div>

          <div>
            <label for="contactValue" class="block text-sm font-medium text-gray-700 mb-2">
              {{ formData.contactMethod === "phone" ? "Phone Number" : "Email Address" }}
            </label>
            <div class="relative">
              <Phone
                v-if="formData.contactMethod === 'phone'"
                class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
              />
              <Mail
                v-else
                class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
              />
              <input
                id="contactValue"
                v-model="formData.contactValue"
                :type="formData.contactMethod === 'phone' ? 'tel' : 'email'"
                class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition"
                :placeholder="formData.contactMethod === 'phone' ? '09XX-XXX-XXXX' : 'your.email@example.com'"
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
            <div v-if="imagePreviewUrl" class="mt-4">
              <p class="text-sm font-medium text-gray-700 mb-2">Image Preview</p>
              <img
                :src="imagePreviewUrl"
                alt="Selected evidence preview"
                class="max-h-56 rounded-lg border border-gray-200 object-contain bg-white"
              />
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
import { onBeforeUnmount, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import type { ComplaintCategory } from "../data/mockData";
import { Upload, FileText, Phone, Mail, Tag, ArrowLeft, Check } from "lucide-vue-next";
import { apiRequest } from "../lib/api";

const router = useRouter();
const categories: ComplaintCategory[] = ["Noise", "Theft", "Domestic", "Property", "Others"];
const isPhoneContactAvailable =
  String((import.meta as { env?: Record<string, unknown> }).env?.VITE_PHONE_CONTACT_AVAILABLE ?? "false").toLowerCase() === "true";

const formData = reactive<{
  contactMethod: "phone" | "email";
  contactValue: string;
  category: ComplaintCategory | "";
  description: string;
}>({
  contactMethod: isPhoneContactAvailable ? "phone" : "email",
  contactValue: "",
  category: "",
  description: "",
});

const fileName = ref("");
const selectedFile = ref<File | null>(null);
const imagePreviewUrl = ref("");
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
  if (!isPhoneContactAvailable && formData.contactMethod === "phone") {
    submitError.value = "Phone contact is currently unavailable. Please use email instead.";
    return;
  }

  isSubmitting.value = true;
  submitError.value = "";

  try {
    const payload = new FormData();
    payload.append("contactMethod", formData.contactMethod);
    payload.append("contactValue", formData.contactValue);
    payload.append("category", formData.category);
    payload.append("description", formData.description);
    if (selectedFile.value) {
      payload.append("evidence", selectedFile.value);
    }

    const response = await apiRequest<CreateComplaintResponse>("/complaints", {
      method: "POST",
      body: payload,
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
  formData.contactMethod = isPhoneContactAvailable ? "phone" : "email";
  formData.contactValue = "";
  formData.category = "";
  formData.description = "";
  fileName.value = "";
  selectedFile.value = null;
  clearImagePreview();
  trackingNumber.value = "";
};

const setContactMethod = (method: "phone" | "email") => {
  if (method === "phone" && !isPhoneContactAvailable) {
    return;
  }
  formData.contactMethod = method;
};

const handleFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  if (file) {
    fileName.value = file.name;
    selectedFile.value = file;
    if (file.type.startsWith("image/")) {
      clearImagePreview();
      imagePreviewUrl.value = URL.createObjectURL(file);
    } else {
      clearImagePreview();
    }
    return;
  }
  fileName.value = "";
  selectedFile.value = null;
  clearImagePreview();
};

const clearImagePreview = () => {
  if (imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value);
    imagePreviewUrl.value = "";
  }
};

onBeforeUnmount(() => {
  clearImagePreview();
});
</script>
