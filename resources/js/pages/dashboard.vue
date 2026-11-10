<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import Accsidebar from "@/components/Accsidebar.vue";
import Tranbutton from "@/components/Tranbutton.vue";
import NotificationBell from "@/components/NotificationBell.vue";
import ReceiptModal from "@/components/ReceiptModal.vue";
import HistoryFilters from "@/components/HistoryFilters.vue";
import Ledger from "@/components/Ledger.vue";
import { Star, X } from "lucide-vue-next";
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
  <div class="min-h-screen bg-paper text-ink md:flex">
    <Accsidebar @click="toggleSidebar" />

    <main class="min-w-0 flex-1 space-y-8 px-4 py-6 max-md:pb-24 md:px-10 md:py-8">
      <header class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="font-display text-2xl font-semibold md:text-3xl">Dashboard</h1>
        <div class="flex flex-wrap items-center gap-3">
          <NotificationBell ref="bell" @new-activity="refreshAll" />
          <Tranbutton ref="tranButton" @done="refreshAll" />
        </div>
      </header>

      <!-- บัญชี + บัญชีที่บันทึกไว้ -->
      <section class="grid gap-6 lg:grid-cols-5" aria-label="Accounts">
        <article v-for="account in bankAccounts" :key="account.account_id" class="passbook lg:col-span-3">
          <div class="passbook-page">
            <h2 class="font-display text-xl font-semibold">{{ userName }}</h2>
            <p class="text-muted">Account {{ account.account_id }}</p>

            <div class="mt-6 flex flex-wrap items-end justify-between gap-x-6 gap-y-1 border-t-2 border-ink pt-4">
              <span class="text-muted">Balance</span>
              <span class="break-all font-display text-3xl font-semibold md:text-4xl">฿{{ formatMoney(account.balance) }}</span>
            </div>
            <p class="mt-3 text-sm text-muted">Interest rate {{ account.interest }}%</p>
          </div>
        </article>

        <aside class="sheet p-5 lg:col-span-2" aria-labelledby="saved-title">
          <h2 id="saved-title" class="flex items-center gap-2 font-display text-lg font-semibold">
            <Star class="h-5 w-5" /> Saved accounts
          </h2>

          <p v-if="!savedAccounts.length" class="mt-3 text-sm text-muted">
            No saved accounts yet. Tick "Save this account" the next time you transfer and it will show up here.
          </p>

          <template v-else>
            <p class="mt-1 text-sm text-muted">Select an account to start a transfer.</p>
            <ul class="mt-3 max-h-72 overflow-y-auto">
              <li v-for="a in savedAccounts" :key="a.id" class="flex items-center gap-1 border-b border-rule last:border-0">
                <button
                  type="button"
                  @click="tranButton?.openTransferTo(a.account_id)"
                  class="flex min-w-0 flex-1 items-center justify-between gap-3 rounded-md px-2 py-3 text-left hover:bg-paper"
                >
                  <span class="min-w-0">
                    <span class="block truncate font-medium">{{ a.nickname || a.holder_name || "Account" }}</span>
                    <span class="block truncate text-sm text-muted">
                      {{ a.account_id }}<span v-if="a.nickname && a.holder_name"> · {{ a.holder_name }}</span>
                    </span>
                  </span>
                  <span class="shrink-0 text-sm font-medium text-bank">Transfer</span>
                </button>
                <button
                  type="button"
                  @click="removeSavedAccount(a.id)"
                  class="rounded-md p-2 text-muted hover:bg-paper hover:text-debit"
                  :aria-label="'Remove ' + (a.nickname || a.holder_name || a.account_id) + ' from saved accounts'"
                >
                  <X class="h-4 w-4" />
                </button>
              </li>
            </ul>
          </template>
        </aside>
      </section>

      <!-- ประวัติธุรกรรม (หน้าสมุดบัญชี) -->
      <section class="passbook" aria-labelledby="history-title">
        <div class="passbook-page">
          <h2 id="history-title" class="mb-4 font-display text-xl font-semibold">Transaction history</h2>
          <HistoryFilters :filters="filters" :has-filters="hasFilters" :error="filterError" @change="applyFilters" @clear="clearFilters" />
          <Ledger :transactions="transactions" :has-filters="hasFilters" @view-receipt="receiptId = $event" />
          <HistoryPagination :pagination="pagination" :loading="historyLoading" @page="goToPage" />
        </div>
      </section>
    </main>
  </div>

  <ReceiptModal v-if="receiptId" :transaction-id="receiptId" @close="receiptId = null" />

  <foot />
</template>
