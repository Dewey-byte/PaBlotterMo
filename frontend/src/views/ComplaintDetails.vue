<template>
  <div v-if="loadingComplaint" class="min-h-screen bg-slate-50 flex items-center justify-center text-slate-600">
    Loading complaint...
  </div>

  <div v-else-if="!complaint" class="min-h-screen bg-slate-50 flex items-center justify-center">
    <div class="text-center">
      <h2 class="text-2xl font-bold text-gray-900 mb-2">Complaint Not Found</h2>
      <button @click="router.push('/admin')" class="text-[#1E3A8A] hover:underline">Back to Dashboard</button>
    </div>
  </div>

  <div v-else class="min-h-screen bg-slate-50">
    <header class="bg-white/90 backdrop-blur shadow-sm border-b border-slate-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <button @click="router.push('/admin')" class="flex items-center space-x-2 text-gray-700 hover:text-[#1E3A8A] transition">
          <ArrowLeft class="w-5 h-5" />
          <span>Back to Admin Dashboard</span>
        </button>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
          <div class="bg-white rounded-2xl shadow-lg border border-slate-100 overflow-hidden">
            <div class="bg-gradient-to-r from-[#1E3A8A] to-[#15803D] px-6 py-4">
              <div class="flex items-center justify-between">
                <div>
                  <h1 class="text-2xl font-bold text-white">Complaint Details</h1>
                  <p class="text-blue-100 text-sm mt-1">{{ complaint.trackingNumber }}</p>
                </div>
                <StatusBadge :status="status" />
              </div>
            </div>

            <div class="p-6 space-y-6">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="flex items-center space-x-2 text-sm font-medium text-gray-600 mb-2">
                    <User class="w-4 h-4" />
                    <span>Resident Name</span>
                  </label>
                  <p class="text-gray-900 font-medium">{{ complaint.residentName }}</p>
                </div>
                <div>
                  <label class="flex items-center space-x-2 text-sm font-medium text-gray-600 mb-2">
                    <Phone class="w-4 h-4" />
                    <span>Contact Number</span>
                  </label>
                  <p class="text-gray-900 font-medium">{{ complaint.contactNumber }}</p>
                </div>
                <div>
                  <label class="flex items-center space-x-2 text-sm font-medium text-gray-600 mb-2">
                    <Tag class="w-4 h-4" />
                    <span>Category</span>
                  </label>
                  <p class="text-gray-900 font-medium">{{ complaint.category }}</p>
                </div>
                <div>
                  <label class="flex items-center space-x-2 text-sm font-medium text-gray-600 mb-2">
                    <Calendar class="w-4 h-4" />
                    <span>Date Submitted</span>
                  </label>
                  <p class="text-gray-900 font-medium">{{ formattedDate() }}</p>
                </div>
              </div>

              <div>
                <label class="flex items-center space-x-2 text-sm font-medium text-gray-600 mb-2">
                  <FileText class="w-4 h-4" />
                  <span>Complaint Description</span>
                </label>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                  <p class="text-gray-900 leading-relaxed">{{ complaint.description }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="space-y-6">
          <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6">
            <h3 class="font-semibold text-gray-900 mb-4">Update Status</h3>
            <select v-model="status" class="w-full px-4 py-3 border border-gray-300 rounded-lg">
              <option value="Pending">Pending</option>
              <option value="Under Investigation">Under Investigation</option>
              <option value="Resolved">Resolved</option>
            </select>
          </div>

          <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6">
            <h3 class="font-semibold text-gray-900 mb-4">Admin Notes</h3>
            <textarea
              v-model="adminNotes"
              rows="6"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg resize-none"
              placeholder="Add notes about this complaint..."
            />
          </div>

          <div v-if="updateError" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            {{ updateError }}
          </div>

          <button
            @click="handleUpdate"
            :disabled="updating"
            class="w-full bg-[#15803D] text-white px-6 py-3 rounded-lg hover:bg-[#15803de6] transition font-medium shadow-lg flex items-center justify-center space-x-2"
          >
            <Save class="w-5 h-5" />
            <span>{{ updating ? "Updating..." : "Update Complaint" }}</span>
          </button>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuth } from "../composables/useAuth";
import { apiRequest } from "../lib/api";
import type { Complaint, ComplaintStatus } from "../data/mockData";
import StatusBadge from "../components/StatusBadge.vue";
import { ArrowLeft, User, Phone, Calendar, Tag, FileText, Save } from "lucide-vue-next";

const router = useRouter();
const route = useRoute();
const { user } = useAuth();

onMounted(() => {
  if (!user.value || user.value.role !== "admin") {
    router.push("/admin/login");
    return;
  }
  void fetchComplaint();
});

const complaint = ref<Complaint | null>(null);
const status = ref<ComplaintStatus>("Pending");
const adminNotes = ref("");
const updating = ref(false);
const updateError = ref("");
const loadingComplaint = ref(true);

const fetchComplaint = async () => {
  const id = route.params.id;
  if (!id) {
    complaint.value = null;
    return;
  }

  try {
    const response = await apiRequest<Complaint>(`/complaints/${id as string}`);
    complaint.value = response;
    status.value = response.status;
    adminNotes.value = response.adminNotes ?? "";
  } catch (err) {
    updateError.value = err instanceof Error ? err.message : "Failed to load complaint.";
    complaint.value = null;
  } finally {
    loadingComplaint.value = false;
  }
};

const formattedDate = () => {
  if (!complaint.value) return "";
  return new Date(complaint.value.dateSubmitted).toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
};

const handleUpdate = async () => {
  if (!complaint.value || updating.value) return;
  updating.value = true;
  updateError.value = "";

  try {
    const response = await apiRequest<{ complaint: Complaint }>(`/complaints/${complaint.value.id}`, {
      method: "PATCH",
      body: JSON.stringify({
        status: status.value,
        adminNotes: adminNotes.value,
      }),
    });
    complaint.value = response.complaint;
    status.value = response.complaint.status;
    adminNotes.value = response.complaint.adminNotes ?? "";
  } catch (err) {
    updateError.value = err instanceof Error ? err.message : "Failed to update complaint.";
    return;
  } finally {
    updating.value = false;
  }

  window.alert("Complaint updated successfully!");
  router.push("/admin");
};
</script>
