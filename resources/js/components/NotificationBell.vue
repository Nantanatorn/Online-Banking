<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import axios from "axios";
import { Bell } from "lucide-vue-next";
import { authHeaders, handleAuthError, getToken } from "@/composables/useAuth";
import { format } from "date-fns";

const emit = defineEmits(["new-activity"]);

const POLL_MS = 10000; // เช็กแจ้งเตือนใหม่ทุก 10 วินาที
const notifications = ref([]);
let latestId = null; // id แจ้งเตือนล่าสุดที่เห็นแล้ว ใช้ตรวจว่ามีรายการใหม่เข้ามา
const unreadCount = ref(0);
const isOpen = ref(false);
let timer = null;

const refresh = async () => {
  if (!getToken()) return;
  try {
    const response = await axios.get("/api/notifications", { headers: authHeaders() });
    notifications.value = response.data.notifications;
    unreadCount.value = response.data.unread_count;

    // มีแจ้งเตือนใหม่ (เงินเข้า/ออก) -> บอกหน้าให้โหลดยอดและประวัติใหม่ (ไม่ยิงตอนโหลดครั้งแรก)
    const newest = notifications.value[0]?.id ?? 0;
    if (latestId !== null && newest > latestId) emit("new-activity");
    latestId = newest;
  } catch (error) {
    handleAuthError(error);
  }
};

const markAllRead = async () => {
  try {
    await axios.post("/api/notifications/read", {}, { headers: authHeaders() });
    unreadCount.value = 0;
    notifications.value = notifications.value.map((n) => ({ ...n, read_at: n.read_at || new Date().toISOString() }));
  } catch (error) {
    handleAuthError(error);
  }
};

const toggle = () => {
  isOpen.value = !isOpen.value;
  if (isOpen.value) refresh();
};

const formatDate = (value) => format(new Date(value), "yyyy-MM-dd HH:mm");

const iconFor = (type) => ({ deposit: "💰", withdraw: "💸", transfer: "📤", receive: "📥" }[type] || "🔔");

// ปิด dropdown เมื่อคลิกนอกกล่อง
const root = ref(null);
const onClickOutside = (e) => {
  if (isOpen.value && root.value && !root.value.contains(e.target)) isOpen.value = false;
};

// กลับมาที่แท็บแล้วเช็กทันที
const onVisible = () => {
  if (!document.hidden) refresh();
};

onMounted(() => {
  refresh();
  timer = setInterval(() => {
    if (!document.hidden) refresh(); // แท็บที่ไม่ได้เปิดอยู่ไม่ต้องยิง
  }, POLL_MS);
  document.addEventListener("visibilitychange", onVisible);
  document.addEventListener("click", onClickOutside);
});

onBeforeUnmount(() => {
  clearInterval(timer);
  document.removeEventListener("click", onClickOutside);
  document.removeEventListener("visibilitychange", onVisible);
});

defineExpose({ refresh });
</script>

<template>
  <div ref="root" class="relative">
    <button
      @click="toggle"
      class="relative p-2 rounded-full text-blue-900 hover:bg-blue-50 transition"
      title="Notifications"
    >
      <Bell class="h-6 w-6" />
      <span
        v-if="unreadCount"
        class="absolute -top-0.5 -right-0.5 min-w-5 h-5 px-1 rounded-full bg-red-500 text-white text-xs font-bold flex items-center justify-center"
      >
        {{ unreadCount > 9 ? "9+" : unreadCount }}
      </span>
    </button>

    <div
      v-if="isOpen"
      class="absolute right-0 mt-2 w-80 max-w-[90vw] bg-white text-gray-800 rounded-lg shadow-xl border z-40"
    >
      <div class="flex items-center justify-between px-4 py-3 border-b">
        <h3 class="font-semibold">Notifications</h3>
        <button v-if="unreadCount" @click="markAllRead" class="text-xs text-blue-600 hover:underline">
          Mark all as read
        </button>
      </div>

      <p v-if="!notifications.length" class="px-4 py-6 text-center text-sm text-gray-500">No notifications yet.</p>

      <ul v-else class="max-h-96 overflow-y-auto divide-y">
        <li
          v-for="n in notifications"
          :key="n.id"
          class="px-4 py-3 text-sm flex space-x-3"
          :class="n.read_at ? '' : 'bg-blue-50'"
        >
          <span class="text-xl">{{ iconFor(n.type) }}</span>
          <div class="flex-1">
            <p class="font-semibold">{{ n.title }}</p>
            <p class="text-gray-600">{{ n.message }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ formatDate(n.created_at) }}</p>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>
