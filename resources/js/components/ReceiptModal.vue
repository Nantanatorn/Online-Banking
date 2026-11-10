<script setup>
import { ref, watch, computed, nextTick } from "vue";
import { toPng } from "html-to-image";
import { Download, Printer } from "lucide-vue-next";
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
  <div class="receipt-overlay modal-backdrop z-[70]" role="dialog" aria-modal="true" aria-label="Transaction receipt">
    <div class="receipt-wrap w-96 max-w-full">
      <!-- ตัวสลิป: id/ref นี้ถูกใช้ตอนบันทึกเป็นรูปและพิมพ์ อย่าเปลี่ยนชื่อ -->
      <div id="receipt-slip" ref="slip" class="receipt-slip bg-sheet text-ink">
        <div class="flex items-center gap-3 bg-ink px-6 py-4 text-white">
          <img src="/bank_2830289.png" alt="" class="h-10 w-10 object-contain" />
          <div>
            <p class="font-display text-lg font-semibold leading-tight">Chino Bank</p>
            <p class="text-sm text-white/70">Transaction receipt</p>
          </div>
        </div>

        <p v-if="loading" class="px-6 py-10 text-center text-muted">Loading receipt...</p>
        <p v-else-if="errorMessage" class="px-6 py-10 text-center text-debit">{{ errorMessage }}</p>

        <div v-else-if="receipt" class="px-6 pb-8 pt-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="font-medium">{{ title }}</p>
              <p class="mt-1 font-display text-3xl font-semibold" :class="incoming ? 'text-credit' : 'text-debit'">
                {{ incoming ? "+" : "-" }}฿{{ formatMoney(receipt.amount) }}
              </p>
              <p class="mt-1 text-sm text-muted">{{ dateText }}</p>
            </div>
            <span class="receipt-stamp" aria-label="Completed">Completed</span>
          </div>

          <div class="my-5 border-t border-dashed border-rule"></div>

          <dl class="space-y-4 text-sm">
            <template v-if="fromTo">
              <div>
                <dt class="text-muted">From</dt>
                <dd class="font-medium">{{ fromTo.from.label }}</dd>
                <dd class="text-muted">Account {{ fromTo.from.account }}</dd>
              </div>
              <div>
                <dt class="text-muted">To</dt>
                <dd class="font-medium">{{ fromTo.to.label }}</dd>
                <dd class="text-muted">Account {{ fromTo.to.account }}</dd>
              </div>
            </template>
            <div v-else>
              <dt class="text-muted">Account</dt>
              <dd class="font-medium">{{ receipt.account.holder_name }}</dd>
              <dd class="text-muted">Account {{ receipt.account.account_id }}</dd>
            </div>

            <div class="flex justify-between">
              <dt class="text-muted">Fee</dt>
              <dd class="font-medium">฿0.00</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-muted">Balance after</dt>
              <dd class="font-display font-semibold">฿{{ formatMoney(receipt.balance) }}</dd>
            </div>
          </dl>

          <div class="my-5 border-t border-dashed border-rule"></div>

          <div>
            <p class="text-sm text-muted">Reference</p>
            <p class="font-display text-lg font-semibold tracking-widest">{{ receipt.reference }}</p>
          </div>
        </div>
      </div>

      <div class="receipt-actions mt-5 flex gap-3">
        <button v-if="receipt" type="button" @click="downloadReceipt" :disabled="downloading" class="btn btn-quiet flex-1">
          <Download class="h-4 w-4" /> {{ downloading ? "Saving..." : "Download" }}
        </button>
        <button v-if="receipt" type="button" @click="printReceipt" class="btn btn-quiet" aria-label="Print or save as PDF">
          <Printer class="h-4 w-4" />
        </button>
        <button type="button" @click="emit('close')" class="btn btn-primary flex-1">Done</button>
      </div>
    </div>
  </div>
</template>

<style>
/* สลิป "พิมพ์ออก": เลื่อนลงมาจากช่องออกสลิป (animation อยู่ที่ตัวห่อ ไม่ใช่ตัวสลิป เพื่อไม่ให้รูปที่บันทึกโดนตัดกลางแอนิเมชัน) */
.receipt-wrap {
  animation: slip-print 0.45s ease-out;
}

.receipt-slip {
  position: relative;
  border-radius: 4px 4px 0 0;
  overflow: visible;
  box-shadow: 0 20px 50px -12px rgb(22 38 63 / 0.45);
}

/* ขอบล่างหยักแบบสลิปธนาคาร */
.receipt-slip::after {
  content: "";
  position: absolute;
  left: 0;
  right: 0;
  top: 100%;
  height: 10px;
  background:
    linear-gradient(-45deg, transparent 7px, #ffffff 0) 0 0 / 14px 10px,
    linear-gradient(45deg, transparent 7px, #ffffff 0) 0 0 / 14px 10px;
}

/* ตราประทับ: ใช้สีเหลืองเหรียญเฉพาะจุดนี้ */
.receipt-stamp {
  flex-shrink: 0;
  transform: rotate(-7deg);
  border: 2px solid #c98f0a;
  color: #8a5f00;
  background: color-mix(in srgb, #f2b632 22%, white);
  border-radius: 4px;
  padding: 0.15rem 0.6rem;
  font-family: var(--font-display);
  font-weight: 600;
  font-size: 0.85rem;
}

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
  .receipt-wrap {
    animation: none !important;
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
