<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import { useCashStore } from '@/stores/cash-store';
import { useSettingsStore } from '@/stores/settings-store';
import { useUserStore } from '@/stores/user-store';
import Toast from 'primevue/toast';
import Button from 'primevue/button';
import { useToast } from "primevue/usetoast";
import { registerToast } from "@/services/notify";
import OnboardingModal from '@/components/OnboardingModal.vue';

// --- IMPORTS DE LOGO ---
import logoMono from '@/svg/nativa-mono.svg';
import logoFull from '@/svg/logo-branca.svg';

const settingsStore = useSettingsStore();
const currentBgImage = ref(null);

const openImages = import.meta.glob('@/images/home/open/*.{png,jpg,jpeg,svg,webp}', { eager: true, query: '?url', import: 'default' });
const closedImages = import.meta.glob('@/images/home/closed/*.{png,jpg,jpeg,svg,webp}', { eager: true, query: '?url', import: 'default' });

const getRandomImage = (imageObj) => {
    const paths = Object.values(imageObj);
    if (paths.length === 0) return null;
    return paths[Math.floor(Math.random() * paths.length)];
};

const toast = useToast();
registerToast(toast);

const route = useRoute();
const mini = ref(true); // Começa retraído
const cashStore = useCashStore();
const userStore = useUserStore();

const isClientLayout = computed(() => route.meta.layout === 'client');

const showOnboarding = computed(() => {
    if (!isClientLayout.value) return false;
    if (!userStore.isLoggedIn) return false;
    if (userStore.user?.onboarding_complete) return false;
    return true; 
});

const handleOnboardingComplete = () => {
    toast.add({ severity: 'success', summary: 'Tudo pronto!', detail: 'Seu cadastro foi concluído.', life: 3000 });
};

// --- TOAST CONFIG ---
const clientToastPT = {
    root: { class: '!w-[90%] !max-w-sm !left-1/2 !-translate-x-1/2 !top-4 !pointer-events-none' },
    container: { class: '!bg-transparent !border-0 !shadow-none !p-0 !mb-3' },
    content: { class: '!bg-transparent !border-0 !p-0' }
};

const getToastIcon = (severity) => {
    switch(severity) {
        case 'success': return 'fa-circle-check';
        case 'error': return 'fa-circle-xmark';
        case 'warn': return 'fa-triangle-exclamation';
        default: return 'fa-circle-info';
    }
};

const getToastColor = (severity) => {
    switch(severity) {
        case 'success': return 'text-green-400 bg-green-500/10 border-green-500/20';
        case 'error': return 'text-red-400 bg-red-500/10 border-red-500/20';
        case 'warn': return 'text-orange-400 bg-orange-500/10 border-orange-500/20';
        default: return 'text-blue-400 bg-blue-500/10 border-blue-500/20';
    }
};

onMounted(async () => {
  await settingsStore.checkRealtimeStatus();
  if (settingsStore.status.general) {
      currentBgImage.value = getRandomImage(openImages);
  } else {
      currentBgImage.value = getRandomImage(closedImages);
  }

  userStore.initializeSession();

  if (!isClientLayout.value) {
      try { await cashStore.checkStatus(); } catch (e) {}
  }
});
</script>

<template>
  <div 
    class="flex h-screen w-full overflow-hidden bg-surface-950 text-surface-200 font-sans relative"
    :class="{'p-3': !isClientLayout}" 
  >
    
    <Toast 
        :position="isClientLayout ? 'top-center' : 'top-right'"
        :pt="isClientLayout ? clientToastPT : {}"
    >
        <template #message="{ message, closeCallback }">
            <div 
                v-if="isClientLayout" 
                class="pointer-events-auto flex items-center gap-4 w-full bg-surface-900/80 backdrop-blur-xl border border-white/10 p-4 rounded-[1.5rem] shadow-[0_8px_30px_rgba(0,0,0,0.5)] relative overflow-hidden animate-toast-slide-down cursor-pointer"
                @click="closeCallback"
            >
                <div class="absolute inset-0 bg-gradient-to-r from-primary-500/5 to-transparent pointer-events-none"></div>
                <div 
                    class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 border transition-colors"
                    :class="getToastColor(message.severity)"
                >
                    <i class="fa-solid text-lg" :class="getToastIcon(message.severity)"></i>
                </div>
                <div class="flex-1 min-w-0 flex flex-col justify-center">
                    <div class="font-bold text-white text-sm leading-tight mb-0.5">{{ message.summary }}</div>
                    <div class="text-surface-400 text-xs truncate">{{ message.detail }}</div>
                </div>
            </div>

            <div v-else class="flex flex-col gap-2 w-full">
                <div class="flex items-start gap-3">
                    <i class="fa-solid text-xl mt-0.5" :class="[getToastIcon(message.severity), {'text-green-500': message.severity=='success', 'text-red-500': message.severity=='error', 'text-orange-500': message.severity=='warn', 'text-blue-500': message.severity=='info'}]"></i>
                    <div class="flex-1">
                        <div class="font-bold text-surface-900 dark:text-surface-50 text-base">{{ message.summary }}</div>
                        <div class="text-surface-600 dark:text-surface-300 text-sm mt-1">{{ message.detail }}</div>
                    </div>
                    <button @click="closeCallback" class="text-surface-400 hover:text-surface-600 transition-colors"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
        </template>
    </Toast>

    <transition name="fade-slow">
        <div v-if="isClientLayout && currentBgImage" class="absolute inset-0 z-0">
            <div class="absolute inset-0 bg-cover bg-center transition-transform duration-[60s] ease-linear scale-125"
                 :style="{ backgroundImage: `url(${currentBgImage})` }">
            </div>
            <div class="absolute inset-0 bg-black/60"></div>
        </div>
    </transition>

    <div 
        v-if="!isClientLayout" 
        class="flex flex-1 rounded-[2.5rem] bg-surface-900 shadow-2xl overflow-hidden relative border border-surface-800/50"
    >
        
        <div 
            class="flex flex-col h-full transition-all duration-700 ease-in-out shrink-0 z-20 py-6 pl-4"
            :class="mini ? 'w-[4.5rem]' : 'w-fit pr-6 min-w-[16rem]'"
            @mouseenter="mini = false"
            @mouseleave="mini = true"
        >
            <div class="flex items-center justify-center h-16 shrink-0 overflow-hidden relative mb-4">
                <transition name="fade-logo">
                    <img v-if="mini" :src="logoMono" class="w-10 h-10 object-contain absolute" />
                </transition>
                <transition name="fade-logo">
                    <img v-if="!mini" :src="logoFull" class="h-10 w-auto object-contain absolute left-2" />
                </transition>
            </div>
            
            <div class="flex-1 overflow-y-auto space-y-1 scrollbar-thin pr-2">
                
                <div class="px-3 pb-2 pt-2 text-[10px] font-bold text-surface-500 uppercase tracking-widest whitespace-nowrap overflow-hidden transition-opacity duration-700" :class="mini ? 'opacity-0' : 'opacity-60'">Operação</div>

                <router-link to="/tables" custom v-slot="{ navigate, isActive }">
                    <div @click="navigate" class="flex items-center cursor-pointer p-3 rounded-xl transition-all duration-200 group relative overflow-hidden whitespace-nowrap mb-1" :class="isActive ? 'bg-primary-500 text-surface-900 shadow-lg shadow-primary-500/20 font-bold' : 'text-surface-400 hover:bg-surface-800 hover:text-white'">
                        <i class="pi fa-solid fa-utensils text-xl shrink-0 w-8 text-center" :class="mini ? 'mx-auto' : 'mr-2'"></i> 
                        <span v-if="!mini" class="animate-fade-in-slow">Mesas</span>
                    </div>
                </router-link>

                <router-link to="/counter" custom v-slot="{ navigate, isActive }">
                    <div @click="navigate" class="flex items-center cursor-pointer p-3 rounded-xl transition-all duration-200 group relative overflow-hidden whitespace-nowrap mb-1" :class="isActive ? 'bg-primary-500 text-surface-900 shadow-lg shadow-primary-500/20 font-bold' : 'text-surface-400 hover:bg-surface-800 hover:text-white'">
                        <i class="pi fa-solid fa-cash-register text-xl shrink-0 w-8 text-center" :class="mini ? 'mx-auto' : 'mr-2'"></i> 
                        <span v-if="!mini" class="animate-fade-in-slow">Balcão</span>
                    </div>
                </router-link>

                <router-link to="/delivery" custom v-slot="{ navigate, isActive }">
                    <div @click="navigate" class="flex items-center cursor-pointer p-3 rounded-xl transition-all duration-200 group relative overflow-hidden whitespace-nowrap mb-1" :class="isActive ? 'bg-primary-500 text-surface-900 shadow-lg shadow-primary-500/20 font-bold' : 'text-surface-400 hover:bg-surface-800 hover:text-white'">
                        <i class="pi fa-solid fa-motorcycle text-xl shrink-0 w-8 text-center" :class="mini ? 'mx-auto' : 'mr-2'"></i> 
                        <span v-if="!mini" class="animate-fade-in-slow">Delivery</span>
                    </div>
                </router-link>

                <div class="h-px bg-surface-800 my-4 mx-3"></div>

                <div class="px-3 pb-2 text-[10px] font-bold text-surface-500 uppercase tracking-widest whitespace-nowrap overflow-hidden transition-opacity duration-700" :class="mini ? 'opacity-0' : 'opacity-60'">Gestão</div>

                <router-link to="/cash-flow" custom v-slot="{ navigate, isActive }">
                    <div @click="navigate" class="flex items-center cursor-pointer p-3 rounded-xl transition-all duration-200 group relative overflow-hidden whitespace-nowrap mb-1" :class="isActive ? 'bg-surface-800 text-white font-bold' : 'text-surface-400 hover:bg-surface-800 hover:text-white'">
                        <i class="pi fa-solid fa-sack-dollar text-xl shrink-0 w-8 text-center" :class="mini ? 'mx-auto' : 'mr-2'"></i> 
                        <span v-if="!mini" class="animate-fade-in-slow">Caixa</span>
                    </div>
                </router-link>

                <router-link to="/products" custom v-slot="{ navigate, isActive }">
                    <div @click="navigate" class="flex items-center cursor-pointer p-3 rounded-xl transition-all duration-200 group relative overflow-hidden whitespace-nowrap mb-1" :class="isActive ? 'bg-surface-800 text-white font-bold' : 'text-surface-400 hover:bg-surface-800 hover:text-white'">
                        <i class="pi fa-solid fa-box-open text-xl shrink-0 w-8 text-center" :class="mini ? 'mx-auto' : 'mr-2'"></i> 
                        <span v-if="!mini" class="animate-fade-in-slow">Produtos</span>
                    </div>
                </router-link>

                <router-link to="/customers" custom v-slot="{ navigate, isActive }">
                    <div @click="navigate" class="flex items-center cursor-pointer p-3 rounded-xl transition-all duration-200 group relative overflow-hidden whitespace-nowrap mb-1" :class="isActive ? 'bg-surface-800 text-white font-bold' : 'text-surface-400 hover:bg-surface-800 hover:text-white'">
                        <i class="pi fa-solid fa-users text-xl shrink-0 w-8 text-center" :class="mini ? 'mx-auto' : 'mr-2'"></i> 
                        <span v-if="!mini" class="animate-fade-in-slow">Clientes</span>
                    </div>
                </router-link>

                <router-link to="/team" custom v-slot="{ navigate, isActive }">
                    <div @click="navigate" class="flex items-center cursor-pointer p-3 rounded-xl transition-all duration-200 group relative overflow-hidden whitespace-nowrap mb-1" :class="isActive ? 'bg-surface-800 text-white font-bold' : 'text-surface-400 hover:bg-surface-800 hover:text-white'">
                        <i class="pi fa-solid fa-id-badge text-xl shrink-0 w-8 text-center" :class="mini ? 'mx-auto' : 'mr-2'"></i> 
                        <span v-if="!mini" class="animate-fade-in-slow">Equipe</span>
                    </div>
                </router-link>

                <router-link to="/settings" custom v-slot="{ navigate, isActive }">
                    <div @click="navigate" class="flex items-center cursor-pointer p-3 rounded-xl transition-all duration-200 group relative overflow-hidden whitespace-nowrap mb-1" :class="isActive ? 'bg-surface-800 text-white font-bold' : 'text-surface-400 hover:bg-surface-800 hover:text-white'">
                        <i class="pi fa-solid fa-cog text-xl shrink-0 w-8 text-center" :class="mini ? 'mx-auto' : 'mr-2'"></i> 
                        <span v-if="!mini" class="animate-fade-in-slow">Configurações</span>
                    </div>
                </router-link>
            </div>
        </div>

        <div class="flex-1 flex flex-col h-full relative z-10 py-3 pr-3">
            <div class="flex-1 bg-surface-950 rounded-[2rem] overflow-hidden relative shadow-inner border border-surface-800/50 p-3">
                <router-view v-slot="{ Component }">
                    <keep-alive include="PublicMenuView">
                        <component :is="Component" />
                    </keep-alive>
                </router-view>
            </div>
        </div>

    </div>

    <div v-else class="flex-1 flex flex-col h-full relative overflow-hidden z-10 p-0">
        <router-view v-slot="{ Component }">
            <keep-alive include="PublicMenuView">
                <component :is="Component" />
            </keep-alive>
        </router-view>
    </div>

    <OnboardingModal 
        v-if="isClientLayout" 
        :visible="showOnboarding"
        @completed="handleOnboardingComplete"
    />

  </div>
</template>

<style>
.animate-fade-in { animation: fadeIn 0.3s ease-in-out; }
.animate-fade-in-slow { animation: fadeIn 0.8s ease-in-out; }

.fade-slow-enter-active { transition: opacity 2s ease-in; }
.fade-slow-enter-from { opacity: 0; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.animate-toast-slide-down { animation: toastSlideDown 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes toastSlideDown {
    from { transform: translateY(-20px) scale(0.9); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}

.fade-logo-enter-active,
.fade-logo-leave-active {
  transition: opacity 0.8s ease; 
}
.fade-logo-enter-from,
.fade-logo-leave-to {
  opacity: 0;
}
</style>