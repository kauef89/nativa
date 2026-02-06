<template>
  <div class="flex flex-col h-full bg-surface-base relative overflow-hidden">
    
    <GuestWelcome 
        v-if="!userStore.isLoggedIn && !guestMode" 
        @enter-menu="enterGuestMode"
    />

    <div v-else class="flex flex-col h-full">
        
        <div v-if="!sessionStore.hasActiveSession" class="bg-surface-1 pt-8 pb-6 px-5 rounded-b-[32px] shadow-2xl z-10 shrink-0 border-b border-surface-3/10">
            <div class="flex justify-between items-start mb-5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-surface-2 flex items-center justify-center text-primary-500 border border-primary-500/20 shadow-lg">
                        <i class="fa-solid fa-user text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-surface-0 font-black text-xl leading-tight">Olá, {{ userFirstName }}</h1>
                        <p class="text-surface-400 text-xs font-bold tracking-wide">O que vamos pedir hoje?</p>
                    </div>
                </div>
                <button @click="userStore.openProfile()" class="w-10 h-10 rounded-full bg-surface-2 text-surface-400 flex items-center justify-center hover:text-surface-0 hover:bg-surface-3 transition-colors">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
            </div>

            <div class="relative group">
                <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-surface-500 group-focus-within:text-primary-500 transition-colors"></i>
                <input 
                    v-model="searchQuery"
                    type="text" 
                    placeholder="Buscar pratos, ingredientes..." 
                    class="w-full h-14 bg-surface-base border border-surface-3/20 rounded-full pl-12 pr-6 text-surface-0 placeholder-surface-500 focus:border-primary-500 focus:outline-none transition-all font-bold shadow-inner"
                >
            </div>
        </div>

        <div class="flex-1 overflow-y-auto scrollbar-hide pb-32">
            
            <div v-if="sessionStore.hasActiveSession" class="p-5 pt-8 animate-fade-in">
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-primary-500/10 text-primary-500 mb-4 animate-pulse border border-primary-500/30">
                        <i class="fa-solid fa-motorcycle text-3xl"></i>
                    </div>
                    <h2 class="text-2xl font-black text-surface-0 mb-1">Acompanhe seu Pedido</h2>
                    <p class="text-sm text-surface-400 font-bold uppercase tracking-widest">Pedido #{{ sessionStore.identifier }}</p>
                </div>

                <div v-if="showPixPayment" class="mb-8 relative z-20">
                    <PixPaymentBlock 
                        :sessionId="sessionStore.sessionId"
                        @payment-confirmed="handlePaymentConfirmed"
                    />
                </div>

                <div class="bg-surface-1 rounded-[32px] p-6 shadow-lg border border-surface-3/10 relative overflow-hidden">
                    <div class="flex justify-between items-center mb-8 border-b border-surface-3/10 pb-4">
                        <div>
                            <span class="text-[10px] uppercase font-black text-surface-500 tracking-widest">Status Atual</span>
                            <div class="text-lg font-black text-primary-400 leading-tight mt-1">
                                {{ currentStatusLabel }}
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] uppercase font-black text-surface-500 tracking-widest">Previsão</span>
                            <div class="text-lg font-black text-surface-0 leading-tight mt-1">
                                {{ estimatedTime }}
                            </div>
                        </div>
                    </div>

                    <SessionTimeline :events="timelineEvents" />
                    
                    <div class="mt-8 grid grid-cols-2 gap-3">
                         <Button label="Ver Detalhes" icon="fa-solid fa-receipt" class="!bg-surface-2 !text-surface-300 !border-none hover:!bg-surface-3 hover:!text-surface-0 !font-black !rounded-full !h-12" @click="openOrderDetails" />
                         <Button v-if="canCancel" label="Cancelar" icon="fa-solid fa-xmark" class="!bg-red-500/10 !text-red-500 !border-none hover:!bg-red-500/20 !font-black !rounded-full !h-12" :loading="isCancelling" @click="handleCancelOrder" />
                         <Button v-else label="Ajuda" icon="fa-brands fa-whatsapp" class="!bg-green-500/10 !text-green-500 !border-none hover:!bg-green-500/20 !font-black !rounded-full !h-12" @click="openWhatsappSupport" />
                    </div>
                </div>
            </div>

            <div v-else class="p-5 space-y-8">
                <div class="h-44 rounded-[32px] bg-gradient-to-r from-primary-600 to-primary-400 relative overflow-hidden flex items-center px-8 shadow-xl shadow-primary-500/20 group cursor-pointer active:scale-95 transition-transform">
                    <div class="relative z-10 text-surface-950 w-2/3">
                        <span class="bg-surface-950/20 backdrop-blur-sm text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-wider mb-2 inline-block text-surface-950 border border-surface-950/10">Oferta do dia</span>
                        <h3 class="font-black text-2xl leading-tight mb-2">Combo Família com 20% OFF</h3>
                        <button class="text-xs font-black bg-surface-950 text-primary-400 px-5 py-2.5 rounded-full mt-1 shadow-lg">EU QUERO!</button>
                    </div>
                    <div class="absolute -right-8 -bottom-10 text-surface-950/10 rotate-12 transition-transform group-hover:scale-110 duration-500">
                        <i class="fa-solid fa-burger text-[9rem]"></i>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-end mb-4 px-1">
                        <h3 class="font-black text-surface-0 text-lg uppercase tracking-wide">Categorias</h3>
                    </div>
                    <div class="flex gap-3 overflow-x-auto pb-4 scrollbar-hide -mx-5 px-5">
                        <button v-for="cat in categories" :key="cat.id" class="flex flex-col items-center gap-2 min-w-[85px] group" @click="scrollToCategory(cat.id)">
                            <div class="w-18 h-18 aspect-square rounded-[24px] bg-surface-1 border border-surface-3/10 flex items-center justify-center text-3xl group-active:scale-90 transition-all group-hover:border-primary-500/50 group-hover:bg-surface-2 shadow-sm">
                                {{ cat.icon || '🍔' }}
                            </div>
                            <span class="text-xs font-bold text-surface-400 group-hover:text-surface-0 transition-colors uppercase tracking-tight">{{ cat.name }}</span>
                        </button>
                    </div>
                </div>

                <ProductList :searchQuery="searchQuery" />
            </div>
        </div>

    </div>

    <CartWidget displayMode="bottom" /> 
    <ProductDetailsModal ref="detailsModal" />
    <ComboWizardModal ref="comboWizard" />
    <SharedBottomNav v-if="guestMode || userStore.isLoggedIn" active="home" />

  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue';
import { useUserStore } from '@/stores/user-store';
import { useSessionStore } from '@/stores/session-store';
import { useMenuStore } from '@/stores/menu-store';
import { useSettingsStore } from '@/stores/settings-store';
import api from '@/services/api';
import { notify } from '@/services/notify';

// Componentes
import SharedBottomNav from '@/components/shared/SharedBottomNav.vue';
import ProductList from '@/components/shared/ProductList.vue';
import CartWidget from '@/components/shared/CartWidget.vue';
import ProductDetailsModal from '@/components/shared/ProductDetailsModal.vue';
import ComboWizardModal from '@/components/shared/ComboWizardModal.vue';
import SessionTimeline from '@/components/shared/SessionTimeline.vue';
import PixPaymentBlock from '@/components/shared/PixPaymentBlock.vue';
import GuestWelcome from '@/components/client/GuestWelcome.vue'; // <--- IMPORTADO
import Button from 'primevue/button';

const userStore = useUserStore();
const sessionStore = useSessionStore();
const menuStore = useMenuStore();
const settingsStore = useSettingsStore();

const searchQuery = ref('');
const isCancelling = ref(false);
const guestMode = ref(false); // <--- Controle de navegação do visitante

const userFirstName = computed(() => userStore.user?.name?.split(' ')[0] || 'Visitante');
const categories = computed(() => menuStore.categories);

// --- AÇÕES ---
const enterGuestMode = () => {
    guestMode.value = true;
};

// ... (MANTENHA TODAS AS OUTRAS FUNÇÕES: handlePaymentConfirmed, handleCancelOrder, etc.) ...
// Copiei apenas a estrutura para brevidade, mas você deve manter o conteúdo do script original
// relacionado a pagamentos, cancelamentos e detalhes de pedido.

// --- LÓGICA DO TRACKING (Mantida) ---
const showPixPayment = computed(() => {
    if (!sessionStore.hasActiveSession) return false;
    const validStatus = ['new', 'pending_payment'].includes(sessionStore.sessionStatus);
    const isPix = sessionStore.paymentMethod === 'pix_sicredi';
    return validStatus && isPix;
});

const currentStatusLabel = computed(() => {
    const map = { 'new': 'Aguardando Confirmação', 'pending_payment': 'Pagamento Pendente', 'preparing': 'Em Preparo', 'delivering': 'Saiu para Entrega', 'finished': 'Entregue', 'cancelled': 'Cancelado' };
    return map[sessionStore.sessionStatus] || sessionStore.sessionStatus;
});

const estimatedTime = computed(() => {
    if (sessionStore.sessionStatus === 'new') return 'Calculando...';
    if (sessionStore.sessionStatus === 'preparing') return '30-40 min';
    if (sessionStore.sessionStatus === 'delivering') return '5-10 min';
    return '--';
});

const timelineEvents = computed(() => {
    const status = sessionStore.sessionStatus;
    return [
        { label: 'Pedido Recebido', done: true, time: sessionStore.createdAt },
        { label: 'Pagamento', done: status !== 'pending_payment' && status !== 'new', active: status === 'pending_payment' },
        { label: 'Preparo', done: ['delivering', 'finished'].includes(status), active: status === 'preparing' },
        { label: 'Entrega', done: status === 'finished', active: status === 'delivering' }
    ];
});

const canCancel = computed(() => ['new', 'pending_payment'].includes(sessionStore.sessionStatus));

const handlePaymentConfirmed = () => { sessionStore.refreshSession(); window.scrollTo({ top: 0, behavior: 'smooth' }); };

const handleCancelOrder = async () => {
    if (!confirm('Tem certeza que deseja cancelar seu pedido?')) return;
    isCancelling.value = true;
    try {
        const { data } = await api.post('/client/cancel-order', { session_id: sessionStore.sessionId });
        if (data.success) { notify('info', 'Cancelado', 'Seu pedido foi cancelado.'); sessionStore.leaveSession(); }
    } catch (e) { notify('error', 'Erro', e.response?.data?.message || 'Não foi possível cancelar.'); } finally { isCancelling.value = false; }
};

const openWhatsappSupport = () => {
    const phone = settingsStore.contact?.whatsapp || '554799999999';
    const msg = `Olá, preciso de ajuda com o pedido #${sessionStore.identifier}`;
    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(msg)}`, '_blank');
};

const scrollToCategory = (id) => {
    const el = document.getElementById(`cat-${id}`);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const openOrderDetails = () => {
    // Implementar modal de detalhes ou router push
    notify('info', 'Detalhes', 'Abrindo detalhes...');
};

onMounted(async () => {
    await menuStore.fetchMenu();
    if (sessionStore.sessionId) sessionStore.refreshSession();
});
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.animate-fade-in { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>