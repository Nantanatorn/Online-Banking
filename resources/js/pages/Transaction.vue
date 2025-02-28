<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import Accsidebar from "@/components/Accsidebar.vue";
import Tranbutton from "@/components/Tranbutton.vue";
import foot from "@/components/foot.vue";
import { Chart } from "chart.js/auto";
import { format } from "date-fns"; // ✅ ใช้ date-fns เพื่อจัดรูปแบบวันที่

// ✅ ตัวแปรเก็บข้อมูล
const isSidebarOpen = ref(false);
const userName = ref("Loading...");
const transactions = ref([]);
const depositTotal = ref(0);
const withdrawTotal = ref(0);
const transferTotal = ref(0);
let transactionChart = null; // 🔹 เก็บอ้างอิง Pie Chart

// ✅ Toggle Sidebar
const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value;
};

// ✅ ดึงข้อมูลผู้ใช้
const fetchUser = async () => {
  try {
    const token = localStorage.getItem("token");
    if (!token) {
      console.error("No token found!");
      userName.value = "Guest";
      return;
    }

    const response = await axios.get("/api/user", {
      headers: { Authorization: `Bearer ${token}` },
    });

    userName.value = response.data.firstname + " " + response.data.lastname || "User";
  } catch (error) {
    console.error("Error fetching user:", error.response ? error.response.data : error.message);
    userName.value = "Guest";
  }
};

// ✅ ดึงประวัติธุรกรรม
const fetchTransactions = async () => {
  try {
    const token = localStorage.getItem("token");
    if (!token) {
      console.error("No token found!");
      return;
    }

    const response = await axios.get("/api/transactionhis", {
      headers: { Authorization: `Bearer ${token}` },
    });

    transactions.value = response.data.transactions;

    calculateTransactionTotals();
    renderPieChart();
  } catch (error) {
    console.error("Error fetching transactions:", error.response ? error.response.data : error.message);
  }
};

// ✅ คำนวณยอดรวมสำหรับ Deposit, Withdraw และ Transfer
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
        labels: ["Deposits", "Withdrawals", "Transfers"],
        datasets: [
          {
            data: [depositTotal.value, withdrawTotal.value, transferTotal.value],
            backgroundColor: ["#4CAF50", "#FF5733", "#3498db"], // 🟢🟥🔵
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
onMounted(async () => {
  await fetchUser();
  await fetchTransactions();
});
</script>

<template>
  <div class="flex h-screen bg-gradient-to-br from-blue-900 to-teal-700 text-white">
    <!-- ✅ Sidebar -->
    <Accsidebar @click="toggleSidebar" />

    <!-- ✅ Main Content -->
    <div class="flex-1 p-6">
      <!-- 🔹 Header -->
      <div class="flex justify-between items-center bg-white text-blue-900 p-4 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold">Transaction Dashboard</h1>
        <Tranbutton />
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
        <table class="w-full border-collapse border border-gray-300 text-sm">
          <thead>
            <tr class="bg-blue-200 text-blue-900">
              <th class="p-2 border border-gray-300">Date</th>
              <th class="p-2 border border-gray-300">Account ID</th>
              <th class="p-2 border border-gray-300">Type</th>
              <th class="p-2 border border-gray-300">Amount</th>
              <th class="p-2 border border-gray-300">Balance</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="transaction in transactions" :key="transaction.id" class="text-center">
              <td class="p-2 border border-gray-300">{{ formatDate(transaction.created_at) }}</td>
              <td class="p-2 border border-gray-300">{{ transaction.account_id }}</td>
              <td class="p-2 border border-gray-300 capitalize">
                <span v-if="transaction.type === 'deposit'" class="text-green-500 font-semibold">Deposit</span>
                <span v-else-if="transaction.type === 'withdraw'" class="text-red-500 font-semibold">Withdraw</span>
                <span v-else-if="transaction.type === 'transfer'" class="text-blue-500 font-semibold">Transfer</span>
              </td>
              <td class="p-2 border border-gray-300" :class="transaction.amount > 0 ? 'text-green-500' : 'text-red-500'">
                ฿{{ transaction.amount }}
              </td>
              <td class="p-2 border border-gray-300 font-bold">฿{{ transaction.balance }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <foot />
</template>
