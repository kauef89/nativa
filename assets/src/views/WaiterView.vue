<template>
  <div class="flex flex-col h-full bg-surface-950 w-full overflow-hidden relative" @click="resetSwipe">
    
    <div class="bg-surface-900 border-b border-surface-800 p-2 shrink-0 flex items-center gap-2 shadow-md z-20 h-[60px]">
        <button 
            class="flex items-center gap-3 shrink-0 pl-2 pr-1 py-1 rounded-lg hover:bg-surface-800 transition-colors max-w-[55%]" 
            @click="showAccountsModal = true"
        >
            <div class="flex items-center gap-2">
                <div class="bg-primary-500/20 text-primary-500 w-8 h-8 rounded-lg flex items-center justify-center text-sm shadow-sm border border-primary-500/10">
                    <i class="fa-solid fa-table"></i>
                </div>
                <h1 class="text-xl font-black text-white leading-none whitespace-nowrap">
                    {{ sessionStore.identifier }}
                </h1>
            </div>
            <div class="h-6 w-px bg-surface-700"></div>
            <div class="flex flex-col items-start min-w-0">
                <span class="text-[9px] text-surface-500 font-bold uppercase leading-none mb-0.5">Conta</span>
                <div class="flex items-center gap-1 text-surface-300">
                    <span class="text-xs font-bold uppercase truncate max-w-[80px]">{{ sessionStore.currentAccount }}</span>
                    <i class="fa-solid fa-chevron-down text-[8px] opacity-50"></i>
                </div>
            </div>
        </button>

        <div class="flex-1 relative min-w-0">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-surface-500 text-xs"></i>
            <input 
                v-model="headerSearch"
                type="text" 
                placeholder="Buscar..." 
                class="w-full bg-surface-950 border border-surface-700 text-white pl-8 pr-2 h-10 rounded-xl focus:outline-none focus:border-primary-500 transition-all text-xs font-medium placeholder:text-surface-600 appearance-none"
            />
            <button v-if="headerSearch" @click="headerSearch = ''" class="absolute right-2 top-1/2 -translate-y-1/2 text-surface-500 p-1">
                <i class="fa-solid fa-circle-xmark text-xs"></i>
            </button>
        </div>

        <button 
            class="w-10 h-10 rounded-xl flex items-center justify-center transition-all shrink-0 relative mr-1"
            :class="cartStore.totalItems > 0 ? 'bg-surface-800 text-white border-primary-500/50 border' : 'bg-surface-900 text-surface-500 border border-surface-800'"
            @click="isCartOpen = true"
        >
            <i class="fa-solid fa-basket-shopping text-lg"></i>
            <span v-if="cartStore.totalItems > 0" 
                  class="absolute -top-1 -right-1 bg-red-500 text-white text-[9px] font-bold h-4 min-w-[16px] px-1 rounded-full flex items-center justify-center shadow-sm border border-surface-900 animate-bounce">
                {{ cartStore.totalItems }}
            </span>
        </button>
    </div>

    <div class="flex-1 overflow-hidden relative bg-surface-950">
        <div v-if="itemToSwap" class="bg-orange-500 text-white text-xs font-bold p-2 text-center flex justify-between items-center animate-slide-down shadow-md z-30 absolute top-0 w-full">
            <span>Escolha o substituto para: {{ itemToSwap.name }}</span>
            <button @click="cancelSwap" class="text-white hover:text-surface-200"><i class="fa-solid fa-times"></i></button>
        </div>
        <div class="h-full flex flex-col"> 
            <MobileMenu :external-search="headerSearch" :hide-local-search="true" @open-product="handleOpenProduct" />
        </div>
    </div>

    <Drawer 
        v-model:visible="isCartOpen" 
        position="bottom" 
        class="!h-[90vh] !bg-surface-900 !border-t !border-surface-700 !rounded-t-[2rem] shadow-2xl"
        :modal="true"
        :pt="{ 
            header: { class: '!bg-surface-900 !border-b !border-surface-800 !p-4 !rounded-t-[2rem]' },
            content: { class: '!bg-surface-900 !p-0' },
            mask: { class: '!bg-black/80 backdrop-blur-sm' }
        }"
    >
        <template #header>
            <div class="absolute top-3 left-1/2 -translate-x-1/2 w-12 h-1.5 bg-surface-700 rounded-full"></div>
            <div class="flex items-center gap-2 mt-2">
                <i class="fa-solid fa-basket-shopping text-primary-500"></i>
                <span class="text-lg font-bold text-white">Revisão do Pedido</span>
            </div>
        </template>

        <div class="flex flex-col h-full overflow-hidden relative" @click="resetSwipe">
            
            <div class="flex-1 overflow-y-auto scrollbar-thin p-4 space-y-6 pb-24">
                
                <div v-if="cartStore.items.length > 0" class="space-y-3">
                    <div class="flex justify-between items-end px-1">
                        <span class="text-xs font-black text-primary-400 uppercase tracking-widest">Novos ({{ cartStore.items.length }})</span>
                        <button class="text-[10px] text-red-400 font-bold uppercase hover:text-red-300" @click="confirmClearCart">Limpar</button>
                    </div>

                    <div v-for="(item, index) in cartStore.items" :key="item.uniqueId" 
                         class="relative rounded-2xl overflow-hidden group select-none h-[100px]"
                    >
                        <div class="absolute inset-0 bg-red-600 flex items-center justify-end pr-6 rounded-2xl border-2 border-surface-950">
                            <div class="flex flex-col items-center text-white">
                                <i class="fa-solid fa-trash-can text-xl mb-1"></i>
                                <span class="text-[10px] font-bold uppercase">Excluir</span>
                            </div>
                        </div>
                        
                        <button class="absolute inset-y-0 right-0 w-24 z-0 cursor-pointer" @click.stop="cartStore.items.splice(index, 1); swipedId = null;"></button>

                        <div 
                            class="relative z-10 bg-surface-950 border-2 border-surface-950 rounded-2xl p-3 flex gap-3 h-full transition-transform duration-300 ease-out shadow-sm"
                            :class="{'!-translate-x-24': swipedId === item.uniqueId}"
                            @touchstart="onTouchStart($event)"
                            @touchend="onTouchEnd($event, item.uniqueId)"
                            @click="handleItemClick(item)"
                        >
                            <div class="w-16 h-16 rounded-xl bg-surface-900 shrink-0 overflow-hidden relative border border-surface-800 self-center">
                                <img v-if="item.image" :src="item.image" class="w-full h-full object-cover">
                                <div v-else class="flex items-center justify-center h-full text-surface-600 opacity-50">
                                    <i class="fa-solid fa-utensils text-lg"></i>
                                </div>
                            </div>

                            <div class="flex-1 min-w-0 flex flex-col justify-center py-0.5">
                                <div class="font-bold text-white text-sm leading-tight line-clamp-2">{{ item.name }}</div>
                                <div v-if="item.modifiers?.length" class="text-[10px] text-surface-400 leading-snug mt-1">
                                    <span v-for="mod in item.modifiers" :key="mod.name" class="block">+ {{ mod.name }}</span>
                                </div>
                                <div v-else class="text-[10px] text-surface-500 italic mt-1">Sem adicionais</div>
                            </div>

                            <div class="flex flex-col justify-between items-end shrink-0 pl-1 py-1" @click.stop>
                                <div class="text-white font-mono font-bold text-sm">
                                    R$ {{ formatMoney(item.price * item.qty) }}
                                </div>
                                <div class="flex items-center bg-surface-900 rounded-lg border border-surface-800 h-8 shadow-inner mt-auto">
                                    <button class="w-8 h-full flex items-center justify-center text-surface-400 active:text-white rounded-l-lg active:bg-surface-800" @click="store.decreaseQty(index)">-</button>
                                    <span class="text-sm font-bold text-white w-5 text-center">{{ item.qty }}</span>
                                    <button class="w-8 h-full flex items-center justify-center text-surface-400 active:text-white rounded-r-lg active:bg-surface-800" @click="store.increaseQty(index)">+</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="py-10 text-center border-2 border-dashed border-surface-800 rounded-2xl bg-surface-950/50">
                    <i class="fa-solid fa-cart-plus text-4xl text-surface-700 mb-3"></i>
                    <p class="text-sm text-surface-500 font-medium">Seu carrinho está vazio.</p>
                    <button class="mt-4 text-primary-500 text-xs font-bold uppercase tracking-wider bg-surface-900 px-4 py-2 rounded-lg border border-surface-800 shadow-sm" @click="isCartOpen = false">
                        + Adicionar Itens
                    </button>
                </div>

                <div v-if="sessionStore.currentAccountItems.length > 0" class="opacity-60 grayscale-[30%]">
                    <div class="flex items-center gap-3 px-1 mb-4 mt-6">
                        <div class="h-px bg-surface-800 flex-1"></div>
                        <span class="text-[10px] font-bold text-surface-500 uppercase tracking-widest">Já na Cozinha</span>
                        <div class="h-px bg-surface-800 flex-1"></div>
                    </div>
                    <div class="space-y-2">
                        <div v-for="item in sessionStore.currentAccountItems" :key="item.id" class="flex justify-between items-start py-2 border-b border-surface-800 last:border-0">
                            <div class="flex gap-3">
                                <span class="bg-surface-800 text-surface-400 font-mono text-[10px] font-bold px-1.5 py-0.5 rounded h-fit mt-0.5 border border-surface-700">{{ item.qty }}x</span>
                                <div>
                                    <div class="text-sm text-surface-300 font-medium leading-tight" :class="{'line-through opacity-50 text-red-400': item.status === 'cancelled'}">{{ item.name }}</div>
                                    <div v-if="item.status === 'pending'" class="text-[9px] text-yellow-500 font-bold uppercase mt-0.5"><i class="fa-solid fa-clock"></i> Aguardando</div>
                                </div>
                            </div>
                            <div class="text-xs font-mono text-surface-500">{{ formatMoney(item.line_total) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-surface-900 border-t border-surface-800 p-5 shrink-0 absolute bottom-0 left-0 right-0 z-20">
                <div class="flex justify-between items-end mb-4 px-1">
                    <span class="text-xs text-surface-400 font-bold uppercase tracking-wider">Total da Conta</span>
                    <div class="text-right">
                        <div v-if="cartStore.totalValue > 0" class="text-xs text-primary-400 font-bold mb-0.5">
                            + R$ {{ formatMoney(cartStore.totalValue) }}
                        </div>
                        <div class="text-2xl font-black text-white leading-none">
                            R$ {{ formatMoney(totalAccount + cartStore.totalValue) }}
                        </div>
                    </div>
                </div>
                <div v-if="cartStore.totalItems > 0">
                    <Button label="ENVIAR PEDIDO" icon="fa-solid fa-paper-plane" class="w-full !bg-primary-600 hover:!bg-primary-500 !border-none !h-14 !text-lg !font-black !text-surface-950 shadow-[0_0_20px_rgba(16,185,129,0.3)] !rounded-xl" :loading="cartStore.isSending" @click="handleSendOrder"/>
                </div>
                <div v-else class="grid grid-cols-2 gap-3">
                    <Button label="Fechar Conta" icon="fa-solid fa-receipt" class="!bg-surface-800 !border-surface-700 !text-surface-300 hover:!text-white !font-bold !h-12 !rounded-xl" @click="handleRequestBill"/>
                    <Button label="Ver Histórico" icon="fa-solid fa-clock-rotate-left" class="!bg-surface-800 !border-surface-700 !text-surface-300 hover:!text-white !font-bold !h-12 !rounded-xl" @click="openHistory"/>
                </div>
            </div>
        </div>
    </Drawer>

    <ProductDetailsModal ref="detailsModal" displayMode="bottom" />
    <ComboWizardModal ref="comboWizard" displayMode="bottom" />
    <TableAccountsModal v-model:visible="showAccountsModal" :session-id="sessionStore.sessionId" :table-number="sessionStore.identifier" @account-selected="onAccountSelected" @view-report="isCartOpen = true"/>
    <SwapTableModal v-model:visible="showSwapModal" :source-table="{ number: sessionStore.identifier, id: currentTableId }" :accounts="sessionStore.accounts" @success="exitTable"/>
    <Dialog v-model:visible="showHistory" modal maximized :pt="{ root: { class: '!bg-surface-950' }, header: { class: '!bg-surface-950 !border-b !border-surface-800' }, content: { class: '!bg-surface-950 !p-0' } }">
        <template #header><span class="font-bold text-white">Histórico Completo</span></template>
        <SessionTimeline :events="timelineEvents" @close="showHistory = false" />
    </Dialog>
  </div>
</template>

<script setup>
// ... (Script mantido EXATAMENTE igual ao anterior, sem alterações lógicas) ...
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router'; 
import { useTablesStore } from '@/stores/tables-store';
import { useSessionStore } from '@/stores/session-store';
import { useCartStore } from '@/stores/cart-store';
import api from '@/services/api';
import { notify } from '@/services/notify';
import Button from 'primevue/button';
import Drawer from 'primevue/drawer';
import Dialog from 'primevue/dialog';
import MobileMenu from '@/components/shared/MobileMenu.vue';
import ProductDetailsModal from '@/components/shared/ProductDetailsModal.vue';
import ComboWizardModal from '@/components/shared/ComboWizardModal.vue';
import TableAccountsModal from '@/components/pdv/TableAccountsModal.vue';
import SwapTableModal from '@/components/manager/SwapTableModal.vue';
import SessionTimeline from '@/components/shared/SessionTimeline.vue';

const router = useRouter();
const tablesStore = useTablesStore();
const sessionStore = useSessionStore();
const cartStore = useCartStore(); 
const store = cartStore; 

const headerSearch = ref('');
const isCartOpen = ref(false); 
const showHistory = ref(false); 
const itemToSwap = ref(null);    
const timelineEvents = ref([]); 
const swipedId = ref(null);
const touchStartX = ref(0);
const showAccountsModal = ref(false);
const showSwapModal = ref(false);
const detailsModal = ref(null);
const comboWizard = ref(null);

const currentTableId = computed(() => {
    const flatTables = tablesStore.tables;
    const t = flatTables.find(tbl => tbl.number == sessionStore.identifier);
    return t ? t.id : null;
});

const formatMoney = (val) => (val || 0).toFixed(2).replace('.', ',');
const totalAccount = computed(() => sessionStore.accountTotal(sessionStore.currentAccount));

onMounted(() => {
    if (!sessionStore.identifier) { router.replace('/staff/tables'); return; }
    tablesStore.fetchTables(); 
});

const onTouchStart = (e) => { touchStartX.value = e.changedTouches[0].screenX; };
const onTouchEnd = (e, id) => {
    const touchEndX = e.changedTouches[0].screenX;
    const diff = touchStartX.value - touchEndX;
    if (diff > 50) swipedId.value = id;
    else if (diff < -50) swipedId.value = null;
};
const handleItemClick = (item) => { if (swipedId.value === item.uniqueId) swipedId.value = null; };
const resetSwipe = () => { swipedId.value = null; };

const exitTable = async () => { sessionStore.leaveSession(); router.push('/staff/tables'); };
const onAccountSelected = () => { if (cartStore.items.length > 0 && confirm('Limpar carrinho?')) cartStore.items = []; showAccountsModal.value = false; };
const onViewReport = async () => { isCartOpen.value = true; showAccountsModal.value = false; };
const openHistory = () => { fetchHistory(); showHistory.value = true; };
const confirmClearCart = () => { if(confirm('Esvaziar carrinho?')) cartStore.items = []; };
const handleSendOrder = async () => { await cartStore.sendOrder(); notify('success', 'Enviado', 'Pedido enviado para a cozinha.'); };
const handleRequestBill = () => { if(confirm(`Solicitar fechamento?`)) notify('info', 'Solicitado', 'Impressão enviada.'); };

const handleOpenProduct = (product) => {
  if (itemToSwap.value) {
      if(!confirm(`Trocar ${itemToSwap.value.name}?`)) return;
      api.post('/swap-items', { original_item_id: itemToSwap.value.id, new_item: product, session_id: sessionStore.sessionId }).then(() => { notify('success', 'Solicitado', 'Aguardando gerente.'); cancelSwap(); sessionStore.refreshSession(); }).catch(e => notify('error', 'Erro', 'Falha na troca.'));
      return;
  }
  const addToCart = (item) => { cartStore.addItem(item); notify('success', 'Adicionado', `${item.name}`); };
  if (product.type === 'combo') comboWizard.value.open(product);
  else if (product.modifiers && product.modifiers.length > 0) detailsModal.value.open(product, addToCart);
  else addToCart({ ...product, qty: 1, price: parseFloat(product.price), modifiers: [] });
};

const cancelSwap = () => { itemToSwap.value = null; };
const fetchHistory = async () => {}; 
</script>

<style scoped>
.scrollbar-thin::-webkit-scrollbar { width: 4px; }
.scrollbar-thin::-webkit-scrollbar-thumb { background: #3f3f46; border-radius: 4px; }
.animate-slide-down { animation: slideDown 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes slideDown { from { transform: translateY(-100%); } to { transform: translateY(0); } }
</style>