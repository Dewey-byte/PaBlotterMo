<template>
  <span
    :class="[
      'px-3 py-1 rounded-full text-xs font-medium border',
      statusClass,
    ]"
  >
    {{ status }}
  </span>
</template>

<script setup lang="ts">
import { computed } from "vue";
import type { ComplaintStatus } from "../data/mockData";

const props = defineProps<{
  status: ComplaintStatus;
}>();

const statusClass = computed(() => {
  switch (props.status) {
    case "Pending":
      return "bg-yellow-100 text-yellow-800 border-yellow-300";
    case "Under Investigation":
      return "bg-blue-100 text-blue-800 border-blue-300";
    case "Resolved":
      return "bg-green-100 text-green-800 border-green-300";
    case "Rejected":
      return "bg-red-100 text-red-800 border-red-300";
    default:
      return "bg-gray-100 text-gray-800 border-gray-300";
  }
});
</script>
