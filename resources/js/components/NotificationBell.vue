<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import axios from "axios";
import { Bell, ArrowDownLeft, ArrowUpRight } from "lucide-vue-next";
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

// เงินเข้า = ลูกศรลงสีเขียว / เงินออก = ลูกศรขึ้นสีแดง
const isIn = (type) => type === "deposit" || type === "receive";

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
      type="button"
      @click="toggle"
      class="btn btn-quiet relative !p-2"
      :aria-label="unreadCount ? `Notifications, ${unreadCount} unread` : 'Notifications'"
      :aria-expanded="isOpen"
    >
      <Bell class="h-5 w-5" />
      <span
        v-if="unreadCount"
        class="absolute -right-1.5 -top-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-bank px-1 text-xs font-semibold text-white"
      >
        {{ unreadCount > 9 ? "9+" : unreadCount }}
      </span>
    </button>

    <div
      v-if="isOpen"
      class="absolute right-0 z-40 mt-2 w-80 max-w-[90vw] rounded-md border border-rule bg-sheet text-ink shadow-lg"
      style="animation: panel-in 0.14s ease-out"
    >
      <div class="flex items-center justify-between border-b border-rule px-4 py-3">
        <h3 class="font-display font-semibold">Notifications</h3>
        <button v-if="unreadCount" type="button" @click="markAllRead" class="text-sm font-medium text-bank hover:underline">
          Mark all as read
        </button>
      </div>

      <p v-if="!notifications.length" class="px-4 py-8 text-center text-sm text-muted">
        Nothing yet. You'll see deposits, withdrawals, and transfers here.
      </p>

      <ul v-else class="max-h-96 divide-y divide-rule overflow-y-auto">
        <li v-for="n in notifications" :key="n.id" class="flex gap-3 px-4 py-3 text-sm" :class="n.read_at ? '' : 'bg-paper'">
          <span
            class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full"
            :class="isIn(n.type) ? 'bg-credit/10 text-credit' : 'bg-debit/10 text-debit'"
          >
            <ArrowDownLeft v-if="isIn(n.type)" class="h-4 w-4" />
            <ArrowUpRight v-else class="h-4 w-4" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="font-medium">{{ n.title }}</p>
            <p class="text-muted">{{ n.message }}</p>
            <p class="mt-1 text-xs text-muted">{{ formatDate(n.created_at) }}</p>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>
