<template>
  <Drawer 
    v-model:visible="store.isOpen" 
    :position="displayMode === 'bottom' ? 'bottom' : 'right'" 
    :modal="true"
    :class="displayMode === 'bottom' ? '!h-[90vh]' : '!w-full md:!w-[450px]'"
    :pt="ptOptions"
  >
    <template #header>
        <div v-if="displayMode === 'bottom'" class="absolute top-3 left-1/2 -translate-x-1/2 w-12 h-1.5 bg-surface-700 rounded-full"></div>
        <div class="flex items-center gap-3 mt-2 w-full pr-8">
            <Button 
                v-if="step === 'checkout'" 
                icon="pi fa-solid fa-arrow-left" 
                text 
                rounded 
                class="!w-8 !h-8 !text-surface-300 hover:!text-white" 
                @click="step = 'cart'" 
            />
            
            <div class="flex items-center font-bold text-xl text-white">
                <i class="pi fa-solid fa-cart-shopping mr-3 text-primary-500"></i> 
                {{ step === 'cart' ? 'Seu Carrinho' : 'Finalizar Pedido' }}
            </div>
        </div>
    </template>

    <div class="flex flex-col h-full bg-surface-900 relative overflow-hidden">
        
        <div class="flex-1 overflow-y-auto scrollbar-thin p-2 relative">
            
            <transition name="slide-left" mode="out-in">
                <div v-if="step === 'cart'" key="cart-list" class="h-full">
                    
                    <div v-if="store.items.length === 0" class="flex flex-col items-center justify-center h-64 text-surface-400">
                        <i class="pi fa-solid fa-cart-shopping text-6xl mb-4 opacity-20"></i>
                        <span>Seu carrinho está vazio</span>
                        <Button label="Ver Cardápio" text class="mt-4 !text-primary-500" @click="store.isOpen = false" />
                    </div>

                    <ul v-else class="space-y-1 pb-4">
                        <li v-for="(item, index) in store.items" :key="item.uniqueId" 
                            class="p-4 rounded-xl bg-surface-900 border border-surface-800 flex items-start gap-4 relative overflow-hidden group"
                        >
                            <div class="w-16 h-16 bg-surface-800 rounded-lg flex items-center justify-center shrink-0 overflow-hidden border border-surface-700">
                                <img v-if="item.image" :src="item.image" class="w-full h-full object-cover">
                                <i v-else class="pi pi-box text-2xl text-surface-500"></i>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start">
                                    <div class="font-bold text-white text-base leading-tight mb-1">{{ item.name }}</div>
                                    <Button icon="pi fa-solid fa-trash" text rounded class="!w-6 !h-6 !text-surface-500 hover:!text-red-400 -mt-1 -mr-2" @click="store.items.splice(index, 1)" />
                                </div>
                                
                                <div v-if="item.modifiers?.length" class="text-xs text-surface-400 mb-2 leading-snug">
                                    <span v-for="(mod, mIdx) in item.modifiers" :key="mIdx" class="block">
                                        + {{ mod.name }}
                                    </span>
                                </div>

                                <div class="flex justify-between items-end mt-2">
                                    <div class="text-primary-500 font-bold font-mono">R$ {{ formatMoney(item.price * item.qty) }}</div>
                                    <div class="flex items-center bg-surface-950 rounded-lg border border-surface-700">
                                        <button class="w-8 h-7 flex items-center justify-center text-surface-400 hover:text-white hover:bg-surface-800 rounded-l-lg transition-colors" @click="store.decreaseQty(index)">
                                            <i class="pi fa-solid fa-minus text-[10px]"></i>
                                        </button>
                                        <span class="font-bold w-6 text-center text-white text-sm">{{ item.qty }}</span>
                                        <button class="w-8 h-7 flex items-center justify-center text-surface-400 hover:text-white hover:bg-surface-800 rounded-r-lg transition-colors" @click="store.increaseQty(index)">
                                            <i class="pi fa-solid fa-plus text-[10px]"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <div v-else key="checkout-form" class="space-y-6 p-2 pb-4">
                    
                    <div class="bg-surface-950 p-1 rounded-xl border border-surface-800 flex relative">
                        <div class="absolute top-1 bottom-1 w-1/2 bg-surface-800 rounded-lg shadow transition-all duration-300" :class="isPickup ? 'translate-x-full' : 'translate-x-0'"></div>
                        <button class="flex-1 py-2 text-sm font-bold relative z-10 transition-colors" :class="!isPickup ? 'text-white' : 'text-surface-400'" @click="isPickup = false">
                            <i class="fa-solid fa-motorcycle mr-2"></i> Entrega
                        </button>
                        <button class="flex-1 py-2 text-sm font-bold relative z-10 transition-colors" :class="isPickup ? 'text-white' : 'text-surface-400'" @click="isPickup = true">
                            <i class="fa-solid fa-bag-shopping mr-2"></i> Retirada
                        </button>
                    </div>

                    <div v-if="!isPickup" class="animate-fade-in space-y-3">
                        <div class="flex justify-between items-center">
                            <label class="text-xs font-bold text-surface-400 uppercase tracking-wider">Onde vamos entregar?</label>
                        </div>

                        <div v-if="savedAddresses.length > 0 && !showNewAddressForm" class="space-y-2">
                            <div v-for="(addr, idx) in savedAddresses" :key="idx" 
                                 class="p-4 rounded-xl border cursor-pointer transition-all flex items-center gap-3 group"
                                 :class="selectedAddressIndex === idx ? 'bg-primary-500/10 border-primary-500' : 'bg-surface-950/50 border-surface-800 hover:border-surface-600'"
                                 @click="selectSavedAddress(idx)"
                            >
                                <div class="w-8 h-8 rounded-full flex items-center justify-center" :class="selectedAddressIndex === idx ? 'bg-primary-500 text-surface-900' : 'bg-surface-800 text-surface-400'">
                                    <i class="fa-solid fa-house"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-sm font-bold text-white">{{ addr.street }}, {{ addr.number }}</div>
                                    <div class="text-xs text-surface-400">{{ addr.district }}</div>
                                </div>
                                <i v-if="selectedAddressIndex === idx" class="fa-solid fa-check text-primary-500"></i>
                            </div>
                        </div>

                        <button 
                            v-if="!showNewAddressForm"
                            class="w-full py-3 border border-dashed border-surface-700 rounded-xl text-surface-400 hover:text-white hover:border-surface-500 hover:bg-surface-800/50 transition-all text-sm font-bold flex items-center justify-center gap-2"
                            @click="showNewAddressForm = true"
                        >
                            <i class="fa-solid fa-plus"></i> Usar outro endereço
                        </button>

                        <div v-else class="bg-surface-950/50 border border-surface-800 rounded-2xl p-4 animate-fade-in relative">
                            <button class="absolute top-2 right-2 text-surface-500 hover:text-white p-2" @click="cancelNewAddress">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                            <div class="mb-4 text-xs font-bold text-surface-500 uppercase tracking-widest">Novo Endereço</div>
                            <SmartAddressForm v-model="checkoutForm.address" />
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-surface-400 uppercase tracking-wider mb-2 block">Pagamento</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button v-for="method in paymentMethods" :key="method.id"
                                class="flex flex-col items-center justify-center p-3 rounded-xl border transition-all gap-2"
                                :class="checkoutForm.paymentMethod === method.id 
                                    ? 'bg-primary-500/10 border-primary-500 text-primary-500' 
                                    : 'bg-surface-950 border-surface-800 text-surface-400 hover:border-surface-600'"
                                @click="checkoutForm.paymentMethod = method.id"
                            >
                                <i :class="method.icon" class="text-xl"></i>
                                <span class="text-xs font-bold">{{ method.label }}</span>
                            </button>
                        </div>

                        <div v-if="checkoutForm.paymentMethod === 'dinheiro'" class="mt-4 bg-surface-950 p-4 rounded-xl border border-surface-800 animate-fade-in">
                            <div class="flex justify-between items-center mb-3">
                                <label class="text-xs text-surface-400 font-bold uppercase">Troco para quanto?</label>
                                
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] uppercase font-bold transition-colors" :class="isNoChange ? 'text-primary-500' : 'text-surface-500'">Sem troco</span>
                                    <ToggleSwitch v-model="isNoChange" @change="handleNoChangeToggle" :pt="{ root: { class: '!w-8 !h-4' }, slider: { class: '!bg-surface-700' } }" />
                                </div>
                            </div>
                            
                            <InputNumber 
                                v-model="checkoutForm.changeFor" 
                                mode="currency" currency="BRL" locale="pt-BR" 
                                placeholder="R$ 0,00"
                                class="w-full" 
                                :inputClass="'!bg-surface-900 !border-surface-700 !text-white !w-full disabled:!opacity-30 disabled:!cursor-not-allowed'"
                                :disabled="isNoChange"
                            />
                            <div v-if="!isNoChange && checkoutForm.changeFor > 0 && checkoutForm.changeFor < finalTotal" class="text-red-500 text-xs mt-1 font-bold">
                                Valor menor que o total.
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-surface-400 uppercase tracking-wider mb-2 block">Observações</label>
                        <Textarea 
                            v-model="checkoutForm.notes" 
                            rows="2" 
                            placeholder="Ex: Tocar interfone, retirar cebola..." 
                            class="w-full !bg-surface-950 !border-surface-700 !text-white"
                        />
                    </div>

                </div>
            </transition>

        </div>

        <div class="bg-surface-900 border-t border-surface-800 p-6 pb-8 shrink-0 z-20">
            <div class="flex justify-between items-end mb-4">
                <span class="text-sm text-surface-400">Total {{ step === 'checkout' && !isPickup ? '(+ Entrega)' : '' }}</span>
                <div class="text-right">
                    <div v-if="deliveryFee > 0 && !isPickup" class="text-xs text-surface-400 mb-1">
                        + R$ {{ formatMoney(deliveryFee) }} entrega
                    </div>
                    <span class="text-2xl font-black text-white tracking-tight">R$ {{ formatMoney(finalTotal) }}</span>
                </div>
            </div>

            <Button 
                :label="mainButtonLabel" 
                :icon="mainButtonIcon" 
                class="w-full h-14 font-bold text-lg !border-none !text-surface-950 shadow-[0_0_20px_rgba(16,185,129,0.2)] transition-all active:scale-[0.98]" 
                :class="canProceed ? '!bg-primary-500 hover:!bg-primary-400' : '!bg-surface-700 !text-surface-400 cursor-not-allowed'"
                :loading="store.isSending"
                :disabled="!canProceed"
                @click="handleMainAction"
            />
        </div>
    </div>
  </Drawer>
</template>

<script setup>
import { ref, computed, watch, reactive, onMounted } from 'vue';
import { useCartStore } from '@/stores/cart-store';
import { useSessionStore } from '@/stores/session-store';
import { useUserStore } from '@/stores/user-store';
import SmartAddressForm from '@/components/SmartAddressForm.vue';
import Drawer from 'primevue/drawer';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import ToggleSwitch from 'primevue/toggleswitch';
import { notify } from '@/services/notify';

const props = defineProps({ displayMode: { type: String, default: 'side' } });

const store = useCartStore();
const sessionStore = useSessionStore();
const userStore = useUserStore();

const step = ref('cart');
const isPickup = ref(false);
const deliveryFee = ref(0);
const isNoChange = ref(false);

// Lógica de Endereços
const showNewAddressForm = ref(false);
const selectedAddressIndex = ref(null);
const savedAddresses = computed(() => {
    // Tenta pegar endereços reais do usuário ou retorna vazio
    // O backend precisa retornar 'addresses' no /me ou login para isso funcionar plenamente
    return userStore.user?.addresses || []; 
});

const checkoutForm = reactive(store.checkoutForm); 

const paymentMethods = [
    { id: 'pix', label: 'PIX', icon: 'fa-brands fa-pix' },
    { id: 'card', label: 'Cartão', icon: 'fa-regular fa-credit-card' },
    { id: 'dinheiro', label: 'Dinheiro', icon: 'fa-solid fa-money-bill-wave' }
];

const finalTotal = computed(() => store.totalValue + (isPickup.value ? 0 : deliveryFee.value));
const isClientMode = computed(() => sessionStore.sessionType === 'delivery' || !sessionStore.sessionType);

const mainButtonLabel = computed(() => {
    if (!isClientMode.value) return 'ENVIAR PARA COZINHA';
    return step.value === 'cart' ? 'CONTINUAR' : 'ENVIAR PEDIDO';
});

const mainButtonIcon = computed(() => {
    if (!isClientMode.value) return 'pi pi-send';
    return step.value === 'cart' ? 'pi pi-arrow-right' : 'pi pi-check';
});

const canProceed = computed(() => {
    if (store.items.length === 0) return false;
    
    if (step.value === 'checkout') {
        if (!isPickup.value) {
            // Se estiver usando form novo, valida os campos
            if (showNewAddressForm.value || savedAddresses.value.length === 0) {
                const addr = checkoutForm.address;
                if (!addr || !addr.street || !addr.district || (!addr.number && addr.number !== 'S/N')) return false;
            } else {
                // Se estiver usando salvo, precisa ter um selecionado
                if (selectedAddressIndex.value === null) return false;
            }
        }
        if (!checkoutForm.paymentMethod) return false;
        
        // Validação Dinheiro
        if (checkoutForm.paymentMethod === 'dinheiro') {
            if (isNoChange.value) return true; // Se marcou "sem troco", tá ok
            if (!checkoutForm.changeFor || checkoutForm.changeFor < finalTotal.value) return false;
        }
    }
    return true;
});

// Ações
const handleNoChangeToggle = () => {
    if (isNoChange.value) {
        checkoutForm.changeFor = 0; // Backend entende 0 como "sem troco/exato"
    } else {
        checkoutForm.changeFor = null;
    }
};

const selectSavedAddress = (idx) => {
    selectedAddressIndex.value = idx;
    checkoutForm.address = savedAddresses.value[idx];
    showNewAddressForm.value = false;
};

const cancelNewAddress = () => {
    showNewAddressForm.value = false;
    // Se tinha um selecionado antes, volta pra ele, senão limpa
    if (savedAddresses.value.length > 0 && selectedAddressIndex.value !== null) {
        checkoutForm.address = savedAddresses.value[selectedAddressIndex.value];
    }
};

const handleMainAction = async () => {
    if (!isClientMode.value) {
        await store.sendOrder();
        return;
    }

    if (step.value === 'cart') {
        if (!userStore.isLoggedIn) {
            notify('info', 'Identifique-se', 'Faça login para continuar.');
            return;
        }
        step.value = 'checkout';
        
        // Auto-seleciona primeiro endereço se houver e nada estiver selecionado
        if (savedAddresses.value.length > 0 && selectedAddressIndex.value === null) {
            selectSavedAddress(0);
        }
        // Se não houver salvos, abre o form direto
        if (savedAddresses.value.length === 0) {
            showNewAddressForm.value = true;
        }
    } else {
        store.checkoutForm.isPickup = isPickup.value;
        await store.checkoutWeb();
        if(store.items.length === 0) {
            step.value = 'cart';
            showNewAddressForm.value = false;
        }
    }
};

watch(() => store.isOpen, (val) => {
    if (!val) setTimeout(() => step.value = 'cart', 300);
});

const formatMoney = (val) => parseFloat(val).toFixed(2).replace('.', ',');

const ptOptions = computed(() => {
    const base = {
        header: { class: '!bg-surface-900 !border-b !border-surface-800 !px-6 !py-4 relative' },
        content: { class: '!bg-surface-900 !p-0' },
        closeButton: { class: '!text-surface-300 hover:!text-white hover:!bg-surface-800 !absolute !right-4 !top-4' },
        mask: { class: '!bg-black/60 backdrop-blur-sm' } 
    };
    if (props.displayMode === 'bottom') {
        return { ...base, root: { class: '!bg-surface-900 !border-t !border-surface-700 !rounded-t-[2rem] !shadow-2xl' } };
    }
    return { ...base, root: { class: '!bg-surface-900 !border-l !border-surface-800 w-full md:w-[450px]' } };
});
</script>

<style scoped>
.slide-left-enter-active, .slide-left-leave-active { transition: all 0.3s ease; }
.slide-left-enter-from { opacity: 0; transform: translateX(20px); }
.slide-left-leave-to { opacity: 0; transform: translateX(-20px); }
.animate-fade-in { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>