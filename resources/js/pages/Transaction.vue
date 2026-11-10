<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import axios from "axios";
import Accsidebar from "@/components/Accsidebar.vue";
import Tranbutton from "@/components/Tranbutton.vue";
import NotificationBell from "@/components/NotificationBell.vue";
import ReceiptModal from "@/components/ReceiptModal.vue";
import HistoryFilters from "@/components/HistoryFilters.vue";
import Ledger from "@/components/Ledger.vue";
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
            backgroundColor: ["#1c8c5e", "#c23b3b", "#e39a9a", "#8ccfae"], // เงินเข้า = เขียว / เงินออก = แดง
            hoverOffset: 4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false, // ✅ ป้องกันไม่ให้กราฟหายไป
        plugins: { legend: { display: false } }, // ใช้รายการยอดข้างกราฟเป็นคำอธิบายแทน
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
  <div class="min-h-screen bg-paper text-ink md:flex">
    <Accsidebar @click="toggleSidebar" />

    <main class="min-w-0 flex-1 space-y-8 px-4 py-6 max-md:pb-24 md:px-10 md:py-8">
      <header class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="font-display text-2xl font-semibold md:text-3xl">Transactions</h1>
        <div class="flex flex-wrap items-center gap-3">
          <NotificationBell ref="bell" @new-activity="refreshAll" />
          <Tranbutton @done="refreshAll" />
        </div>
      </header>

      <!-- สรุปยอด (ตามตัวกรองที่เลือก) -->
      <section class="sheet p-5 md:p-6" aria-labelledby="overview-title">
        <h2 id="overview-title" class="font-display text-xl font-semibold">Overview</h2>
        <div class="mt-4 flex flex-wrap items-center gap-8">
          <div class="h-56 w-56 shrink-0">
            <canvas id="transactionPieChart" role="img" aria-label="Pie chart of deposits, received, withdrawals and transfers"></canvas>
          </div>

          <dl class="min-w-0 flex-1 basis-64 divide-y divide-rule">
            <div class="flex items-center justify-between gap-4 py-2.5">
              <dt class="flex items-center gap-2"><span class="h-3 w-3 rounded-sm" style="background: #1c8c5e"></span> Deposits</dt>
              <dd class="font-display font-medium text-credit">฿{{ formatMoney(depositTotal) }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-2.5">
              <dt class="flex items-center gap-2"><span class="h-3 w-3 rounded-sm" style="background: #8ccfae"></span> Received</dt>
              <dd class="font-display font-medium text-credit">฿{{ formatMoney(receiveTotal) }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-2.5">
              <dt class="flex items-center gap-2"><span class="h-3 w-3 rounded-sm" style="background: #c23b3b"></span> Withdrawals</dt>
              <dd class="font-display font-medium text-debit">฿{{ formatMoney(withdrawTotal) }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-2.5">
              <dt class="flex items-center gap-2"><span class="h-3 w-3 rounded-sm" style="background: #e39a9a"></span> Transfers</dt>
              <dd class="font-display font-medium text-debit">฿{{ formatMoney(transferTotal) }}</dd>
            </div>
          </dl>
        </div>
      </section>

      <!-- ประวัติธุรกรรม (หน้าสมุดบัญชี) -->
      <section class="passbook" aria-labelledby="history-title">
        <div class="passbook-page">
          <h2 id="history-title" class="mb-4 font-display text-xl font-semibold">Transaction history</h2>
          <HistoryFilters :filters="filters" :has-filters="hasFilters" :error="filterError" @change="onFilterChange" @clear="onClearFilters" />
          <Ledger :transactions="transactions" :has-filters="hasFilters" @view-receipt="receiptId = $event" />
          <HistoryPagination :pagination="pagination" :loading="historyLoading" @page="onPage" />
        </div>
      </section>
    </main>
  </div>

  <ReceiptModal v-if="receiptId" :transaction-id="receiptId" @close="receiptId = null" />

  <foot />
</template>
