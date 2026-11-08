// helper กลางสำหรับหน้าที่ต้องล็อกอิน (เก็บ JWT ไว้ใน localStorage)

export const getToken = () => localStorage.getItem("token");

export const authHeaders = () => ({ Authorization: `Bearer ${getToken()}` });

export const clearSession = () => {
  localStorage.removeItem("token");
  localStorage.removeItem("account_id");
};

// ไม่มี token -> เด้งไปหน้า login แล้วคืน false
export const requireLogin = () => {
  if (getToken()) return true;
  window.location.href = "/Ulogin";
  return false;
};

// token หมดอายุ/ไม่ถูกต้อง (401) -> ล้าง session แล้วเด้งไปหน้า login
export const handleAuthError = (error) => {
  if (error?.response?.status === 401) {
    clearSession();
    window.location.href = "/Ulogin";
    return true;
  }
  return false;
};

// แปลงเป็นตัวเลขอย่างปลอดภัย (API ส่ง decimal มาเป็น string เช่น "100.00")
export const toNumber = (value) => Number(value) || 0;

// จัดรูปเงินให้มี , คั่นหลักพัน และทศนิยม 2 ตำแหน่ง เช่น 1500000 -> "1,500,000.00" (ว่าง/null -> "-")
export const formatMoney = (value) => {
  if (value === null || value === undefined || value === "") return "-";
  const n = Number(value);
  if (Number.isNaN(n)) return "-";
  return n.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

// ประเภทที่เงินเข้าบัญชี (+) / ออกจากบัญชี (-)
export const isIncoming = (type) => type === "deposit" || type === "receive";
