<script setup>
import { Link } from "@inertiajs/vue3"; 
</script>

<template>
  <header class="bg-gradient-to-r from-blue-900 to-teal-700 text-white shadow-xl hidden md:block">
    <div class="container mx-auto flex items-center h-24">
      
      <!-- 🔹 Logo -->
      <Link href="/" class="flex items-center justify-center">
        <img class="h-16 drop-shadow-lg" src="/bank_2830289.png" alt="Chino Bank Logo" />
        <span class="ml-4 uppercase font-black tracking-wide text-lg md:text-xl">Chino Bank</span>
      </Link>

      <!-- 🔹 Navigation -->
      <nav class="contents font-semibold text-base lg:text-lg">
        <ul class="mx-auto flex items-center">
          <li class="p-5 xl:p-8">
            <Link href="/" class="hover:text-teal-200 transition duration-300">Home</Link>
          </li>
          <li class="p-5 xl:p-8">
            <Link href="/" class="hover:text-teal-200 transition duration-300">Service</Link> 
          </li>
          <li class="p-5 xl:p-8">
            <Link href="/about" class="hover:text-teal-200 transition duration-300">About</Link>
          </li>
        </ul>
      </nav>

      <!-- 🔹 Open Account Button -->

      <Link href="/Ulogin">
        <button class="border border-teal-300 rounded-full font-bold px-8 py-2 bg-teal-400 text-white hover:bg-teal-300 hover:text-gray-900 transition duration-300 shadow-md">
          Sign in 
        </button>
      </Link>

      
    </div>
  </header>
</template>
