<template>
  <footer class="bg-ink text-white">
   <div class="mx-auto max-w-6xl px-4 py-10 max-md:pb-24 md:px-6">
    <div class="grid gap-8 md:grid-cols-4">
      <aside class="flex flex-col gap-2">
        <img src="/bank_2830289.png" alt="Chino Bank logo" class="h-11 w-11 object-contain" />
        <p class="font-display text-lg font-semibold">Chino Bank</p>
        <p class="text-sm text-white/70">Your trusted banking partner</p>
      </aside>

      <nav aria-label="Services">
        <h6 class="font-display font-medium text-white">Services</h6>
        <ul class="mt-3 space-y-2 text-sm text-white/70">
          <li><a class="hover:text-white" href="#">Deposit</a></li>
          <li><a class="hover:text-white" href="#">Withdraw</a></li>
          <li><a class="hover:text-white" href="#">Transfer</a></li>
        </ul>
      </nav>

      <nav aria-label="Company">
        <h6 class="font-display font-medium text-white">Company</h6>
        <ul class="mt-3 space-y-2 text-sm text-white/70">
          <li><a class="hover:text-white" href="#">About Us</a></li>
          <li><a class="hover:text-white" href="#">Careers</a></li>
          <li><a class="hover:text-white" href="#">Contact Us</a></li>
          <li><a class="hover:text-white" href="#">News &amp; Updates</a></li>
        </ul>
      </nav>

      <nav aria-label="Legal">
        <h6 class="font-display font-medium text-white">Legal</h6>
        <ul class="mt-3 space-y-2 text-sm text-white/70">
          <li><a class="hover:text-white" href="#">Terms of Use</a></li>
          <li><a class="hover:text-white" href="#">Privacy Policy</a></li>
          <li><a class="hover:text-white" href="#">Cookie Policy</a></li>
        </ul>
      </nav>
    </div>

    <p class="mt-10 border-t border-white/15 pt-5 text-sm text-white/60">
      © {{ new Date().getFullYear() }} Chino Bank(Nantanatorn Suetrong). All rights reserved.
    </p>
   </div>
  </footer>
</template>
