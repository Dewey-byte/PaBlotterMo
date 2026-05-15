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
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 sm:py-4">
        <button @click="router.push({ path: '/admin', query: { tab: 'complaints' } })" class="flex items-center space-x-2 text-gray-700 hover:text-[#1E3A8A] transition">
          <ArrowLeft class="w-5 h-5" />
          <span>Back to Complaints</span>
        </button>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
          <div class="bg-white rounded-2xl shadow-lg border border-slate-100 overflow-hidden">
            <div class="bg-gradient-to-r from-[#1E3A8A] to-[#15803D] px-4 sm:px-6 py-4">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                  <h1 class="text-xl sm:text-2xl font-bold text-white">Complaint Details</h1>
                  <p class="text-blue-100 text-sm mt-1 break-all">{{ complaint.trackingNumber }}</p>
                </div>
                <div class="shrink-0 self-start sm:self-auto">
                  <StatusBadge :status="status" />
                </div>
              </div>
            </div>

            <div class="p-6 space-y-6">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="flex items-center space-x-2 text-sm font-medium text-gray-600 mb-2">
                    <User class="w-4 h-4" />
                    <span>Complainant</span>
                  </label>
                  <p class="text-gray-900 font-medium">{{ complaint.residentName || "Anonymous" }}</p>
                </div>
                <div>
                  <label class="flex items-center space-x-2 text-sm font-medium text-gray-600 mb-2">
                    <Phone class="w-4 h-4" />
                    <span>{{ complaint.contactMethod === "email" ? "Email Address" : "Phone Number" }}</span>
                  </label>
                  <p class="text-gray-900 font-medium">{{ complaint.contactValue || complaint.contactNumber }}</p>
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

              <div v-if="availableEvidencePaths.length > 0">
                <label class="flex items-center space-x-2 text-sm font-medium text-gray-600 mb-2">
                  <ImageIcon class="w-4 h-4" />
                  <span>Resident Evidence ({{ availableEvidencePaths.length }})</span>
                </label>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 space-y-4">
                  <div v-for="(evidencePath, index) in availableEvidencePaths" :key="`${evidencePath}-${index}`">
                    <p class="text-xs text-gray-500 mb-2">Attachment {{ index + 1 }}</p>
                    <img
                      v-if="supportsInlineImgPreview(evidencePath, evidenceMimeAt(index))"
                      :src="resolveEvidenceUrl(complaint, index)"
                      alt="Resident evidence"
                      class="max-h-80 rounded-lg border border-gray-200 object-contain bg-white"
                    />
                    <div v-else-if="isHeicFamilyEvidence(evidencePath, evidenceMimeAt(index))" class="space-y-2">
                      <template v-if="!heicPreviewFailed[index]">
                        <img
                          :src="resolveEvidencePreviewUrl(complaint, index)"
                          alt="Resident evidence (JPEG preview)"
                          class="max-h-80 rounded-lg border border-gray-200 object-contain bg-white"
                          @error="markHeicPreviewFailed(index)"
                        />
                        <p class="text-xs text-gray-600">
                          Server-generated JPEG preview for HEIC/HEIF when PHP Imagick supports it.
                          <a
                            :href="resolveEvidenceUrl(complaint, index)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-medium text-[#1E3A8A] hover:underline"
                          >
                            Open original
                          </a>
                        </p>
                      </template>
                      <div
                        v-else
                        class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
                      >
                        <p class="mb-2">
                          No JPEG preview available (install Imagick with HEIF, or open the original file).
                        </p>
                        <a
                          :href="resolveEvidenceUrl(complaint, index)"
                          target="_blank"
                          rel="noopener noreferrer"
                          class="font-medium text-[#1E3A8A] hover:underline"
                        >
                          Open / download
                        </a>
                      </div>
                    </div>
                    <div v-else-if="looksLikeVideoEvidence(evidencePath, evidenceMimeAt(index))" class="space-y-2">
                      <video
                        v-show="!videoPlaybackFailed[index]"
                        :src="resolveEvidenceUrl(complaint, index)"
                        controls
                        playsinline
                        class="max-h-80 rounded-lg border border-gray-200 object-contain bg-white w-full"
                        @error="markVideoPlaybackFailed(index)"
                      />
                      <div
                        v-if="videoPlaybackFailed[index]"
                        class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
                      >
                        <p class="mb-2">
                          Inline playback isn’t supported for this clip in your browser (for example some iPhone MOV/HEVC). Open
                          it in a new tab or download it instead.
                        </p>
                        <a
                          :href="resolveEvidenceUrl(complaint, index)"
                          target="_blank"
                          rel="noopener noreferrer"
                          class="font-medium text-[#1E3A8A] hover:underline"
                        >
                          Open in new tab
                        </a>
                      </div>
                      <p v-else class="text-xs text-gray-600">
                        <a
                          :href="resolveEvidenceUrl(complaint, index)"
                          target="_blank"
                          rel="noopener noreferrer"
                          class="font-medium text-[#1E3A8A] hover:underline"
                        >
                          Open in new tab
                        </a>
                        <span class="text-gray-500"> — use if playback fails</span>
                      </p>
                    </div>
                    <a
                      v-else
                      :href="resolveEvidenceUrl(complaint, index)"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="inline-flex items-center text-[#1E3A8A] hover:underline font-medium"
                    >
                      View uploaded file
                    </a>
                    <p class="mt-2 text-xs text-gray-600">
                      <a
                        :href="resolveEvidenceUrl(complaint, index)"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="font-medium text-[#1E3A8A] hover:underline"
                      >
                        Open in new tab
                      </a>
                    </p>
                  </div>
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
              <option value="Rejected">Rejected</option>
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

          <div v-if="updateSuccess" class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
            {{ updateSuccess }}
          </div>

          <div v-if="notificationMessage" :class="[
            'px-4 py-3 rounded-lg text-sm border',
            notificationSent === false ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-blue-50 border-blue-200 text-blue-800',
          ]">
            {{ notificationMessage }}
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
import { computed, onMounted, ref, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuth } from "../composables/useAuth";
import { API_BASE_URL, apiRequest } from "../lib/api";
import type { Complaint, ComplaintStatus } from "../data/mockData";
import StatusBadge from "../components/StatusBadge.vue";
import { ArrowLeft, User, Phone, Calendar, Tag, FileText, Save, Image as ImageIcon } from "lucide-vue-next";

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
const updateSuccess = ref("");
const notificationMessage = ref("");
const notificationSent = ref<boolean | null>(null);
const loadingComplaint = ref(true);
const videoPlaybackFailed = ref<Record<number, boolean>>({});
const heicPreviewFailed = ref<Record<number, boolean>>({});

watch(
  () => complaint.value?.id,
  () => {
    videoPlaybackFailed.value = {};
    heicPreviewFailed.value = {};
  },
);

const availableEvidencePaths = computed(() => {
  const c = complaint.value;
  if (!c) return [];

  const paths = (c.evidencePaths ?? []).filter((p) => String(p ?? "").trim() !== "");
  if (paths.length > 0) return paths;

  const fromUrls = c.evidenceUrls?.length ?? 0;
  const fromMimes = c.evidenceMimeTypes?.length ?? 0;
  const legacy = c.evidenceUrl || c.evidencePath ? 1 : 0;
  const n = Math.max(fromUrls, fromMimes, legacy);

  if (n > 0) {
    return Array.from({ length: n }, (_, i) => `attachment-${i + 1}`);
  }

  return c.evidencePath ? [c.evidencePath] : [];
});

const evidenceMimeAt = (index: number) => complaint.value?.evidenceMimeTypes?.[index] ?? null;

/** Path segment used for HEIC/octet-stream heuristics when the API omits MIME (legacy uploads). */
const evidenceBasename = (path: string) => {
  const stripped = path.split(/[?#]/)[0] ?? path;
  return stripped.split(/[/\\]/).pop() ?? stripped;
};

const evidenceFileExtension = (path: string): string => {
  const base = evidenceBasename(path);
  const m = /\.([^.]+)$/.exec(base);
  return m ? m[1].toLowerCase() : "";
};

const mimeIsHeicFamily = (mime?: string | null) => {
  if (!mime) return false;
  const m = mime.toLowerCase();
  if (m === "image/heic" || m === "image/heif" || m === "image/heic-sequence") return true;
  return m.startsWith("image/") && (m.includes("heic") || m.includes("heif"));
};

const isHeicFamilyEvidence = (pathHint: string, mime?: string | null) => {
  if (mimeIsHeicFamily(mime)) return true;
  const ext = evidenceFileExtension(pathHint);
  if (ext !== "heic" && ext !== "heif") return false;
  const mu = mime?.toLowerCase() ?? "";
  return mu === "" || mu === "application/octet-stream" || mu.startsWith("image/");
};

/** Chromium and most browsers only decode these inline; broad `image/*` breaks on HEIC mislabeled as JPEG, TIFF, etc. */
const supportsInlineImgPreview = (pathHint: string, mime?: string | null) => {
  if (isHeicFamilyEvidence(pathHint, mime)) return false;
  const mu = ((mime ?? "") as string).toLowerCase().split(";")[0].trim();
  if (mu) {
    const safeMime = new Set(["image/jpeg", "image/jpg", "image/pjpeg", "image/png", "image/gif", "image/webp"]);
    if (mu.startsWith("image/")) {
      return safeMime.has(mu);
    }
  }
  const ext = evidenceFileExtension(pathHint);
  return ["jpg", "jpeg", "jfif", "png", "gif", "webp"].includes(ext);
};

const looksLikeVideoEvidence = (pathHint: string, mime?: string | null) => {
  const mu = (mime ?? "").toLowerCase();
  if (mu.startsWith("video/")) return true;
  const ext = evidenceFileExtension(pathHint);
  return ["mp4", "mov", "m4v", "webm", "ogg", "3gp"].includes(ext);
};

const markVideoPlaybackFailed = (index: number) => {
  videoPlaybackFailed.value = { ...videoPlaybackFailed.value, [index]: true };
};

const markHeicPreviewFailed = (index: number) => {
  heicPreviewFailed.value = { ...heicPreviewFailed.value, [index]: true };
};

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

const toAbsoluteUrl = (value: string) => {
  if (value.startsWith("http://") || value.startsWith("https://")) {
    return value;
  }
  const origin = API_BASE_URL.replace(/\/api\/?$/, "");
  return `${origin}${value.startsWith("/") ? value : `/${value}`}`;
};

const resolveEvidenceUrl = (currentComplaint: Complaint, index: number) => {
  const direct = currentComplaint.evidenceUrls?.[index];
  if (direct && (direct.startsWith("http://") || direct.startsWith("https://"))) {
    return direct;
  }

  const id = currentComplaint.id;
  if (typeof id === "number" && !Number.isNaN(id)) {
    const base = API_BASE_URL.replace(/\/$/, "");
    return `${base}/complaints/${id}/evidence/${index}`;
  }

  if (direct) {
    return toAbsoluteUrl(direct);
  }

  if (index === 0 && currentComplaint.evidenceUrl) {
    return currentComplaint.evidenceUrl.startsWith("http")
      ? currentComplaint.evidenceUrl
      : toAbsoluteUrl(currentComplaint.evidenceUrl);
  }

  const evidencePath = currentComplaint.evidencePaths?.[index];
  if (evidencePath) {
    return toAbsoluteUrl(evidencePath);
  }

  return index === 0 && currentComplaint.evidencePath ? toAbsoluteUrl(currentComplaint.evidencePath) : "";
};

const resolveEvidencePreviewUrl = (currentComplaint: Complaint, index: number) => {
  const base = resolveEvidenceUrl(currentComplaint, index);
  if (!base) return "";
  return base.includes("?") ? `${base}&preview=1` : `${base}?preview=1`;
};

const handleUpdate = async () => {
  if (!complaint.value || updating.value) return;
  updating.value = true;
  updateError.value = "";
  updateSuccess.value = "";
  notificationMessage.value = "";
  notificationSent.value = null;

  try {
    const response = await apiRequest<{
      message: string;
      complaint: Complaint;
      notificationSent: boolean | null;
      notificationReason?: string | null;
    }>(
      `/complaints/${complaint.value.id}`,
      {
      method: "PATCH",
      body: JSON.stringify({
        status: status.value,
        adminNotes: adminNotes.value,
      }),
    },
    );
    complaint.value = response.complaint;
    status.value = response.complaint.status;
    adminNotes.value = response.complaint.adminNotes ?? "";
    updateSuccess.value = response.message;
    notificationSent.value = response.notificationSent;

    if (response.notificationSent === true) {
      notificationMessage.value = "Complainant was notified using their selected contact method.";
    } else if (response.notificationSent === false) {
      notificationMessage.value = response.notificationReason
        ? `Status updated, but notification failed: ${response.notificationReason}`
        : "Status updated, but notification delivery failed. Check SMS/email configuration.";
    }
  } catch (err) {
    updateError.value = err instanceof Error ? err.message : "Failed to update complaint.";
    return;
  } finally {
    updating.value = false;
  }
};
</script>
