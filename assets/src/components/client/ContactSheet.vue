<template>
  <Dialog 
    v-model:visible="isOpen" 
    modal 
    position="bottom" 
    :dismissableMask="true"
    :showHeader="false"
    class="!w-full !m-0 !max-w-md !mx-auto !bg-transparent !shadow-none"
    :contentStyle="{ padding: '0', background: 'transparent' }"
    :pt="{
        mask: { class: '!bg-surface-950/80 backdrop-blur-sm' },
        root: { class: '!border-0 !rounded-none' }
    }"
  >
    <div class="bg-surface-900 border-t border-surface-800 rounded-t-[2rem] p-6 pb-8 shadow-2xl relative overflow-hidden animate-slide-up">
        
        <div class="w-12 h-1.5 bg-surface-800 rounded-full mx-auto mb-6"></div>

        <div class="text-center mb-8">
            <h3 class="text-xl font-bold text-white">Fale Conosco</h3>
            <p class="text-surface-400 text-sm mt-1">Escolha como prefere ser atendido</p>
        </div>

        <div class="w-full space-y-3">
            <a :href="whatsappLink" target="_blank" 
               class="flex items-center gap-4 p-4 rounded-xl border border-surface-800 bg-surface-950 hover:bg-surface-800 transition-colors w-full cursor-pointer group"
            >
                <div class="w-10 h-10 rounded-full bg-green-500/10 flex items-center justify-center text-green-500 border border-green-500/20">
                    <i class="fa-brands fa-whatsapp text-xl"></i>
                </div>
                <div class="flex flex-col text-left">
                    <span class="font-bold text-base text-white">WhatsApp</span>
                    <span class="text-surface-500 text-xs">Iniciar conversa</span>
                </div>
                <i class="fa-solid fa-chevron-right ml-auto text-surface-600"></i>
            </a>

            <a :href="phoneLink" 
               class="flex items-center gap-4 p-4 rounded-xl border border-surface-800 bg-surface-950 hover:bg-surface-800 transition-colors w-full cursor-pointer group"
            >
                <div class="w-10 h-10 rounded-full bg-surface-800 flex items-center justify-center text-white border border-surface-700">
                    <i class="fa-solid fa-phone text-sm"></i>
                </div>
                <div class="flex flex-col text-left">
                    <span class="font-bold text-base text-white">Ligar Agora</span>
                    <span class="text-surface-500 text-xs font-mono">{{ formattedPhone }}</span>
                </div>
                <i class="fa-solid fa-chevron-right ml-auto text-surface-600"></i>
            </a>
        </div>

        <div class="grid grid-cols-2 gap-3 w-full mt-3" v-if="hasSecondaryLinks">
            <a v-if="settingsStore.contact.instagram" :href="instagramLink" target="_blank" class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl border border-surface-800 bg-surface-950 hover:bg-surface-800 transition-colors">
                <i class="fa-brands fa-instagram text-xl text-pink-400"></i>
                <span class="text-xs font-bold text-surface-300">Instagram</span>
            </a>
            </div>

        <button class="text-surface-500 font-bold text-xs uppercase tracking-widest mt-6 w-full py-3 hover:text-white transition-colors" @click="isOpen = false">
            Fechar
        </button>
    </div>
  </Dialog>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useSettingsStore } from '@/stores/settings-store';
import Dialog from 'primevue/dialog';

const isOpen = ref(false);
const settingsStore = useSettingsStore();

const open = () => isOpen.value = true;

// Lógica de Links Dinâmicos
const whatsappLink = computed(() => {
    const num = settingsStore.contact.whatsapp || '';
    // Garante que tenha apenas números e adiciona o link universal
    return `https://wa.me/${num.replace(/[^0-9]/g, '')}`;
});

const phoneLink = computed(() => {
    const num = settingsStore.contact.whatsapp || ''; // Usa o Whats como telefone principal se não houver outro
    return `tel:+${num.replace(/[^0-9]/g, '')}`;
});

const instagramLink = computed(() => {
    const ig = settingsStore.contact.instagram || '';
    if (ig.startsWith('http')) return ig;
    return `https://instagram.com/${ig.replace('@', '').replace('/', '')}`;
});

// Formata o telefone para exibição (XX) XXXXX-XXXX
const formattedPhone = computed(() => {
    let num = settingsStore.contact.whatsapp || '';
    // Remove 55 do início se houver (para exibição mais limpa) e formata
    num = num.replace(/^55/, '');
    
    if (num.length > 10) {
        return num.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
    } else if (num.length > 9) {
        return num.replace(/^(\d{2})(\d{4})(\d{4}).*/, '($1) $2-$3');
    }
    return num;
});

const hasSecondaryLinks = computed(() => {
    return !!settingsStore.contact.instagram || !!settingsStore.contact.google_reviews;
});

defineExpose({ open });
</script>

<style scoped>
.animate-slide-up { animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes slideUp { 
    from { transform: translateY(100%); opacity: 0; } 
    to { transform: translateY(0); opacity: 1; } 
}
</style>