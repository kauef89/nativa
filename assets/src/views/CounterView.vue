<template>
  <div class="flex flex-col h-full w-full bg-surface-1 rounded-[24px] overflow-hidden items-center justify-center p-6 border-none shadow-xl">
      
      <div class="flex flex-col items-center animate-pulse">
        <div class="w-20 h-20 bg-primary-500/10 rounded-full flex items-center justify-center mb-6">
            <i class="fa-solid fa-store text-4xl text-primary-500"></i>
        </div>
        <h2 class="text-2xl font-black text-surface-0 mb-2">Iniciando Balcão...</h2>
        <p class="text-surface-400 font-bold">Preparando terminal de venda rápida.</p>
      </div>

  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useSessionStore } from '@/stores/session-store';

const sessionStore = useSessionStore();

onMounted(async () => {
    // Inicia sessão de balcão e redireciona automaticamente
    await sessionStore.openSession('counter', null);
});
</script>