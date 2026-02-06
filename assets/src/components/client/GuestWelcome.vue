<template>
  <div class="flex flex-col h-full w-full bg-surface-1 rounded-[24px] relative overflow-hidden">
    
    <div class="absolute inset-0 z-0">
        <transition name="fade-slow" mode="in-out">
            <img 
                v-if="bgImage" 
                :key="bgImage" 
                :src="bgImage" 
                class="w-full h-full object-cover transition-opacity duration-1000" 
            />
        </transition>
        <div class="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-black/40 to-transparent"></div>
    </div>

    <div class="relative z-10 flex flex-col h-full px-6 pb-8 pt-12 items-center justify-between">
        
        <div class="w-full flex justify-center animate-fade-in-down">
            <img src="@/svg/logo-branca.svg" alt="Nativa" class="h-8 md:h-16 w-auto object-contain drop-shadow-lg" />
        </div>

        <div class="w-full max-w-sm flex flex-col items-center gap-5 animate-fade-in-up">
            
            <div class="flex flex-col items-center gap-1.5 text-center">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-black/40 backdrop-blur-md border border-white/10 shadow-lg">
                    <span class="w-1.5 h-1.5 rounded-full" :class="isOpen ? 'bg-green-400 animate-pulse' : 'bg-red-400'"></span>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-white">
                        {{ isOpen ? 'Aberto' : 'Fechado' }}
                    </span>
                </div>
                
                <div class="flex flex-col items-center" @click="showHours = true">
                    <span class="text-xs font-bold text-white drop-shadow-[0_2px_2px_rgba(0,0,0,0.8)]">
                        {{ statusMessage }}
                    </span>
                    <button class="text-[9px] font-bold text-white/80 hover:text-white uppercase tracking-wider underline decoration-dotted mt-1 drop-shadow-md">
                        Ver Horários
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 w-full mt-2">
                
                <GoogleLoginBtn label="Entrar" />

                <button 
                    v-if="deferredPrompt"
                    class="h-12 bg-primary-500 hover:bg-primary-400 text-surface-950 rounded-full font-black text-xs uppercase tracking-wider transition-all active:scale-95 flex items-center justify-center gap-2 shadow-lg"
                    @click="installPwa"
                >
                    <i class="fa-solid fa-download"></i> Baixar App
                </button>

                <button 
                    v-else
                    class="h-12 bg-surface-950/60 hover:bg-surface-950/80 text-white backdrop-blur-md border border-white/10 rounded-full font-black text-xs uppercase tracking-wider transition-all active:scale-95 flex items-center justify-center gap-2 shadow-lg"
                    @click="goToMenu"
                >
                    <i class="fa-solid fa-book-open"></i> Cardápio
                </button>
            </div>

            <div class="flex justify-center gap-4 opacity-70">
                <a href="#" class="text-[9px] text-white font-bold uppercase tracking-wider hover:underline drop-shadow-md">Termos</a>
                <span class="text-[9px] text-white">•</span>
                <a href="#" class="text-[9px] text-white font-bold uppercase tracking-wider hover:underline drop-shadow-md">Privacidade</a>
            </div>

        </div>
    </div>

    <HoursSheet v-model:visible="showHours" />

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useSettingsStore } from '@/stores/settings-store';
import GoogleLoginBtn from '@/components/shared/GoogleLoginBtn.vue';
import HoursSheet from '@/components/shared/HoursSheet.vue';

const router = useRouter();
const settingsStore = useSettingsStore();

const openImages = import.meta.glob('/assets/src/images/home/open/*.webp', { eager: true, as: 'url' });
const closedImages = import.meta.glob('/assets/src/images/home/closed/*.webp', { eager: true, as: 'url' });

const bgImage = ref('');
const deferredPrompt = ref(null);
const showHours = ref(false);

const isOpen = computed(() => settingsStore.status.general);

const statusMessage = computed(() => {
    if (!settingsStore.hours?.general || Object.keys(settingsStore.hours.general).length === 0) {
        return '';
    }
    
    const days = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    const now = new Date();
    const currentDayKey = days[now.getDay()];
    const todaySchedule = settingsStore.hours.general[currentDayKey];

    if (isOpen.value) {
        return `Hoje até ${todaySchedule?.end || '...'}`;
    } else {
        const currentTime = now.getHours() + ':' + String(now.getMinutes()).padStart(2, '0');
        if (todaySchedule?.active && todaySchedule.start > currentTime) {
            return `Voltaremos hoje às ${todaySchedule.start}`;
        }
        
        const nextDayKey = days[(now.getDay() + 1) % 7];
        const nextSchedule = settingsStore.hours.general[nextDayKey];
        if (nextSchedule?.active) {
            return `Amanhã às ${nextSchedule.start}`;
        }

        return 'Confira nossos horários';
    }
});

const pickRandomImage = () => {
    const source = isOpen.value ? openImages : closedImages;
    const paths = Object.values(source);
    if (paths.length > 0) {
        const random = paths[Math.floor(Math.random() * paths.length)];
        bgImage.value = random;
    }
};

const goToMenu = () => { router.push('/home'); };

const installPwa = () => {
    if (deferredPrompt.value) {
        deferredPrompt.value.prompt();
        deferredPrompt.value.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                deferredPrompt.value = null;
            }
        });
    }
};

onMounted(async () => {
    await settingsStore.fetchPublicSettings(); 
    await settingsStore.checkRealtimeStatus();
    pickRandomImage();
    
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt.value = e;
    });
});
</script>

<style scoped>
/* Animações Suaves */
.animate-fade-in-up { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; transform: translateY(20px); }
.animate-fade-in-down { animation: fadeInDown 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; transform: translateY(-20px); }

@keyframes fadeInUp { to { opacity: 1; transform: translateY(0); } }
@keyframes fadeInDown { to { opacity: 1; transform: translateY(0); } }

.fade-slow-enter-active, .fade-slow-leave-active { transition: opacity 1.5s ease; }
.fade-slow-enter-from, .fade-slow-leave-to { opacity: 0; }
</style>