<template>
  <div class="w-full h-full flex items-center justify-center bg-surface-950">
      <div class="flex flex-col items-center">
        <i class="fa-solid fa-spinner fa-spin text-4xl text-primary-500 mb-4"></i>
        <p class="text-surface-400 animate-pulse">Iniciando Balcão...</p>
      </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useSessionStore } from '@/stores/session-store';

const sessionStore = useSessionStore();

onMounted(async () => {
    // Inicia sessão de balcão e redireciona automaticamente (via store) para /pdv
    await sessionStore.openSession('counter', null);
});
</script>