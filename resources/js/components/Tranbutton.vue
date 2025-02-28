<script setup>
import { ref } from "vue";
import axios from "axios";

// ✅ Modal State
const isDepositModalOpen = ref(false);
const isWithdrawModalOpen = ref(false);
const isTransferModalOpen = ref(false);
const amount = ref("");
const targetAccountId = ref("");
const successMessage = ref("");
const errorMessage = ref("");

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
};

// ✅ Close Modal
const closeModal = () => {
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
};

// ✅ Submit Deposit
const submitDeposit = async () => {
  if (!amount.value || amount.value <= 0) {
    errorMessage.value = "Please enter a valid deposit amount.";
    return;
  }

  try {
    const token = localStorage.getItem("token");
    const response = await axios.post(
      "/api/deposit",
      { amount: parseFloat(amount.value) },
      { headers: { Authorization: `Bearer ${token}` } }
    );

    successMessage.value = response.data.message;
    setTimeout(closeModal, 1500);
  } catch (error) {
    errorMessage.value = error.response?.data?.error || "Deposit failed. Try again.";
  }
};

// ✅ Submit Withdraw
const submitWithdraw = async () => {
  if (!amount.value || amount.value <= 0) {
    errorMessage.value = "Please enter a valid withdrawal amount.";
    return;
  }

  try {
    const token = localStorage.getItem("token");
    const response = await axios.post(
      "/api/withdraw",
      { amount: parseFloat(amount.value) },
      { headers: { Authorization: `Bearer ${token}` } }
    );

    successMessage.value = response.data.message;
    setTimeout(closeModal, 1500);
  } catch (error) {
    errorMessage.value = error.response?.data?.error || "Withdrawal failed. Try again.";
  }
};

// ✅ Submit Transfer
const submitTransfer = async () => {
  if (!amount.value || amount.value <= 0 || !targetAccountId.value) {
    errorMessage.value = "Please enter a valid amount and target account ID.";
    return;
  }

  try {
    const token = localStorage.getItem("token");
    const userAccountId = localStorage.getItem("account_id"); // ✅ ต้องมีบัญชีต้นทาง

    const response = await axios.post(
    "/api/transfer", // ✅ แก้ให้ถูกต้อง
    {
      amount: parseFloat(amount.value),
      source_account_id: userAccountId, // ✅ บัญชีต้นทาง
      target_account_id: targetAccountId.value,
    },
    { headers: { Authorization: `Bearer ${token}` } }
  );

    successMessage.value = response.data.message;
    setTimeout(closeModal, 1500);
  } catch (error) {
    errorMessage.value = error.response?.data?.error || "Transfer failed. Try again.";
  }
};

</script>

<template>
  <div>
    <!-- ✅ Navbar -->
    <div class="flex items-center space-x-4">
      <i class="bi bi-search text-2xl cursor-pointer"></i>
      <i class="bi bi-bell text-2xl cursor-pointer"></i>

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
    <div v-if="isDepositModalOpen" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
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
    <div v-if="isWithdrawModalOpen" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
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
    <div v-if="isTransferModalOpen" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
      <div class="bg-white p-6 rounded-lg shadow-lg w-96 text-gray-800">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold">Transfer Money</h2>
          <button @click="closeModal" class="text-gray-600 hover:text-red-500">✖</button>
        </div>

        <label class="block text-sm font-medium">Amount (THB):</label>
        <input type="number" v-model="amount" class="w-full px-4 py-2 border rounded-lg focus:ring focus:ring-blue-300" placeholder="Enter transfer amount" />

        <label class="block text-sm font-medium mt-3">Target Account ID:</label>
        <input type="text" v-model="targetAccountId" class="w-full px-4 py-2 border rounded-lg focus:ring focus:ring-blue-300" placeholder="Enter account ID" />

        <p v-if="successMessage" class="text-green-600 mt-2">✅ {{ successMessage }}</p>
        <p v-if="errorMessage" class="text-red-500 mt-2">❌ {{ errorMessage }}</p>

        <button @click="submitTransfer" class="bg-blue-600 hover:bg-blue-700 text-white w-full mt-4 py-2 rounded-lg">
          Confirm Transfer
        </button>
      </div>
    </div>
  </div>
</template>
