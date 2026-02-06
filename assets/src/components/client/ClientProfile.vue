<template>
  <div class="flex flex-col h-full bg-surface-base overflow-y-auto pb-24 animate-fade-in">
    
    <div class="p-6 pt-10 border-b border-surface-3/10 bg-surface-1 relative rounded-b-[32px] shadow-lg">
        <div class="flex items-center gap-5">
            <div class="w-16 h-16 bg-surface-2 rounded-full border-2 border-surface-3/20 flex items-center justify-center text-2xl font-black text-surface-500 shadow-xl overflow-hidden relative shrink-0">
                <img v-if="userAvatar" :src="userAvatar" class="w-full h-full object-cover" />
                <span v-else-if="userStore.user?.name">{{ userStore.user.name.charAt(0) }}</span>
                <i v-else class="fa-solid fa-user"></i>
            </div>
            
            <div class="flex-1 min-w-0">
                <h2 class="text-xl font-black text-surface-0 leading-tight truncate">
                    {{ userStore.user?.name || 'Visitante' }}
                </h2>
                
                <div v-if="userStore.isLoggedIn" class="flex flex-col gap-1 mt-1">
                    <p class="text-xs text-surface-400 font-bold flex items-center gap-2">
                        <span>{{ formatPhone(userStore.user?.phone) }}</span>
                        <button class="text-primary-500 hover:text-primary-400 transition-colors p-1 rounded-md hover:bg-surface-2" @click="openPhoneSheet">
                            <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                        </button>
                    </p>
                </div>
                <p v-else class="text-xs text-surface-400 mt-1 font-medium">Faça login para ver seus dados</p>
            </div>
        </div>

        <div v-if="userStore.isLoggedIn" class="mt-8 bg-gradient-to-r from-primary-900/60 to-surface-2 border border-primary-500/30 rounded-[24px] p-5 flex items-center justify-between relative overflow-hidden group shadow-xl">
            <div class="absolute inset-0 bg-primary-500/10 blur-xl group-hover:bg-primary-500/20 transition-all duration-500"></div>
            
            <div class="relative z-10 flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-primary-500/20 flex items-center justify-center text-primary-400 animate-pulse-slow border border-primary-500/30">
                    <i class="fa-solid fa-crown text-xl"></i>
                </div>
                <div>
                    <span class="text-[9px] uppercase font-black text-primary-400 tracking-widest block mb-0.5">Fidelidade</span>
                    <span class="text-3xl font-black text-surface-0 tracking-tight leading-none">{{ userStore.user?.loyalty_points || 0 }} <small class="text-xs font-bold text-surface-400">pts</small></span>
                </div>
            </div>

            <button class="relative z-10 bg-surface-1 hover:bg-surface-3 text-surface-0 text-xs font-black px-5 py-2.5 rounded-full border border-surface-3/50 transition-all shadow-md active:scale-95 uppercase tracking-wide" @click="$emit('open-rewards')">
                Trocar
            </button>
        </div>
    </div>

    <div class="p-5 space-y-3 mt-4">
        <button class="menu-btn" @click="$emit('open-history')">
            <div class="icon-box"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <span>Meus Pedidos</span>
            <i class="arrow fa-solid fa-chevron-right"></i>
        </button>

        <button class="menu-btn" @click="isAddressModalOpen = true">
            <div class="icon-box"><i class="fa-solid fa-map-location-dot"></i></div>
            <span>Meus Endereços</span>
            <i class="arrow fa-solid fa-chevron-right"></i>
        </button>

        <button v-if="userStore.isLoggedIn" class="menu-btn" @click="handleLogout">
            <div class="icon-box !text-red-400 !bg-red-500/10 !border-red-500/20"><i class="fa-solid fa-right-from-bracket"></i></div>
            <span class="text-red-400">Sair da Conta</span>
        </button>
        
        <button v-else class="menu-btn" @click="handleLogin">
            <div class="icon-box !text-green-400 !bg-green-500/10 !border-green-500/20"><i class="fa-solid fa-right-to-bracket"></i></div>
            <span class="text-green-400">Entrar / Cadastrar</span>
        </button>
    </div>

    <div class="mt-auto p-8 text-center pb-24">
        <p class="text-[10px] text-surface-600 font-black uppercase tracking-[0.3em]">Nativa Delivery v2.0</p>
    </div>

    <Dialog v-model:visible="isAddressModalOpen" modal dismissableMask position="bottom" :showHeader="false" class="!m-0 !rounded-t-[32px] overflow-hidden shadow-[0_-10px_40px_rgba(0,0,0,0.6)]" :style="{ width: '100%', maxWidth: '500px' }" :pt="{ root: { class: '!bg-surface-1 !border-none' }, content: { class: '!p-0' } }">
        <div class="bg-surface-1 h-[80vh] flex flex-col">
            <div class="p-5 border-b border-surface-3/10 text-center relative">
                <div class="w-12 h-1.5 bg-surface-3 rounded-full mx-auto mb-4 opacity-50"></div>
                <h3 class="font-black text-surface-0 text-lg">Meus Endereços</h3>
                <button @click="isAddressModalOpen = false" class="absolute right-5 top-7 text-surface-400 hover:text-surface-0"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="flex-1 p-4 overflow-hidden">
                <SmartAddressWidget v-if="userStore.user?.id" :client-id="userStore.user.id" mode="management" />
                <div v-else class="flex flex-col items-center justify-center h-full text-surface-500">
                    <i class="fa-solid fa-lock text-4xl mb-3 opacity-50"></i>
                    <p class="text-sm font-bold">Faça login para gerenciar endereços</p>
                </div>
            </div>
        </div>
    </Dialog>

    <Dialog v-model:visible="isPhoneSheetOpen" modal dismissableMask position="bottom" :showHeader="false" class="!w-full !m-0 !max-w-md !mx-auto !bg-transparent !shadow-none" :contentStyle="{ padding: '0', background: 'transparent' }" :pt="{ mask: { class: '!bg-surface-950/80 backdrop-blur-sm' }, root: { class: '!border-0 !rounded-none' } }">
        <div class="bg-surface-1 border-t border-surface-3/10 rounded-t-[2.5rem] p-8 pb-12 shadow-2xl relative animate-slide-up">
            <div class="w-12 h-1.5 bg-surface-3 rounded-full mx-auto mb-6 opacity-50"></div>
            <h3 class="text-xl font-black text-surface-0 mb-2 text-center">Atualizar WhatsApp</h3>
            <p class="text-sm text-surface-400 text-center mb-8 font-medium">Informe seu novo número para contato.</p>
            <div class="flex flex-col gap-4">
                <div class="relative group">
                    <i class="fa-brands fa-whatsapp absolute left-4 top-1/2 -translate-y-1/2 text-surface-500 group-focus-within:text-green-500 z-10 transition-colors"></i>
                    <InputMask v-model="newPhone" mask="(99) 99999-9999" placeholder="(00) 00000-0000" class="!bg-surface-2 !border-none !text-surface-0 !h-14 !w-full !rounded-[20px] focus:!ring-2 focus:!ring-primary-500 !pl-12 font-bold text-lg" autoFocus />
                </div>
                <Button label="Salvar Número" icon="fa-solid fa-check" class="!bg-primary-600 hover:!bg-primary-500 !border-none !h-14 !rounded-full !font-black !w-full !text-surface-950 text-lg shadow-lg" :loading="loadingPhone" @click="updatePhone" />
            </div>
        </div>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useUserStore } from '@/stores/user-store';
import { useRouter } from 'vue-router';
import { useFormat } from '@/composables/useFormat';
import api from '@/services/api';
import { notify } from '@/services/notify';
import Dialog from 'primevue/dialog';
import SmartAddressWidget from '@/components/shared/SmartAddressWidget.vue';
import InputMask from 'primevue/inputmask';
import Button from 'primevue/button';
import md5 from 'blueimp-md5'; 

const emit = defineEmits(['open-history', 'open-rewards']);
const userStore = useUserStore();
const router = useRouter();
const { formatPhone, formatCPF, cleanDigits } = useFormat(); 

const isAddressModalOpen = ref(false);
const isPhoneSheetOpen = ref(false);
const newPhone = ref('');
const loadingPhone = ref(false);

const userAvatar = computed(() => {
    // 1. Prioridade para imagem do backend
    if (userStore.user?.avatar_url) return userStore.user.avatar_url;
    
    // 2. Gravatar baseado no e-mail
    if (userStore.user?.email) {
        try {
            const hash = md5(userStore.user.email.trim().toLowerCase());
            return `https://www.gravatar.com/avatar/${hash}?d=mp`;
        } catch (e) { return null; }
    }
    
    return null;
});

const openPhoneSheet = () => {
    newPhone.value = userStore.user?.phone || '';
    isPhoneSheetOpen.value = true;
};

const updatePhone = async () => {
    const clean = cleanDigits(newPhone.value);
    if (clean.length < 10) {
        notify('warn', 'Atenção', 'Número inválido.');
        return;
    }

    loadingPhone.value = true;
    try {
        const { data } = await api.post('/my-profile/update-phone', { phone: clean });
        if (data.success) {
            notify('success', 'Sucesso', 'Telefone atualizado!');
            // Atualização otimista
            if (userStore.user) userStore.user.phone = clean;
            // Atualização real via backend
            await userStore.refreshProfile();
            isPhoneSheetOpen.value = false;
        }
    } catch (e) {
        notify('error', 'Erro', e.response?.data?.message || 'Falha ao atualizar.');
    } finally {
        loadingPhone.value = false;
    }
};

const handleLogout = () => {
    userStore.logout();
    router.replace('/home');
};

const handleLogin = () => {
    router.push('/'); 
};
</script>

<style scoped>
.menu-btn {
    @apply w-full bg-surface-900 border border-surface-800 rounded-xl p-4 flex items-center justify-between text-left transition-all hover:bg-surface-800 hover:border-surface-700 active:scale-[0.98];
}
.icon-box {
    @apply w-10 h-10 rounded-lg bg-surface-950 flex items-center justify-center text-surface-400 border border-surface-800 mr-4;
}
.menu-btn span {
    @apply font-bold text-sm text-surface-200 flex-1;
}
.arrow {
    @apply text-surface-600 text-xs;
}
.animate-pulse-slow {
    animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .7; }
}
.animate-slide-up { animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
</style>