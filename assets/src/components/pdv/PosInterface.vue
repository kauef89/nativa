<template>
  <div class="flex flex-col h-full w-full bg-surface-950">
    <div class="shrink-0 border-b border-surface-800 px-4 py-4 flex items-center justify-between shadow-sm z-20">
        <div class="flex items-center gap-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl bg-primary-500/20 text-primary-500 border border-primary-500/50 shadow-inner">
                    <i class="fa-solid" :class="sessionStore.sessionType === 'table' ? 'fa-table' : 'fa-cash-register'"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white leading-tight">
                        <span v-if="sessionStore.sessionType === 'table'">Mesa {{ sessionStore.identifier }}</span>
                        <span v-else>Venda Balcão</span>
                    </h1>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-[10px] text-surface-400 uppercase font-black tracking-widest">
                            {{ viewMode === 'ordering' ? 'Montagem de Pedido' : 'Checkout & Pagamento' }}
                        </span>
                        <span v-if="sessionStore.sessionType === 'table'" class="text-[10px] bg-surface-800 text-primary-400 px-2 py-0.5 rounded font-bold uppercase border border-surface-700">
                            <i class="fa-solid fa-user-tag mr-1"></i> {{ sessionStore.currentAccount }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex gap-2">
             <Button 
                v-if="viewMode === 'checkout'"
                label="Voltar para Produtos" 
                icon="fa-solid fa-chevron-left" 
                class="!bg-surface-800 !border-surface-700 !text-surface-300 hover:!text-white" 
                @click="viewMode = 'ordering'" 
             />
             
             <Button 
                v-if="viewMode === 'ordering' && sessionStore.sessionType === 'table'"
                label="Contas" 
                icon="fa-solid fa-users" 
                class="!bg-surface-800 !border-surface-700 !text-surface-300 hover:!text-white hover:!bg-surface-700" 
                @click="showAccountsModal = true" 
             />

             <Button 
                v-if="viewMode === 'ordering' && sessionStore.sessionType === 'table'"
                label="Pagamento" 
                icon="fa-solid fa-wallet" 
                class="!bg-green-600 hover:!bg-green-500 !border-none !text-white !font-bold" 
                @click="viewMode = 'checkout'" 
             />

             <Button 
                label="Sair" 
                icon="fa-solid fa-xmark" 
                class="!bg-surface-800 !border-surface-700 !text-red-400 hover:!bg-red-500/10" 
                @click="handleExit" 
             />
        </div>
    </div>

    <div v-if="itemToSwap" class="bg-orange-500 text-white text-sm font-bold p-3 text-center flex justify-between items-center shadow-md animate-fade-in z-30">
        <div class="flex items-center gap-2">
            <i class="fa-solid fa-rotate animate-spin-slow"></i>
            <span>Selecione o produto para substituir: <strong>{{ itemToSwap.name }}</strong></span>
        </div>
        <button @click="cancelSwap" class="text-white hover:bg-orange-600 p-1 rounded transition-colors"><i class="fa-solid fa-times"></i> Cancelar Troca</button>
    </div>

    <div class="flex-1 grid grid-cols-5 overflow-hidden p-4 gap-4">
        
        <template v-if="viewMode === 'ordering'">
            <div class="col-span-3 overflow-hidden relative bg-surface-900 border border-surface-800 rounded-2xl shadow-xl flex flex-col min-w-0">
                <ProductList @open-product="handleOpenProduct" />
            </div>

            <div class="col-span-2 bg-surface-900 border border-surface-800 rounded-2xl shadow-xl flex flex-col overflow-hidden relative transition-all duration-300 min-w-0">
                <CartWidget displayMode="inline" />
            </div>
        </template>

        <template v-else>
            <div class="col-span-3 overflow-hidden relative bg-surface-900 border border-surface-800 rounded-2xl shadow-xl flex flex-col min-w-0">
                <CheckoutItemList 
                    @request-cancel="onRequestCancel" 
                    @request-swap="onRequestSwap" 
                />
            </div>

            <div class="col-span-2 bg-surface-900 border border-surface-800 rounded-2xl shadow-xl flex flex-col overflow-hidden transition-all duration-300 min-w-0">
                <OrderSummaryWidget />
            </div>
        </template>
    </div>

    <ProductDetailsModal ref="detailsModal" />
    <ComboWizardModal ref="comboWizard" />
    <TableAccountsModal v-model:visible="showAccountsModal" :session-id="sessionStore.sessionId" :table-number="sessionStore.identifier" @account-selected="onAccountSelected" />
    <Dialog v-model:visible="showMigrationDialog" modal header="Atenção: Itens no Carrinho" :style="{ width: '450px' }" :pt="{ root: { class: '!bg-surface-900 !border !border-surface-800 !rounded-2xl' }, header: { class: '!bg-surface-950 !border-b !border-surface-800 !text-white' }, content: { class: '!bg-surface-900 !p-6' }, footer: { class: '!bg-surface-950 !border-t !border-surface-800 !p-4' } }">
        <div class="text-center">
            <div class="w-16 h-16 bg-primary-500/20 rounded-full flex items-center justify-center mx-auto mb-4 text-primary-500 text-2xl"><i class="fa-solid fa-cart-flatbed"></i></div>
            <p class="text-surface-300 text-sm mb-2">Você já selecionou <strong>{{ cartStore.items.length }} itens</strong> na conta atual.</p>
            <p class="text-white font-bold text-lg">Deseja mover esses itens para a conta <span class="text-primary-400">{{ pendingAccount }}</span>?</p>
        </div>
        <template #footer>
            <div class="grid grid-cols-2 gap-3 w-full">
                <Button label="Não, Limpar Carrinho" icon="fa-solid fa-trash" class="!bg-surface-800 !border-surface-700 !text-red-400 hover:!bg-red-500/10 hover:!border-red-500/30" @click="confirmSwitch(false)" />
                <Button :label="`Sim, Mover p/ ${pendingAccount}`" icon="fa-solid fa-check" class="!bg-primary-600 hover:!bg-primary-500 !border-none !text-white !font-bold" @click="confirmSwitch(true)" />
            </div>
        </template>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useSessionStore } from '@/stores/session-store';
import { useCartStore } from '@/stores/cart-store';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import api from '@/services/api';
import { notify } from '@/services/notify';

import ProductList from '@/components/shared/ProductList.vue';
import ProductDetailsModal from '@/components/shared/ProductDetailsModal.vue';
import ComboWizardModal from '@/components/shared/ComboWizardModal.vue';
import CartWidget from '@/components/shared/CartWidget.vue';
import CheckoutItemList from './CheckoutItemList.vue';
import OrderSummaryWidget from './OrderSummaryWidget.vue';
import TableAccountsModal from './TableAccountsModal.vue'; 

const route = useRoute();
const sessionStore = useSessionStore();
const cartStore = useCartStore();
const viewMode = ref('ordering'); 

const detailsModal = ref(null);
const comboWizard = ref(null);
const showAccountsModal = ref(false);
const showMigrationDialog = ref(false);
const pendingAccount = ref('');
const itemToSwap = ref(null); 

const emit = defineEmits(['exit']);

const handleOpenProduct = (product) => {
    // --- 1. LÓGICA DE TROCA (SWAP) ---
    if (itemToSwap.value) {
        
        // Função que será chamada após configurar o produto no modal
        const processSwap = (configuredItem) => {
            if (!confirm(`Confirma a troca de '${itemToSwap.value.name}' por '${configuredItem.name}'?`)) return;
            
            // Monta payload completo garantindo que modificadores e preço correto vão para o backend
            const payload = { 
                original_item_id: itemToSwap.value.id, 
                session_id: sessionStore.sessionId,
                new_item: {
                    id: configuredItem.id, // ID original do produto
                    name: configuredItem.name,
                    qty: configuredItem.qty || 1,
                    price: configuredItem.price,
                    modifiers: configuredItem.modifiers || [], 
                }
            };

            api.post('/swap-items', payload).then((res) => { 
                if (res.data.success) {
                    notify('success', 'Troca Solicitada', res.data.message || 'Item trocado com sucesso.');
                    cancelSwap();
                    sessionStore.refreshSession();
                    viewMode.value = 'checkout'; 
                }
            }).catch(e => {
                notify('error', 'Erro', e.response?.data?.message || 'Falha na troca.');
            });
        };

        // Roteia para o configurador correto antes de confirmar
        if (product.type === 'combo') {
            // Abre Wizard e passa processSwap como callback
            comboWizard.value.open(product, null, null, processSwap);
        } else {
            // CORREÇÃO: Abre o modal de detalhes para TODOS os produtos (Simples ou com Modificadores)
            // Isso permite conferir e ajustar quantidade antes de trocar
            detailsModal.value.open(product, processSwap);
        }
        return;
    }
    // -----------------------

    // --- 2. LÓGICA PADRÃO (ADICIONAR AO CARRINHO) ---
    if (product.type === 'combo') {
        comboWizard.value.open(product);
    } else if (product.modifiers && product.modifiers.length > 0) {
        detailsModal.value.open(product, (item) => cartStore.addItem(item));
    } else {
        cartStore.addItem({ 
            ...product, 
            qty: 1, 
            price: parseFloat(product.price), 
            modifiers: [] 
        });
    }
};

const onRequestCancel = async (item) => {
    if (!confirm(`Deseja cancelar ${item.qty}x ${item.name}?`)) return;
    try {
        const { data } = await api.post('/cancel-item', { item_id: item.id });
        if (data.success) {
            notify('success', 'Cancelado', data.message || 'Item cancelado.');
            await sessionStore.refreshSession();
        }
    } catch (e) {
        notify('error', 'Erro', e.response?.data?.message || 'Falha ao cancelar.');
    }
};

const onRequestSwap = (item) => {
    itemToSwap.value = item;
    viewMode.value = 'ordering'; 
    notify('info', 'Troca Iniciada', `Escolha o produto para substituir ${item.name}`);
};

const cancelSwap = () => {
    itemToSwap.value = null;
    // Se estava na tela de produtos só pra trocar, volta pro checkout
    if (viewMode.value === 'ordering' && route.query.mode === 'checkout') {
       // Opcional: viewMode.value = 'checkout'; 
    }
};

const handleExit = () => {
    if (cartStore.items.length > 0) {
        if (!confirm('Existem itens no carrinho que não foram enviados. Sair mesmo assim?')) return;
    }
    sessionStore.leaveSession();
    emit('exit');
};

const onAccountSelected = (accountName) => {
    if (cartStore.items.length === 0) {
        sessionStore.setAccount(accountName);
        showAccountsModal.value = false;
        return;
    }
    pendingAccount.value = accountName;
    showMigrationDialog.value = true;
};

const confirmSwitch = (keepItems) => {
    if (!keepItems) cartStore.items = [];
    sessionStore.setAccount(pendingAccount.value);
    showMigrationDialog.value = false;
    showAccountsModal.value = false;
    pendingAccount.value = '';
};

onMounted(() => {
    if (route.query.mode === 'checkout') {
        viewMode.value = 'checkout';
    }
    if (sessionStore.sessionId) {
        sessionStore.refreshSession();
    }
});
</script>

<style scoped>
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 300ms;
}
.animate-spin-slow {
    animation: spin 3s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>