<script setup>
import Headermain from "@/components/Headermain.vue";
import Swipercarousel from "@/components/Swipercarousel.vue";
import foot from "@/components/foot.vue";
</script>

<template>
  <div class="h-full bg-gradient-to-br from-blue-900 to-teal-700">
    <Headermain /> 

    <main class="p-6">
      
      <Swipercarousel />

      <section class="mt-8 bg-white/10 backdrop-blur-lg p-6 rounded-lg shadow-lg">
        <h2 class="text-3xl font-bold text-white text-center">Banking Service Details</h2>
        <p class="mt-2 text-gray-200 text-lg text-center">
          Explore our online banking services, including transaction limits and fees.
        </p>

        
        <div class="mt-6 bg-white/20 p-6 rounded-lg shadow-md">
          <h3 class="text-2xl font-semibold text-white">Transaction Limits</h3>
          <ul class="mt-2 text-gray-300 space-y-2">
            <li><span class="text-teal-300 font-bold">Transfer to others / Interbank:</span> Maximum **500,000 THB/day**</li>
            <li><span class="text-teal-300 font-bold">Withdraw:</span> Unlimited</li>
          </ul>
        </div>

       
        <div class="mt-6 bg-white/20 p-6 rounded-lg shadow-md">
          <h3 class="text-2xl font-semibold text-white">Service Channels</h3>
          <p class="mt-2 text-gray-300">
            Available on <span class="text-teal-300 font-bold">ChinoBank</span> for convenient online transactions.
          </p>
        </div>

        
        <div class="mt-6 bg-teal-700 p-6 rounded-lg shadow-md text-center">
          <h3 class="text-2xl font-semibold text-white">No Service Fees</h3>
          <p class="mt-2 text-gray-200 text-lg"> fee-free transactions with ChinoBank.</p>
        </div>
      </section>
    </main>
  </div>
</template>
