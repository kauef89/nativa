<template>
  <div class="flex flex-col h-full relative overflow-hidden">
    
    <div class="px-6 pt-6 pb-2 shrink-0 z-10 flex justify-between items-start animate-fade-in">
        <img src="@/svg/logo-branca.svg" class="h-8 w-auto opacity-90" alt="Nativa" />
        
        <div class="flex flex-col items-end">
            <div class="flex items-center gap-2 px-3 py-1.5 rounded-full backdrop-blur-md border shadow-lg" 
                 :class="storeStatus.class">
                <div class="w-2 h-2 rounded-full animate-pulse" :class="storeStatus.dot"></div>
                <span class="text-xs font-bold uppercase tracking-wider text-white">{{ storeStatus.label }}</span>
            </div>
            <span class="text-[10px] text-surface-400 font-medium mt-1 mr-1 text-right">{{ storeStatus.subtext }}</span>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto scrollbar-thin px-4 pb-24 pt-4 flex flex-col">
        
        <div v-if="userStore.isLoadingOrder" class="animate-pulse space-y-4 m-auto w-full max-w-md">
            <div class="h-64 bg-surface-900/50 rounded-3xl"></div>
        </div>

        <div v-else-if="!userStore.activeOrder" class="flex-1 flex flex-col items-center justify-center animate-fade-in min-h-[300px]">
            <div class="relative mb-8 group">
                <div class="absolute inset-0 bg-primary-500/20 blur-[50px] rounded-full group-hover:bg-primary-500/30 transition-colors duration-700"></div>
                <img src="@/images/home/open/open_05.webp" class="w-56 h-56 object-cover rounded-full relative z-10 mask-squircle shadow-2xl rotate-12 group-hover:rotate-0 transition-all duration-700 scale-100 group-hover:scale-105" />
            </div>
            
            <h2 class="text-2xl font-black text-white mb-2 tracking-tight">Bateu a fome, {{ firstName }}?</h2>
            <p class="text-surface-400 text-center text-sm max-w-xs mb-8 leading-relaxed">
                Nossa cozinha está a todo vapor! Escolha seu combo favorito e receba rapidinho.
            </p>

            <Button 
                label="Ver Cardápio" 
                icon="fa-solid fa-utensils" 
                class="glass-btn-primary"
                @click="$emit('go-to-menu')"
            />
        </div>

        <div v-else-if="isCancelled" class="animate-fade-in pb-4 m-auto w-full max-w-md">
            <div class="bg-surface-900/60 backdrop-blur-xl border border-red-500/20 rounded-[2rem] p-8 text-center relative overflow-hidden shadow-[0_20px_50px_rgba(239,68,68,0.1)]">
                <div class="absolute inset-0 bg-red-500/5"></div>
                
                <div class="w-20 h-20 bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-6 relative z-10 border border-red-500/20">
                    <i class="fa-solid fa-heart-crack text-4xl text-red-400"></i>
                </div>

                <h2 class="text-2xl font-black text-white mb-2 relative z-10">Pedido Cancelado</h2>
                <p class="text-surface-400 text-sm mb-8 leading-relaxed relative z-10">
                    Poxa, infelizmente seu pedido #{{ userStore.activeOrder.id }} não pode ser processado. Verifique os detalhes ou tente novamente.
                </p>

                <div class="flex flex-col gap-3 relative z-10">
                    <Button label="Pedir Novamente" icon="fa-solid fa-rotate-right" class="!bg-white !text-surface-900 !font-bold !border-none !rounded-xl !h-12" @click="$emit('go-to-menu')" />
                    <button class="text-surface-500 text-xs hover:text-white mt-2" @click="openWhatsAppHelp">Falar com Suporte</button>
                </div>
            </div>
        </div>

        <div v-else-if="isCompleted" class="animate-fade-in pb-4 m-auto w-full max-w-md">
            <div class="bg-surface-900/60 backdrop-blur-xl border border-green-500/20 rounded-[2rem] p-8 text-center relative overflow-hidden shadow-[0_20px_50px_rgba(16,185,129,0.1)]">
                
                <div class="absolute top-0 left-0 w-full h-full opacity-30 pointer-events-none bg-[url('https://www.transparenttextures.com/patterns/confetti.png')]"></div>

                <div class="w-20 h-20 bg-green-500/10 rounded-full flex items-center justify-center mx-auto mb-6 relative z-10 border border-green-500/20">
                    <i class="fa-solid fa-star text-4xl text-green-400 animate-pulse"></i>
                </div>

                <h2 class="text-2xl font-black text-white mb-2 relative z-10">Pedido Entregue!</h2>
                <p class="text-surface-400 text-sm mb-8 leading-relaxed relative z-10">
                    Esperamos que sua experiência com a Nativa tenha sido incrível! Que tal nos avaliar?
                </p>

                <div class="flex flex-col gap-3 relative z-10">
                    <Button 
                        v-if="settingsStore.contact.google_reviews"
                        label="Avaliar no Google" 
                        icon="fa-brands fa-google" 
                        class="!bg-blue-600 hover:!bg-blue-500 !text-white !font-bold !border-none !rounded-xl !h-12 shadow-lg" 
                        @click="openGoogleReviews" 
                    />
                    
                    <Button label="Pedir Novamente" icon="fa-solid fa-rotate-right" severity="secondary" class="!bg-surface-800 !text-white !font-bold !border-surface-700 !rounded-xl !h-12" @click="$emit('go-to-menu')" />
                </div>
            </div>
        </div>

        <div v-else class="animate-fade-in pb-4 w-full">
            
            <div class="bg-surface-900/60 backdrop-blur-xl border border-white/10 rounded-[2rem] p-6 relative overflow-hidden shadow-[0_20px_50px_rgba(0,0,0,0.5)]">
                <div class="absolute top-0 right-0 w-64 h-64 bg-primary-500/10 rounded-full blur-[80px] -mr-20 -mt-20 pointer-events-none"></div>

                <div class="flex justify-between items-start mb-8 relative z-10">
                    <div>
                        <div class="text-[10px] font-bold text-surface-400 uppercase tracking-widest mb-1">Pedido #{{ userStore.activeOrder.id }}</div>
                        <div class="text-3xl font-black text-white tracking-tight leading-none">{{ statusDetails.label }}</div>
                        <div class="text-xs text-surface-400 mt-1" v-if="userStore.activeOrder.address">
                            Entregar em: <span class="text-surface-200 font-bold">{{ userStore.activeOrder.address.street }}</span>
                        </div>
                    </div>
                    <div class="w-14 h-14 rounded-2xl bg-surface-800/50 border border-white/5 flex items-center justify-center text-2xl shadow-inner backdrop-blur-md" :class="statusDetails.color">
                        <i :class="statusDetails.icon"></i>
                    </div>
                </div>

                <div class="relative pl-2 space-y-0 mb-8">
                    <div class="absolute left-[15px] top-3 bottom-6 w-0.5 bg-surface-800/50 rounded-full"></div>
                    <div class="absolute left-[15px] top-3 w-0.5 bg-primary-500/50 rounded-full transition-all duration-1000" :style="{ height: progressHeight + '%' }"></div>

                    <div v-for="(step, idx) in steps" :key="idx" class="relative flex items-start group min-h-[60px] last:min-h-0">
                        <div class="relative z-10 w-8 h-8 rounded-full border-4 flex items-center justify-center transition-all duration-500 shrink-0 bg-surface-900"
                            :class="getCurrentStepIndex() >= idx ? 'border-primary-500 shadow-[0_0_15px_rgba(16,185,129,0.4)] scale-110' : 'border-surface-800 scale-90 opacity-60'">
                            <div class="w-2.5 h-2.5 rounded-full transition-colors duration-500" :class="getCurrentStepIndex() >= idx ? 'bg-primary-500' : 'bg-surface-700'"></div>
                        </div>
                        <div class="flex flex-col ml-4 mt-1 transition-all duration-500" :class="getCurrentStepIndex() >= idx ? 'opacity-100 translate-x-0' : 'opacity-40 -translate-x-2'">
                            <span class="font-bold text-sm leading-none mb-1" :class="getCurrentStepIndex() >= idx ? 'text-white' : 'text-surface-400'">{{ step.label }}</span>
                            <span class="text-[10px] font-mono font-medium" :class="getCurrentStepIndex() >= idx ? 'text-primary-400' : 'text-surface-600'">
                                <span v-if="idx === 0">{{ extractTime(userStore.activeOrder.date) }}</span>
                                <span v-else-if="getCurrentStepIndex() === idx">Agora</span>
                                <span v-else-if="getCurrentStepIndex() > idx">Concluído</span>
                                <span v-else>--:--</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-6 border-t border-white/5 relative z-10">
                    <button class="col-span-1 bg-surface-800/40 hover:bg-surface-800 border border-white/5 hover:border-surface-600 rounded-xl p-3 flex flex-col items-center justify-center gap-2 transition-all active:scale-[0.98]" @click="openOrderDetails">
                        <i class="fa-solid fa-receipt text-surface-400 text-xl"></i>
                        <span class="text-xs font-bold text-surface-300">Detalhes</span>
                    </button>
                    <button class="col-span-1 bg-surface-800/40 hover:bg-surface-800 border border-white/5 hover:border-green-500/30 rounded-xl p-3 flex flex-col items-center justify-center gap-2 transition-all active:scale-[0.98] group" @click="openWhatsAppHelp">
                        <i class="fa-brands fa-whatsapp text-green-500 text-xl group-hover:scale-110 transition-transform"></i>
                        <span class="text-xs font-bold text-surface-300 group-hover:text-green-400">Ajuda</span>
                    </button>
                    <button v-if="canAddItems" class="col-span-2 bg-surface-800/40 hover:bg-surface-800 border border-white/5 hover:border-primary-500/30 rounded-xl p-4 flex items-center justify-between transition-all active:scale-[0.98] group" @click="$emit('go-to-menu')">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-primary-500/10 flex items-center justify-center text-primary-500 group-hover:scale-110 transition-transform"><i class="fa-solid fa-plus"></i></div>
                            <div class="text-left"><div class="font-bold text-white text-sm">Adicionar mais itens</div><div class="text-[10px] text-surface-400">Esqueceu algo?</div></div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-surface-600 group-hover:text-white transition-colors"></i>
                    </button>
                    <button v-if="canEdit" class="col-span-2 bg-surface-800/40 hover:bg-surface-800 border border-white/5 hover:border-blue-500/30 rounded-xl p-4 flex items-center justify-between transition-all active:scale-[0.98] group" @click="openPaymentModal">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-blue-500/10 flex items-center justify-center text-blue-400 group-hover:scale-110 transition-transform"><i class="fa-regular fa-credit-card"></i></div>
                            <div class="text-left"><div class="font-bold text-white text-sm">Forma de Pagamento</div><div class="text-[10px] text-surface-400 capitalize">Atual: {{ userStore.activeOrder.payment_method }}</div></div>
                        </div>
                        <span class="text-xs font-bold text-blue-400">Trocar</span>
                    </button>
                    <button v-if="canCancel" class="col-span-2 bg-red-500/5 hover:bg-red-500/10 border border-red-500/10 hover:border-red-500/30 rounded-xl p-3 flex items-center justify-center gap-2 transition-all active:scale-[0.98] group mt-2" @click="confirmCancel">
                        <i class="fa-solid fa-ban text-red-400 group-hover:scale-110 transition-transform"></i>
                        <span class="text-xs font-bold text-red-300">Cancelar Pedido</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <Dialog v-model:visible="detailsDialog.isOpen" modal header="Detalhes do Pedido" :style="{ width: '90%', maxWidth: '450px' }" :pt="{ root: { class: '!bg-surface-900 !border !border-surface-700 !rounded-2xl' }, header: { class: '!bg-surface-900 !text-white !border-b !border-surface-800' }, content: { class: '!bg-surface-900 !text-surface-200 !p-0' }, closeButton: { class: '!text-surface-400 hover:!text-white' } }">
        <div v-if="detailsDialog.isLoading" class="flex justify-center p-8"><i class="fa-solid fa-spinner fa-spin text-2xl text-primary-500"></i></div>
        <div v-else class="flex flex-col">
            <div class="p-4 space-y-4 max-h-[60vh] overflow-y-auto scrollbar-thin">
                <ul class="space-y-3">
                    <li v-for="(item, idx) in detailsDialog.items" :key="idx" class="flex flex-col border-b border-surface-800 pb-3 last:border-0 last:pb-0">
                        <div class="flex justify-between items-start text-sm">
                            <div class="flex items-start"><span class="font-bold text-white mr-3">{{ item.qty }}x</span><div><span class="text-surface-200 block font-medium">{{ item.name }}</span></div></div>
                            <span class="font-mono text-white">R$ {{ formatMoney(item.line_total) }}</span>
                        </div>
                        <div v-if="item.modifiers && item.modifiers.length > 0" class="pl-8 mt-1 space-y-1">
                            <div v-for="mod in item.modifiers" :key="mod.name" class="flex justify-between text-[10px] text-surface-400"><span>+ {{ mod.name }}</span><span v-if="mod.price > 0">R$ {{ formatMoney(mod.price) }}</span></div>
                        </div>
                    </li>
                </ul>
            </div>
            <div class="bg-surface-950 p-4 border-t border-surface-800 text-sm">
                <div class="flex justify-between mb-1"><span class="text-surface-400">Subtotal</span><span class="text-surface-200">R$ {{ formatMoney(detailsDialog.totals.subtotal) }}</span></div>
                <div class="flex justify-between mb-1" v-if="detailsDialog.totals.fee > 0"><span class="text-surface-400">Taxa de Entrega</span><span class="text-surface-200">R$ {{ formatMoney(detailsDialog.totals.fee) }}</span></div>
                <div class="flex justify-between mb-1" v-if="detailsDialog.totals.discount > 0"><span class="text-surface-400">Desconto</span><span class="text-green-400">- R$ {{ formatMoney(detailsDialog.totals.discount) }}</span></div>
                <div class="flex justify-between pt-3 mt-2 border-t border-surface-800"><span class="text-white font-bold text-base">TOTAL</span><span class="text-primary-400 font-black text-xl">R$ {{ formatMoney(detailsDialog.totals.total) }}</span></div>
            </div>
        </div>
    </Dialog>

    <Dialog v-model:visible="showCancelDialog" modal :showHeader="false" :contentStyle="{background: 'transparent', padding: 0}" :pt="{ mask: { class: '!bg-black/80 backdrop-blur-sm' } }">
        <div class="bg-surface-900 border border-surface-700 rounded-2xl p-6 max-w-sm w-full mx-4 shadow-2xl">
            <h3 class="text-xl font-bold text-white mb-2">Cancelar Pedido?</h3>
            <p class="text-surface-400 text-sm mb-6">Essa ação não pode ser desfeita. Se tiver dúvidas, chame no WhatsApp.</p>
            <div class="flex gap-3">
                <Button label="Voltar" text class="flex-1 !text-surface-400 hover:!text-white" @click="showCancelDialog = false" />
                <Button label="Sim, Cancelar" severity="danger" class="flex-1" @click="doCancel" :loading="loadingAction" />
            </div>
        </div>
    </Dialog>

    <Dialog v-model:visible="showPaymentDialog" modal header="Trocar Pagamento" :style="{ width: '90%', maxWidth: '400px' }" :pt="{ root: { class: '!bg-surface-900 !border !border-surface-700' }, header: { class: '!bg-surface-900 !text-white' }, content: { class: '!bg-surface-900' } }">
        <div class="flex flex-col gap-2 pt-2">
            <button v-for="m in ['pix', 'card', 'dinheiro']" :key="m" class="p-4 rounded-xl border border-surface-700 hover:border-primary-500/50 hover:bg-surface-800 text-left capitalize font-bold text-white flex justify-between items-center transition-all group" @click="doUpdatePayment(m)">
                <span class="flex items-center gap-3"><i class="text-lg w-6 text-center text-surface-400 group-hover:text-white" :class="{'fa-brands fa-pix': m==='pix', 'fa-regular fa-credit-card': m==='card', 'fa-solid fa-money-bill-wave': m==='dinheiro'}"></i>{{ m }}</span><i class="fa-solid fa-chevron-right text-xs text-surface-600"></i>
            </button>
        </div>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue';
import { useUserStore } from '@/stores/user-store';
import { useSettingsStore } from '@/stores/settings-store';
import api from '@/services/api';
import { notify } from '@/services/notify';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';

const emit = defineEmits(['go-to-menu', 'contact']);
const userStore = useUserStore();
const settingsStore = useSettingsStore();

const showCancelDialog = ref(false);
const showPaymentDialog = ref(false);
const loadingAction = ref(false);

const detailsDialog = reactive({ isOpen: false, isLoading: false, items: [], totals: { subtotal: 0, fee: 0, discount: 0, total: 0 } });
const firstName = computed(() => userStore.user?.name?.split(' ')[0] || 'Visitante');

const formatMoney = (val) => { if (val === undefined || val === null || val === '') return '0,00'; const num = parseFloat(val); if (isNaN(num)) return '0,00'; return num.toFixed(2).replace('.', ','); };
const extractTime = (dateStr) => dateStr ? (dateStr.split(' ')[1] || dateStr) : '';

const storeStatus = computed(() => {
    const isOpen = settingsStore.status.general;
    const now = new Date();
    if (isOpen) {
        if (now.getHours() >= 22) return { label: 'Quase Fechando', subtext: 'Peça até 23:00', class: 'bg-orange-500/20 border-orange-500/30', dot: 'bg-orange-500' };
        return { label: 'Aberto Agora', subtext: 'Hoje até 23:00', class: 'bg-green-500/20 border-green-500/30', dot: 'bg-green-500' };
    }
    return { label: 'Fechado', subtext: 'Abriremos às 18:00', class: 'bg-red-500/20 border-red-500/30', dot: 'bg-red-500' };
});

const steps = [
    { slug: ['novo', 'pendente', 'aguardando-pagamento'], label: 'Recebido' },
    { slug: ['preparando', 'em-preparo'], label: 'Preparando' },
    { slug: ['em-rota', 'saiu-para-entrega', 'entrega'], label: 'Saiu para Entrega' },
    { slug: ['entregue', 'concluido'], label: 'Entregue' }
];

const isCancelled = computed(() => userStore.activeOrder && ['cancelado', 'wc-cancelled'].includes(userStore.activeOrder.status));
const isCompleted = computed(() => userStore.activeOrder && ['concluido', 'entregue', 'wc-completed'].includes(userStore.activeOrder.status));

const getCurrentStepIndex = () => {
    if (!userStore.activeOrder) return -1;
    return steps.findIndex(s => s.slug.includes(userStore.activeOrder.status));
};

const progressHeight = computed(() => {
    const idx = getCurrentStepIndex();
    return idx === -1 ? 0 : Math.min((idx * 30), 100); 
});

const statusDetails = computed(() => {
    const idx = getCurrentStepIndex();
    if (idx === 0) return { label: 'Pedido Recebido', icon: 'fa-solid fa-clipboard-check', color: 'text-yellow-400 bg-yellow-500/10' };
    if (idx === 1) return { label: 'No Fogo! 🔥', icon: 'fa-solid fa-fire-burner', color: 'text-orange-400 bg-orange-500/10' };
    if (idx === 2) return { label: 'A caminho 🛵', icon: 'fa-solid fa-motorcycle', color: 'text-blue-400 bg-blue-500/10' };
    return { label: 'Entregue', icon: 'fa-solid fa-check', color: 'text-green-400 bg-green-500/10' };
});

const canCancel = computed(() => userStore.activeOrder && ['novo', 'pendente', 'aguardando-pagamento'].includes(userStore.activeOrder.status));
const canEdit = computed(() => userStore.activeOrder && !isCancelled.value && !isCompleted.value && !['em-rota', 'entregue', 'concluido', 'cancelado'].includes(userStore.activeOrder.status));
const canAddItems = computed(() => canEdit.value);

const confirmCancel = () => showCancelDialog.value = true;
const doCancel = async () => {
    loadingAction.value = true;
    await userStore.cancelActiveOrder();
    loadingAction.value = false;
    showCancelDialog.value = false;
};

const openPaymentModal = () => showPaymentDialog.value = true;
const doUpdatePayment = async (method) => {
    let change = 0;
    if(method === 'dinheiro') {
        const val = prompt("Troco para quanto? (Deixe vazio se não precisar)");
        if(val) change = parseFloat(val.replace(',', '.'));
    }
    await userStore.updatePayment(method, change);
    showPaymentDialog.value = false;
};

const openWhatsAppHelp = () => {
    const phone = settingsStore.contact?.whatsapp || '5547999999999'; 
    const orderId = userStore.activeOrder?.id || '';
    const text = encodeURIComponent(`Olá, preciso de ajuda com o meu pedido #${orderId}.`);
    window.open(`https://wa.me/${phone}?text=${text}`, '_blank');
};

const openGoogleReviews = () => {
    if (settingsStore.contact?.google_reviews) {
        window.open(settingsStore.contact.google_reviews, '_blank');
    }
};

const openOrderDetails = async () => {
    if (!userStore.activeOrder) return;
    detailsDialog.isOpen = true;
    detailsDialog.isLoading = true;
    try {
        const { data } = await api.get(`/order-details/${userStore.activeOrder.id}`);
        if (data.success) {
            detailsDialog.items = data.items;
            detailsDialog.totals = data.totals;
        }
    } catch (e) {
        notify('error', 'Erro', 'Não foi possível carregar os detalhes.');
        detailsDialog.isOpen = false;
    } finally {
        detailsDialog.isLoading = false;
    }
};

onMounted(() => {
    userStore.fetchActiveOrder();
    settingsStore.fetchPublicSettings();
    if (window.OneSignal) {
        window.OneSignal.push(() => {
            window.OneSignal.on('notificationDisplay', () => {
                userStore.fetchActiveOrder();
            });
        });
    }
});
</script>

<style scoped>
.glass-btn-primary { @apply !w-auto !px-8 !py-4 !rounded-xl !text-lg !font-black !uppercase !tracking-wide shadow-lg shadow-primary-500/20 !border-none text-surface-900 bg-primary-500 hover:bg-primary-400 transition-transform hover:scale-105 active:scale-95; }
.mask-squircle { mask-image: url("data:image/svg+xml;utf8,<svg width='200' height='200' xmlns='http://www.w3.org/2000/svg'><path d='M100,0 C20,0 0,20 0,100 C0,180 20,200 100,200 C180,200 200,180 200,100 C200,20 180,0 100,0 Z' /></svg>"); -webkit-mask-image: url("data:image/svg+xml;utf8,<svg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'><path d='M 0,100 C 0,20 20,0 100,0 S 200,20 200,100 180,200 100,200 0,180 0,100' /></svg>"); mask-size: contain; -webkit-mask-size: contain; mask-repeat: no-repeat; mask-position: center; }
.animate-fade-in { animation: fadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>