<script setup>
import { ref, computed } from "vue";
import ReceiptModal from "@/components/ReceiptModal.vue";
import { X, Star, ArrowDownToLine, ArrowUpFromLine, Send, Delete, CircleCheck, CircleAlert } from "lucide-vue-next";
import axios from "axios";
import { authHeaders, handleAuthError, formatMoney } from "@/composables/useAuth";

const emit = defineEmits(["done"]);

const apiError = (error, fallback) =>
  error.response?.data?.error ||
  Object.values(error.response?.data?.errors || {})[0]?.[0] ||
  fallback;

// ✅ Modal State
const isDepositModalOpen = ref(false);
const isWithdrawModalOpen = ref(false);
const isTransferModalOpen = ref(false);
const amount = ref("");
const targetAccountId = ref("");
const successMessage = ref("");
const receiptId = ref(null); // transaction id ที่จะแสดงสลิป
const errorMessage = ref("");

// ✅ บัญชีที่บันทึกไว้ (bookmark)
const savedAccounts = ref([]);
const saveRecipient = ref(false);
const recipientNickname = ref("");
const isTargetSaved = computed(() =>
  savedAccounts.value.some((a) => String(a.account_id) === String(targetAccountId.value).trim())
);

const fetchSavedAccounts = async () => {
  try {
    const response = await axios.get("/api/saved-accounts", { headers: authHeaders() });
    savedAccounts.value = response.data.saved_accounts;
  } catch (error) {
    if (handleAuthError(error)) return;
    savedAccounts.value = [];
  }
};

const pickSaved = (account) => {
  targetAccountId.value = String(account.account_id);
  saveRecipient.value = false;
};

// บันทึกบัญชีปลายทางหลังโอนสำเร็จ (ถ้าติ๊กไว้) ล้มเหลวก็ไม่กระทบการโอน
const saveRecipientIfNeeded = async () => {
  if (!saveRecipient.value || isTargetSaved.value) return;
  try {
    await axios.post(
      "/api/saved-accounts",
      { account_id: parseInt(targetAccountId.value), nickname: recipientNickname.value.trim() || null },
      { headers: authHeaders() }
    );
  } catch (error) {
    console.error("Save account failed:", error.response?.data || error.message);
  }
};

// ✅ Confirm Recipient Modal State (ตรวจชื่อผู้รับก่อนใส่ PIN)
const isConfirmModalOpen = ref(false);
const recipient = ref(null);
const lookupLoading = ref(false);

// ✅ PIN Modal State (ใช้ยืนยันก่อนถอน/โอน)
const PIN_LENGTH = 6;
// วงเงินโอนรายวัน (ดึงจาก backend ตอนเปิด modal โอน)
const transferLimit = ref(null); // { limit, used, remaining }

const fetchTransferLimit = async () => {
  try {
    const response = await axios.get("/api/transfer/limit", { headers: authHeaders() });
    transferLimit.value = response.data;
  } catch (error) {
    if (handleAuthError(error)) return;
    transferLimit.value = null;
  }
};
const isPinModalOpen = ref(false);
const pendingAction = ref(null); // "withdraw" | "transfer"
const pin = ref("");
const pinError = ref("");
const pinLoading = ref(false);
const pinKeys = ["1", "2", "3", "4", "5", "6", "7", "8", "9", "clear", "0", "back"];

// ✅ Open Deposit Modal
const openDepositModal = () => {
  isDepositModalOpen.value = true;
  isWithdrawModalOpen.value = false;
  isTransferModalOpen.value = false;
  resetMessages();
};

// ✅ Open Withdraw Modal
const openWithdrawModal = () => {
  isWithdrawModalOpen.value = true;
  isDepositModalOpen.value = false;
  isTransferModalOpen.value = false;
  resetMessages();
};

// ✅ Open Transfer Modal
const openTransferModal = () => {
  isTransferModalOpen.value = true;
  isDepositModalOpen.value = false;
  isWithdrawModalOpen.value = false;
  resetMessages();
  fetchSavedAccounts();
  fetchTransferLimit();
};

// ✅ เปิด Transfer modal พร้อมเติมบัญชีปลายทาง (เรียกจากหน้า dashboard ตอนกดบัญชีที่บันทึกไว้)
const openTransferTo = (accountId) => {
  openTransferModal();
  targetAccountId.value = String(accountId);
};
defineExpose({ openTransferTo });

// ✅ Close Modal
const closeModal = () => {
  closePinModal();
  isConfirmModalOpen.value = false;
  isDepositModalOpen.value = false;
  isWithdrawModalOpen.value = false;
  isTransferModalOpen.value = false;
};

// ✅ Reset Messages & Inputs
const resetMessages = () => {
  successMessage.value = "";
  errorMessage.value = "";
  amount.value = "";
  targetAccountId.value = "";
  saveRecipient.value = false;
  recipientNickname.value = "";
};

// ✅ Submit Deposit
const submitDeposit = async () => {
  if (!amount.value || amount.value <= 0) {
    errorMessage.value = "Please enter a valid deposit amount.";
    return;
  }

  try {
    const response = await axios.post(
      "/api/deposit",
      { amount: parseFloat(amount.value) },
      { headers: authHeaders() }
    );

    emit("done");
    closeModal();
    receiptId.value = response.data.transaction_id; // แสดงสลิปหลังฝากสำเร็จ
  } catch (error) {
    if (handleAuthError(error)) return;
    errorMessage.value = apiError(error, "Deposit failed. Try again.");
  }
};

// ✅ Submit Withdraw
const submitWithdraw = async () => {
  if (!amount.value || amount.value <= 0) {
    errorMessage.value = "Please enter a valid withdrawal amount.";
    return;
  }

  errorMessage.value = "";
  openPinModal("withdraw");
};

const performWithdraw = () =>
  axios.post(
    "/api/withdraw",
    { amount: parseFloat(amount.value), pin: pin.value },
    { headers: authHeaders() }
  );

// ✅ Submit Transfer
const submitTransfer = async () => {
  if (!amount.value || amount.value <= 0 || !targetAccountId.value) {
    errorMessage.value = "Please enter a valid amount and target account ID.";
    return;
  }

  let userAccountId = localStorage.getItem("account_id"); // ✅ ตรวจสอบค่าจาก LocalStorage

  if (!userAccountId) {
    errorMessage.value = "❌ Source account ID is missing. Please log in again.";
    console.error("❌ LocalStorage missing 'account_id'. Please set it.");
    return;
  }

  if (transferLimit.value && parseFloat(amount.value) > Number(transferLimit.value.remaining)) {
    errorMessage.value = "Daily transfer limit exceeded. Remaining today: ฿" + formatMoney(transferLimit.value.remaining);
    return;
  }

  errorMessage.value = "";

  // ดึงชื่อเจ้าของบัญชีปลายทางมาให้ตรวจก่อน
  lookupLoading.value = true;
  try {
    const response = await axios.get(`/api/accounts/${encodeURIComponent(targetAccountId.value.trim())}`, {
      headers: authHeaders(),
    });
    if (response.data.is_own) {
      errorMessage.value = "Cannot transfer to your own account.";
      return;
    }
    recipient.value = response.data;
    isConfirmModalOpen.value = true;
  } catch (error) {
    if (handleAuthError(error)) return;
    errorMessage.value =
      error.response?.status === 404 ? "Target account not found." : apiError(error, "Could not verify the account.");
  } finally {
    lookupLoading.value = false;
  }
};

// ✅ ยืนยันผู้รับ -> ไปใส่ PIN
const confirmRecipient = () => {
  isConfirmModalOpen.value = false;
  openPinModal("transfer");
};

const performTransfer = () =>
  axios.post(
    "/api/transfer",
    {
      amount: parseFloat(amount.value),
      source_account_id: parseInt(localStorage.getItem("account_id")), // ✅ ส่งบัญชีต้นทาง
      target_account_id: parseInt(targetAccountId.value),
      pin: pin.value,
    },
    { headers: authHeaders() }
  );

// ✅ PIN Modal
const openPinModal = (action) => {
  pendingAction.value = action;
  pin.value = "";
  pinError.value = "";
  isPinModalOpen.value = true;
};

const closePinModal = () => {
  isPinModalOpen.value = false;
  pendingAction.value = null;
  pin.value = "";
  pinError.value = "";
  pinLoading.value = false;
};

const pressKey = (key) => {
  if (pinLoading.value) return;
  pinError.value = "";
  if (key === "clear") pin.value = "";
  else if (key === "back") pin.value = pin.value.slice(0, -1);
  else if (pin.value.length < PIN_LENGTH) pin.value += key;
  if (pin.value.length === PIN_LENGTH) submitPin();
};

const submitPin = async () => {
  if (pin.value.length !== PIN_LENGTH || pinLoading.value) return;

  pinLoading.value = true;
  try {
    const response = await (pendingAction.value === "withdraw" ? performWithdraw() : performTransfer());
    if (pendingAction.value === "transfer") await saveRecipientIfNeeded();
    const transactionId = response.data.transaction_id;
    emit("done");
    fetchTransferLimit();
    closeModal(); // ปิดทั้ง PIN modal และ modal ถอน/โอน
    receiptId.value = transactionId; // แสดงสลิปหลังถอน/โอนสำเร็จ
  } catch (error) {
    if (handleAuthError(error)) return;
    const wrongPin = error.response?.status === 403 && error.response?.data?.error === "Incorrect PIN";
    const rateLimited = error.response?.status === 429;
    if (wrongPin || rateLimited) {
      // ให้ลองใหม่ใน PIN modal เดิม
      pinError.value = apiError(error, "Incorrect PIN");
      pin.value = "";
      pinLoading.value = false;
      return;
    }
    const fallback = pendingAction.value === "withdraw" ? "Withdrawal failed. Try again." : "Transfer failed. Try again.";
    const message = apiError(error, fallback);
    closePinModal();
    errorMessage.value = message;
  }
};


</script>

<template>
  <div>
    <!-- ปุ่มทำรายการ -->
    <div class="flex flex-wrap items-center gap-2">
      <button type="button" @click="openDepositModal" class="btn btn-credit"><ArrowDownToLine class="h-4 w-4" /> Deposit</button>
      <button type="button" @click="openWithdrawModal" class="btn btn-debit"><ArrowUpFromLine class="h-4 w-4" /> Withdraw</button>
      <button type="button" @click="openTransferModal" class="btn btn-primary"><Send class="h-4 w-4" /> Transfer</button>
    </div>

    <!-- Deposit -->
    <div v-if="isDepositModalOpen" class="modal-backdrop z-50" role="dialog" aria-modal="true" aria-labelledby="deposit-title">
      <div class="modal-panel max-w-sm">
        <div class="mb-4 flex items-center justify-between">
          <h2 id="deposit-title" class="font-display text-xl font-semibold">Deposit money</h2>
          <button type="button" @click="closeModal" class="rounded-md p-1 text-muted hover:bg-paper hover:text-ink" aria-label="Close"><X class="h-5 w-5" /></button>
        </div>

        <label for="deposit-amount" class="mb-1 block text-sm font-medium">Amount (THB)</label>
        <input id="deposit-amount" type="number" v-model="amount" class="field" placeholder="0.00" />

        <p v-if="successMessage" class="mt-3 flex items-center gap-2 text-sm text-credit"><CircleCheck class="h-4 w-4" /> {{ successMessage }}</p>
        <p v-if="errorMessage" role="alert" class="mt-3 flex items-center gap-2 text-sm text-debit"><CircleAlert class="h-4 w-4" /> {{ errorMessage }}</p>

        <button type="button" @click="submitDeposit" class="btn btn-credit mt-5 w-full">Deposit</button>
      </div>
    </div>

    <!-- Withdraw -->
    <div v-if="isWithdrawModalOpen" class="modal-backdrop z-50" role="dialog" aria-modal="true" aria-labelledby="withdraw-title">
      <div class="modal-panel max-w-sm">
        <div class="mb-4 flex items-center justify-between">
          <h2 id="withdraw-title" class="font-display text-xl font-semibold">Withdraw money</h2>
          <button type="button" @click="closeModal" class="rounded-md p-1 text-muted hover:bg-paper hover:text-ink" aria-label="Close"><X class="h-5 w-5" /></button>
        </div>

        <label for="withdraw-amount" class="mb-1 block text-sm font-medium">Amount (THB)</label>
        <input id="withdraw-amount" type="number" v-model="amount" class="field" placeholder="0.00" />

        <p v-if="successMessage" class="mt-3 flex items-center gap-2 text-sm text-credit"><CircleCheck class="h-4 w-4" /> {{ successMessage }}</p>
        <p v-if="errorMessage" role="alert" class="mt-3 flex items-center gap-2 text-sm text-debit"><CircleAlert class="h-4 w-4" /> {{ errorMessage }}</p>

        <button type="button" @click="submitWithdraw" class="btn btn-debit mt-5 w-full">Withdraw</button>
      </div>
    </div>

    <!-- Transfer -->
    <div v-if="isTransferModalOpen" class="modal-backdrop z-50" role="dialog" aria-modal="true" aria-labelledby="transfer-title">
      <div class="modal-panel max-w-sm">
        <div class="mb-4 flex items-center justify-between">
          <h2 id="transfer-title" class="font-display text-xl font-semibold">Transfer money</h2>
          <button type="button" @click="closeModal" class="rounded-md p-1 text-muted hover:bg-paper hover:text-ink" aria-label="Close"><X class="h-5 w-5" /></button>
        </div>

        <label for="transfer-amount" class="mb-1 block text-sm font-medium">Amount (THB)</label>
        <input id="transfer-amount" type="number" v-model="amount" class="field" placeholder="0.00" />
        <p v-if="transferLimit" class="mt-1.5 text-sm text-muted">
          Daily limit ฿{{ formatMoney(transferLimit.limit) }}. Remaining today
          <span class="font-medium text-ink">฿{{ formatMoney(transferLimit.remaining) }}</span>, resets at midnight.
        </p>

        <label for="transfer-target" class="mb-1 mt-4 block text-sm font-medium">To account</label>
        <input id="transfer-target" type="text" v-model="targetAccountId" class="field" placeholder="Account number" />

        <!-- บัญชีที่บันทึกไว้ -->
        <div v-if="savedAccounts.length" class="mt-4">
          <p class="mb-1.5 flex items-center gap-1.5 text-sm text-muted"><Star class="h-4 w-4" /> Saved accounts</p>
          <div class="flex max-h-28 flex-wrap gap-2 overflow-y-auto">
            <button
              v-for="a in savedAccounts"
              :key="a.id"
              type="button"
              @click="pickSaved(a)"
              class="rounded-md border px-3 py-1.5 text-left text-sm transition-colors"
              :class="String(a.account_id) === String(targetAccountId).trim() ? 'border-bank bg-bank/10' : 'border-rule bg-sheet hover:bg-paper'"
            >
              <span class="font-medium">{{ a.nickname || a.holder_name || "Account" }}</span>
              <span class="block text-xs text-muted">{{ a.account_id }}</span>
            </button>
          </div>
        </div>

        <!-- บันทึกบัญชีนี้ -->
        <div v-if="targetAccountId && !isTargetSaved" class="mt-4">
          <label class="flex cursor-pointer items-center gap-2 text-sm">
            <input type="checkbox" v-model="saveRecipient" class="h-4 w-4 accent-[#1f5fbf]" />
            <span>Save this account for next time</span>
          </label>
          <input v-if="saveRecipient" type="text" v-model="recipientNickname" maxlength="50" class="field mt-2" placeholder="Nickname (optional)" />
        </div>

        <p v-if="successMessage" class="mt-3 flex items-center gap-2 text-sm text-credit"><CircleCheck class="h-4 w-4" /> {{ successMessage }}</p>
        <p v-if="errorMessage" role="alert" class="mt-3 flex items-center gap-2 text-sm text-debit"><CircleAlert class="h-4 w-4" /> {{ errorMessage }}</p>

        <button type="button" @click="submitTransfer" :disabled="lookupLoading" class="btn btn-primary mt-5 w-full">
          {{ lookupLoading ? "Checking account..." : "Review transfer" }}
        </button>
      </div>
    </div>

    <!-- ยืนยันผู้รับ (ซ้อนบน Transfer) -->
    <div v-if="isConfirmModalOpen && recipient" class="modal-backdrop z-[55]" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
      <div class="modal-panel max-w-sm">
        <h2 id="confirm-title" class="mb-4 font-display text-xl font-semibold">Check the recipient</h2>

        <dl class="space-y-3 rounded-md border border-rule bg-paper p-4 text-sm">
          <div>
            <dt class="text-muted">Recipient</dt>
            <dd class="font-display text-lg font-semibold">{{ recipient.holder_name }}</dd>
          </div>
          <div>
            <dt class="text-muted">Account number</dt>
            <dd class="font-medium">{{ recipient.account_id }}</dd>
          </div>
          <div class="border-t border-rule pt-3">
            <dt class="text-muted">Amount</dt>
            <dd class="font-display text-2xl font-semibold">฿{{ formatMoney(amount) }}</dd>
          </div>
        </dl>

        <p class="mt-3 text-sm text-muted">Transfers can't be undone, so make sure the name and account are right.</p>

        <div class="mt-5 flex gap-3">
          <button type="button" @click="isConfirmModalOpen = false" class="btn btn-quiet flex-1">Back</button>
          <button type="button" @click="confirmRecipient" class="btn btn-primary flex-1">Enter PIN</button>
        </div>
      </div>
    </div>

    <!-- สลิป/ใบเสร็จหลังทำรายการ -->
    <ReceiptModal v-if="receiptId" :transaction-id="receiptId" auto-download @close="receiptId = null" />

    <!-- PIN (ซ้อนบน Withdraw/Transfer) -->
    <div v-if="isPinModalOpen" class="modal-backdrop z-[60]" role="dialog" aria-modal="true" aria-labelledby="pin-title">
      <div class="modal-panel max-w-xs">
        <div class="mb-1 flex items-center justify-between">
          <h2 id="pin-title" class="font-display text-xl font-semibold">Enter your PIN</h2>
          <button type="button" @click="closePinModal" class="rounded-md p-1 text-muted hover:bg-paper hover:text-ink" aria-label="Close"><X class="h-5 w-5" /></button>
        </div>
        <p class="mb-5 text-sm text-muted">
          Confirm {{ pendingAction === "withdraw" ? "withdrawing" : "transferring" }} ฿{{ formatMoney(amount) }} with your 6-digit PIN.
        </p>

        <div class="mb-2 flex justify-center gap-3" aria-hidden="true">
          <span
            v-for="n in PIN_LENGTH"
            :key="n"
            class="h-3.5 w-3.5 rounded-full border-2 transition-colors"
            :class="n <= pin.length ? 'border-ink bg-ink' : 'border-rule'"
          ></span>
        </div>
        <p class="h-5 text-center text-sm text-debit" role="alert">{{ pinError }}</p>

        <div class="mt-2 grid grid-cols-3 gap-2">
          <button
            v-for="key in pinKeys"
            :key="key"
            type="button"
            @click="pressKey(key)"
            :disabled="pinLoading"
            class="flex h-14 items-center justify-center rounded-md border border-rule bg-sheet font-display text-xl font-medium transition-colors hover:bg-paper active:bg-rule disabled:opacity-50"
            :aria-label="key === 'back' ? 'Delete last digit' : key === 'clear' ? 'Clear PIN' : key"
          >
            <Delete v-if="key === 'back'" class="h-5 w-5" />
            <span v-else-if="key === 'clear'" class="text-sm">Clear</span>
            <template v-else>{{ key }}</template>
          </button>
        </div>

        <button type="button" @click="submitPin" :disabled="pin.length !== PIN_LENGTH || pinLoading" class="btn btn-primary mt-4 w-full">
          {{ pinLoading ? "Processing..." : "Confirm" }}
        </button>
      </div>
    </div>
  </div>
</template>
