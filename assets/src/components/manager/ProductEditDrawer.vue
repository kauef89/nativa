<template>
  <Drawer 
    :visible="visible" 
    @update:visible="$emit('update:visible', $event)" 
    position="right" 
    :pt="{ 
        root: { class: '!bg-surface-900 !border-l !border-surface-800 !w-full md:!w-[450px] !h-screen !transition-all !duration-300' },
        header: { class: '!bg-surface-950 !border-b !border-surface-800 !p-5' },
        content: { class: '!p-0' },
        mask: { class: '!bg-black/50 backdrop-blur-sm' }
    }"
  >
    <template #header>
        <div class="flex items-center gap-4 overflow-hidden w-full">
            <div class="w-12 h-12 rounded-xl bg-surface-800 flex items-center justify-center shrink-0 border border-surface-700">
                <img v-if="product?.image" :src="product.image" class="w-full h-full object-cover rounded-xl opacity-80" />
                <i v-else class="fa-solid fa-box text-primary-500 text-xl"></i>
            </div>
            <div class="flex flex-col min-w-0">
                <span class="font-bold text-white text-lg truncate leading-tight">{{ product?.name }}</span>
                <span class="text-xs text-surface-400 font-mono mt-0.5">{{ product?.sku || 'Sem SKU' }}</span>
            </div>
        </div>
    </template>

    <div v-if="product" class="p-6 space-y-8 h-full flex flex-col overflow-y-auto scrollbar-thin">
        
        <div class="space-y-3">
            <label class="text-xs font-bold text-surface-400 uppercase tracking-wider flex items-center gap-2">
                <i class="fa-solid fa-traffic-light"></i> Status do Produto
            </label>
            
            <div class="flex flex-col gap-2">
                <button @click="localStatus = 'disponivel'"
                    class="flex items-center justify-between p-4 rounded-xl border transition-all group"
                    :class="localStatus === 'disponivel' 
                        ? 'bg-green-500/10 border-green-500 text-white shadow-[0_0_15px_rgba(34,197,94,0.2)]' 
                        : 'bg-surface-950 border-surface-800 text-surface-400 hover:bg-surface-800 hover:text-surface-200'"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center"
                             :class="localStatus === 'disponivel' ? 'bg-green-500 text-surface-900' : 'bg-surface-800 text-surface-500'">
                            <i class="fa-solid fa-check text-sm"></i>
                        </div>
                        <div class="flex flex-col text-left">
                            <span class="font-bold text-sm">Disponível</span>
                            <span class="text-[10px] opacity-60 font-normal">Visível e comprável</span>
                        </div>
                    </div>
                    <div v-if="localStatus === 'disponivel'" class="w-3 h-3 rounded-full bg-green-500 shadow-glow-green"></div>
                </button>

                <button @click="localStatus = 'indisponivel'"
                    class="flex items-center justify-between p-4 rounded-xl border transition-all group"
                    :class="localStatus === 'indisponivel' 
                        ? 'bg-orange-500/10 border-orange-500 text-white shadow-[0_0_15px_rgba(249,115,22,0.2)]' 
                        : 'bg-surface-950 border-surface-800 text-surface-400 hover:bg-surface-800 hover:text-surface-200'"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center"
                             :class="localStatus === 'indisponivel' ? 'bg-orange-500 text-surface-900' : 'bg-surface-800 text-surface-500'">
                            <i class="fa-solid fa-ban text-sm"></i>
                        </div>
                        <div class="flex flex-col text-left">
                            <span class="font-bold text-sm">Esgotado</span>
                            <span class="text-[10px] opacity-60 font-normal">Visível, mas bloqueado</span>
                        </div>
                    </div>
                </button>

                <button @click="localStatus = 'oculto'"
                    class="flex items-center justify-between p-4 rounded-xl border transition-all group"
                    :class="localStatus === 'oculto' 
                        ? 'bg-red-500/10 border-red-500 text-white shadow-[0_0_15px_rgba(239,68,68,0.2)]' 
                        : 'bg-surface-950 border-surface-800 text-surface-400 hover:bg-surface-800 hover:text-surface-200'"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center"
                             :class="localStatus === 'oculto' ? 'bg-red-500 text-surface-900' : 'bg-surface-800 text-surface-500'">
                            <i class="fa-solid fa-eye-slash text-sm"></i>
                        </div>
                        <div class="flex flex-col text-left">
                            <span class="font-bold text-sm">Oculto</span>
                            <span class="text-[10px] opacity-60 font-normal">Invisível no cardápio</span>
                        </div>
                    </div>
                </button>
            </div>
        </div>

        <hr class="border-surface-800" />

        <div class="space-y-3">
            <label class="text-xs font-bold text-surface-400 uppercase tracking-wider flex items-center gap-2">
                <i class="fa-solid fa-layer-group"></i> Canais & Regras
            </label>
            
            <div class="bg-surface-950 rounded-2xl border border-surface-800 overflow-hidden divide-y divide-surface-800">
                
                <div class="flex justify-between items-center p-4 hover:bg-surface-900/50 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-surface-800 flex items-center justify-center text-primary-500">
                            <i class="fa-solid fa-motorcycle"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-sm font-bold text-white">Delivery / App</span>
                            <span class="text-[10px] text-surface-500">Exibir no site para clientes</span>
                        </div>
                    </div>
                    <InputSwitch v-model="localShowDelivery" />
                </div>

                <div class="flex justify-between items-center p-4 hover:bg-surface-900/50 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-surface-800 flex items-center justify-center text-blue-400">
                            <i class="fa-solid fa-utensils"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-sm font-bold text-white">Mesa / Garçom</span>
                            <span class="text-[10px] text-surface-500">Disponível no pedido local</span>
                        </div>
                    </div>
                    <InputSwitch v-model="localShowTable" />
                </div>

                <div class="flex justify-between items-center p-4 bg-red-500/5 hover:bg-red-500/10 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-red-500/20 flex items-center justify-center text-red-400">
                            <i class="fa-solid fa-user-shield"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-sm font-bold text-white">Produto +18</span>
                            <span class="text-[10px] text-surface-400">Exige maioridade (Bebidas Alc.)</span>
                        </div>
                    </div>
                    <InputSwitch v-model="localIs18Plus" />
                </div>

            </div>
        </div>

        <hr class="border-surface-800" />

        <div class="space-y-3">
            <div class="flex justify-between items-center">
                <label class="text-xs font-bold text-surface-400 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-boxes-stacked"></i> Estoque Rápido
                </label>
                <button class="text-[10px] font-bold text-primary-400 hover:text-white transition-colors uppercase tracking-wider" @click="localStock = 999">
                    Resetar (999)
                </button>
            </div>

            <div class="flex items-center gap-2 bg-surface-950 p-2 rounded-2xl border border-surface-800 shadow-inner">
                <button class="w-12 h-12 rounded-xl bg-surface-800 text-surface-400 hover:bg-red-500/20 hover:text-red-500 transition-all active:scale-95 flex items-center justify-center" @click="localStock > 0 ? localStock-- : null">
                    <i class="fa-solid fa-minus"></i>
                </button>
                
                <div class="flex-1 flex flex-col items-center justify-center">
                    <input 
                        type="number" 
                        v-model="localStock" 
                        class="w-full bg-transparent text-center text-3xl font-mono font-bold text-white focus:outline-none p-0 border-none appearance-none"
                    />
                    <span class="text-[9px] text-surface-500 uppercase font-bold tracking-wider">Unidades</span>
                </div>

                <button class="w-12 h-12 rounded-xl bg-surface-800 text-surface-400 hover:bg-green-500/20 hover:text-green-500 transition-all active:scale-95 flex items-center justify-center" @click="localStock++">
                    <i class="fa-solid fa-plus"></i>
                </button>
            </div>
        </div>

        <div class="flex-1"></div>

        <div class="grid grid-cols-2 gap-4 pt-6 border-t border-surface-800 mt-auto bg-surface-900 sticky bottom-0 z-10">
            <Button label="Cancelar" text class="!text-surface-400 hover:!text-white !font-bold" @click="$emit('update:visible', false)" />
            <Button label="Salvar Alterações" icon="fa-solid fa-check" class="!bg-primary-600 hover:!bg-primary-500 !border-none !font-bold !text-surface-950 shadow-[0_0_20px_rgba(16,185,129,0.2)]" :loading="saving" @click="handleSave" />
        </div>

    </div>
  </Drawer>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useProductsStore } from '@/stores/products-store';
import Button from 'primevue/button';
import Drawer from 'primevue/drawer';
import InputSwitch from 'primevue/inputswitch';

const props = defineProps(['visible', 'product']);
const emit = defineEmits(['update:visible', 'saved']);
const store = useProductsStore();

const localStatus = ref('disponivel');
const localStock = ref(0);
const localShowDelivery = ref(true);
const localShowTable = ref(true);
const localIs18Plus = ref(false);
const saving = ref(false);

watch(() => props.product, (newVal) => {
    if (newVal) {
        // Mapeamento correto com o Backend (API retorna 'disponivel', 'indisponivel', 'oculto')
        localStatus.value = newVal.availability || 'disponivel';
        localStock.value = newVal.stock_quantity || 0;
        
        localShowDelivery.value = newVal.show_delivery !== false; 
        localShowTable.value = newVal.show_table !== false;       
        localIs18Plus.value = !!newVal.is_18_plus;                
    }
});

const handleSave = async () => {
    saving.value = true;
    const success = await store.updateProduct(props.product.id, {
        availability: localStatus.value, // Envia a string correta
        stock_quantity: localStock.value,
        show_delivery: localShowDelivery.value,
        show_table: localShowTable.value,
        is_18_plus: localIs18Plus.value
    });
    saving.value = false;
    
    if (success) {
        emit('update:visible', false);
        emit('saved');
    }
};
</script>

<style scoped>
/* Sombra interna verde para o indicador */
.shadow-glow-green {
    box-shadow: 0 0 10px #22c55e;
}
</style>