<template>
  <div class="flex h-[100dvh] w-full overflow-hidden bg-surface-base text-surface-200 font-sans relative md:p-3 gap-3">
    
    <DesktopSidebar v-if="!isClientLayout" class="hidden md:flex" />

    <div class="flex-1 h-full overflow-hidden relative z-10 rounded-[24px] bg-surface-1 shadow-2xl">
        <router-view v-slot="{ Component }">
            <transition name="fade" mode="out-in">
                <component :is="Component" />
            </transition>
        </router-view>
    </div>

    <SharedBottomNav v-if="showBottomNav" class="md:hidden" />
    
    <OnboardingModal v-if="isClientLayout" :visible="showOnboarding" @completed="handleOnboardingComplete" />
    
    <Toast :pt="toastPT">
        <template #message="slotProps">
            <div class="flex items-center gap-3 p-4">
                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" :class="getToastColor(slotProps.message.severity)">
                    <i class="fa-solid text-sm" :class="getToastIcon(slotProps.message.severity)"></i>
                </div>
                <div class="flex flex-col">
                    <span class="font-bold text-surface-0 text-sm">{{ slotProps.message.summary }}</span>
                    <span class="text-xs text-surface-400">{{ slotProps.message.detail }}</span>
                </div>
            </div>
        </template>
    </Toast>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import { useCashStore } from '@/stores/cash-store';
import { useSettingsStore } from '@/stores/settings-store';
import { useUserStore } from '@/stores/user-store';
import Toast from 'primevue/toast';
import { useToast } from "primevue/usetoast";
import { registerToast } from "@/services/notify";
import OnboardingModal from '@/components/client/OnboardingModal.vue';
import SharedBottomNav from '@/components/shared/SharedBottomNav.vue';
import DesktopSidebar from '@/components/shared/DesktopSidebar.vue';

const settingsStore = useSettingsStore();
const userStore = useUserStore();
const cashStore = useCashStore();
const route = useRoute();
const toast = useToast();

// Registra o serviço de Toast para uso global em outros arquivos
registerToast(toast);

// --- LÓGICA DE LAYOUT E RESPONSIVIDADE ---

// 1. Verifica se é a visão do cliente (sem sidebar, layout específico)
const isClientLayout = computed(() => route.meta.layout === 'client');

// 2. Monitora a largura da tela para saber se é mobile (ex: < 768px)
const windowWidth = ref(window.innerWidth);
const isMobile = computed(() => windowWidth.value < 768);

const onResize = () => { 
    windowWidth.value = window.innerWidth; 
};

// 3. Computada Mestra que decide se mostra a Bottom Nav GLOBAL
const showBottomNav = computed(() => {
    // [CORREÇÃO] Se for layout de Cliente, o App.vue NÃO deve renderizar a barra.
    // Motivo: A ClientView.vue já possui sua própria instância da barra, 
    // conectada aos eventos locais (@trigger) que abrem modais e sheets.
    if (isClientLayout.value) return false; 
    
    // Se for Staff (Logado), SÓ mostra se for mobile.
    // (No desktop, a Sidebar lateral já assume o papel de navegação).
    return isMobile.value;
});

// --- CONTROLES DO ONBOARDING ---
const showOnboarding = computed(() => 
    // CORREÇÃO: Removemos a verificação do layout aqui, pois o v-if 
    // no template já cuida de exibir apenas para clientes.
    // O importante é: estar logado E NÃO ter completado o cadastro.
    userStore.isLoggedIn && 
    !userStore.user?.onboarding_complete
);

const handleOnboardingComplete = () => {
    toast.add({ severity: 'success', summary: 'Pronto!', detail: 'Cadastro finalizado.' });
};

// --- CONFIGURAÇÃO VISUAL DO TOAST ---
const toastPT = {
    root: { class: '!w-[90%] !max-w-sm !left-1/2 !-translate-x-1/2 !top-4 !pointer-events-none !z-[9999]' },
    message: { class: '!bg-surface-2/95 !backdrop-blur-xl !border-none !shadow-2xl !rounded-2xl !mb-3 !p-0' },
    messageContent: { class: '!p-0' }
};

const getToastIcon = (severity) => {
    switch(severity) {
        case 'success': return 'fa-check';
        case 'error': return 'fa-xmark';
        case 'warn': return 'fa-triangle-exclamation';
        default: return 'fa-circle-info';
    }
};

const getToastColor = (severity) => {
    switch(severity) {
        case 'success': return 'bg-green-500/20 text-green-500';
        case 'error': return 'bg-red-500/20 text-red-500';
        case 'warn': return 'bg-orange-500/20 text-orange-500';
        default: return 'bg-blue-500/20 text-blue-500';
    }
};

// --- CICLO DE VIDA ---

onMounted(async () => {
    // Adiciona listener de resize para responsividade dinâmica
    window.addEventListener('resize', onResize);

    // Inicializa configurações e sessão
    await settingsStore.checkRealtimeStatus();
    userStore.initializeSession();

    if (userStore.isLoggedIn) {
        await userStore.refreshProfile();
    }

    // Se for Staff, tenta verificar o status do caixa (pode falhar se sem permissão, ok ignorar)
    if (!isClientLayout.value) {
        try { 
            await cashStore.checkStatus(); 
        } catch (e) {
            console.warn('Falha ao checar caixa (normal se não for staff):', e);
        }
    }
});

onUnmounted(() => {
    window.removeEventListener('resize', onResize);
});
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>