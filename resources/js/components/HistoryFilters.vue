<script setup>
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
  { value: "receive", label: "Receive" },
];
</script>

<template>
  <div class="mb-4">
    <div class="flex flex-wrap items-end gap-3">
      <label class="text-sm">
        <span class="block text-gray-600 mb-1">Type</span>
        <select
          v-model="filters.type"
          @change="emit('change')"
          class="border rounded-lg px-3 py-2 bg-white text-gray-800 focus:ring focus:ring-blue-300"
        >
          <option v-for="o in typeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
        </select>
      </label>

      <label class="text-sm">
        <span class="block text-gray-600 mb-1">From</span>
        <input
          type="date"
          v-model="filters.from"
          :max="filters.to || undefined"
          @change="emit('change')"
          class="border rounded-lg px-3 py-2 bg-white text-gray-800 focus:ring focus:ring-blue-300"
        />
      </label>

      <label class="text-sm">
        <span class="block text-gray-600 mb-1">To</span>
        <input
          type="date"
          v-model="filters.to"
          :min="filters.from || undefined"
          @change="emit('change')"
          class="border rounded-lg px-3 py-2 bg-white text-gray-800 focus:ring focus:ring-blue-300"
        />
      </label>

      <button
        v-if="hasFilters"
        @click="emit('clear')"
        class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold transition"
      >
        ✖ Clear filters
      </button>
    </div>

    <p v-if="error" class="text-red-500 text-sm mt-2">❌ {{ error }}</p>
  </div>
</template>
