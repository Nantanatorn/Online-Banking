<script setup>
import { ref } from "vue";
import { Swiper, SwiperSlide } from "swiper/vue";
import "swiper/css";
import "swiper/css/pagination";
import "swiper/css/autoplay";
import { Autoplay, Pagination } from "swiper/modules";

const slides = ref([
  { id: 1, src: "/30719.jpg", alt: "Banking App", text: "Welcome to Chino Bank" },
  { id: 2, src: "/xwe.webp", alt: "Secure Transactions", text: "Secure & Fast Transactions" },
  { id: 3, src: "/8069144.jpg", alt: "Fast & Easy Payments", text: "Fast internet banking" },
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
        <div class="relative w-full h-[500px]">
          <img :src="slide.src" :alt="slide.alt" class="w-full h-full object-cover rounded-lg" />

          <!-- Dark Overlay -->
          <div class="absolute inset-0 bg-black/50 rounded-lg"></div>

          <!-- Overlay Text -->
          <div class="absolute inset-0 flex flex-col items-center justify-center text-center text-white p-6">
            <h2 class="text-3xl font-bold drop-shadow-lg">{{ slide.text }}</h2>
            <p class="mt-2 text-lg drop-shadow-md">Convi Fast Secure</p>
          </div>
        </div>
      </SwiperSlide>
    </Swiper>
  </div>
</template>
