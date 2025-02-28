<script setup>
import { ref } from "vue";
import { Link } from "@inertiajs/vue3";
import axios from "axios";
import Swal from "sweetalert2";

const form = ref({
  firstname: "",
  lastname: "",
  idcard: "",
  phone: "",
  email: "",
  address: "",
  gender: "male",
  pin: "",
  confirmPin: "",
});

const errorMessage = ref(null);
const loading = ref(false);

// ✅ Validation functions (only numbers allowed)
const validateIDCard = () => {
  form.value.idcard = form.value.idcard.replace(/\D/g, "").slice(0, 13);
};
const validatePhone = () => {
  form.value.phone = form.value.phone.replace(/\D/g, "").slice(0, 15);
};
const validatePIN = () => {
  form.value.pin = form.value.pin.replace(/\D/g, "").slice(0, 6);
  form.value.confirmPin = form.value.confirmPin.replace(/\D/g, "").slice(0, 6);
};

const handleRegister = async () => {
  if (loading.value) return; // ✅ Prevent multiple clicks
  if (form.value.idcard.length !== 13 || form.value.pin.length < 4 || form.value.pin.length > 6) {
    Swal.fire({
      title: "Invalid Input",
      text: "ID Card must be 13 digits and PIN must be 4-6 digits.",
      icon: "warning",
      confirmButtonText: "OK",
    });
    return;
  }
  
  if (form.value.pin !== form.value.confirmPin) {
    Swal.fire({
      title: "PIN Mismatch",
      text: "PIN and Confirm PIN must be the same.",
      icon: "error",
      confirmButtonText: "OK",
    });
    return;
  }

  errorMessage.value = null;
  loading.value = true;

  try {
    const { data } = await axios.post("/api/register", {
      firstname: form.value.firstname.trim(),
      lastname: form.value.lastname.trim(),
      idcard: form.value.idcard.trim(),
      phone: form.value.phone.trim(),
      email: form.value.email.trim(),
      address: form.value.address.trim(),
      gender: form.value.gender,
      pin: form.value.pin.trim(),
    });

    Swal.fire({
      title: "Registration Successful!",
      text: "Your account has been created.",
      icon: "success",
      confirmButtonText: "OK",
    }).then(() => {
      window.location.href = "/Ulogin"; // ✅ Redirect to Login Page
    });

  } catch (error) {
    const errors = error.response?.data?.errors;
    if (errors) {
      errorMessage.value = Object.values(errors).flat().join("\n");
    } else {
      errorMessage.value = "An error occurred. Please try again.";
    }

    Swal.fire({
      title: "Registration Failed",
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
    <div class="w-full max-w-2xl bg-white/10 backdrop-blur-lg rounded-xl p-6 shadow-lg">
      <header class="flex flex-col items-center">
        <img class="w-20 mb-4 drop-shadow-lg" src="/bank_2830289.png" alt="Bank Logo" />
        <h2 class="text-lg font-semibold text-white">Open a New Account</h2>
      </header>

      <div v-if="errorMessage" class="bg-red-100 text-red-600 p-2 mb-4 rounded text-center">
        {{ errorMessage }}
      </div>

      <form @submit.prevent="handleRegister">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-teal-200">First Name</label>
            <input v-model="form.firstname" type="text" class="w-full p-2 bg-white/20 text-white placeholder-gray-300 border-b-2 border-teal-300 outline-none focus:bg-white/30 rounded-md transition" required />
          </div>
          <div>
            <label class="block text-teal-200">Last Name</label>
            <input v-model="form.lastname" type="text" class="w-full p-2 bg-white/20 text-white placeholder-gray-300 border-b-2 border-teal-300 outline-none focus:bg-white/30 rounded-md transition" required />
          </div>
          <div>
            <label class="block text-teal-200">ID Card</label>
            <input v-model="form.idcard" @input="validateIDCard" type="text" class="w-full p-2 bg-white/20 text-white placeholder-gray-300 border-b-2 border-teal-300 outline-none focus:bg-white/30 rounded-md transition" required maxlength="13" />
          </div>
          <div>
            <label class="block text-teal-200">Phone</label>
            <input v-model="form.phone" @input="validatePhone" type="text" class="w-full p-2 bg-white/20 text-white placeholder-gray-300 border-b-2 border-teal-300 outline-none focus:bg-white/30 rounded-md transition" required maxlength="15" />
          </div>
          <div>
            <label class="block text-teal-200">Email</label>
            <input v-model="form.email" type="email" class="w-full p-2 bg-white/20 text-white placeholder-gray-300 border-b-2 border-teal-300 outline-none focus:bg-white/30 rounded-md transition" required />
          </div>
          <div>
            <label class="block text-teal-200">Address</label>
            <textarea v-model="form.address" class="w-full p-2 bg-white/20 text-white placeholder-gray-300 border-b-2 border-teal-300 outline-none focus:bg-white/30 rounded-md transition" required></textarea>
          </div>
          <div>
            <label class="block text-teal-200">Gender</label>
            <select v-model="form.gender" class="w-full p-2 bg-white/20 text-white border-b-2 border-teal-300 outline-none focus:bg-white/30 rounded-md transition">
              <option value="male" class="text-black">Male</option>
              <option value="female" class="text-black">Female</option>
              <option value="other" class="text-black">Other</option>
            </select>
          </div>
          <div>
            <label class="block text-teal-200">PIN (4-6 digits)</label>
            <input v-model="form.pin" @input="validatePIN" type="password" class="w-full p-2 bg-white/20 text-white placeholder-gray-300 border-b-2 border-teal-300 outline-none focus:bg-white/30 rounded-md transition" required maxlength="6" />
          </div>
          <div>
            <label class="block text-teal-200">Confirm PIN</label>
            <input v-model="form.confirmPin" @input="validatePIN" type="password" class="w-full p-2 bg-white/20 text-white placeholder-gray-300 border-b-2 border-teal-300 outline-none focus:bg-white/30 rounded-md transition" required maxlength="6" />
          </div>
        </div>

        <button type="submit" class="w-full bg-teal-500 hover:bg-teal-400 text-white font-bold py-2 px-4 mt-4 rounded-lg transition duration-300 shadow-md flex items-center justify-center" :disabled="loading">
          <span v-if="!loading">Register</span>
        </button>
      </form>

      <footer class="text-center mt-4 text-teal-200">
        <p>Already have an account? <Link href="/Ulogin" class="hover:text-teal-100">Login</Link></p>
      </footer>
    </div>
  </div>
</template>
