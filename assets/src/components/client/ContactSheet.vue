<template>
  <BottomSheet 
    :visible="visible" 
    @update:visible="$emit('update:visible', $event)"
    :no-header="true" 
  >
    <div class="flex flex-col h-full bg-surface-1 relative overflow-hidden rounded-t-[32px]">

        <div class="shrink-0 z-20 relative bg-surface-1 pb-2 pt-5 px-6 flex items-center justify-between">
            <div class="flex flex-col">
                <h2 class="text-xl font-black text-surface-0 leading-none flex items-center gap-2">
                    Fale Conosco <i class="fa-solid fa-comment-dots text-primary-500 text-lg animate-pulse"></i>
                </h2>
                <p class="text-xs font-bold text-surface-400 mt-1">Canais de atendimento e localização</p>
            </div>
            
            <button 
                class="w-10 h-10 rounded-full flex items-center justify-center transition-all bg-surface-2 text-surface-400 hover:text-surface-0 hover:bg-surface-3 active:scale-95"
                @click="$emit('update:visible', false)"
            >
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <div v-if="loading" class="flex-1 flex items-center justify-center min-h-[300px]">
            <div class="flex flex-col items-center gap-3 text-primary-500">
                <i class="fa-solid fa-circle-notch fa-spin text-3xl"></i>
                <span class="text-xs font-bold uppercase tracking-widest">Carregando contato...</span>
            </div>
        </div>

        <div v-else class="flex-1 overflow-y-auto scrollbar-hidden pb-8 px-6">
            
            <div class="relative w-full h-56 rounded-[28px] overflow-hidden mb-6 shadow-lg shrink-0 bg-surface-2 border border-surface-3/10 group">
                <iframe
                    v-if="mapUrl"
                    :src="mapUrl"
                    width="100%"
                    height="100%"
                    style="border:0;"
                    allowfullscreen=""
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    class="contrast-[0.9] grayscale-[0.2] group-hover:grayscale-0 group-hover:contrast-100 transition-all duration-500 scale-105 group-hover:scale-100"
                ></iframe>
                
                <div v-else class="absolute inset-0 flex flex-col items-center justify-center text-surface-400 bg-surface-2/50 backdrop-blur-sm">
                    <i class="fa-solid fa-map-location-dot text-4xl mb-3 opacity-50"></i>
                    <span class="text-xs font-bold uppercase tracking-widest">Localização indisponível</span>
                </div>

                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-surface-1 via-surface-1/80 to-transparent pt-16 pb-4 px-4 flex items-end z-10 pointer-events-none">
                    <div class="flex items-center gap-3 pointer-events-auto">
                        <div class="w-10 h-10 rounded-full bg-primary-500 text-surface-950 flex items-center justify-center shrink-0 shadow-sm">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <div class="flex flex-col drop-shadow-sm">
                            <span class="text-[10px] font-black text-primary-400 uppercase tracking-widest mb-0.5">Nossa Loja</span>
                            <p class="text-sm font-bold text-surface-0 leading-tight line-clamp-2">
                                {{ contact.address || 'Endereço não configurado' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-6 animate-fade-in-up">
                
                <a v-if="contact.whatsapp" 
                   :href="whatsappLink" 
                   target="_blank"
                   class="group w-full h-16 flex items-center justify-between px-6 rounded-full bg-[#25D366] hover:bg-[#20bd5a] text-white shadow-xl shadow-green-900/30 active:scale-[0.98] transition-all relative overflow-hidden"
                >
                    <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/diagmonds-light.png')] opacity-10 mix-blend-overlay group-hover:opacity-20 transition-opacity"></div>

                    <div class="flex items-center gap-4 relative z-10">
                        <i class="fa-brands fa-whatsapp text-3xl"></i>
                        <div class="flex flex-col text-left">
                            <span class="text-[10px] font-black uppercase opacity-90 tracking-wider">Canal Principal</span>
                            <span class="text-lg font-black tracking-tight">Chamar no WhatsApp</span>
                        </div>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square text-lg opacity-60 group-hover:opacity-100 group-hover:translate-x-1 transition-all relative z-10"></i>
                </a>

                <div class="flex justify-center gap-4">
                    
                    <a v-if="contact.whatsapp" 
                       :href="`tel:${contact.whatsapp}`"
                       class="action-circle-btn group bg-surface-2 text-surface-0 hover:bg-surface-3"
                       v-tooltip.bottom="'Ligar'"
                    >
                        <i class="fa-solid fa-phone text-xl group-hover:scale-110 transition-transform"></i>
                    </a>

                    <a v-if="contact.instagram" 
                       :href="`https://instagram.com/${cleanInstagram(contact.instagram)}`" 
                       target="_blank"
                       class="action-circle-btn group bg-gradient-to-tr from-yellow-400 via-red-500 to-purple-500 text-white shadow-lg shadow-purple-500/20 hover:shadow-purple-500/40"
                       v-tooltip.bottom="'Instagram'"
                    >
                        <i class="fa-brands fa-instagram text-2xl group-hover:scale-110 transition-transform"></i>
                    </a>

                    <a v-if="contact.google_reviews" 
                       :href="contact.google_reviews" 
                       target="_blank"
                       class="action-circle-btn group bg-white text-surface-900 shadow-lg hover:bg-gray-50"
                       v-tooltip.bottom="'Avaliar'"
                    >
                        <img src="https://upload.wikimedia.org/wikipedia/commons/5/53/Google_%22G%22_Logo.svg" alt="Google" class="w-6 h-6 group-hover:scale-110 transition-transform">
                    </a>
                </div>

                <div class="text-center mt-2">
                     <span class="text-xs font-bold text-surface-400 flex items-center justify-center gap-2">
                        <i class="fa-regular fa-clock"></i> Consulte nossos horários no WhatsApp
                     </span>
                </div>

            </div>
        </div>
    </div>
  </BottomSheet>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import api from '@/services/api';
import { useFormat } from '@/composables/useFormat';
import BottomSheet from '@/components/shared/BottomSheet.vue';

const props = defineProps(['visible']);
const emit = defineEmits(['update:visible']);

const { formatPhone } = useFormat();
const contact = ref({});
const loading = ref(true);

// API Key injetada globalmente pelo WordPress
const mapsKey = window.nativaData?.mapsApiKey || '';

// Busca configurações ao abrir
watch(() => props.visible, async (isOpen) => {
    if (isOpen && !contact.value.address) {
        loading.value = true;
        try {
            // Simulação de delay para ver o loading state
            // await new Promise(r => setTimeout(r, 800)); 
            const { data } = await api.get('/settings/public');
            if (data.success) {
                contact.value = data.contact;
            }
        } catch (e) {
            console.error('Erro ao carregar contato:', e);
        } finally {
            loading.value = false;
        }
    }
});

const mapUrl = computed(() => {
    if (!contact.value.address || !mapsKey) return null;
    const query = encodeURIComponent(contact.value.address);
    // URL correta para a Google Maps Embed API
    return `https://www.google.com/maps/embed/v1/place?key=${mapsKey}&q=${query}&language=pt-BR`;
});

const whatsappLink = computed(() => {
    if (!contact.value.whatsapp) return '#';
    const num = contact.value.whatsapp.replace(/\D/g, '');
    // Mensagem padrão opcional
    const text = encodeURIComponent("Olá! Gostaria de mais informações.");
    return `https://wa.me/55${num}?text=${text}`;
});

const cleanInstagram = (handle) => handle.replace('@', '').replace('https://instagram.com/', '').replace('/', '');
</script>

<style scoped>
.scrollbar-hidden::-webkit-scrollbar { display: none; }
.scrollbar-hidden { -ms-overflow-style: none; scrollbar-width: none; }

.action-circle-btn {
    @apply w-16 h-16 rounded-full flex items-center justify-center border border-surface-3/10 transition-all active:scale-95 shadow-sm;
}

.animate-fade-in-up { animation: fadeInUp 0.4s ease-out cubic-bezier(0.165, 0.84, 0.44, 1); }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>