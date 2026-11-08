<script setup>
import { computed } from "vue";

const props = defineProps({
  pagination: { type: Object, required: true },
  loading: { type: Boolean, default: false },
});
const emit = defineEmits(["page"]);

const current = computed(() => props.pagination.current_page);
const last = computed(() => props.pagination.last_page);

const rangeText = computed(() => {
  const { total, per_page, current_page } = props.pagination;
  if (!total) return "No transactions";
  const start = (current_page - 1) * per_page + 1;
  const end = Math.min(current_page * per_page, total);
  return `Showing ${start}–${end} of ${total}`;
});

// แสดงเลขหน้าเป็นช่วง: 1 … 4 5 [6] 7 8 … 20
const pages = computed(() => {
  const set = new Set([1, last.value, current.value - 2, current.value - 1, current.value, current.value + 1, current.value + 2]);
  const sorted = [...set].filter((p) => p >= 1 && p <= last.value).sort((a, b) => a - b);
  const result = [];
  sorted.forEach((p, i) => {
    if (i && p - sorted[i - 1] > 1) result.push("gap-" + p);
    result.push(p);
  });
  return result;
});

const go = (page) => {
  if (props.loading || page < 1 || page > last.value || page === current.value) return;
  emit("page", page);
};
</script>

<template>
  <div class="flex flex-wrap items-center justify-between gap-3 mt-4 text-sm text-gray-600">
    <p>{{ rangeText }}</p>

    <nav v-if="last > 1" class="flex items-center space-x-1">
      <button
        @click="go(current - 1)"
        :disabled="current === 1 || loading"
        class="px-3 py-1 rounded-lg border bg-white hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed"
      >
        ‹ Prev
      </button>

      <template v-for="p in pages" :key="p">
        <span v-if="typeof p === 'string'" class="px-1">…</span>
        <button
          v-else
          @click="go(p)"
          :disabled="loading"
          class="min-w-8 px-2 py-1 rounded-lg border transition"
          :class="p === current ? 'bg-blue-600 border-blue-600 text-white font-semibold' : 'bg-white hover:bg-gray-100'"
        >
          {{ p }}
        </button>
      </template>

      <button
        @click="go(current + 1)"
        :disabled="current === last || loading"
        class="px-3 py-1 rounded-lg border bg-white hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed"
      >
        Next ›
      </button>
    </nav>
  </div>
</template>
