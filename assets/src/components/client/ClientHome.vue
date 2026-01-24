<template>
  <div class="flex flex-col h-full bg-surface-50 relative overflow-hidden">
    
    <div v-if="!sessionStore.hasActiveSession" class="bg-surface-900 pt-6 pb-6 px-4 rounded-b-[2.5rem] shadow-xl z-10 shrink-0">
        <div class="flex justify-between items-start mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-primary-500/20 flex items-center justify-center text-primary-500 border border-primary-500/30">
                    <i class="fa-solid fa-user text-sm"></i>
                </div>
                <div>
                    <h1 class="text-white font-bold text-lg leading-tight">Olá, {{ userFirstName }}</h1>
                    <p class="text-surface-400 text-xs">O que vamos pedir hoje?</p>
                </div>
            </div>
            <button @click="userStore.openProfile()" class="w-8 h-8 rounded-full bg-surface-800 text-surface-400 flex items-center justify-center hover:text-white hover:bg-surface-700 transition-colors">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>

        <div class="relative">
            <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-surface-500"></i>
            <input 
                v-model="searchQuery"
                type="text" 
                placeholder="Buscar pratos, ingredientes..." 
                class="w-full h-12 bg-surface-950 border border-surface-800 rounded-xl pl-10 pr-4 text-white placeholder-surface-500 focus:border-primary-500 focus:outline-none transition-colors"
            >
        </div>
    </div>

    <div class="flex-1 overflow-y-auto scrollbar-hide pb-24">
        
        <div v-if="sessionStore.hasActiveSession" class="p-4 pt-8 animate-fade-in">
            
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary-500/10 text-primary-500 mb-3 animate-pulse">
                    <i class="fa-solid fa-motorcycle text-2xl"></i>
                </div>
                <h2 class="text-xl font-bold text-surface-900 mb-1">Acompanhe seu Pedido</h2>
                <p class="text-sm text-surface-500">Mesa/Pedido #{{ sessionStore.identifier }}</p>
            </div>

            <div v-if="showPixPayment" class="mb-8 relative z-20">
                <PixPaymentBlock 
                    :sessionId="sessionStore.sessionId"
                    @payment-confirmed="handlePaymentConfirmed"
                />
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-surface-200 relative overflow-hidden">
                <div class="flex justify-between items-center mb-8 border-b border-surface-100 pb-4">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-surface-400 tracking-wider">Status Atual</span>
                        <div class="text-lg font-black text-primary-600 leading-tight mt-1">
                            {{ currentStatusLabel }}
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] uppercase font-bold text-surface-400 tracking-wider">Previsão</span>
                        <div class="text-lg font-bold text-surface-900 leading-tight mt-1">
                            {{ estimatedTime }}
                        </div>
                    </div>
                </div>

                <SessionTimeline :events="timelineEvents" />
                
                <div class="mt-8 grid grid-cols-2 gap-3">
                     <Button 
                        label="Ver Detalhes" 
                        icon="fa-solid fa-receipt" 
                        class="!bg-surface-100 !text-surface-600 !border-none hover:!bg-surface-200"
                        @click="openOrderDetails" 
                     />
                     <Button 
                        v-if="canCancel"
                        label="Cancelar" 
                        icon="fa-solid fa-xmark" 
                        class="!bg-red-50 !text-red-500 !border-none hover:!bg-red-100"
                        :loading="isCancelling"
                        @click="handleCancelOrder" 
                     />
                     <Button 
                        v-else
                        label="Ajuda" 
                        icon="fa-brands fa-whatsapp" 
                        class="!bg-green-50 !text-green-600 !border-none hover:!bg-green-100"
                        @click="openWhatsappSupport" 
                     />
                </div>
            </div>

        </div>

        <div v-else class="p-4 space-y-6">
            
            <div class="h-40 rounded-2xl bg-gradient-to-r from-primary-600 to-primary-500 relative overflow-hidden flex items-center px-6 shadow-lg shadow-primary-500/20">
                <div class="relative z-10 text-white w-2/3">
                    <span class="bg-white/20 backdrop-blur-sm text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wider mb-2 inline-block">Oferta do dia</span>
                    <h3 class="font-black text-xl leading-tight mb-1">Combo Família com 20% OFF</h3>
                    <button class="text-xs font-bold bg-white text-primary-600 px-4 py-2 rounded-lg mt-2 shadow-sm active:scale-95 transition-transform">Eu quero!</button>
                </div>
                <div class="absolute -right-6 -bottom-8 text-white/20 rotate-12">
                    <i class="fa-solid fa-burger text-[8rem]"></i>
                </div>
            </div>

            <div>
                <div class="flex justify-between items-end mb-3 px-1">
                    <h3 class="font-bold text-surface-900 text-lg">Categorias</h3>
                </div>
                <div class="flex gap-3 overflow-x-auto pb-4 scrollbar-hide -mx-4 px-4">
                    <button 
                        v-for="cat in categories" 
                        :key="cat.id"
                        class="flex flex-col items-center gap-2 min-w-[80px] group"
                        @click="scrollToCategory(cat.id)"
                    >
                        <div class="w-16 h-16 rounded-2xl bg-white shadow-sm border border-surface-200 flex items-center justify-center text-2xl group-active:scale-95 transition-all group-hover:border-primary-500/50">
                            {{ cat.icon || '🍔' }}
                        </div>
                        <span class="text-xs font-bold text-surface-600 group-hover:text-primary-600">{{ cat.name }}</span>
                    </button>
                </div>
            </div>

            <ProductList :searchQuery="searchQuery" />

        </div>
    </div>

    <CartWidget displayMode="bottom" /> <ProductDetailsModal ref="detailsModal" />
    <ComboWizardModal ref="comboWizard" />
    
    <SharedBottomNav v-if="!sessionStore.hasActiveSession" active="home" />

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useUserStore } from '@/stores/user-store';
import { useSessionStore } from '@/stores/session-store';
import { useMenuStore } from '@/stores/menu-store';
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
import Button from 'primevue/button';

const userStore = useUserStore();
const sessionStore = useSessionStore();
const menuStore = useMenuStore();

const searchQuery = ref('');
const isCancelling = ref(false);

const userFirstName = computed(() => userStore.user?.name?.split(' ')[0] || 'Visitante');
const categories = computed(() => menuStore.categories);

// --- LÓGICA DO TRACKING ---

// Define se mostra o Bloco Pix
const showPixPayment = computed(() => {
    if (!sessionStore.hasActiveSession) return false;
    
    // Status que exigem pagamento: 'new' (se configurado para pré-pagamento) ou 'pending_payment'
    const validStatus = ['new', 'pending_payment'].includes(sessionStore.sessionStatus);
    
    // Método deve ser Pix Sicredi
    const isPix = sessionStore.paymentMethod === 'pix_sicredi';
    
    return validStatus && isPix;
});

const currentStatusLabel = computed(() => {
    const map = {
        'new': 'Aguardando Confirmação',
        'pending_payment': 'Pagamento Pendente',
        'preparing': 'Em Preparo',
        'delivering': 'Saiu para Entrega',
        'finished': 'Entregue',
        'cancelled': 'Cancelado'
    };
    return map[sessionStore.sessionStatus] || sessionStore.sessionStatus;
});

const estimatedTime = computed(() => {
    // Pode vir do backend futuramente
    if (sessionStore.sessionStatus === 'new') return 'Calculando...';
    if (sessionStore.sessionStatus === 'preparing') return '30-40 min';
    if (sessionStore.sessionStatus === 'delivering') return '5-10 min';
    return '--';
});

const timelineEvents = computed(() => {
    // Converte o histórico da store para o formato do componente Timeline
    const status = sessionStore.sessionStatus;
    return [
        { label: 'Pedido Recebido', done: true, time: sessionStore.createdAt },
        { label: 'Pagamento', done: status !== 'pending_payment' && status !== 'new', active: status === 'pending_payment' },
        { label: 'Preparo', done: ['delivering', 'finished'].includes(status), active: status === 'preparing' },
        { label: 'Entrega', done: status === 'finished', active: status === 'delivering' }
    ];
});

const canCancel = computed(() => ['new', 'pending_payment'].includes(sessionStore.sessionStatus));

const handlePaymentConfirmed = () => {
    // Quando o componente PixPaymentBlock emite sucesso
    sessionStore.refreshSession(); // Atualiza status para 'new' ou 'preparing'
    window.scrollTo({ top: 0, behavior: 'smooth' }); // Rola para ver o novo status
};

const handleCancelOrder = async () => {
    if (!confirm('Tem certeza que deseja cancelar seu pedido?')) return;
    isCancelling.value = true;
    try {
        const { data } = await api.post('/client/cancel-order', { session_id: sessionStore.sessionId });
        if (data.success) {
            notify('info', 'Cancelado', 'Seu pedido foi cancelado.');
            sessionStore.leaveSession(); // Limpa a sessão local
        }
    } catch (e) {
        notify('error', 'Erro', e.response?.data?.message || 'Não foi possível cancelar.');
    } finally {
        isCancelling.value = false;
    }
};

const openWhatsappSupport = () => {
    const phone = '554799999999'; // Pegar das configs
    const msg = `Olá, preciso de ajuda com o pedido #${sessionStore.identifier}`;
    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(msg)}`, '_blank');
};

const scrollToCategory = (id) => {
    const el = document.getElementById(`cat-${id}`);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

onMounted(() => {
    menuStore.fetchMenu();
    if (sessionStore.sessionId) sessionStore.refreshSession();
});
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>