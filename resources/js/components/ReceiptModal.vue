<script setup>
import { ref, watch, computed, nextTick } from "vue";
import { toPng } from "html-to-image";
import axios from "axios";
import { authHeaders, handleAuthError, formatMoney } from "@/composables/useAuth";
import { format } from "date-fns";

const props = defineProps({
  transactionId: { type: String, required: true },
  autoDownload: { type: Boolean, default: false }, // true = บันทึกสลิปลงเครื่องอัตโนมัติ (ใช้ตอนเพิ่งทำรายการเสร็จ)
});
const emit = defineEmits(["close"]);

const receipt = ref(null);
const loading = ref(true);
const errorMessage = ref("");

const load = async () => {
  loading.value = true;
  errorMessage.value = "";
  receipt.value = null;
  try {
    const response = await axios.get(`/api/transactions/${props.transactionId}/receipt`, { headers: authHeaders() });
    receipt.value = response.data;
  } catch (error) {
    if (handleAuthError(error)) return;
    errorMessage.value = error.response?.data?.error || "Could not load the receipt.";
  } finally {
    loading.value = false;
  }

  // บันทึกรูปหลังสลิปแสดงข้อมูลครบแล้วเท่านั้น (ไม่ใช่ตอนยังขึ้น Loading)
  if (props.autoDownload && receipt.value) {
    await nextTick();
    await new Promise((resolve) => requestAnimationFrame(() => resolve()));
    await downloadReceipt();
  }
};

watch(() => props.transactionId, load, { immediate: true });

const titles = {
  deposit: "Deposit",
  withdraw: "Withdrawal",
  transfer: "Transfer",
  receive: "Money Received",
};
const title = computed(() => titles[receipt.value?.type] || "Transaction");
const incoming = computed(() => ["deposit", "receive"].includes(receipt.value?.type));
const dateText = computed(() =>
  receipt.value ? format(new Date(receipt.value.created_at), "dd MMM yyyy, HH:mm:ss") : ""
);

// โอน: ต้นทาง = บัญชีเรา ปลายทาง = อีกฝั่ง / รับโอน: กลับกัน
const fromTo = computed(() => {
  const r = receipt.value;
  if (!r || !r.counterparty) return null;
  const mine = { label: r.account.holder_name, account: r.account.account_id };
  const other = { label: r.counterparty.holder_name, account: r.counterparty.account_id };
  return r.type === "receive" ? { from: other, to: mine } : { from: mine, to: other };
});

const printReceipt = () => window.print();

// บันทึกสลิปเป็นรูป PNG ลงเครื่อง
const downloading = ref(false);
const slip = ref(null);
const downloadReceipt = async () => {
  if (!slip.value || !receipt.value || downloading.value) return;
  downloading.value = true;
  try {
    const dataUrl = await toPng(slip.value, { pixelRatio: 2, cacheBust: true });
    const link = document.createElement("a");
    link.download = `receipt-${receipt.value.reference}.png`;
    link.href = dataUrl;
    link.click();
  } catch (error) {
    console.error("Receipt download failed:", error);
  } finally {
    downloading.value = false;
  }
};
</script>

<template>
  <div class="receipt-overlay fixed inset-0 flex items-center justify-center bg-black/40 backdrop-blur-sm z-[70] p-4">
    <div class="w-96 max-w-full">
      <div id="receipt-slip" ref="slip" class="bg-white text-gray-800 rounded-lg shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-blue-900 to-teal-700 text-white px-6 py-4 flex items-center space-x-3">
          <img src="/bank_2830289.png" alt="" class="h-10" />
          <div>
            <p class="font-bold text-lg leading-tight">Chino Bank</p>
            <p class="text-xs text-teal-100">Transaction Receipt</p>
          </div>
        </div>

        <p v-if="loading" class="p-8 text-center text-gray-500">Loading receipt...</p>
        <p v-else-if="errorMessage" class="p-8 text-center text-red-500">{{ errorMessage }}</p>

        <div v-else-if="receipt" class="px-6 py-5">
          <div class="text-center">
            <p class="text-green-600 font-semibold">✅ {{ title }} successful</p>
            <p class="text-3xl font-bold mt-2" :class="incoming ? 'text-green-600' : 'text-red-600'">
              {{ incoming ? "+" : "-" }}฿{{ formatMoney(receipt.amount) }}
            </p>
            <p class="text-xs text-gray-500 mt-1">{{ dateText }}</p>
          </div>

          <div class="border-t border-dashed my-4"></div>

          <dl class="text-sm space-y-3">
            <template v-if="fromTo">
              <div>
                <dt class="text-gray-500">From</dt>
                <dd class="font-semibold">{{ fromTo.from.label }}</dd>
                <dd class="text-gray-500">Account {{ fromTo.from.account }}</dd>
              </div>
              <div>
                <dt class="text-gray-500">To</dt>
                <dd class="font-semibold">{{ fromTo.to.label }}</dd>
                <dd class="text-gray-500">Account {{ fromTo.to.account }}</dd>
              </div>
            </template>
            <div v-else>
              <dt class="text-gray-500">Account</dt>
              <dd class="font-semibold">{{ receipt.account.holder_name }}</dd>
              <dd class="text-gray-500">Account {{ receipt.account.account_id }}</dd>
            </div>

            <div class="flex justify-between">
              <dt class="text-gray-500">Fee</dt>
              <dd class="font-semibold">฿0.00</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">Balance after</dt>
              <dd class="font-semibold">฿{{ formatMoney(receipt.balance) }}</dd>
            </div>
          </dl>

          <div class="border-t border-dashed my-4"></div>

          <div class="text-center">
            <p class="text-xs text-gray-500">Reference No.</p>
            <p class="font-mono font-bold tracking-widest">{{ receipt.reference }}</p>
          </div>
        </div>
      </div>

      <div class="receipt-actions flex space-x-3 mt-4">
        <button
          v-if="receipt"
          @click="downloadReceipt"
          :disabled="downloading"
          class="flex-1 py-2 rounded-lg bg-white/90 hover:bg-white disabled:opacity-60 text-blue-900 font-semibold transition"
        >
          {{ downloading ? "Saving..." : "⬇️ Download" }}
        </button>
        <button
          v-if="receipt"
          @click="printReceipt"
          class="py-2 px-3 rounded-lg bg-white/90 hover:bg-white text-blue-900 font-semibold transition"
          title="Print / Save PDF"
        >
          🖨️
        </button>
        <button
          @click="emit('close')"
          class="flex-1 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold transition"
        >
          Close
        </button>
      </div>
    </div>
  </div>
</template>

<style>
/* พิมพ์เฉพาะสลิป ซ่อนส่วนอื่นของหน้า */
@media print {
  body * {
    visibility: hidden;
  }
  #receipt-slip,
  #receipt-slip * {
    visibility: visible;
  }
  .receipt-overlay {
    position: absolute !important;
    inset: 0 !important;
    background: none !important;
    backdrop-filter: none !important;
  }
  #receipt-slip {
    box-shadow: none !important;
    margin: 0 auto;
    width: 24rem;
  }
  .receipt-actions {
    display: none !important;
  }
}
</style>
