<script setup>
import { Link } from "@inertiajs/vue3";

// โครงหน้า login / register: แผงกรมท่าด้านซ้าย (เส้นบรรทัดจางๆ แบบสมุดบัญชี) + ฟอร์มด้านขวา
defineProps({
  wide: { type: Boolean, default: false },
});
</script>

<template>
  <div class="min-h-screen bg-paper text-ink md:grid md:grid-cols-[minmax(280px,38%)_1fr]">
    <aside class="auth-rules flex flex-col justify-between bg-ink p-6 text-white md:min-h-screen md:p-10">
      <Link href="/" class="flex items-center gap-3">
        <img src="/bank_2830289.png" alt="" class="h-12 w-12 object-contain" />
        <span class="font-display text-xl font-semibold">Chino Bank</span>
      </Link>

      <div class="mt-10 hidden max-w-xs md:block">
        <p class="font-display text-2xl font-medium leading-snug">Your trusted banking partner</p>
        <p class="mt-3 text-white/70">Every deposit, withdrawal, and transfer is recorded in your passbook.</p>
      </div>
    </aside>

    <main class="flex items-center justify-center px-4 py-10 md:px-10">
      <div class="w-full" :class="wide ? 'max-w-2xl' : 'max-w-sm'">
        <slot />
      </div>
    </main>
  </div>
</template>

<style>
/* เส้นบรรทัดจางๆ บนแผงกรมท่า */
.auth-rules {
  background-image: repeating-linear-gradient(to bottom, transparent 0 31px, rgb(255 255 255 / 0.06) 31px 32px);
}
</style>
