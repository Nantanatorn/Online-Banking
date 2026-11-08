<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { LayoutDashboard, ReceiptText, LogOut } from "lucide-vue-next";
import { getToken, authHeaders, clearSession } from "@/composables/useAuth";

// ✅ Sidebar state (เปิด-ปิด)
const isSidebarOpen = ref(false);

// ✅ ชื่อผู้ใช้ (ดึงจาก API)
const userName = ref("Loading...");

// ✅ ฟังก์ชัน Toggle Sidebar เมื่อคลิก
const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value;
};

// ✅ ฟังก์ชันดึงข้อมูลผู้ใช้จาก API
const fetchUser = async () => {
  try {
    const token = localStorage.getItem("token");
    if (!token) {
      console.error("No token found!");
      userName.value = "Guest";
      return;
    }

    const response = await axios.get("/api/user", {
      headers: { Authorization: `Bearer ${token}` } // ✅ ต้องใช้ ` ` ไม่ใช่ ${}
    });

    userName.value = `${response.data.firstname} ${response.data.lastname}`.trim() || "User";
  } catch (error) {
    console.error("Error fetching user:", error.response ? error.response.data : error.message);
    userName.value = "Guest"; // ถ้า API ล้มเหลว ให้ใช้ "Guest"
  }
};

// ✅ ฟังก์ชัน Logout
const logoutUser = async () => {
  try {
    if (getToken()) {
      await axios.post("/api/logout", {}, { headers: authHeaders() });
    }
  } catch (error) {
    // token หมดอายุ/เซิร์ฟเวอร์ล่ม ก็ยังต้องออกจากระบบฝั่งเบราว์เซอร์ได้
    console.error("Logout request failed:", error.response ? error.response.data : error.message);
  } finally {
    clearSession();
    window.location.href = "/";
  }
};

// ✅ โหลดข้อมูลผู้ใช้เมื่อ Component ทำงาน
onMounted(fetchUser);
</script>

<template>
  <div class="sticky top-0 flex h-screen self-start">
    <!-- ✅ Sidebar (คลิกแล้วขยาย-ย่อได้) -->
    <nav
      @click="toggleSidebar"
      :class="isSidebarOpen ? 'w-64' : 'w-20'"
      class="bg-gradient-to-b from-blue-900 to-teal-700 text-white h-full flex flex-col transition-all duration-300 p-4 cursor-pointer"
    >
      <!-- 🔹 โลโก้ -->
      <div class="flex items-center space-x-3">
        <img class="h-12 drop-shadow-lg" src="/bank_2830289.png" alt="Chino Bank Logo" />
        <span v-if="isSidebarOpen" class="text-lg font-semibold">Chino Bank</span>
      </div>

      <!-- 🔹 เมนู -->
      <ul class="mt-6 space-y-3">
        <li>
          <a href="/dashboard" class="flex items-center space-x-3 p-3 rounded-md hover:bg-teal-500 transition">
            <LayoutDashboard class="h-5 w-5 shrink-0" />
            <span v-if="isSidebarOpen">Dashboard</span>
          </a>
        </li>
        <li>
          <a href="/Tran" class="flex items-center space-x-3 p-3 rounded-md hover:bg-teal-500 transition">
            <ReceiptText class="h-5 w-5 shrink-0" />
            <span v-if="isSidebarOpen">Transaction</span>
          </a>
        </li>
      </ul>

      <!-- 🔹 ส่วนล่าง (แสดงชื่อผู้ใช้ + Logout) -->
      <div class="mt-auto space-y-3">
        <!-- ✅ แสดงชื่อผู้ใช้ -->
        <div class="p-3 text-center border-t border-white/20">
          <span v-if="isSidebarOpen" class="text-sm font-semibold text-white">Welcome, {{ userName }}</span>
        </div>

        <!-- ✅ Logout -->
        <a
          href="#"
          class="flex items-center space-x-3 p-3 rounded-md hover:bg-red-500 transition"
          @click="logoutUser"
        >
          <LogOut class="h-5 w-5 shrink-0" />
          <span v-if="isSidebarOpen">Logout</span>
        </a>
      </div>
    </nav>
  </div>
</template>
