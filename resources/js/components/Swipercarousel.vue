<script setup>
import { ref } from "vue";
import { Swiper, SwiperSlide } from "swiper/vue";
import "swiper/css";
import "swiper/css/pagination";
import "swiper/css/autoplay";
import { Autoplay, Pagination } from "swiper/modules";
import { Link } from "@inertiajs/vue3";

const slides = ref([
  { id: 1, src: "/30719.jpg", alt: "Banking App", text: "Welcome to Chino Bank" },
  { id: 2, src: "/xwe.webp", alt: "Secure Transactions", text: "Secure & Fast Transactions" },
  { id: 3, src: "/8069144.jpg", alt: "Fast & Easy Payments", text: "Fast Internet Banking" },
]);
</script>

<template>
  <div class="w-full max-w-8xl mx-auto mt-3">
    <Swiper
      :modules="[Autoplay, Pagination]"
      :slides-per-view="1"
      :loop="true"
      :pagination="{ clickable: true }"
      :autoplay="{ delay: 3000, disableOnInteraction: false }"
      class="rounded-lg shadow-lg overflow-hidden relative"
    >
      <SwiperSlide v-for="slide in slides" :key="slide.id" class="relative">
        <!-- Image -->
        <div class="relative w-full h-[500px] sm:h-[400px] md:h-[500px] lg:h-[550px]">
          <img :src="slide.src" :alt="slide.alt" class="w-full h-full object-cover rounded-lg" />

          <!-- Dark Overlay -->
          <div class="absolute inset-0 bg-black/50 rounded-lg"></div>

          <!-- Overlay Text & CTA -->
          <div class="absolute inset-0 flex flex-col items-center justify-center text-center text-white p-6">
            <h2 class="text-3xl md:text-4xl font-bold drop-shadow-lg mb-3">
              {{ slide.text }}
            </h2>
            <p class="text-lg md:text-xl text-gray-300 mb-6">
              Experience seamless & secure banking today!
            </p>
            <Link href="/Uregister">
              <button
                class="border border-teal-300 rounded-full font-bold px-8 py-3 bg-teal-400 text-white hover:bg-teal-300 hover:text-gray-900 transition duration-300 shadow-md"
              >
                Open Account Now
              </button>
            </Link>
          </div>
        </div>
      </SwiperSlide>
    </Swiper>
  </div>
</template>
