<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import Accsidebar from "@/components/Accsidebar.vue";
import Tranbutton from "@/components/Tranbutton.vue";
import NotificationBell from "@/components/NotificationBell.vue";
import ReceiptModal from "@/components/ReceiptModal.vue";
import HistoryFilters from "@/components/HistoryFilters.vue";
import HistoryPagination from "@/components/HistoryPagination.vue";
import { useTransactionHistory } from "@/composables/useTransactionHistory";
import foot from "@/components/foot.vue";
import { authHeaders, requireLogin, handleAuthError, toNumber, isIncoming, formatMoney } from "@/composables/useAuth";
import { format } from "date-fns";

// ✅ ตัวแปรเก็บข้อมูล
const isSidebarOpen = ref(false);
const userName = ref("Loading...");
const bankAccounts = ref([]);

// ✅ ประวัติธุรกรรม: ตัวกรอง + แบ่งหน้า
const { transactions, pagination, filters, loading: historyLoading, filterError, hasFilters, load: fetchTransactions, applyFilters, clearFilters, goToPage } =
  useTransactionHistory({ perPage: 10 });

// ✅ Toggle Sidebar
const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value;
};

// ✅ ดึงข้อมูลผู้ใช้
const fetchUser = async () => {
  try {
    const response = await axios.get("/api/user", { headers: authHeaders() });

    userName.value = `${response.data.firstname} ${response.data.lastname}`.trim() || "User";
  } catch (error) {
    if (handleAuthError(error)) return;
    console.error("Error fetching user:", error);
    userName.value = "Guest";
  }
};

// ✅ ดึงข้อมูลบัญชีทั้งหมด
const fetchBankAccounts = async () => {
  try {
    const response = await axios.get("/api/balance", { headers: authHeaders() });

    bankAccounts.value = response.data.bank_accounts;
  } catch (error) {
    if (handleAuthError(error)) return;
    console.error("Error fetching bank accounts:", error);
  }
};

// ✅ บัญชีที่บันทึกไว้ (bookmark)
const savedAccounts = ref([]);
const tranButton = ref(null);
const receiptId = ref(null); // เปิดสลิปจากตารางประวัติ
const bell = ref(null);

const fetchSavedAccounts = async () => {
  try {
    const response = await axios.get("/api/saved-accounts", { headers: authHeaders() });
    savedAccounts.value = response.data.saved_accounts;
  } catch (error) {
    if (handleAuthError(error)) return;
    console.error("Error fetching saved accounts:", error);
  }
};

const removeSavedAccount = async (id) => {
  try {
    await axios.delete(`/api/saved-accounts/${id}`, { headers: authHeaders() });
    savedAccounts.value = savedAccounts.value.filter((a) => a.id !== id);
  } catch (error) {
    if (handleAuthError(error)) return;
    console.error("Error removing saved account:", error);
  }
};

// ✅ แปลงวันที่
const formatDate = (dateString) => {
  return format(new Date(dateString), "yyyy-MM-dd HH:mm");
};

// ✅ ดึงข้อมูลทั้งหมดเมื่อ Component ถูกโหลด
const refreshAll = async () => {
  await fetchUser();
  await fetchBankAccounts();
  await fetchTransactions();
  await fetchSavedAccounts();
  bell.value?.refresh();
};

onMounted(() => {
  if (!requireLogin()) return;
  refreshAll();
});
</script>

<template>
  <div class="flex min-h-screen bg-gradient-to-br from-blue-900 to-teal-700 text-white">
    <!-- ✅ Sidebar -->
    <Accsidebar @click="toggleSidebar" />

    <!-- ✅ Main Content -->
    <div class="flex-1 p-6 space-y-6">
      
      <!-- 🔹 Header -->
      <div class="flex justify-between items-center bg-white text-blue-900 p-5 rounded-xl shadow-lg">
        <h1 class="text-3xl font-bold">🏦 Bank Dashboard</h1>
        <div class="flex items-center space-x-4">
          <NotificationBell ref="bell" @new-activity="refreshAll" />
          <Tranbutton ref="tranButton" @done="refreshAll" />
        </div>
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
            <p class="text-2xl font-bold text-green-600">฿{{ formatMoney(account.balance) }}</p>

            <h3 class="text-lg font-semibold mt-3">Interest</h3>
            <p class="text-2xl font-bold text-blue-500">{{ account.interest }}%</p>
          </div>

          <!-- ⭐ Saved Accounts -->
          <div class="bg-gray-100 p-6 rounded-lg shadow-md lg:col-span-2">
            <h3 class="text-lg font-semibold mb-1">⭐ Saved Accounts</h3>
            <p v-if="savedAccounts.length" class="text-xs text-gray-500 mb-3">Click an account to transfer to it</p>
            <p v-if="!savedAccounts.length" class="text-gray-500 text-sm">
              No saved accounts yet. Tick "Save this account" when you transfer.
            </p>
            <ul v-else class="space-y-2 max-h-72 overflow-y-auto">
              <li
                v-for="a in savedAccounts"
                :key="a.id"
                @click="tranButton?.openTransferTo(a.account_id)"
                class="flex items-center justify-between bg-white rounded-lg px-4 py-2 shadow-sm cursor-pointer hover:bg-blue-50 hover:shadow-md transition"
                title="Click to transfer"
              >
                <div>
                  <p class="font-semibold text-gray-800">{{ a.nickname || a.holder_name || "Account" }}</p>
                  <p class="text-sm text-gray-500">
                    {{ a.account_id }}<span v-if="a.nickname && a.holder_name"> · {{ a.holder_name }}</span>
                  </p>
                </div>
                <div class="flex items-center space-x-3">
                  <span class="text-sm font-semibold text-blue-500">Transfer →</span>
                  <button @click.stop="removeSavedAccount(a.id)" class="text-gray-400 hover:text-red-500 transition" title="Remove">✖</button>
                </div>
              </li>
            </ul>
          </div>

        </div>
      </div>

      <!-- 🔹 Transaction History -->
      <div class="bg-white p-6 rounded-xl shadow-lg text-blue-900">
        <h2 class="text-2xl font-semibold mb-4">📜 Transaction History</h2>
        <HistoryFilters :filters="filters" :has-filters="hasFilters" :error="filterError" @change="applyFilters" @clear="clearFilters" />
        <div class="overflow-x-auto">
          <table class="w-full border-collapse rounded-lg overflow-hidden">
            <thead>
              <tr class="bg-blue-500 text-white">
                <th class="p-3 text-left">📅 Date</th>
                <th class="p-3 text-left">🔢 Account ID</th>
                <th class="p-3 text-left">💰 Type</th>
                <th class="p-3 text-right">💲 Amount</th>
                <th class="p-3 text-right">💼 Balance</th>
                <th class="p-3 text-center">🧾 Receipt</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!transactions.length">
                <td colspan="6" class="p-6 text-center text-gray-500">
                  {{ hasFilters ? "No transactions match your filters." : "No transactions yet." }}
                </td>
              </tr>
              <tr v-for="transaction in transactions" :key="transaction.id" class="border-b hover:bg-gray-100">
                <td class="p-3">{{ formatDate(transaction.created_at) }}</td>
                <td class="p-3">{{ transaction.account_id }}</td>
                <td class="p-3 capitalize">
                  <span v-if="transaction.type === 'deposit'" class="text-green-600 font-semibold">Deposit</span>
                  <span v-else-if="transaction.type === 'withdraw'" class="text-red-600 font-semibold">Withdraw</span>
                  <span v-else-if="transaction.type === 'transfer'" class="text-blue-600 font-semibold">Transfer</span>
                  <span v-else-if="transaction.type === 'receive'" class="text-purple-600 font-semibold">Receive</span>
                </td>
                <td class="p-3 text-right font-bold" :class="isIncoming(transaction.type) ? 'text-green-500' : 'text-red-500'">
                  {{ isIncoming(transaction.type) ? "+" : "-" }}฿{{ formatMoney(transaction.amount) }}
                </td>
                <td class="p-3 text-right font-bold">{{ transaction.balance == null ? "-" : "฿" + formatMoney(transaction.balance) }}</td>
                <td class="p-3 text-center">
                  <button @click="receiptId = transaction.id" class="text-blue-600 hover:underline text-sm font-semibold">View</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <HistoryPagination :pagination="pagination" :loading="historyLoading" @page="goToPage" />
      </div>

    </div>
  </div>

  <ReceiptModal v-if="receiptId" :transaction-id="receiptId" @close="receiptId = null" />

  <foot />
</template>
