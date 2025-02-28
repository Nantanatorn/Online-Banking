<template>
    <div class="flex h-screen bg-indigo-700">
      <div class="w-full max-w-xs m-auto bg-indigo-100 rounded p-5">
        <header>
          <img class="w-20 mx-auto mb-5" src="public/bank_2830289.png" />
        </header>
        <form @submit.prevent="handleSubmit">
          <div>
            <label class="block mb-2 text-indigo-500" for="username">UserID</label>
            <input
              v-model="username"
              class="w-full p-2 mb-6 text-indigo-700 border-b-2 border-indigo-500 outline-none focus:bg-gray-300"
              type="text"
              name="username"
              required
            />
          </div>
          <div>
            <label class="block mb-2 text-indigo-500" for="password">PIN/Password</label>
            <input
              v-model="password"
              class="w-full p-2 mb-6 text-indigo-700 border-b-2 border-indigo-500 outline-none focus:bg-gray-300"
              type="password"
              name="password"
              required
            />
          </div>
          <div>
            <button
              type="submit"
              class="w-full bg-indigo-700 hover:bg-pink-700 text-white font-bold py-2 px-4 mb-6 rounded"
            >
              Login
            </button>
          </div>
        </form>
        <footer>
          <a class="text-indigo-700 hover:text-pink-700 text-sm float-left" href="#">Forgot Password?</a>
          <Link class="text-indigo-700 hover:text-pink-700 text-sm float-right" href="/register">Create Account</Link>
        </footer>
      </div>
    </div>
  </template>
  
  <script setup>
  import { ref } from "vue";
  import { Link } from '@inertiajs/vue3';
  
  const username = ref("");
  const password = ref("");
  
  const handleSubmit = () => {
    console.log("Logging in with:", { username: username.value, password: password.value });
    
  };
  </script>