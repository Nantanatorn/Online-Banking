<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
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
import { Chart } from "chart.js/auto";
import { format } from "date-fns"; // ✅ ใช้ date-fns เพื่อจัดรูปแบบวันที่

// ✅ ตัวแปรเก็บข้อมูล
const isSidebarOpen = ref(false);
const userName = ref("Loading...");
// ✅ ประวัติธุรกรรม: ตัวกรอง + แบ่งหน้า (ยอดรวมของกราฟมาจาก server ตามตัวกรองทั้งชุด ไม่ใช่แค่หน้านี้)
const { transactions, totals, pagination, filters, loading: historyLoading, filterError, hasFilters, load, applyFilters, clearFilters, goToPage } =
  useTransactionHistory({ perPage: 10 });
const depositTotal = ref(0);
const withdrawTotal = ref(0);
const transferTotal = ref(0);
const receiveTotal = ref(0);
let transactionChart = null; // 🔹 เก็บอ้างอิง Pie Chart

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
    console.error("Error fetching user:", error.response ? error.response.data : error.message);
    userName.value = "Guest";
  }
};

// ✅ นำยอดรวมจาก server มาวาดกราฟ
const syncTotals = () => {
  depositTotal.value = toNumber(totals.value.deposit);
  withdrawTotal.value = toNumber(totals.value.withdraw);
  transferTotal.value = toNumber(totals.value.transfer);
  receiveTotal.value = toNumber(totals.value.receive);
  renderPieChart();
};

// ✅ โหลดประวัติ (หน้าปัจจุบัน) แล้วอัปเดตกราฟ
const fetchTransactions = async () => {
  if (await load()) syncTotals();
};

// ✅ เปลี่ยนตัวกรอง / ล้างตัวกรอง / เปลี่ยนหน้า
const onFilterChange = async () => {
  if (await applyFilters()) syncTotals();
};
const onClearFilters = async () => {
  if (await clearFilters()) syncTotals();
};
const onPage = async (page) => {
  if (await goToPage(page)) syncTotals();
};

// ✅ แสดง Pie Chart (พร้อมแก้ขนาด)
const renderPieChart = () => {
  setTimeout(() => {
    const ctx = document.getElementById("transactionPieChart");
    if (!ctx) return;

    if (transactionChart) {
      transactionChart.destroy(); // 🔹 ลบกราฟเก่าก่อนสร้างใหม่
    }

    transactionChart = new Chart(ctx, {
      type: "pie",
      data: {
        labels: ["Deposits", "Withdrawals", "Transfers", "Received"],
        datasets: [
          {
            data: [depositTotal.value, withdrawTotal.value, transferTotal.value, receiveTotal.value],
            backgroundColor: ["#4CAF50", "#FF5733", "#3498db", "#9b59b6"], // 🟢🟥🔵
            hoverOffset: 4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false, // ✅ ป้องกันไม่ให้กราฟหายไป
      },
    });
  }, 500);
};

// ✅ ฟังก์ชันแปลงวันที่ให้เป็น `DD/MM/YYYY HH:mm`
const formatDate = (dateString) => {
  return format(new Date(dateString), "dd/MM/yyyy HH:mm");
};

// ✅ ดึงข้อมูลทั้งหมดเมื่อ Component ถูกโหลด
const bell = ref(null);
const receiptId = ref(null); // เปิดสลิปจากตารางประวัติ

const refreshAll = async () => {
  await fetchUser();
  await fetchTransactions();
  bell.value?.refresh();
};

onMounted(() => {
  if (!requireLogin()) return;
  refreshAll();
});

onBeforeUnmount(() => {
  if (transactionChart) transactionChart.destroy();
});
</script>

<template>
  <div class="flex min-h-screen bg-gradient-to-br from-blue-900 to-teal-700 text-white">
    <!-- ✅ Sidebar -->
    <Accsidebar @click="toggleSidebar" />

    <!-- ✅ Main Content -->
    <div class="flex-1 p-6">
      <!-- 🔹 Header -->
      <div class="flex justify-between items-center bg-white text-blue-900 p-4 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold">Transaction Dashboard</h1>
        <div class="flex items-center space-x-4">
          <NotificationBell ref="bell" @new-activity="refreshAll" />
          <Tranbutton @done="refreshAll" />
        </div>
      </div>

      <!-- 🔹 Pie Chart -->
      <div class="bg-white p-6 rounded-lg shadow-md mt-6 text-blue-900">
        <h2 class="text-2xl font-semibold mb-4">Transaction Overview</h2>
        <div class="w-64 h-64 mx-auto">
          <canvas id="transactionPieChart"></canvas>
        </div>
      </div>

      <!-- 🔹 Transaction History -->
      <div class="bg-white p-6 rounded-lg shadow-md mt-6 text-blue-900">
        <h2 class="text-2xl font-semibold mb-4">Transaction History</h2>
        <HistoryFilters :filters="filters" :has-filters="hasFilters" :error="filterError" @change="onFilterChange" @clear="onClearFilters" />
        <table class="w-full border-collapse border border-gray-300 text-sm">
          <thead>
            <tr class="bg-blue-200 text-blue-900">
              <th class="p-2 border border-gray-300">Date</th>
              <th class="p-2 border border-gray-300">Account ID</th>
              <th class="p-2 border border-gray-300">Type</th>
              <th class="p-2 border border-gray-300">Amount</th>
              <th class="p-2 border border-gray-300">Balance</th>
              <th class="p-2 border border-gray-300">Receipt</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!transactions.length">
              <td colspan="6" class="p-6 text-center text-gray-500">
                {{ hasFilters ? "No transactions match your filters." : "No transactions yet." }}
              </td>
            </tr>
            <tr v-for="transaction in transactions" :key="transaction.id" class="text-center">
              <td class="p-2 border border-gray-300">{{ formatDate(transaction.created_at) }}</td>
              <td class="p-2 border border-gray-300">{{ transaction.account_id }}</td>
              <td class="p-2 border border-gray-300 capitalize">
                <span v-if="transaction.type === 'deposit'" class="text-green-500 font-semibold">Deposit</span>
                <span v-else-if="transaction.type === 'withdraw'" class="text-red-500 font-semibold">Withdraw</span>
                <span v-else-if="transaction.type === 'transfer'" class="text-blue-500 font-semibold">Transfer</span>
                <span v-else-if="transaction.type === 'receive'" class="text-purple-500 font-semibold">Receive</span>
              </td>
              <td class="p-2 border border-gray-300" :class="isIncoming(transaction.type) ? 'text-green-500' : 'text-red-500'">
                {{ isIncoming(transaction.type) ? "+" : "-" }}฿{{ formatMoney(transaction.amount) }}
              </td>
              <td class="p-2 border border-gray-300 font-bold">{{ transaction.balance == null ? "-" : "฿" + formatMoney(transaction.balance) }}</td>
              <td class="p-2 border border-gray-300">
                <button @click="receiptId = transaction.id" class="text-blue-600 hover:underline text-sm font-semibold">View</button>
              </td>
            </tr>
          </tbody>
        </table>
        <HistoryPagination :pagination="pagination" :loading="historyLoading" @page="onPage" />
      </div>
    </div>
  </div>

  <ReceiptModal v-if="receiptId" :transaction-id="receiptId" @close="receiptId = null" />

  <foot />
</template>
