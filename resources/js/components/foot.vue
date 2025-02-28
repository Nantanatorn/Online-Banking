<script setup>
import { Link } from "@inertiajs/vue3"; 
</script>

<template>
  <footer class="bg-gradient-to-r from-blue-900 to-teal-700 text-white p-10">
    <div class="container mx-auto grid grid-cols-1 md:grid-cols-4 gap-8">
      <!-- ✅ Logo & Company Info -->
      <aside class="flex flex-col items-center md:items-start">
        <img src="/bank_2830289.png" alt="Bank Logo" class="w-12 h-12 mb-3 drop-shadow-lg" />
        <p class="text-lg font-semibold">Chino Bank</p>
        <p class="text-gray-300 text-sm">Your trusted banking partner</p>
      </aside>

      <!-- ✅ Services -->
      <nav>
        <h6 class="footer-title text-teal-200 font-semibold">Services</h6>
        <ul class="mt-2 space-y-1">
          <li><a class="text-gray-300 hover:text-white transition" href="#">Deposit</a></li>
          <li><a class="text-gray-300 hover:text-white transition" href="#">Withdraw</a></li>
          <li><a class="text-gray-300 hover:text-white transition" href="#">Transfer</a></li>
        </ul>
      </nav>

      <!-- ✅ Company Info -->
      <nav>
        <h6 class="footer-title text-teal-200 font-semibold">Company</h6>
        <ul class="mt-2 space-y-1">
          <li><a class="text-gray-300 hover:text-white transition" href="#">About Us</a></li>
          <li><a class="text-gray-300 hover:text-white transition" href="#">Careers</a></li>
          <li><a class="text-gray-300 hover:text-white transition" href="#">Contact Us</a></li>
          <li><a class="text-gray-300 hover:text-white transition" href="#">News & Updates</a></li>
        </ul>
      </nav>

      <!-- ✅ Legal Links -->
      <nav>
        <h6 class="footer-title text-teal-200 font-semibold">Legal</h6>
        <ul class="mt-2 space-y-1">
          <li><a class="text-gray-300 hover:text-white transition" href="#">Terms of Use</a></li>
          <li><a class="text-gray-300 hover:text-white transition" href="#">Privacy Policy</a></li>
          <li><a class="text-gray-300 hover:text-white transition" href="#">Cookie Policy</a></li>
        </ul>
      </nav>
    </div>

    <!-- ✅ Footer Bottom -->
    <div class="mt-8 text-center text-gray-300 text-sm">
      © {{ new Date().getFullYear() }} Chino Bank(Nantanatorn Suetrong). All rights reserved.
    </div>
  </footer>
</template>
