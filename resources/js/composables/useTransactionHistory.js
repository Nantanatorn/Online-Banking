// ดึงประวัติธุรกรรมแบบมีตัวกรอง (ประเภท/ช่วงวันที่) + แบ่งหน้า ใช้ร่วมกันทั้งหน้า dashboard และ Transaction
import { ref, reactive, computed } from "vue";
import axios from "axios";
import { authHeaders, handleAuthError } from "@/composables/useAuth";

export function useTransactionHistory({ perPage = 10 } = {}) {
  const transactions = ref([]);
  const totals = ref({ deposit: 0, withdraw: 0, transfer: 0, receive: 0 });
  const pagination = ref({ current_page: 1, last_page: 1, per_page: perPage, total: 0 });
  const filters = reactive({ type: "", from: "", to: "" });
  const loading = ref(false);
  const filterError = ref("");

  const hasFilters = computed(() => Boolean(filters.type || filters.from || filters.to));

  // คืน true เมื่อโหลดสำเร็จ
  const load = async (page = pagination.value.current_page) => {
    if (filters.from && filters.to && filters.to < filters.from) {
      filterError.value = "End date must be on or after the start date.";
      return false;
    }
    filterError.value = "";

    const params = { page, per_page: perPage };
    if (filters.type) params.type = filters.type;
    if (filters.from) params.from = filters.from;
    if (filters.to) params.to = filters.to;

    loading.value = true;
    try {
      const response = await axios.get("/api/transactionhis", { headers: authHeaders(), params });
      transactions.value = response.data.transactions;
      pagination.value = response.data.pagination;
      totals.value = response.data.totals;

      // ลบรายการจนหน้าสุดท้ายหายไป -> ถอยไปหน้าสุดท้ายที่ยังมี
      if (!transactions.value.length && pagination.value.current_page > pagination.value.last_page) {
        return load(pagination.value.last_page);
      }
      return true;
    } catch (error) {
      if (handleAuthError(error)) return false;
      filterError.value = error.response?.data?.message || "Could not load transactions.";
      return false;
    } finally {
      loading.value = false;
    }
  };

  // เปลี่ยนตัวกรอง -> กลับไปหน้าแรกเสมอ
  const applyFilters = () => load(1);

  const clearFilters = () => {
    filters.type = "";
    filters.from = "";
    filters.to = "";
    return load(1);
  };

  const goToPage = (page) => load(page);

  return { transactions, totals, pagination, filters, loading, filterError, hasFilters, load, applyFilters, clearFilters, goToPage };
}
