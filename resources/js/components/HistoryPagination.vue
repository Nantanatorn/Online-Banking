<script setup>
import { computed } from "vue";
import { ChevronLeft, ChevronRight } from "lucide-vue-next";

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
  <div class="mt-5 flex flex-wrap items-center justify-between gap-3 text-sm text-muted">
    <p>{{ rangeText }}</p>

    <nav v-if="last > 1" class="flex items-center gap-1" aria-label="Transaction pages">
      <button type="button" @click="go(current - 1)" :disabled="current === 1 || loading" class="btn btn-quiet !px-2 !py-1" aria-label="Previous page">
        <ChevronLeft class="h-4 w-4" />
      </button>

      <template v-for="p in pages" :key="p">
        <span v-if="typeof p === 'string'" class="px-1">…</span>
        <button
          v-else
          type="button"
          @click="go(p)"
          :disabled="loading"
          :aria-current="p === current ? 'page' : undefined"
          class="btn min-w-9 !px-2 !py-1"
          :class="p === current ? 'bg-ink text-white' : 'btn-quiet'"
        >
          {{ p }}
        </button>
      </template>

      <button type="button" @click="go(current + 1)" :disabled="current === last || loading" class="btn btn-quiet !px-2 !py-1" aria-label="Next page">
        <ChevronRight class="h-4 w-4" />
      </button>
    </nav>
  </div>
</template>
