<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { LayoutDashboard, ReceiptText, LogOut } from "lucide-vue-next";
import { getToken, authHeaders, clearSession } from "@/composables/useAuth";

// หน้าปัจจุบัน ใช้ไฮไลต์เมนูที่เปิดอยู่
const path = window.location.pathname;

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
  <div class="sticky top-0 flex h-screen self-start max-md:fixed max-md:inset-x-0 max-md:bottom-0 max-md:top-auto max-md:z-30 max-md:h-16">
    <!-- Sidebar (คลิกแล้วขยาย-ย่อได้ / บนมือถือเป็นแถบล่าง) -->
    <nav
      @click="toggleSidebar"
      :class="isSidebarOpen ? 'w-60' : 'w-20'"
      class="flex h-full cursor-pointer flex-col bg-ink p-4 text-white transition-[width] duration-200 max-md:w-full max-md:flex-row max-md:items-center max-md:justify-around max-md:p-2"
    >
      <!-- โลโก้ -->
      <div class="flex items-center gap-3 max-md:hidden">
        <img class="h-11 w-11 shrink-0 object-contain" src="/bank_2830289.png" alt="Chino Bank logo" />
        <span v-if="isSidebarOpen" class="font-display text-lg font-semibold">Chino Bank</span>
      </div>

      <!-- เมนู -->
      <ul class="mt-8 space-y-1 max-md:mt-0 max-md:flex max-md:space-y-0 max-md:gap-2">
        <li>
          <a
            href="/dashboard"
            :aria-current="path === '/dashboard' ? 'page' : undefined"
            class="flex items-center gap-3 rounded-md p-3 transition-colors"
            :class="path === '/dashboard' ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white'"
          >
            <LayoutDashboard class="h-5 w-5 shrink-0" />
            <span :class="isSidebarOpen ? 'max-md:sr-only' : 'sr-only'">Dashboard</span>
          </a>
        </li>
        <li>
          <a
            href="/Tran"
            :aria-current="path.toLowerCase() === '/tran' ? 'page' : undefined"
            class="flex items-center gap-3 rounded-md p-3 transition-colors"
            :class="path.toLowerCase() === '/tran' ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white'"
          >
            <ReceiptText class="h-5 w-5 shrink-0" />
            <span :class="isSidebarOpen ? 'max-md:sr-only' : 'sr-only'">Transactions</span>
          </a>
        </li>
      </ul>

      <!-- ส่วนล่าง: ชื่อผู้ใช้ + Logout -->
      <div class="mt-auto space-y-2 max-md:mt-0 max-md:space-y-0">
        <p v-if="isSidebarOpen" class="border-t border-white/15 px-3 pt-4 text-sm text-white/70 max-md:hidden">
          Signed in as<br /><span class="font-medium text-white">{{ userName }}</span>
        </p>

        <a
          href="#"
          class="flex items-center gap-3 rounded-md p-3 text-white/70 transition-colors hover:bg-white/10 hover:text-white"
          @click="logoutUser"
        >
          <LogOut class="h-5 w-5 shrink-0" />
          <span :class="isSidebarOpen ? 'max-md:sr-only' : 'sr-only'">Log out</span>
        </a>
      </div>
    </nav>
  </div>
</template>
