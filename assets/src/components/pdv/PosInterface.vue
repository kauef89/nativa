<template>
  <div class="flex flex-col h-full w-full bg-surface-1 rounded-[24px] overflow-hidden transition-all shadow-2xl relative">
    
    <div class="h-20 shrink-0 bg-surface-1 flex justify-between items-center px-6 z-20">
        <div class="flex items-center gap-4">
            <Button icon="fa-solid fa-arrow-left" text rounded class="!w-12 !h-12 !text-surface-400 hover:!text-surface-0 hover:!bg-surface-2" @click="handleBackNavigation" />
            <div class="flex flex-col">
                <h2 class="text-xl font-black text-surface-0 leading-none flex items-center gap-2">
                    <i class="fa-solid text-primary-500" :class="getSessionIcon"></i>
                    {{ getSessionTitle }}
                </h2>
                <div class="flex items-center gap-2 mt-1">
                    <span class="text-[10px] uppercase font-bold text-surface-500 tracking-wider">
                        Conta: <span class="text-primary-400 cursor-pointer hover:underline font-black" @click="showAccountsModal = true">{{ sessionStore.currentAccount }}</span>
                    </span>
                    <span v-if="linkedClient" class="text-[10px] bg-green-500/10 text-green-400 px-2 py-0.5 rounded-full font-bold border border-green-500/20 flex items-center gap-1">
                        <i class="fa-solid fa-user-check"></i> {{ linkedClient.name }}
                    </span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-1 bg-surface-2 p-1 rounded-full">
            <button 
                class="px-6 py-2.5 rounded-full text-xs font-black uppercase tracking-wide transition-all flex items-center gap-2"
                :class="viewMode === 'ordering' ? 'bg-primary-500 text-surface-950 shadow-lg' : 'text-surface-400 hover:text-surface-0 hover:bg-surface-3'"
                @click="switchMode('ordering')"
            >
                <i class="fa-solid fa-utensils"></i> Pedir
            </button>
            <button 
                class="px-6 py-2.5 rounded-full text-xs font-black uppercase tracking-wide transition-all flex items-center gap-2"
                :class="viewMode === 'checkout' ? 'bg-green-500 text-surface-950 shadow-lg' : 'text-surface-400 hover:text-surface-0 hover:bg-surface-3'"
                @click="switchMode('checkout')"
            >
                <i class="fa-solid fa-file-invoice-dollar"></i> Pagar
            </button>
        </div>

        <div class="flex items-center gap-2">
            <Button icon="fa-solid fa-print" class="!w-12 !h-12 !bg-surface-2 hover:!bg-surface-3 !text-surface-300 hover:!text-surface-0 !border-none !rounded-full" @click="handlePrintAccount" />
            <Button v-if="!linkedClient" icon="fa-solid fa-user-plus" class="!w-12 !h-12 !bg-surface-2 hover:!bg-surface-3 !text-surface-300 hover:!text-surface-0 !border-none !rounded-full" @click="showClientSearch = true" />
            <Button icon="fa-solid fa-users" class="!w-12 !h-12 !bg-surface-2 hover:!bg-surface-3 !text-surface-300 hover:!text-surface-0 !border-none !rounded-full" @click="showAccountsModal = true" />
        </div>
    </div>

    <div class="flex-1 flex overflow-hidden relative p-4 pt-0 gap-4">
        
        <div class="flex-1 flex flex-col min-w-0 relative bg-surface-2 rounded-[24px] overflow-hidden shadow-inner">
            <transition name="fade" mode="out-in">
                <div v-if="viewMode === 'ordering'" class="h-full w-full" key="product-list">
                    <ProductList @open-product="handleOpenProduct" />
                </div>
                <div v-else class="h-full w-full" key="checkout-list">
                    <CheckoutItemList @request-swap="onRequestEdit" @request-cancel="onRequestCancel" @request-edit="onRequestEdit"/>
                </div>
            </transition>
        </div>

        <div class="w-[400px] shrink-0 bg-surface-2 rounded-[24px] flex flex-col z-10 shadow-xl overflow-hidden">
            <transition name="fade" mode="out-in">
                
                <div v-if="viewMode === 'ordering'" class="h-full w-full" key="cart-widget">
                    <CartWidget 
                        :displayMode="'inline'" 
                        @require-identification="handleRequireIdentification"
                    />
                </div>
                
                <div v-else class="h-full w-full" key="summary-widget">
                    <OrderSummaryWidget 
                        @open-payment="switchMode('checkout')"
                        @toggle-history="showTimeline = true"
                        @payment-success="handlePaymentSuccess" 
                        @require-identification="handleRequireIdentification"
                    />
                </div>
            </transition>
        </div>

    </div>

    <ProductDetailsModal ref="detailsModal" :show-takeout="true" />
    <ComboWizardModal ref="comboWizard" :show-takeout="true" />
    
    <TableAccountsModal 
        v-model:visible="showAccountsModal" 
        :session-id="sessionStore.sessionId" 
        :table-number="sessionStore.sessionType === 'table' ? sessionStore.identifier : null" 
        :enforce-identification="pendingLaunch"
        @account-selected="onAccountSelected" 
    />
    
    <SaleFinalizationModal :visible="showFinalizationModal" :order-data="finalizedOrderData" @close="handleFinalizationClose" />
    
    <Dialog v-model:visible="showExitDialog" modal header="Pedido Vazio" :style="{ width: '400px' }" 
            :pt="{ root: { class: '!bg-surface-1 !border-none !rounded-[24px]' }, header: { class: '!bg-transparent !text-surface-0 !border-b !border-surface-3/10 !p-6' }, content: { class: '!bg-transparent !p-6' }, footer: { class: '!bg-transparent !border-t !border-surface-3/10 !p-4' } }">
        <div class="text-center">
            <div class="w-16 h-16 bg-surface-2 rounded-full flex items-center justify-center mx-auto mb-4 text-surface-400 text-2xl">
                <i class="fa-solid fa-ghost"></i>
            </div>
            <p class="text-surface-300 text-sm mb-2 font-bold">Este pedido não possui itens.</p>
            <p class="text-surface-0 font-black">Deseja cancelar ou manter rascunho?</p>
        </div>
        <template #footer>
            <div class="flex flex-col w-full gap-2">
                <Button label="Cancelar Pedido" icon="fa-solid fa-trash" class="!bg-red-600 hover:!bg-red-500 !border-none !text-surface-0 !font-black !w-full !rounded-full !h-12" :loading="isCancelling" @click="confirmCancelEmptyOrder" />
                <Button label="Manter Rascunho" icon="fa-solid fa-save" class="!bg-surface-2 hover:!bg-surface-3 !text-surface-300 hover:!text-surface-0 !w-full !font-bold !border-none !rounded-full !h-12" @click="exitKeepingDraft" />
            </div>
        </template>
    </Dialog>

    <Dialog v-model:visible="showClientSearch" modal header="Identificar Cliente" :style="{ width: '450px' }" 
            :pt="{ root: { class: '!bg-surface-1 !border-none !rounded-[24px]' }, header: { class: '!bg-transparent !text-surface-0 !border-b !border-surface-3/10 !p-6' }, content: { class: '!bg-transparent !p-6' } }">
        <div class="flex flex-col gap-4">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-surface-500 z-10"></i>
                <AutoComplete 
                    v-model="clientSearchQuery" 
                    :suggestions="clientSuggestions" 
                    @complete="searchClient" 
                    @item-select="selectClient"
                    optionLabel="label" 
                    placeholder="Buscar cliente..." 
                    class="w-full"
                    :pt="{ input: { class: '!bg-surface-2 !border-none !text-surface-0 !pl-12 !w-full !h-14 !rounded-full !font-bold' }, panel: { class: '!bg-surface-2 !border-none !rounded-[16px] !shadow-2xl' } }"
                >
                    <template #option="slotProps">
                        <div class="flex flex-col py-1 px-2">
                            <span class="font-black text-surface-0 text-sm">{{ slotProps.option.name }}</span>
                            <span class="text-[10px] text-surface-400 font-bold">
                                CPF: {{ formatCPF(slotProps.option.cpf) || '---' }}
                            </span>
                        </div>
                    </template>
                </AutoComplete>
            </div>
        </div>
    </Dialog>

    <Dialog v-model:visible="showTimeline" modal maximized 
        :pt="{ root: { class: '!bg-surface-1 !border-none' }, header: { class: '!bg-surface-1 !border-b !border-surface-3/10 !p-4' }, content: { class: '!bg-surface-1 !p-0' } }">
        <template #header><span class="font-black text-surface-0 text-lg">Histórico Completo</span></template>
        <SessionTimeline :events="timelineEvents" @close="showTimeline = false" />
    </Dialog>

    <Dialog v-model:visible="showApprovalModal" modal header="Aprovação Necessária" :style="{ width: '300px' }" 
            :pt="{ root: { class: '!bg-surface-2 !border-none !rounded-[24px]' }, header: { class: '!bg-transparent !text-surface-0 !p-4 !border-none' }, content: { class: '!bg-transparent !p-6' } }">
        <form @submit.prevent="handleInstantApproval" class="flex flex-col gap-4 text-center" autocomplete="off">
            <p class="text-sm text-surface-300">Digite seu PIN para liberar a ação.</p>
            <InputText v-model="approvalPin" type="password" maxlength="4" autocomplete="new-password" name="nativa_pos_pin"
                class="!text-center !text-2xl !tracking-[0.5em] !font-bold !bg-surface-1 !border-none !text-surface-0 !h-12 !rounded-full focus:!ring-2 focus:!ring-primary-500" 
                placeholder="••••" autoFocus />
            <Button type="submit" label="Confirmar" class="!bg-primary-600 hover:!bg-primary-500 !border-none !w-full !font-black !rounded-full" :loading="isApproving" />
        </form>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useSessionStore } from '@/stores/session-store';
import { useCartStore } from '@/stores/cart-store';
import { useMenuStore } from '@/stores/menu-store'; 
import { useUserStore } from '@/stores/user-store'; 
import { useFormat } from '@/composables/useFormat'; 
import { PrinterService } from '@/services/printer-service';
import api from '@/services/api';
import { notify } from '@/services/notify';

// UI
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import AutoComplete from 'primevue/autocomplete'; 
import InputText from 'primevue/inputtext'; 
import InputMask from 'primevue/inputmask';

// Componentes
import ProductList from '@/components/shared/ProductList.vue';
import ProductDetailsModal from '@/components/shared/ProductDetailsModal.vue';
import ComboWizardModal from '@/components/shared/ComboWizardModal.vue';
import CartWidget from '@/components/shared/CartWidget.vue';
import CheckoutItemList from '@/components/pdv/CheckoutItemList.vue';
import OrderSummaryWidget from '@/components/pdv/OrderSummaryWidget.vue';
import TableAccountsModal from '@/components/pdv/TableAccountsModal.vue'; 
import SaleFinalizationModal from '@/components/pdv/SaleFinalizationModal.vue';
import SessionTimeline from '@/components/shared/SessionTimeline.vue';

const route = useRoute();
const router = useRouter();
const sessionStore = useSessionStore();
const cartStore = useCartStore();
const menuStore = useMenuStore();
const userStore = useUserStore(); 
const { formatCPF } = useFormat(); 

const viewMode = ref('ordering'); 
const showAccountsModal = ref(false);
const showClientSearch = ref(false);
const showExitDialog = ref(false);
const showTimeline = ref(false);
const pendingLaunch = ref(false); // Flag para lançamento pendente

const detailsModal = ref(null);
const comboWizard = ref(null);
const showMigrationDialog = ref(false);
const pendingAccount = ref('');
const itemToSwap = ref(null);

// Estado de Finalização
const showFinalizationModal = ref(false);
const finalizedOrderData = ref(null);

// Lógica de Cliente / Mesa
const linkedClient = ref(null);
const clientSuggestions = ref([]);
const clientSearchQuery = ref(null);

// Aprovação
const showApprovalModal = ref(false);
const approvalPin = ref('');
const currentRequestId = ref(null);
const isApproving = ref(false);
const isCancelling = ref(false);

const timelineEvents = ref([]); 

const getSessionIcon = computed(() => {
    switch(sessionStore.sessionType) {
        case 'table': return 'fa-utensils';
        case 'delivery': return 'fa-motorcycle';
        case 'pickup': return 'fa-bag-shopping';
        default: return 'fa-cash-register';
    }
});

const getSessionTitle = computed(() => {
    switch(sessionStore.sessionType) {
        case 'table': return `Mesa ${sessionStore.identifier}`;
        case 'delivery': return `Delivery: ${sessionStore.identifier}`;
        case 'pickup': return `Retirada: ${sessionStore.identifier}`;
        case 'counter': return `Balcão: ${sessionStore.identifier || 'Venda Rápida'}`;
        default: return 'Ponto de Venda';
    }
});

const isManager = computed(() => {
    return userStore.user?.role === 'nativa_manager' || userStore.user?.role === 'administrator';
});

// --- Lógica de Rota (CORREÇÃO) ---
const switchMode = (mode) => {
    const query = { ...route.query };
    if (mode === 'checkout') query.mode = 'checkout';
    else delete query.mode;
    router.replace({ query });
};

watch(() => route.query.mode, (newMode) => {
    viewMode.value = newMode === 'checkout' ? 'checkout' : 'ordering';
}, { immediate: true });
// ---------------------------------

// Nova função para lidar com o pedido de identificação
const handleRequireIdentification = () => {
    showAccountsModal.value = true;
    pendingLaunch.value = true;
};

const handleBackNavigation = () => {
    const hasServerItems = sessionStore.items.length > 0;
    const hasLocalItems = cartStore.items.length > 0;
    if (hasServerItems || hasLocalItems) {
        sessionStore.leaveSession();
        router.push('/staff/tables');
        return;
    }
    showExitDialog.value = true;
};

const confirmCancelEmptyOrder = async () => {
    if (!sessionStore.sessionId) { exitKeepingDraft(); return; }
    isCancelling.value = true;
    try {
        await api.post('/update-order-status', { order_id: sessionStore.sessionId, status: 'cancelado' });
        notify('info', 'Cancelado', 'O pedido vazio foi descartado.');
        showExitDialog.value = false;
        exitKeepingDraft();
    } catch (e) { notify('error', 'Erro', 'Falha ao cancelar. Saindo como rascunho.'); exitKeepingDraft(); } 
    finally { isCancelling.value = false; }
};

const exitKeepingDraft = () => {
    showExitDialog.value = false;
    sessionStore.leaveSession();
    router.push('/staff/tables');
};

const handlePrintAccount = async () => {
    if (!sessionStore.items || sessionStore.items.length === 0) { notify('warn', 'Vazio', 'Não há itens lançados.'); return; }
    
    const accountItems = sessionStore.items.filter(i => i.sub_account === sessionStore.currentAccount && i.status !== 'cancelled').map(i => ({
        qty: i.qty, name: i.name, total: i.line_total, modifiers: i.modifiers
    }));

    if (accountItems.length === 0) { notify('warn', 'Vazio', 'Esta conta não possui itens.'); return; }

    const payload = {
        identifier: sessionStore.identifier,
        client_name: sessionStore.clientName || sessionStore.currentAccount,
        sessionId: sessionStore.sessionId,
        items: accountItems,
        totals: {
            subtotal: sessionStore.accountTotal(sessionStore.currentAccount),
            fee: sessionStore.deliveryFee || 0,
            total: sessionStore.accountTotal(sessionStore.currentAccount)
        }
    };
    await PrinterService.printAccount(payload);
};

const handlePaymentSuccess = (data) => {
    finalizedOrderData.value = {
        id: sessionStore.sessionId,
        client_name: sessionStore.clientName || sessionStore.currentAccount,
        client_cpf: linkedClient.value?.cpf || '',
        items: sessionStore.items.filter(i => i.sub_account === sessionStore.currentAccount),
        totals: { total: data.amount },
        payments: data.payments
    };
    showFinalizationModal.value = true;
};

const handleFinalizationClose = () => {
    showFinalizationModal.value = false;
    finalizedOrderData.value = null;
    if (sessionStore.sessionStatus === 'closed') {
        sessionStore.leaveSession();
        router.push('/staff/tables');
    } else {
        sessionStore.refreshSession();
        switchMode('ordering');
    }
};

const openInstantApproval = (logId) => {
    currentRequestId.value = logId;
    approvalPin.value = '';
    showApprovalModal.value = true;
};

const handleInstantApproval = async () => {
    if (!approvalPin.value) return;
    isApproving.value = true;
    try {
        const { data } = await api.post('/approve-request', { log_id: currentRequestId.value, action: 'approve', pin: approvalPin.value });
        if (data.success) {
            notify('success', 'Aprovado', 'Operação autorizada com sucesso.');
            showApprovalModal.value = false;
            await sessionStore.refreshSession();
        }
    } catch(e) { notify('error', 'Erro', e.response?.data?.message || 'PIN incorreto ou falha.'); } 
    finally { isApproving.value = false; }
};

const checkSessionClient = async () => {
    if (!sessionStore.sessionId) return;
    try {
        const { data } = await api.get(`/session-items?session_id=${sessionStore.sessionId}`);
        if (data.success && data.session_info?.client_id) {
             linkedClient.value = { id: data.session_info.client_id, name: data.session_info.client_name || 'Cliente' };
        }
    } catch (e) {}
};

const searchClient = async (event) => {
    try { const { data } = await api.get(`/search-clients?q=${event.query}`); if (data.success) clientSuggestions.value = data.clients; } catch (e) {}
};

const selectClient = async (event) => {
    const client = event.value;
    if (!client || !client.id) return;
    if (!sessionStore.sessionId && sessionStore.sessionType === 'table') {
        const success = await sessionStore.createAccount('Principal'); 
        if (!success) { notify('error', 'Erro', 'Não foi possível abrir a mesa.'); return; }
    }
    if (!sessionStore.sessionId) { notify('error', 'Erro', 'Sessão inválida.'); return; }
    try {
        const { data } = await api.post('/link-client', { session_id: sessionStore.sessionId, client_id: client.id });
        if (data.success) {
            linkedClient.value = { id: client.id, name: client.name };
            if (data.accounts) { sessionStore.accounts = data.accounts; sessionStore.setAccount(client.name); }
            notify('success', 'Vinculado', `Conta criada para ${client.name}!`);
            showClientSearch.value = false;
            clientSearchQuery.value = null;
        }
    } catch (e) { notify('error', 'Erro', e.response?.data?.message || 'Falha ao vincular.'); }
};

const findProductInMenu = (productId) => {
    if (!productId) return null;
    for (const cat of menuStore.categories) {
        if (cat.products) { const found = cat.products.find(p => p.id == productId); if (found) return found; }
        if (cat.children) { for (const child of cat.children) { const foundChild = child.products.find(p => p.id == productId); if (foundChild) return foundChild; } }
    }
    return null;
};

const onRequestEdit = async (item) => {
    if (!item.product_id) { notify('error', 'Erro', 'ID do produto original não identificado.'); return; }
    if (menuStore.categories.length === 0) await menuStore.fetchMenu();
    const product = findProductInMenu(item.product_id);
    if (!product) { notify('error', 'Erro', 'Produto não encontrado no cardápio atual.'); return; }
    const processEdit = (configuredItem) => {
        const payload = { original_item_id: item.id, session_id: sessionStore.sessionId, new_item: { id: configuredItem.id, name: configuredItem.name, qty: configuredItem.qty, price: configuredItem.price, modifiers: configuredItem.modifiers } };
        api.post('/swap-items', payload).then((res) => { if(res.data.success) { if (isManager.value && res.data.log_id) openInstantApproval(res.data.log_id); else { notify('success', 'Solicitado', 'Edição enviada para aprovação.'); sessionStore.refreshSession(); } } }).catch(e => { notify('error', 'Erro', e.response?.data?.message || 'Falha na edição.'); });
    };
    if (product.type === 'combo') comboWizard.value.open(product, null, null, processEdit);
    else detailsModal.value.open(product, processEdit);
};

const handleOpenProduct = (product) => {
    if (product.type === 'combo') comboWizard.value.open(product);
    else if (product.modifiers && product.modifiers.length > 0) detailsModal.value.open(product, (item) => cartStore.addItem(item));
    else cartStore.addItem({ ...product, qty: 1, price: parseFloat(product.price), modifiers: [] });
};

const onRequestCancel = async (item) => {
    if (!confirm(`Deseja cancelar ${item.qty}x ${item.name}?`)) return;
    try {
        const { data } = await api.post('/cancel-item', { item_id: item.id });
        if (data.success) {
            if (isManager.value && data.log_id) openInstantApproval(data.log_id);
            else { notify('success', 'Cancelado', data.message || 'Item cancelado.'); await sessionStore.refreshSession(); }
        }
    } catch (e) { notify('error', 'Erro', e.response?.data?.message || 'Falha ao cancelar.'); }
};

const onAccountSelected = async (accountName) => {
    // 1. Atualiza a conta
    // CORREÇÃO: Injetar a conta localmente se não existir (para o caso de venda nova balcão)
    if (!sessionStore.accounts.includes(accountName)) {
        sessionStore.accounts.push(accountName);
    }
    sessionStore.setAccount(accountName);
    
    // 2. Se houver itens no carrinho (não enviados), questiona se move
    if (cartStore.items.length > 0) {
        // Se for fluxo de lançamento automático, move silenciosamente
        if (!pendingLaunch.value) {
            pendingAccount.value = accountName; 
            showMigrationDialog.value = true;
            showAccountsModal.value = false;
            return;
        }
    }

    showAccountsModal.value = false;
    
    // 3. Lógica de Lançamento Automático
    if (pendingLaunch.value) {
         try {
             await cartStore.sendOrder();
             switchMode('checkout');
         } catch (e) {
             console.error("Erro no lançamento automático:", e);
         } finally {
             pendingLaunch.value = false;
         }
    }
};

const confirmSwitch = (keepItems) => {
    if (!keepItems) cartStore.items = [];
    sessionStore.setAccount(pendingAccount.value);
    showMigrationDialog.value = false; showAccountsModal.value = false; pendingAccount.value = '';
};

onMounted(async () => {
    // viewMode controlado pelo watcher (immediate)
    if (sessionStore.sessionId) { await sessionStore.refreshSession(); checkSessionClient(); }
});
</script>

<style scoped>
.transition-all { transition-property: all; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 300ms; }
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.animate-fade-in { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>