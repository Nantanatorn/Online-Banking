<script setup>
import { ref } from "vue";
import axios from "axios";
import { toast, Toaster } from "vue-sonner";
import { Link } from "@inertiajs/vue3";


const username = ref("");
const password = ref("");
const errorMessage = ref(null);
const loading = ref(false);

const handleSubmit = async () => {
  if (loading.value) return; // ✅ Prevent multiple clicks

  errorMessage.value = null;
  loading.value = true;

  try {
    const { data } = await axios.post("/api/login", {
      userid: username.value.trim(),
      pin: password.value.trim(),
    });

    console.log("Login Response:", data); // ✅ Debugging: Check API response

    if (data.token) {
      localStorage.setItem("token", data.token); // ✅ Store token in localStorage
      // เก็บเลขบัญชีต้นทางไว้ใช้ตอนโอนเงิน (Tranbutton.vue อ่านค่านี้)
      const accountId = data.user?.account_id;
      if (accountId) {
        localStorage.setItem("account_id", String(accountId));
      } else {
        localStorage.removeItem("account_id");
      }

      toast.success("Login successful", { description: "Taking you to your dashboard..." });
      setTimeout(() => {
        window.location.href = "/dashboard"; // ✅ Redirect after login
      }, 900);
    } else {
      throw new Error("No token received from server.");
    }

  } catch (error) {
    errorMessage.value =
      error.response?.data?.error === "Unauthorized"
        ? "Incorrect UserID or PIN."
        : "An error occurred. Please try again.";

    toast.error("Login failed", { description: errorMessage.value });

    console.error("Login error:", error.response ? error.response.data : error.message);
  } finally {
    loading.value = false;
  }
};

</script>

<template>
  <!-- พื้นหลังฟ้าเขียวแบบเดิม + แผ่นฟอร์มสีขาวแบบสมุดบัญชี -->
  <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-blue-900 to-teal-700 px-4 py-10">
    <Toaster position="top-right" rich-colors />
    <div class="w-full max-w-sm">
      <Link href="/" class="mb-6 flex items-center justify-center gap-3 text-white">
        <img src="/bank_2830289.png" alt="" class="h-14 w-14 object-contain drop-shadow-lg" />
        <span class="font-display text-2xl font-semibold">Chino Bank</span>
      </Link>

      <div class="sheet p-6 shadow-xl">
        <h1 class="font-display text-2xl font-semibold text-ink">Log in</h1>
        <p class="mt-1 text-muted">Use your 13-digit user ID and 6-digit PIN.</p>

        <form @submit.prevent="handleSubmit" class="mt-5 space-y-4 text-ink">
          <div>
            <label for="username" class="mb-1 block text-sm font-medium">User ID</label>
            <input
              id="username"
              v-model="username"
              class="field"
              type="text"
              name="username"
              inputmode="numeric"
              autocomplete="username"
              maxlength="13"
              required
            />
          </div>

          <div>
            <label for="password" class="mb-1 block text-sm font-medium">PIN</label>
            <input
              id="password"
              v-model="password"
              class="field"
              type="password"
              name="password"
              inputmode="numeric"
              autocomplete="current-password"
              maxlength="6"
              required
            />
          </div>

          <button type="submit" class="btn btn-primary w-full" :disabled="loading">
            <svg v-if="loading" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" aria-hidden="true">
              <circle class="opacity-25" cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            <span>{{ loading ? "Logging in..." : "Log in" }}</span>
          </button>
        </form>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-2 text-sm">
          <a class="text-muted hover:text-ink hover:underline" href="#">Forgot Password?</a>
          <Link class="font-medium text-bank hover:underline" href="/Uregister">Create an account</Link>
        </div>
      </div>
    </div>
  </div>
</template>
