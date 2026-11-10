<script setup>
import { X } from "lucide-vue-next";

// แถบกรองประวัติ: ประเภท + ช่วงวันที่ (แก้ค่าใน filters ตรงๆ แล้วแจ้งหน้าแม่ให้โหลดใหม่)
defineProps({
  filters: { type: Object, required: true },
  hasFilters: { type: Boolean, default: false },
  error: { type: String, default: "" },
});
const emit = defineEmits(["change", "clear"]);

const typeOptions = [
  { value: "", label: "All types" },
  { value: "deposit", label: "Deposit" },
  { value: "withdraw", label: "Withdraw" },
  { value: "transfer", label: "Transfer" },
  { value: "receive", label: "Received" },
];
</script>

<template>
  <div class="mb-5">
    <div class="flex flex-wrap items-end gap-3">
      <label class="text-sm">
        <span class="mb-1 block text-muted">Type</span>
        <select v-model="filters.type" @change="emit('change')" class="field !w-auto min-w-36">
          <option v-for="o in typeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
        </select>
      </label>

      <label class="text-sm">
        <span class="mb-1 block text-muted">From</span>
        <input type="date" v-model="filters.from" :max="filters.to || undefined" @change="emit('change')" class="field !w-auto" />
      </label>

      <label class="text-sm">
        <span class="mb-1 block text-muted">To</span>
        <input type="date" v-model="filters.to" :min="filters.from || undefined" @change="emit('change')" class="field !w-auto" />
      </label>

      <button v-if="hasFilters" type="button" @click="emit('clear')" class="btn btn-quiet">
        <X class="h-4 w-4" /> Clear filters
      </button>
    </div>

    <p v-if="error" role="alert" class="mt-2 text-sm text-debit">{{ error }}</p>
  </div>
</template>
