<template>
  <div v-if="displayMode === 'inline'" class="flex flex-col h-full bg-surface-2 relative overflow-hidden rounded-[24px] border border-surface-3/10">
    <div class="p-5 border-b border-surface-3/10 bg-surface-2 flex justify-between items-center shrink-0 z-10">
        <div>
            <h3 class="font-black text-surface-0 text-xs uppercase tracking-wider flex items-center gap-2">
                <i class="fa-solid fa-cart-shopping text-primary-500"></i> Carrinho
            </h3>
            <p class="text-[10px] text-surface-400 font-bold uppercase tracking-widest mt-0.5">
                {{ store.totalItems }} itens
            </p>
        </div>
        <button v-if="store.items.length > 0" class="w-8 h-8 rounded-full bg-surface-3 hover:bg-red-500/20 text-surface-400 hover:text-red-400 flex items-center justify-center transition-colors" v-tooltip.left="'Limpar'" @click="confirmClearCart"><i class="fa-solid fa-trash-can text-xs"></i></button>
    </div>
    
    <div v-if="paymentStep === 'items'" class="flex-1 overflow-y-auto p-3 space-y-2 scrollbar-thin">
        <div v-if="store.items.length === 0" class="flex flex-col items-center justify-center h-full text-surface-600 opacity-40 select-none">
            <i class="fa-solid fa-basket-shopping text-4xl mb-3"></i><p class="text-xs font-bold uppercase tracking-widest text-center">Vazio</p>
        </div>
        <ul v-else class="space-y-2">
            <li v-for="(item, index) in store.items" :key="item.uniqueId" class="bg-surface-3 rounded-[20px] p-3 flex gap-3 relative overflow-hidden border border-surface-4/20 hover:border-surface-4/50 shadow-sm transition-all">
                <div class="flex-1 min-w-0 flex flex-col justify-center">
                    <div class="flex justify-between items-start mb-1">
                        <span class="font-black text-surface-100 text-sm leading-tight">{{ item.name }}</span>
                        <span class="font-black text-surface-0 text-xs ml-2 whitespace-nowrap">{{ formatCurrency(item.price * item.qty) }}</span>
                    </div>
                    <div v-if="item.modifiers?.length" class="text-[10px] text-surface-400 pl-2 border-l-2 border-surface-4 italic leading-tight font-medium mb-1">{{ item.modifiers.map(m => m.name).join(', ') }}</div>
                </div>
                <div class="flex flex-col items-end gap-1">
                    <div class="flex items-center bg-surface-1 rounded-full px-1 h-6 border border-surface-4/30">
                        <button class="w-6 h-full text-surface-400 hover:text-surface-0 flex items-center justify-center" @click="store.decreaseQty(index)"><i class="fa-solid fa-minus text-[8px]"></i></button>
                        <span class="text-xs font-black text-surface-0 w-4 text-center">{{ item.qty }}</span>
                        <button class="w-6 h-full text-surface-400 hover:text-surface-0 flex items-center justify-center" @click="store.increaseQty(index)"><i class="fa-solid fa-plus text-[8px]"></i></button>
                    </div>
                    <div class="flex gap-1">
                        <button class="w-6 h-6 rounded-full bg-surface-1 text-surface-400 hover:text-primary-400 flex items-center justify-center transition-colors" @click="handleEditItem(item, index)"><i class="fa-solid fa-pen text-[9px]"></i></button>
                        <button class="w-6 h-6 rounded-full bg-surface-1 text-surface-400 hover:text-red-400 flex items-center justify-center transition-colors" @click="store.items.splice(index, 1)"><i class="fa-solid fa-trash text-[9px]"></i></button>
                    </div>
                </div>
            </li>
        </ul>
    </div>
    
    <div v-else class="flex-1 flex flex-col bg-surface-2"><PaymentWidget :totalDue="grandTotal" mode="promise" :showFooter="false" :initialPayments="store.posPaymentPromise.payments || []" @update:payments="handlePosPaymentsUpdate" @validity-change="handlePosValidityChange" class="!border-none !bg-transparent flex-1" /></div>
    
    <div class="p-4 border-t border-surface-3/10 bg-surface-2 shrink-0 space-y-3 z-10 shadow-[0_-5px_15px_rgba(0,0,0,0.3)]">
        <div v-if="sessionStore.deliveryFee > 0" class="flex justify-between items-center px-1 text-xs text-surface-400 font-bold"><span>Taxa de Entrega</span><span>+ {{ formatCurrency(sessionStore.deliveryFee) }}</span></div>
        <div class="flex justify-between items-center px-1"><span class="text-[10px] text-surface-500 font-bold uppercase tracking-widest">Total</span><span class="text-xl font-black text-surface-0 tracking-tight">{{ formatCurrency(grandTotal) }}</span></div>
        
        <Button v-if="paymentStep === 'items' && isDeliveryOrPickup" label="PAGAMENTO" icon="fa-solid fa-arrow-right" iconPos="right" class="w-full h-12 !bg-surface-3 hover:!bg-primary-600 hover:!text-surface-950 !border-none !text-xs !uppercase !font-black !text-surface-300 shadow-lg !rounded-full transition-all" :disabled="store.items.length === 0" @click="paymentStep = 'payment'" />
        
        <Button 
            v-else 
            label="LANÇAR" 
            icon="fa-solid fa-paper-plane" 
            class="w-full h-12 !bg-primary-600 hover:!bg-primary-500 !border-none !text-xs !uppercase !font-black !text-surface-950 shadow-lg !rounded-full" 
            :loading="store.isSending" 
            :disabled="store.items.length === 0 || (isDeliveryOrPickup && !isPosPaymentValid)" 
            @click="handleLaunch" 
        />
    </div>
  </div>

  <Drawer 
    v-else
    v-model:visible="store.isOpen" 
    :position="displayMode === 'bottom' ? 'bottom' : 'right'" 
    :modal="true"
    :class="displayMode === 'bottom' ? '!h-[90vh] !border-none' : '!w-full md:!w-[450px] !border-none'"
    :pt="ptOptions"
  >
    <template #header>
        <div v-if="displayMode === 'bottom'" class="absolute top-3 left-1/2 -translate-x-1/2 w-12 h-1.5 bg-surface-3/50 rounded-full"></div>
        <div class="flex items-center gap-3 mt-2 w-full pr-8">
            <Button v-if="step === 'checkout'" icon="fa-solid fa-arrow-left" text rounded class="!w-8 !h-8 !text-surface-400 hover:!text-surface-0" @click="step = 'cart'" />
            <div class="flex items-center font-black text-xl text-surface-0">
                <i class="fa-solid fa-cart-shopping mr-3 text-primary-500"></i> 
                {{ step === 'cart' ? 'Meu Pedido' : 'Finalizar' }}
            </div>
        </div>
    </template>

    <div class="flex flex-col h-full bg-surface-1 relative overflow-hidden">
        
        <div class="flex-1 overflow-y-auto scrollbar-thin p-4 relative bg-surface-1" @click="resetSwipe">
            <transition name="slide-left" mode="out-in">
                
                <div v-if="step === 'cart'" key="cart-list" class="h-full pb-20">
                    <div v-if="store.items.length === 0" class="flex flex-col items-center justify-center h-64 text-surface-500/50">
                        <i class="fa-solid fa-cart-plus text-6xl mb-4"></i>
                        <span class="font-bold text-sm">Seu carrinho está vazio</span>
                        <Button label="Ver Cardápio" text class="mt-4 !text-primary-500 !font-black !text-sm" @click="store.isOpen = false" />
                    </div>

                    <ul v-else class="space-y-3">
                        <li v-for="(item, index) in store.items" :key="item.uniqueId" 
                            class="bg-surface-2 rounded-[24px] p-4 border border-surface-3/10 shadow-sm"
                        >
                            <div class="flex flex-col gap-3 w-full">
                                <div>
                                    <div class="font-black text-surface-0 text-base leading-tight">{{ item.name }}</div>
                                    <div v-if="item.modifiers?.length" class="text-[11px] text-surface-400 mt-1 italic leading-relaxed">
                                        {{ item.modifiers.map(m => m.name).join(', ') }}
                                    </div>
                                    <div v-if="item.notes" class="text-[10px] text-yellow-500/80 font-bold flex items-center gap-1 mt-1">
                                        <i class="fa-solid fa-pen"></i> {{ item.notes }}
                                    </div>
                                </div>
                                <div class="flex justify-between items-end">
                                    <span class="font-black text-surface-0 text-lg tracking-tight">
                                        {{ formatCurrency(item.price * item.qty) }}
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <div class="flex items-center bg-surface-950/30 rounded-full h-9 px-1 border border-surface-3/20 shadow-inner">
                                            <button class="w-8 h-full flex items-center justify-center text-surface-400 hover:text-surface-200 active:scale-90 transition-transform" @click="store.decreaseQty(index)"><i class="fa-solid fa-minus text-[10px] font-black"></i></button>
                                            <span class="font-black text-sm text-surface-0 w-6 text-center select-none">{{ item.qty }}</span>
                                            <button class="w-8 h-full flex items-center justify-center text-primary-400 hover:text-primary-300 active:scale-90 transition-transform" @click="store.increaseQty(index)"><i class="fa-solid fa-plus text-[10px] font-black"></i></button>
                                        </div>
                                        <button class="w-9 h-9 rounded-full bg-surface-3 text-surface-400 hover:text-white hover:bg-surface-4 flex items-center justify-center transition-colors active:scale-90" @click.stop="handleEditItem(item, index)"><i class="fa-solid fa-pen text-xs"></i></button>
                                        <button class="w-9 h-9 rounded-full bg-surface-3 text-surface-400 hover:text-red-400 hover:bg-red-500/10 flex items-center justify-center transition-colors active:scale-90" @click.stop="store.items.splice(index, 1)"><i class="fa-solid fa-trash text-xs"></i></button>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <div v-else key="checkout-form" class="space-y-6 pb-4">
                    <div class="bg-surface-2 p-1.5 rounded-[20px] border border-surface-3/10 flex relative shrink-0">
                        <div class="absolute top-1.5 bottom-1.5 w-[calc(50%-6px)] bg-surface-3 rounded-[16px] shadow-sm transition-all duration-300" :class="isPickup ? 'translate-x-[calc(100%+6px)]' : 'translate-x-0'"></div>
                        <button class="flex-1 py-3 text-sm font-black relative z-10 transition-colors uppercase tracking-wide" :class="!isPickup ? 'text-surface-0' : 'text-surface-400'" @click="isPickup = false"><i class="fa-solid fa-motorcycle mr-2"></i> Entrega</button>
                        <button class="flex-1 py-3 text-sm font-black relative z-10 transition-colors uppercase tracking-wide" :class="isPickup ? 'text-surface-0' : 'text-surface-400'" @click="isPickup = true"><i class="fa-solid fa-bag-shopping mr-2"></i> Retirada</button>
                    </div>

                    <div v-if="!isPickup" class="space-y-4">
                        <SmartAddressForm v-model="checkoutForm.address" />
                        
                        <div v-if="deliveryStore.freeShippingThreshold > 0 && store.checkoutForm.address" class="p-4 bg-surface-2 rounded-2xl border border-surface-3/10 shadow-sm animate-fade-in">
                            <div class="flex justify-between items-end mb-1.5">
                                <span class="text-[10px] font-black uppercase tracking-widest" :class="isFreeShipping ? 'text-green-400' : 'text-surface-400'">
                                    {{ isFreeShipping ? 'Frete Grátis Conquistado! 🎉' : `Faltam ${formatCurrency(missingForFreeShipping)} para frete grátis` }}
                                </span>
                                <span class="text-[9px] font-bold text-surface-500">{{ Math.min(shippingProgress, 100).toFixed(0) }}%</span>
                            </div>
                            <div class="w-full h-2 bg-surface-3 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-primary-600 to-primary-400 transition-all duration-500 rounded-full" :style="{ width: `${shippingProgress}%` }"></div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-surface-3/10">
                        <h3 class="text-xs font-black text-surface-400 uppercase tracking-widest mb-3">Pagamento</h3>
                        <PaymentWidget :totalDue="finalTotal" mode="promise" :showFooter="false" @update:payments="handleClientPaymentsUpdate" @validity-change="handleClientValidityChange" class="!bg-surface-2 !border-none" />
                    </div>

                    <div class="space-y-2 pt-2">
                        <h3 class="text-xs font-black text-surface-400 uppercase tracking-widest">Observações</h3>
                        <Textarea v-model="checkoutForm.notes" rows="2" placeholder="Ex: Tocar campainha, tirar cebola..." class="!bg-surface-2 !border-none !text-surface-0 w-full !text-sm !rounded-[16px] p-3" />
                    </div>
                </div>
            </transition>
        </div>
        
        <div class="bg-surface-1 border-t border-surface-3/10 p-6 shrink-0 z-20 shadow-[0_-5px_30px_rgba(0,0,0,0.5)]">
            
            <div class="flex justify-between items-end mb-4 px-1">
                <div class="flex flex-col">
                    <span class="text-[10px] text-surface-400 font-bold uppercase tracking-widest">Total</span>
                    <span class="text-xs text-surface-500" v-if="!isPickup && !isFreeShipping && store.checkoutForm.deliveryFee > 0">
                        (+ {{ formatCurrency(store.checkoutForm.deliveryFee) }} taxa)
                    </span>
                </div>
                <span class="text-3xl font-black text-surface-0 tracking-tight leading-none">{{ formatCurrency(finalTotal) }}</span>
            </div>

            <transition name="fade" mode="out-in">
                
                <div v-if="userStore.isLoggedIn" key="logged-btn" class="w-full">
                    <Button 
                        :label="mainButtonLabel" 
                        :icon="mainButtonIcon" 
                        class="w-full h-14 font-black text-lg !border-none !text-surface-950 shadow-lg !rounded-full transition-all active:scale-[0.98]" 
                        :class="canProceed ? '!bg-primary-500 hover:!bg-primary-400' : '!bg-surface-2 !text-surface-500 cursor-not-allowed'" 
                        :loading="store.isSending" 
                        :disabled="!canProceed" 
                        @click="handleMainAction" 
                    />
                </div>

                <div v-else key="guest-btn" class="flex flex-col gap-3 w-full">
                    <div class="bg-primary-500/10 border border-primary-500/20 p-3 rounded-2xl flex gap-3 items-center animate-fade-in">
                        <i class="fa-solid fa-circle-info text-primary-500 text-sm"></i>
                        <p class="text-xs text-primary-200 font-bold leading-tight">
                            Identifique-se para finalizar seu pedido.
                        </p>
                    </div>

                    <GoogleLoginBtn 
                        label="Entrar e Continuar" 
                        class="w-full shadow-lg shadow-primary-900/20"
                        @success="handleLoginSuccess" 
                    />
                </div>

            </transition>
        </div>


    </div>
    
    <AddressChangeModal v-model:visible="showAddressModal" :client-id="userStore.user?.id" @address-selected="handleAddressSelected" />
    <ProductDetailsModal ref="detailsModal" displayMode="bottom" :show-takeout="true" />
    <ComboWizardModal ref="comboWizard" displayMode="bottom" :show-takeout="true" />
    
  </Drawer>
</template>

<script setup>
import { ref, computed, reactive, watch, onMounted } from 'vue';
import { useCartStore } from '@/stores/cart-store';
import { useSessionStore } from '@/stores/session-store'; 
import { useUserStore } from '@/stores/user-store';
import { useDeliveryStore } from '@/stores/delivery-store'; 
import { useFormat } from '@/composables/useFormat'; 
import { useMenuStore } from '@/stores/menu-store'; 
import { useMobile } from '@/composables/useMobile'; // Importado para uso no ptOptions

import SmartAddressForm from './SmartAddressForm.vue';
import PaymentWidget from '@/components/shared/PaymentWidget.vue';
import AddressChangeModal from '@/components/delivery/AddressChangeModal.vue'; 
import ProductDetailsModal from '@/components/shared/ProductDetailsModal.vue';
import ComboWizardModal from '@/components/shared/ComboWizardModal.vue';

import Drawer from 'primevue/drawer';
import Button from 'primevue/button';
import Textarea from 'primevue/textarea';
import { notify } from '@/services/notify';
import GoogleLoginBtn from '@/components/shared/GoogleLoginBtn.vue';

const props = defineProps({ displayMode: { type: String, default: 'side' } });
// Adicionado evento require-identification
const emit = defineEmits(['require-identification']);

const store = useCartStore();
const sessionStore = useSessionStore();
const userStore = useUserStore();
const deliveryStore = useDeliveryStore();
const menuStore = useMenuStore();
const checkoutForm = reactive(store.checkoutForm);
const { formatCurrency } = useFormat();
const { isMobile } = useMobile(); // Hook para detectar mobile

const step = ref('cart');
const paymentStep = ref('items');
const isPickup = ref(false);
const showAddressModal = ref(false);
const detailsModal = ref(null);
const comboWizard = ref(null);
const isClientPaymentValid = ref(false);
const clientPayments = ref([]);
const isPosPaymentValid = ref(false);

const isDeliveryOrPickup = computed(() => ['delivery', 'pickup'].includes(sessionStore.sessionType));
const grandTotal = computed(() => store.totalValue + (sessionStore.deliveryFee || 0));
const finalTotal = computed(() => store.totalValue + (isPickup.value ? 0 : (store.checkoutForm.deliveryFee || 0)));

// --- NOVA LÓGICA DE LANÇAMENTO ---
const handleLaunch = () => {
    // 1. Verifica Balcão + Conta Genérica (Principal)
    // Se for balcão e ainda não tiver nome, exige identificação
    if (sessionStore.sessionType === 'counter' && sessionStore.currentAccount === 'Principal') {
        notify('warn', 'Identificação Necessária', 'Informe o cliente ou comanda para continuar.');
        emit('require-identification'); // Emite para o PosInterface abrir o modal
        return;
    }
    
    // 2. Se já estiver identificado (ou for Mesa/Delivery que já tratam isso antes), envia.
    store.sendOrder();
};

// ... Lógica existente ...

const formattedAddresses = computed(() => {
    if (!userStore.user?.addresses) return [];
    const noNickList = userStore.user.addresses.filter(a => !a.nickname);
    let namelessCounter = 1;
    return userStore.user.addresses.map(addr => {
        const displayAddr = { ...addr };
        if (displayAddr.nickname) {
            displayAddr.displayLabel = displayAddr.nickname;
        } else {
            if (noNickList.length > 1) {
                displayAddr.displayLabel = `Casa ${namelessCounter}`;
                if (!addr.nickname) namelessCounter++;
            } else {
                displayAddr.displayLabel = 'Casa';
            }
        }
        return displayAddr;
    });
});

const getDisplayLabel = (addr) => {
    const found = formattedAddresses.value.find(a => a.id === addr.id);
    return found ? found.displayLabel : (addr.nickname || addr.street);
};

const handleLoginSuccess = () => {
    if (step.value === 'cart') {
        step.value = 'checkout';
    }
};

const shippingProgress = computed(() => {
    const threshold = deliveryStore.freeShippingThreshold || 0;
    if (threshold <= 0) return 0;
    return (store.totalValue / threshold) * 100;
});

const isFreeShipping = computed(() => {
    const threshold = deliveryStore.freeShippingThreshold || 0;
    return threshold > 0 && store.totalValue >= threshold;
});

const missingForFreeShipping = computed(() => {
    return (deliveryStore.freeShippingThreshold || 0) - store.totalValue;
});

const handleAddressSelected = (address) => {
    store.checkoutForm.address = address;
    showAddressModal.value = false;
    calculateFreightForCart();
    notify('success', 'Endereço', `Entregar em: ${getDisplayLabel(address)}`);
};

const calculateFreightForCart = async () => {
    const address = store.checkoutForm.address;
    if (address && address.district) {
         const bairro = deliveryStore.availableBairros.find(b => b.name === address.district);
         if (bairro) {
             store.checkoutForm.deliveryFee = parseFloat(bairro.fee);
             deliveryStore.freeShippingThreshold = parseFloat(bairro.free_above);
         } else {
             store.checkoutForm.deliveryFee = 0; 
         }
    }
};

const handleEditItem = async (item, index) => {
    if (!item.id && !item.product_id) return;
    const prodId = item.id || item.product_id;
    let product = null;
    for (const cat of menuStore.categories) {
        if (cat.products) { const p = cat.products.find(x => x.id == prodId); if (p) { product = p; break; } }
        if (cat.children) { for (const child of cat.children) { const p = child.products.find(x => x.id == prodId); if (p) { product = p; break; } } }
        if (product) break;
    }
    if (!product) { notify('warn', 'Ops', 'Produto indisponível.'); return; }
    const onSave = (updatedItem) => { store.updateItem(index, updatedItem); };
    if (product.type === 'combo') { comboWizard.value.open(product, index, item.savedSelections, onSave); } 
    else { detailsModal.value.open(product, onSave, index, item.savedSelections); }
};

const handlePosPaymentsUpdate = (payments) => { store.posPaymentPromise.payments = payments; };
const handlePosValidityChange = (isValid) => { isPosPaymentValid.value = isValid; };
const handleClientPaymentsUpdate = (payments) => { clientPayments.value = payments; };
const handleClientValidityChange = (isValid) => { isClientPaymentValid.value = isValid; };
const confirmClearCart = () => { if (confirm('Deseja limpar todos os itens do carrinho?')) { store.items = []; paymentStep.value = 'items'; } };
const resetSwipe = () => {}; 

const mainButtonLabel = computed(() => step.value === 'cart' ? 'CONTINUAR' : 'ENVIAR PEDIDO');
const mainButtonIcon = computed(() => step.value === 'cart' ? 'fa-solid fa-arrow-right' : 'fa-solid fa-check');
const canProceed = computed(() => {
    if (store.items.length === 0) return false;
    if (step.value === 'checkout') {
        if (!isPickup.value) { if (!checkoutForm.address || !checkoutForm.address.street) return false; }
        return isClientPaymentValid.value;
    }
    return true;
});

const handleMainAction = async () => {
    if (step.value === 'cart') {
        if (!userStore.isLoggedIn) { notify('info', 'Identifique-se', 'Acesse sua conta para continuar.'); return; }
        step.value = 'checkout';
    } else {
        checkoutForm.isPickup = isPickup.value;
        checkoutForm.payments = clientPayments.value; 
        await store.checkoutWeb(); 
        if (store.items.length === 0) { step.value = 'cart'; isPickup.value = false; clientPayments.value = []; }
    }
};

watch(() => store.items.length, (newVal) => { if (newVal === 0) paymentStep.value = 'items'; });
watch(() => store.isOpen, (isOpen) => { if (!isOpen) step.value = 'cart'; });

onMounted(() => {
    deliveryStore.fetchBairros();
    if (userStore.isLoggedIn && userStore.user?.addresses?.length > 0 && !store.checkoutForm.address) {
        const primary = userStore.user.addresses.find(a => a.is_primary) || userStore.user.addresses[0];
        store.checkoutForm.address = primary;
        calculateFreightForCart();
    }
});

watch(() => store.checkoutForm.address, () => { calculateFreightForCart(); }, { deep: true });

const ptOptions = computed(() => {
    const common = {
        header: { class: '!bg-surface-1 !border-b !border-surface-3/10 !px-6 !py-4 relative !z-20' },
        content: { class: '!bg-surface-1 !p-0' },
        closeButton: { class: '!text-surface-400 hover:!text-surface-0 hover:!bg-surface-3 !absolute !right-4 !top-4 !rounded-full !w-8 !h-8' },
        mask: { class: '!bg-surface-base/80 backdrop-blur-sm' }
    };
    // Prioriza o prop displayMode para definir o estilo
    if (props.displayMode === 'bottom') {
        return { ...common, root: { class: '!bg-surface-1 !border-t !border-surface-3/10 !rounded-t-[32px] !shadow-2xl !overflow-hidden !h-[90vh]' } };
    } else {
        return { ...common, root: { class: '!bg-surface-1 !border-l !border-surface-3/10 w-full md:w-[450px] !shadow-2xl !overflow-hidden' } };
    }
});
</script>

<style scoped>
.slide-left-enter-active, .slide-left-leave-active { transition: all 0.3s ease; }
.slide-left-enter-from { opacity: 0; transform: translateX(20px); }
.slide-left-leave-to { opacity: 0; transform: translateX(-20px); }
.animate-fade-in { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>