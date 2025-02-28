<script setup>
import { ref } from "vue";
import { Link } from "@inertiajs/vue3";
import axios from "axios";
import Swal from "sweetalert2";

const username = ref("");
const password = ref("");
const errorMessage = ref(null);
const loading = ref(false);

// ✅ Function to allow only numbers (UserID: 13 digits, PIN: 4-6 digits)
const validateUserID = () => {
  username.value = username.value.replace(/\D/g, "").slice(0, 13);
};
const validatePIN = () => {
  password.value = password.value.replace(/\D/g, "").slice(0, 6);
};

const handleSubmit = async () => {
  if (loading.value) return; // ✅ Prevent multiple clicks
  if (username.value.length !== 13 || password.value.length < 4 || password.value.length > 6) {
    Swal.fire({
      title: "Invalid Input",
      text: "UserID must be 13 digits and PIN must be 4-6 digits.",
      icon: "warning",
      confirmButtonText: "OK",
    });
    return;
  }

  errorMessage.value = null;
  loading.value = true;

  try {
    const { data } = await axios.post("/api/login", {
      userid: username.value.trim(),
      pin: password.value.trim(),
    });

    Swal.fire({
      title: "Login Successful!",
      text: "Redirecting to Dashboard...",
      icon: "success",
      confirmButtonText: "OK",
    }).then(() => {
      window.location.href = "/dashboard"; // ✅ Redirect to Dashboard
    });

  } catch (error) {
    errorMessage.value = error.response?.status === 401
      ? "Incorrect UserID or PIN."
      : "An error occurred. Please try again.";

    Swal.fire({
      title: "Login Failed",
      text: errorMessage.value,
      icon: "error",
      confirmButtonText: "Try Again",
    });
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="flex h-screen bg-gradient-to-br from-blue-900 to-teal-700 items-center justify-center">
    <div class="w-full max-w-xs bg-white/10 backdrop-blur-lg rounded-xl p-6 shadow-lg">
      <header class="flex flex-col items-center">
        <img class="w-20 mb-4 drop-shadow-lg" src="/bank_2830289.png" alt="Bank Logo" />
        <h2 class="text-lg font-semibold text-white">Welcome to Chino Bank</h2>
      </header>

      <div v-if="errorMessage" class="bg-red-100 text-red-600 p-2 mb-4 rounded text-center">
        {{ errorMessage }}
      </div>

      <form @submit.prevent="handleSubmit">
        <div>
          <label class="block mb-2 text-teal-200" for="username">UserID</label>
          <input
            v-model="username"
            @input="validateUserID"
            class="w-full p-2 mb-4 bg-white/20 text-white placeholder-gray-300 border-b-2 border-teal-300 outline-none focus:bg-white/30 rounded-md transition"
            type="text"
            name="username"
            maxlength="13"
            required
          />
        </div>
        <div>
          <label class="block mb-2 text-teal-200" for="password">PIN/Password</label>
          <input
            v-model="password"
            @input="validatePIN"
            class="w-full p-2 mb-4 bg-white/20 text-white placeholder-gray-300 border-b-2 border-teal-300 outline-none focus:bg-white/30 rounded-md transition"
            type="password"
            name="password"
            maxlength="6"  
            required
          />
        </div>
        <div>
          <button
            type="submit"
            class="w-full bg-teal-500 hover:bg-teal-400 text-white font-bold py-2 px-4 rounded-lg transition duration-300 flex items-center justify-center shadow-md"
            :disabled="loading"
          >
            <svg v-if="loading" class="animate-spin h-5 w-5 mr-2" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            <span v-if="!loading">Login</span>
          </button>
        </div>
      </form>

      <footer class="flex justify-between text-sm mt-4 text-teal-200">
        <a class="hover:text-teal-100" href="#">Forgot Password?</a>
        <Link class="hover:text-teal-100" href="/Uregister">
          Create an Account
        </Link>
      </footer>
    </div>
  </div>
</template>
