<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import Accsidebar from "@/components/Accsidebar.vue";
import Tranbutton from "@/components/Tranbutton.vue";
import foot from "@/components/foot.vue";
import { format } from "date-fns";

// ✅ ตัวแปรเก็บข้อมูล
const isSidebarOpen = ref(false);
const userName = ref("Loading...");
const bankAccounts = ref([]);
const transactions = ref([]);
const depositTotal = ref(0);
const withdrawTotal = ref(0);
const transferTotal = ref(0);

// ✅ Toggle Sidebar
const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value;
};

// ✅ ดึงข้อมูลผู้ใช้
const fetchUser = async () => {
  try {
    const token = localStorage.getItem("token");
    if (!token) return (userName.value = "Guest");

    const response = await axios.get("/api/user", {
      headers: { Authorization: `Bearer ${token}` },
    });

    userName.value = response.data.firstname + " " + response.data.lastname || "User";
  } catch (error) {
    console.error("Error fetching user:", error);
    userName.value = "Guest";
  }
};

// ✅ ดึงข้อมูลบัญชีทั้งหมด
const fetchBankAccounts = async () => {
  try {
    const token = localStorage.getItem("token");
    if (!token) return;

    const response = await axios.get("/api/balance", {
      headers: { Authorization: `Bearer ${token}` },
    });

    bankAccounts.value = response.data.bank_accounts;
  } catch (error) {
    console.error("Error fetching bank accounts:", error);
  }
};

// ✅ ดึงประวัติธุรกรรม
const fetchTransactions = async () => {
  try {
    const token = localStorage.getItem("token");
    if (!token) return;

    const response = await axios.get("/api/transactionhis", {
      headers: { Authorization: `Bearer ${token}` },
    });

    transactions.value = response.data.transactions;
    calculateTransactionTotals();
  } catch (error) {
    console.error("Error fetching transactions:", error);
  }
};

// ✅ คำนวณยอดรวม
const calculateTransactionTotals = () => {
  depositTotal.value = transactions.value
    .filter((t) => t.type === "deposit")
    .reduce((sum, t) => sum + t.amount, 0);

  withdrawTotal.value = transactions.value
    .filter((t) => t.type === "withdraw")
    .reduce((sum, t) => sum + Math.abs(t.amount), 0);

  transferTotal.value = transactions.value
    .filter((t) => t.type === "transfer")
    .reduce((sum, t) => sum + Math.abs(t.amount), 0);
};

// ✅ แปลงวันที่
const formatDate = (dateString) => {
  return format(new Date(dateString), "yyyy-MM-dd HH:mm");
};

// ✅ ดึงข้อมูลทั้งหมดเมื่อ Component ถูกโหลด
onMounted(async () => {
  await fetchUser();
  await fetchBankAccounts();
  await fetchTransactions();
});
</script>

<template>
  <div class="flex h-screen bg-gradient-to-br from-blue-900 to-teal-700 text-white">
    <!-- ✅ Sidebar -->
    <Accsidebar @click="toggleSidebar" />

    <!-- ✅ Main Content -->
    <div class="flex-1 p-6 space-y-6">
      
      <!-- 🔹 Header -->
      <div class="flex justify-between items-center bg-white text-blue-900 p-5 rounded-xl shadow-lg">
        <h1 class="text-3xl font-bold">🏦 Bank Dashboard</h1>
        <Tranbutton />
      </div>

      <!-- 🔹 Account Details -->
      <div class="bg-white p-6 rounded-xl shadow-lg text-blue-900">
        <h2 class="text-2xl font-semibold mb-4">💳 Bank Accounts</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          
          <div v-for="account in bankAccounts" :key="account.account_id" class="bg-gray-100 p-6 rounded-lg shadow-md text-center">
            <h3 class="text-lg font-semibold">Account Name</h3>
            <p class="text-xl font-bold text-gray-700">{{ userName }}</p>

            <h3 class="text-lg font-semibold mt-3">Account ID</h3>
            <p class="text-xl font-bold text-gray-700">{{ account.account_id }}</p>

            <h3 class="text-lg font-semibold mt-3">Balance</h3>
            <p class="text-2xl font-bold text-green-600">฿{{ account.balance }}</p>

            <h3 class="text-lg font-semibold mt-3">Interest</h3>
            <p class="text-2xl font-bold text-blue-500">฿{{ account.interest }}</p>
          </div>

        </div>
      </div>

      <!-- 🔹 Transaction History -->
      <div class="bg-white p-6 rounded-xl shadow-lg text-blue-900">
        <h2 class="text-2xl font-semibold mb-4">📜 Transaction History</h2>
        <div class="overflow-x-auto">
          <table class="w-full border-collapse rounded-lg overflow-hidden">
            <thead>
              <tr class="bg-blue-500 text-white">
                <th class="p-3 text-left">📅 Date</th>
                <th class="p-3 text-left">🔢 Account ID</th>
                <th class="p-3 text-left">💰 Type</th>
                <th class="p-3 text-right">💲 Amount</th>
                <th class="p-3 text-right">💼 Balance</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="transaction in transactions" :key="transaction.id" class="border-b hover:bg-gray-100">
                <td class="p-3">{{ formatDate(transaction.created_at) }}</td>
                <td class="p-3">{{ transaction.account_id }}</td>
                <td class="p-3 capitalize">
                  <span v-if="transaction.type === 'deposit'" class="text-green-600 font-semibold">Deposit</span>
                  <span v-else-if="transaction.type === 'withdraw'" class="text-red-600 font-semibold">Withdraw</span>
                  <span v-else-if="transaction.type === 'transfer'" class="text-blue-600 font-semibold">Transfer</span>
                </td>
                <td class="p-3 text-right font-bold" :class="transaction.amount > 0 ? 'text-green-500' : 'text-red-500'">
                  ฿{{ transaction.amount }}
                </td>
                <td class="p-3 text-right font-bold">฿{{ transaction.balance }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  <foot />
</template>
