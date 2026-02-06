<template>
  <div class="relative w-full h-12 rounded-full overflow-hidden group shadow-lg cursor-pointer transition-transform active:scale-95">
    
    <div class="absolute inset-0 bg-white/90 hover:bg-white backdrop-blur-md transition-all duration-300 flex items-center justify-center gap-2.5 z-10 pointer-events-none">
        <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" alt="Google" class="w-4 h-4" />
        
        <span class="text-surface-900 font-bold text-xs uppercase tracking-wide">
            {{ label }}
        </span>
    </div>

    <div id="google-btn-container" class="absolute inset-0 z-20 opacity-0 cursor-pointer w-full h-full transform scale-150 origin-center"></div>
    
    <div v-if="!clientId" class="absolute bottom-0 w-full text-center bg-red-500 text-white text-[9px] z-30">
        Configurar ID
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useUserStore } from '@/stores/user-store';

const props = defineProps({
    label: { type: String, default: 'Google' }
});

const userStore = useUserStore();
const clientId = ref(window.nativaData?.googleClientId || '');

onMounted(() => {
  if (!clientId.value || !window.google) return;

  window.google.accounts.id.initialize({
    client_id: clientId.value,
    callback: (response) => userStore.loginWithGoogle(response.credential),
    auto_select: false,
    cancel_on_tap_outside: true
  });

  window.google.accounts.id.renderButton(
    document.getElementById("google-btn-container"),
    { theme: "filled_blue", size: "large", shape: "rectangular", width: "500", height: "100" }
  );
});
</script>

<style scoped>
#google-btn-container :deep(iframe) {
    width: 100% !important;
    height: 100% !important;
}
</style>