<template>
  <div class="flex flex-col min-h-full animate-fade-in bg-surface-950">
    
    <div v-if="!userStore.isLoggedIn" class="flex flex-col items-center justify-center flex-1 p-6 min-h-[70vh]">
        <div class="w-full max-w-sm bg-surface-900 border border-surface-800 rounded-2xl p-8 shadow-xl text-center">
            <div class="w-20 h-20 bg-surface-800 rounded-full flex items-center justify-center mx-auto mb-6 border border-surface-700">
                <i class="fa-solid fa-user-astronaut text-3xl text-primary-500"></i>
            </div>
            <h2 class="text-2xl font-bold text-white mb-2">Bem-vindo!</h2>
            <p class="text-surface-400 text-sm mb-8">Acesse sua conta para acompanhar seus pedidos e desbloquear ofertas.</p>
            
            <div class="w-full bg-white/5 p-1 rounded-xl border border-surface-700">
                <div id="google-btn-wrapper" class="w-full flex justify-center min-h-[50px]"></div>
            </div>
        </div>
    </div>
    
    <div v-else class="flex flex-col h-full">
        <div class="p-8 pb-10 flex flex-col items-center bg-surface-900 border-b border-surface-800 shadow-sm relative z-10">
            <img :src="userStore.user.avatar || 'https://www.gravatar.com/avatar/?d=mp'" class="w-20 h-20 rounded-full border-4 border-surface-800 shadow-md mb-3">
            <h2 class="text-xl font-bold text-white">{{ userStore.user.name }}</h2>
            <p class="text-surface-400 text-sm">{{ userStore.user.email }}</p>
            <div v-if="userStore.user.dob" class="mt-2 text-xs text-primary-400 font-bold bg-primary-500/10 px-2 py-0.5 rounded">
                {{ calculateAge(userStore.user.dob) }} anos
            </div>
        </div>

        <div class="flex-1 p-6 space-y-3 bg-surface-950">
            <h3 class="text-xs font-bold text-surface-500 uppercase tracking-widest mb-2 ml-1">Minha Conta</h3>
            
            <button class="w-full bg-surface-900 border border-surface-800 rounded-xl p-4 flex items-center gap-4 hover:bg-surface-800 transition-colors group"
                @click="emit('open-history')"
            >
                <div class="w-9 h-9 rounded-lg bg-surface-800 flex items-center justify-center text-primary-500 border border-surface-700 group-hover:border-primary-500 group-hover:text-primary-400 transition-colors">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div class="flex-1 text-left">
                    <div class="text-white font-bold text-sm">Meus Pedidos</div>
                    <div class="text-surface-500 text-xs">Histórico e status</div>
                </div>
                <i class="fa-solid fa-chevron-right text-surface-600"></i>
            </button>

            <button class="w-full bg-surface-900 border border-surface-800 rounded-xl p-4 flex items-center gap-4 hover:bg-surface-800 transition-colors group"
                @click="emit('open-addresses')"
            >
                <div class="w-9 h-9 rounded-lg bg-surface-800 flex items-center justify-center text-blue-500 border border-surface-700 group-hover:border-blue-500 group-hover:text-blue-400 transition-colors">
                    <i class="fa-solid fa-map-location-dot"></i>
                </div>
                <div class="flex-1 text-left">
                    <div class="text-white font-bold text-sm">Meus Endereços</div>
                    <div class="text-surface-500 text-xs">Gerenciar locais</div>
                </div>
                <i class="fa-solid fa-chevron-right text-surface-600"></i>
            </button>

            <div class="pt-6">
                <button @click="userStore.logout" class="w-full border border-surface-800 text-red-400 font-bold py-3 rounded-xl hover:bg-surface-900 hover:border-red-500/30 transition-colors flex items-center justify-center gap-2 text-sm">
                    <i class="fa-solid fa-right-from-bracket"></i> Sair da conta
                </button>
            </div>
        </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useUserStore } from '@/stores/user-store';

const emit = defineEmits(['open-history', 'open-addresses']);
const userStore = useUserStore();

// Lógica de Google Login
const renderGoogleButton = () => {
    if (document.getElementById('google-client-script')) {
        initGoogleBtn();
        return;
    }
    const script = document.createElement('script');
    script.id = 'google-client-script';
    script.src = "https://accounts.google.com/gsi/client";
    script.async = true;
    script.onload = initGoogleBtn;
    document.body.appendChild(script);
};

const initGoogleBtn = () => {
    /* global google */
    if (typeof google === 'undefined') return;
    const clientId = '855300367759-q1apf6ltpunkmfie5nikp8g20fuvdjso.apps.googleusercontent.com'; // Use sua chave real
    google.accounts.id.initialize({
        client_id: clientId,
        callback: handleGoogleCallback,
        auto_select: false,
        cancel_on_tap_outside: false
    });
    const container = document.getElementById("google-btn-wrapper");
    if (container) {
        google.accounts.id.renderButton(container, { theme: "outline", size: "large", width: "300", text: "continue_with", shape: "pill" });
    }
};

const handleGoogleCallback = async (response) => {
    await userStore.loginWithGoogle(response.credential);
};

const calculateAge = (dob) => {
    const birthDate = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
    return age;
};

onMounted(() => {
    if (!userStore.isLoggedIn) {
        setTimeout(renderGoogleButton, 100);
    }
});
</script>