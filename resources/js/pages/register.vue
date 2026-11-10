<script setup>
import { ref } from "vue";
import { Link } from "@inertiajs/vue3";
import axios from "axios";
import { toast, Toaster } from "vue-sonner";
import AuthShell from "@/components/AuthShell.vue";

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
  if (form.value.idcard.length !== 13 || form.value.pin.length !== 6) {
    toast.warning("Invalid input", { description: "ID card must be 13 digits and PIN must be 6 digits." });
    return;
  }
  
  if (form.value.pin !== form.value.confirmPin) {
    toast.error("PINs do not match", { description: "PIN and Confirm PIN must be the same." });
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

    toast.success("Account created", { description: "Taking you to the login page..." });
    setTimeout(() => {
      window.location.href = "/Ulogin"; // ✅ Redirect to Login Page
    }, 1200);

  } catch (error) {
    const errors = error.response?.data?.errors;
    if (errors) {
      errorMessage.value = Object.values(errors).flat().join("\n");
    } else {
      errorMessage.value = "An error occurred. Please try again.";
    }

    toast.error("Registration failed", { description: errorMessage.value });
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <AuthShell wide>
    <Toaster position="top-right" rich-colors />
    <h1 class="font-display text-3xl font-semibold">Open an account</h1>
    <p class="mt-2 text-muted">Fill in your details to get a Chino Bank account.</p>

    <form @submit.prevent="handleRegister" class="mt-6">
      <div class="grid gap-x-4 gap-y-4 sm:grid-cols-2">
        <div>
          <label for="firstname" class="mb-1 block text-sm font-medium">First name</label>
          <input id="firstname" v-model="form.firstname" type="text" autocomplete="given-name" class="field" required />
        </div>
        <div>
          <label for="lastname" class="mb-1 block text-sm font-medium">Last name</label>
          <input id="lastname" v-model="form.lastname" type="text" autocomplete="family-name" class="field" required />
        </div>
        <div>
          <label for="idcard" class="mb-1 block text-sm font-medium">ID card number (13 digits)</label>
          <input id="idcard" v-model="form.idcard" @input="validateIDCard" type="text" inputmode="numeric" class="field" required maxlength="13" />
        </div>
        <div>
          <label for="phone" class="mb-1 block text-sm font-medium">Phone</label>
          <input id="phone" v-model="form.phone" @input="validatePhone" type="text" inputmode="tel" autocomplete="tel" class="field" required maxlength="15" />
        </div>
        <div>
          <label for="email" class="mb-1 block text-sm font-medium">Email</label>
          <input id="email" v-model="form.email" type="email" autocomplete="email" class="field" required />
        </div>
        <div>
          <label for="gender" class="mb-1 block text-sm font-medium">Gender</label>
          <select id="gender" v-model="form.gender" class="field">
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="sm:col-span-2">
          <label for="address" class="mb-1 block text-sm font-medium">Address</label>
          <textarea id="address" v-model="form.address" rows="2" autocomplete="street-address" class="field" required></textarea>
        </div>
        <div>
          <label for="pin" class="mb-1 block text-sm font-medium">PIN (6 digits)</label>
          <input id="pin" v-model="form.pin" @input="validatePIN" type="password" inputmode="numeric" autocomplete="new-password" class="field" required maxlength="6" />
        </div>
        <div>
          <label for="confirm-pin" class="mb-1 block text-sm font-medium">Confirm PIN</label>
          <input id="confirm-pin" v-model="form.confirmPin" @input="validatePIN" type="password" inputmode="numeric" autocomplete="new-password" class="field" required maxlength="6" />
        </div>
      </div>

      <button type="submit" class="btn btn-primary mt-6 w-full" :disabled="loading">
        {{ loading ? "Creating account..." : "Create account" }}
      </button>
    </form>

    <p class="mt-6 text-sm text-muted">
      Already have an account? <Link href="/Ulogin" class="font-medium text-bank hover:underline">Log in</Link>
    </p>
  </AuthShell>
</template>
