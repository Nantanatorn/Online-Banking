<script setup>
import { ref, computed } from "vue";
import ReceiptModal from "@/components/ReceiptModal.vue";
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
    <!-- ✅ Navbar -->
    <div class="flex items-center space-x-4">

      <!-- ✅ Action Buttons -->
      <button
        @click="openDepositModal"
        class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-full shadow-md transition"
      >
        Deposit
      </button>
      <button
        @click="openWithdrawModal"
        class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-full shadow-md transition"
      >
        Withdraw
      </button>
      <button
        @click="openTransferModal"
        class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-full shadow-md transition"
      >
        Transfer
      </button>
    </div>

    <!-- ✅ Deposit Modal -->
    <div v-if="isDepositModalOpen" class="fixed inset-0 flex items-center justify-center bg-black/40 backdrop-blur-sm z-50">
      <div class="bg-white p-6 rounded-lg shadow-lg w-96 text-gray-800">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold">Deposit Money</h2>
          <button @click="closeModal" class="text-gray-600 hover:text-red-500">✖</button>
        </div>

        <label class="block text-sm font-medium">Amount (THB):</label>
        <input type="number" v-model="amount" class="w-full px-4 py-2 border rounded-lg focus:ring focus:ring-green-300" placeholder="Enter deposit amount" />

        <p v-if="successMessage" class="text-green-600 mt-2">✅ {{ successMessage }}</p>
        <p v-if="errorMessage" class="text-red-500 mt-2">❌ {{ errorMessage }}</p>

        <button @click="submitDeposit" class="bg-green-600 hover:bg-green-700 text-white w-full mt-4 py-2 rounded-lg">
          Confirm Deposit
        </button>
      </div>
    </div>

    <!-- ✅ Withdraw Modal -->
    <div v-if="isWithdrawModalOpen" class="fixed inset-0 flex items-center justify-center bg-black/40 backdrop-blur-sm z-50">
      <div class="bg-white p-6 rounded-lg shadow-lg w-96 text-gray-800">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold">Withdraw Money</h2>
          <button @click="closeModal" class="text-gray-600 hover:text-red-500">✖</button>
        </div>

        <label class="block text-sm font-medium">Amount (THB):</label>
        <input type="number" v-model="amount" class="w-full px-4 py-2 border rounded-lg focus:ring focus:ring-red-300" placeholder="Enter withdrawal amount" />

        <p v-if="successMessage" class="text-green-600 mt-2">✅ {{ successMessage }}</p>
        <p v-if="errorMessage" class="text-red-500 mt-2">❌ {{ errorMessage }}</p>

        <button @click="submitWithdraw" class="bg-red-600 hover:bg-red-700 text-white w-full mt-4 py-2 rounded-lg">
          Confirm Withdraw
        </button>
      </div>
    </div>

    <!-- ✅ Transfer Modal -->
    <div v-if="isTransferModalOpen" class="fixed inset-0 flex items-center justify-center bg-black/40 backdrop-blur-sm z-50">
      <div class="bg-white p-6 rounded-lg shadow-lg w-96 text-gray-800">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold">Transfer Money</h2>
          <button @click="closeModal" class="text-gray-600 hover:text-red-500">✖</button>
        </div>

        <label class="block text-sm font-medium">Amount (THB):</label>
        <input type="number" v-model="amount" class="w-full px-4 py-2 border rounded-lg focus:ring focus:ring-blue-300" placeholder="Enter transfer amount" />
        <p v-if="transferLimit" class="text-xs text-gray-500 mt-1">
          Daily limit ฿{{ formatMoney(transferLimit.limit) }} · Remaining today
          <span class="font-semibold text-blue-600">฿{{ formatMoney(transferLimit.remaining) }}</span>
          (resets at midnight)
        </p>

        <label class="block text-sm font-medium mt-3">Target Account ID:</label>
        <input type="text" v-model="targetAccountId" class="w-full px-4 py-2 border rounded-lg focus:ring focus:ring-blue-300" placeholder="Enter account ID" />

        <!-- ⭐ บัญชีที่บันทึกไว้ -->
        <div v-if="savedAccounts.length" class="mt-3">
          <p class="text-xs text-gray-500 mb-1">⭐ Saved accounts</p>
          <div class="flex flex-wrap gap-2 max-h-28 overflow-y-auto">
            <button
              v-for="a in savedAccounts"
              :key="a.id"
              type="button"
              @click="pickSaved(a)"
              class="text-left px-3 py-1 rounded-lg border text-sm transition"
              :class="String(a.account_id) === String(targetAccountId).trim() ? 'bg-blue-100 border-blue-500' : 'bg-gray-50 hover:bg-gray-100'"
            >
              <span class="font-semibold">{{ a.nickname || a.holder_name || "Account" }}</span>
              <span class="block text-xs text-gray-500">{{ a.account_id }}</span>
            </button>
          </div>
        </div>

        <!-- ☆ บันทึกบัญชีนี้ -->
        <div v-if="targetAccountId && !isTargetSaved" class="mt-3">
          <label class="flex items-center space-x-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="saveRecipient" />
            <span>☆ Save this account for next time</span>
          </label>
          <input
            v-if="saveRecipient"
            type="text"
            v-model="recipientNickname"
            maxlength="50"
            class="w-full mt-2 px-3 py-1 border rounded-lg text-sm focus:ring focus:ring-blue-300"
            placeholder="Nickname (optional)"
          />
        </div>

        <p v-if="successMessage" class="text-green-600 mt-2">✅ {{ successMessage }}</p>
        <p v-if="errorMessage" class="text-red-500 mt-2">❌ {{ errorMessage }}</p>

        <button @click="submitTransfer" :disabled="lookupLoading" class="bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white w-full mt-4 py-2 rounded-lg">
          {{ lookupLoading ? "Checking account..." : "Confirm Transfer" }}
        </button>
      </div>
    </div>

    <!-- ✅ Confirm Recipient Modal (ซ้อนบน Transfer) -->
    <div v-if="isConfirmModalOpen && recipient" class="fixed inset-0 flex items-center justify-center bg-black/40 backdrop-blur-sm z-[55]">
      <div class="bg-white p-6 rounded-lg shadow-lg w-96 text-gray-800">
        <h2 class="text-xl font-semibold mb-4">Confirm Transfer</h2>

        <div class="bg-gray-50 rounded-lg p-4 space-y-3 text-sm">
          <div>
            <p class="text-gray-500">Recipient</p>
            <p class="text-lg font-bold">{{ recipient.holder_name }}</p>
          </div>
          <div>
            <p class="text-gray-500">Account ID</p>
            <p class="font-semibold">{{ recipient.account_id }}</p>
          </div>
          <div>
            <p class="text-gray-500">Amount</p>
            <p class="text-2xl font-bold text-blue-600">฿{{ formatMoney(amount) }}</p>
          </div>
        </div>

        <p class="text-xs text-gray-500 mt-3">Please check the recipient carefully. Transfers cannot be undone.</p>

        <div class="flex space-x-3 mt-4">
          <button @click="isConfirmModalOpen = false" class="flex-1 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition">Back</button>
          <button @click="confirmRecipient" class="flex-1 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition">Continue</button>
        </div>
      </div>
    </div>

    <!-- ✅ สลิป/ใบเสร็จหลังทำรายการ -->
    <ReceiptModal v-if="receiptId" :transaction-id="receiptId" auto-download @close="receiptId = null" />

    <!-- ✅ PIN Modal (ซ้อนบน Withdraw/Transfer) -->
    <div v-if="isPinModalOpen" class="fixed inset-0 flex items-center justify-center bg-black/40 backdrop-blur-sm z-[60]">
      <div class="bg-white p-6 rounded-lg shadow-lg w-80 text-gray-800">
        <div class="flex justify-between items-center mb-2">
          <h2 class="text-xl font-semibold">Enter PIN</h2>
          <button @click="closePinModal" class="text-gray-600 hover:text-red-500">✖</button>
        </div>
        <p class="text-sm text-gray-500 mb-4">
          Confirm {{ pendingAction === "withdraw" ? "withdrawal" : "transfer" }} of ฿{{ formatMoney(amount) }} with your 6-digit PIN.
        </p>

        <div class="flex justify-center space-x-3 mb-2">
          <span
            v-for="n in PIN_LENGTH"
            :key="n"
            class="h-4 w-4 rounded-full border-2 transition"
            :class="n <= pin.length ? 'bg-blue-600 border-blue-600' : 'border-gray-400'"
          ></span>
        </div>
        <p class="text-red-500 text-sm text-center h-5">{{ pinError }}</p>

        <div class="grid grid-cols-3 gap-3 mt-2">
          <button
            v-for="key in pinKeys"
            :key="key"
            @click="pressKey(key)"
            :disabled="pinLoading"
            class="h-12 rounded-lg bg-gray-100 hover:bg-gray-200 active:bg-gray-300 text-xl font-semibold transition disabled:opacity-50"
          >
            <template v-if="key === 'back'">⌫</template>
            <template v-else-if="key === 'clear'"><span class="text-sm">Clear</span></template>
            <template v-else>{{ key }}</template>
          </button>
        </div>

        <button
          @click="submitPin"
          :disabled="pin.length !== PIN_LENGTH || pinLoading"
          class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 text-white w-full mt-4 py-2 rounded-lg transition"
        >
          {{ pinLoading ? "Processing..." : "Confirm" }}
        </button>
      </div>
    </div>
  </div>
</template>
