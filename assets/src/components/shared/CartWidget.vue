<template>
  <div v-if="displayMode === 'inline'" class="flex flex-col h-full bg-surface-900 relative overflow-hidden">
    <div class="p-4 border-b border-surface-800 bg-surface-950 flex justify-between items-center shrink-0">
        <div class="flex items-center font-bold text-lg text-white uppercase tracking-tighter">
            <i class="fa-solid fa-cart-shopping mr-3 text-primary-500"></i> Carrinho
        </div>
        <Button 
            v-if="store.items.length > 0"
            icon="fa-solid fa-trash-can" 
            text rounded 
            class="!text-red-400 !w-8 !h-8 hover:!bg-red-500/10" 
            @click="confirmClearCart" 
            v-tooltip.left="'Limpar'"
        />
    </div>
    
    <div class="flex-1 overflow-y-auto scrollbar-thin p-2">
        <div v-if="store.items.length === 0" class="flex flex-col items-center justify-center h-full text-surface-600 opacity-40">
            <i class="fa-solid fa-clipboard-list text-4xl mb-3"></i>
            <p class="text-xs font-bold uppercase tracking-widest text-center">Nenhum item<br>selecionado</p>
        </div>
        <ul v-else class="space-y-1">
            <li v-for="(item, index) in store.items" :key="item.uniqueId" 
                class="bg-surface-950/50 border border-surface-800/50 rounded-lg p-2.5 flex items-center gap-3 group transition-all hover:border-surface-700">
                
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="bg-surface-800 text-surface-400 font-mono text-[10px] font-bold px-1.5 py-0.5 rounded border border-surface-700">
                            {{ item.qty }}x
                        </span>
                        <span class="font-bold text-surface-200 text-sm leading-none truncate">{{ item.name }}</span>
                    </div>
                    
                    <div v-if="item.modifiers?.length" class="text-[9px] text-surface-500 mt-1 pl-1 border-l border-surface-800 ml-1 italic truncate">
                        {{ item.modifiers.map(m => m.name).join(', ') }}
                    </div>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <div class="font-bold text-primary-400 text-xs font-mono">R$ {{ formatMoney(item.price * item.qty) }}</div>
                    
                    <div class="flex items-center bg-surface-900 rounded border border-surface-800 overflow-hidden shadow-inner">
                        <button class="w-6 h-6 text-surface-500 hover:text-white hover:bg-surface-800 transition-colors flex items-center justify-center text-xs" @click="store.decreaseQty(index)">-</button>
                        <button class="w-6 h-6 text-surface-500 hover:text-white hover:bg-surface-800 transition-colors flex items-center justify-center text-xs" @click="store.increaseQty(index)">+</button>
                    </div>
                </div>
            </li>
        </ul>
    </div>

    <div class="p-4 border-t border-surface-800 bg-surface-950 shrink-0 space-y-3 shadow-[0_-10px_20px_rgba(0,0,0,0.2)]">
        <div class="flex justify-between items-center px-1">
            <span class="text-[10px] text-surface-500 font-bold uppercase tracking-widest">Subtotal</span>
            <span class="text-xl font-black text-white tracking-tight">R$ {{ formatMoney(store.totalValue) }}</span>
        </div>
        <Button 
            label="LANÇAR PEDIDO" 
            icon="fa-solid fa-paper-plane" 
            class="w-full h-12 !bg-primary-600 hover:!bg-primary-500 !border-none !text-base !font-black !text-surface-950 shadow-lg shadow-primary-900/20" 
            :loading="store.isSending"
            :disabled="store.items.length === 0"
            @click="store.sendOrder()"
        />
    </div>
  </div>

  <Drawer 
    v-else
    v-model:visible="store.isOpen" 
    :position="displayMode === 'bottom' ? 'bottom' : 'right'" 
    :modal="true"
    :class="displayMode === 'bottom' ? '!h-[90vh]' : '!w-full md:!w-[450px]'"
    :pt="ptOptions"
  >
    <template #header>
        <div v-if="displayMode === 'bottom'" class="absolute top-3 left-1/2 -translate-x-1/2 w-12 h-1.5 bg-surface-700 rounded-full"></div>
        <div class="flex items-center gap-3 mt-2 w-full pr-8">
            <Button v-if="step === 'checkout'" icon="fa-solid fa-arrow-left" text rounded class="!w-8 !h-8 !text-surface-300 hover:!text-white" @click="step = 'cart'" />
            <div class="flex items-center font-bold text-xl text-white">
                <i class="fa-solid fa-cart-shopping mr-3 text-primary-500"></i> 
                {{ step === 'cart' ? 'Seu Carrinho' : 'Finalizar Pedido' }}
            </div>
        </div>
    </template>

    <div class="flex flex-col h-full bg-surface-900 relative overflow-hidden">
        <div class="flex-1 overflow-y-auto scrollbar-thin p-2 relative" @click="resetSwipe">
            <transition name="slide-left" mode="out-in">
                
                <div v-if="step === 'cart'" key="cart-list" class="h-full">
                    <div v-if="store.items.length === 0" class="flex flex-col items-center justify-center h-64 text-surface-400">
                        <i class="fa-solid fa-cart-shopping text-6xl mb-4 opacity-20"></i>
                        <span>Seu carrinho está vazio</span>
                        <Button label="Ver Cardápio" text class="mt-4 !text-primary-500" @click="store.isOpen = false" />
                    </div>

                    <ul v-else class="space-y-3 pb-4 px-1">
                        <li v-for="(item, index) in store.items" :key="item.uniqueId" class="relative rounded-xl overflow-hidden group">
                            <div class="absolute inset-0 bg-red-600 flex items-center justify-end pr-5 rounded-xl border-2 border-surface-900">
                                <i class="fa-solid fa-trash-can text-white text-xl"></i>
                            </div>
                            <button class="absolute inset-y-0 right-0 w-20 z-0" @click.stop="store.items.splice(index, 1); swipedId = null;"></button>

                            <div 
                                class="relative z-10 bg-surface-900 border-2 border-surface-900 rounded-xl p-3 flex gap-3 transition-transform duration-300 shadow-sm"
                                :class="{'!-translate-x-20': swipedId === item.uniqueId}"
                                @touchstart="onTouchStart($event)"
                                @touchend="onTouchEnd($event, item.uniqueId)"
                                @click="handleItemClick(item)"
                            >
                                <div class="w-16 h-16 bg-surface-800 rounded-lg shrink-0 overflow-hidden border border-surface-700">
                                    <img v-if="item.image" :src="item.image" class="w-full h-full object-cover">
                                </div>
                                <div class="flex-1 min-w-0 flex flex-col justify-center">
                                    <div class="font-bold text-white text-sm leading-tight line-clamp-2">{{ item.name }}</div>
                                    <div class="text-primary-400 font-bold font-mono text-xs mt-1">R$ {{ formatMoney(item.price * item.qty) }}</div>
                                </div>
                                <div class="flex items-center bg-surface-950 rounded-lg border border-surface-700 h-8 self-center overflow-hidden">
                                    <button class="w-8 h-full text-surface-400 hover:text-white" @click="store.decreaseQty(index)">-</button>
                                    <span class="text-xs font-bold text-white px-2">{{ item.qty }}</span>
                                    <button class="w-8 h-full text-surface-400 hover:text-white" @click="store.increaseQty(index)">+</button>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <div v-else key="checkout-form" class="space-y-6 p-2 pb-4">
                    <div class="bg-surface-950 p-1 rounded-xl border border-surface-800 flex relative">
                        <div class="absolute top-1 bottom-1 w-1/2 bg-surface-800 rounded-lg shadow transition-all duration-300" :class="isPickup ? 'translate-x-full' : 'translate-x-0'"></div>
                        <button class="flex-1 py-2 text-sm font-bold relative z-10 transition-colors" :class="!isPickup ? 'text-white' : 'text-surface-400'" @click="isPickup = false">Entrega</button>
                        <button class="flex-1 py-2 text-sm font-bold relative z-10 transition-colors" :class="isPickup ? 'text-white' : 'text-surface-400'" @click="isPickup = true">Retirada</button>
                    </div>
                    <div v-if="!isPickup" class="animate-fade-in space-y-3">
                        <SmartAddressForm v-model="checkoutForm.address" />
                    </div>
                    </div>
            </transition>
        </div>

        <div class="bg-surface-900 border-t border-surface-800 p-6 shrink-0 z-20">
            <div class="flex justify-between items-end mb-4">
                <span class="text-xs text-surface-400 font-bold uppercase tracking-widest">Total</span>
                <span class="text-2xl font-black text-white tracking-tight">R$ {{ formatMoney(finalTotal) }}</span>
            </div>
            <Button 
                :label="mainButtonLabel" 
                class="w-full h-14 font-black text-lg !border-none !text-surface-950 shadow-lg" 
                :class="canProceed ? '!bg-primary-500' : '!bg-surface-700 !text-surface-500'"
                :loading="store.isSending"
                @click="handleMainAction"
            />
        </div>
    </div>
  </Drawer>
</template>

<script setup>
import { ref, computed, reactive, watch } from 'vue';
import { useCartStore } from '@/stores/cart-store';
import { useUserStore } from '@/stores/user-store';
import SmartAddressForm from './SmartAddressForm.vue';
import Drawer from 'primevue/drawer';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import ToggleSwitch from 'primevue/toggleswitch';
import api from '@/services/api';
import { notify } from '@/services/notify';

const props = defineProps({ 
    displayMode: { type: String, default: 'side' } 
});

const store = useCartStore();
const userStore = useUserStore();

// Lógica de Estado e Swipe (Mantida do anterior)
const step = ref('cart');
const isPickup = ref(false);
const deliveryFee = ref(0);
const isNoChange = ref(false);
const isLoadingMethods = ref(false);
const paymentCategories = ref([]);
const selectedMethodType = ref(null);
const showNewAddressForm = ref(false);
const selectedAddressIndex = ref(null);
const swipedId = ref(null);
const touchStartX = ref(0);

const checkoutForm = reactive(store.checkoutForm);
const savedAddresses = computed(() => userStore.user?.addresses || []);
const finalTotal = computed(() => store.totalValue + (isPickup.value ? 0 : deliveryFee.value));
const mainButtonLabel = computed(() => step.value === 'cart' ? 'CONTINUAR' : 'ENVIAR PEDIDO');
const mainButtonIcon = computed(() => step.value === 'cart' ? 'fa-solid fa-arrow-right' : 'fa-solid fa-check');

const formatMoney = (val) => parseFloat(val || 0).toFixed(2).replace('.', ',');

const confirmClearCart = () => {
    if (confirm('Deseja limpar todos os itens do carrinho?')) store.items = [];
};

const onTouchStart = (e) => touchStartX.value = e.changedTouches[0].screenX;
const onTouchEnd = (e, id) => {
    const diff = touchStartX.value - e.changedTouches[0].screenX;
    if (diff > 50) swipedId.value = id;
    else if (diff < -50) swipedId.value = null;
};
const resetSwipe = () => swipedId.value = null;
const handleItemClick = (item) => { if (swipedId.value === item.uniqueId) swipedId.value = null; };

const selectPaymentMethod = (method) => {
    checkoutForm.paymentMethodId = method.id;
    checkoutForm.paymentMethod = method.type; 
    selectedMethodType.value = method.type;
};

const handleNoChangeToggle = () => checkoutForm.changeFor = isNoChange.value ? 0 : null;

const canProceed = computed(() => {
    if (store.items.length === 0) return false;
    if (step.value === 'checkout') {
        if (!isPickup && !checkoutForm.address) return false;
        if (!checkoutForm.paymentMethodId) return false;
    }
    return true;
});

const handleMainAction = async () => {
    if (step.value === 'cart') {
        if (!userStore.isLoggedIn) { notify('info', 'Identifique-se', 'Acesse sua conta para continuar.'); return; }
        step.value = 'checkout';
    } else {
        checkoutForm.isPickup = isPickup.value;
        await store.checkoutWeb();
    }
};

const ptOptions = computed(() => ({
    header: { class: '!bg-surface-900 !border-b !border-surface-800 !px-6 !py-4 relative' },
    content: { class: '!bg-surface-900 !p-0' },
    closeButton: { class: '!text-surface-300 hover:!text-white hover:!bg-surface-800 !absolute !right-4 !top-4' },
    mask: { class: '!bg-black/80 backdrop-blur-sm' },
    root: { class: props.displayMode === 'bottom' ? '!bg-surface-900 !border-t !border-surface-700 !rounded-t-[2.5rem]' : '!bg-surface-900 !border-l !border-surface-800 w-full md:w-[450px]' }
}));
</script>