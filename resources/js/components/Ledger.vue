<script setup>
// ตารางประวัติแบบสมุดบัญชี: เงินออกอยู่คอลัมน์ Withdrawal เงินเข้าอยู่คอลัมน์ Deposit
import { format } from "date-fns";
import { FileText } from "lucide-vue-next";
import { formatMoney, isIncoming } from "@/composables/useAuth";

defineProps({
  transactions: { type: Array, required: true },
  hasFilters: { type: Boolean, default: false },
});
const emit = defineEmits(["view-receipt"]);

const labels = {
  deposit: "Deposit",
  withdraw: "Withdraw",
  transfer: "Transfer",
  receive: "Received",
};

const typeLabel = (t) => labels[t.type] || t.type;
// ธุรกรรมโอน/รับมีบัญชีอีกฝั่งใน to_account_id
const counterparty = (t) => (t.to_account_id ? (t.type === "receive" ? `from ${t.to_account_id}` : `to ${t.to_account_id}`) : "");

const dayText = (value) => format(new Date(value), "dd MMM yyyy");
const timeText = (value) => format(new Date(value), "HH:mm");
</script>

<template>
  <div>
  <!-- มือถือ: รายการสองบรรทัด (ตารางกว้างเกินจอ) กดเพื่อดูสลิป -->
  <ul class="divide-y divide-rule border-t-2 border-ink md:hidden">
    <li v-if="!transactions.length" class="py-8 text-center text-muted">
      {{ hasFilters ? "No transactions match these filters. Try a wider date range or clear the filters." : "No transactions yet. Make a deposit to get started." }}
    </li>
    <li v-for="t in transactions" :key="t.id">
      <button
        type="button"
        @click="emit('view-receipt', t.id)"
        class="flex w-full items-start justify-between gap-3 py-3 text-left"
        :aria-label="'View receipt for ' + typeLabel(t) + ' on ' + dayText(t.created_at)"
      >
        <span class="min-w-0">
          <span class="block font-medium">{{ typeLabel(t) }} <span v-if="counterparty(t)" class="font-normal text-muted">{{ counterparty(t) }}</span></span>
          <span class="block text-sm text-muted">{{ dayText(t.created_at) }}, {{ timeText(t.created_at) }}</span>
        </span>
        <span class="shrink-0 text-right">
          <span class="block font-display font-medium" :class="isIncoming(t.type) ? 'text-credit' : 'text-debit'">
            {{ isIncoming(t.type) ? "+" : "-" }}฿{{ formatMoney(t.amount) }}
          </span>
          <span class="block text-sm text-muted">Balance {{ t.balance == null ? "-" : formatMoney(t.balance) }}</span>
        </span>
      </button>
    </li>
  </ul>

  <!-- relative: ให้ข้อความ sr-only (absolute) อยู่ในกรอบเลื่อนนี้ ไม่ดันให้ทั้งหน้าเลื่อนแนวนอนบนมือถือ -->
  <div class="relative hidden overflow-x-auto md:block">
    <table class="ledger">
      <thead>
        <tr>
          <th>Date</th>
          <th>Details</th>
          <th class="text-right">Withdrawal (฿)</th>
          <th class="text-right">Deposit (฿)</th>
          <th class="text-right">Balance (฿)</th>
          <th><span class="sr-only">Receipt</span></th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="!transactions.length">
          <td colspan="6" class="!py-10 text-center text-muted">
            {{ hasFilters ? "No transactions match these filters. Try a wider date range or clear the filters." : "No transactions yet. Make a deposit to get started." }}
          </td>
        </tr>

        <tr v-for="t in transactions" :key="t.id">
          <td class="whitespace-nowrap">
            {{ dayText(t.created_at) }}
            <span class="block text-sm text-muted">{{ timeText(t.created_at) }}</span>
          </td>
          <td>
            <span class="font-medium">{{ typeLabel(t) }}</span>
            <span v-if="counterparty(t)" class="block text-sm text-muted">{{ counterparty(t) }}</span>
          </td>
          <td class="num text-debit">{{ isIncoming(t.type) ? "" : formatMoney(t.amount) }}</td>
          <td class="num text-credit">{{ isIncoming(t.type) ? formatMoney(t.amount) : "" }}</td>
          <td class="num font-display font-medium">{{ t.balance == null ? "-" : formatMoney(t.balance) }}</td>
          <td class="text-right">
            <button
              type="button"
              @click="emit('view-receipt', t.id)"
              class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm font-medium text-bank hover:bg-paper"
              :aria-label="'View receipt for ' + typeLabel(t) + ' on ' + dayText(t.created_at)"
            >
              <FileText class="h-4 w-4" /> Receipt
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  </div>
</template>
