<template>
  <Drawer 
    :visible="visible" 
    @update:visible="$emit('update:visible', $event)" 
    position="right" 
    class="!bg-surface-900 !border-l !border-surface-800 !w-full !md:w-[400px]"
    :pt="{ 
        header: { class: '!bg-surface-950 !border-b !border-surface-800 !p-4' },
        content: { class: '!p-0' }
    }"
  >
    <template #header>
        <div class="flex items-center gap-3 overflow-hidden">
            <div class="w-10 h-10 rounded-lg bg-surface-800 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-box text-primary-500"></i>
            </div>
            <div class="flex flex-col truncate">
                <span class="font-bold text-white truncate">{{ product?.name }}</span>
                <span class="text-xs text-surface-400 font-mono">{{ product?.sku || 'Sem SKU' }}</span>
            </div>
        </div>
    </template>

    <div v-if="product" class="p-6 space-y-8 h-full flex flex-col">
        
        <div class="space-y-3">
            <label class="text-xs font-bold text-surface-400 uppercase tracking-wider">Disponibilidade</label>
            
            <div class="grid grid-cols-1 gap-3">
                <button @click="localStatus = 'publish'"
                    class="flex items-center justify-between p-4 rounded-xl border transition-all"
                    :class="localStatus === 'publish' ? 'bg-green-500/20 border-green-500 text-white' : 'bg-surface-800 border-surface-700 text-surface-400 hover:bg-surface-700'"
                >
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-eye text-lg" :class="localStatus === 'publish' ? 'text-green-500' : ''"></i>
                        <div class="flex flex-col text-left">
                            <span class="font-bold">Exibir no Cardápio</span>
                            <span class="text-[10px] opacity-70">O cliente pode comprar</span>
                        </div>
                    </div>
                    <i v-if="localStatus === 'publish'" class="fa-solid fa-check text-green-500"></i>
                </button>

                <button @click="localStatus = 'out_of_stock'"
                    class="flex items-center justify-between p-4 rounded-xl border transition-all"
                    :class="localStatus === 'out_of_stock' ? 'bg-orange-500/20 border-orange-500 text-white' : 'bg-surface-800 border-surface-700 text-surface-400 hover:bg-surface-700'"
                >
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-ban text-lg" :class="localStatus === 'out_of_stock' ? 'text-orange-500' : ''"></i>
                        <div class="flex flex-col text-left">
                            <span class="font-bold">Marcar Esgotado</span>
                            <span class="text-[10px] opacity-70">Exibe como "Indisponível"</span>
                        </div>
                    </div>
                    <i v-if="localStatus === 'out_of_stock'" class="fa-solid fa-check text-orange-500"></i>
                </button>

                <button @click="localStatus = 'hidden'"
                    class="flex items-center justify-between p-4 rounded-xl border transition-all"
                    :class="localStatus === 'hidden' ? 'bg-red-500/20 border-red-500 text-white' : 'bg-surface-800 border-surface-700 text-surface-400 hover:bg-surface-700'"
                >
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-eye-slash text-lg" :class="localStatus === 'hidden' ? 'text-red-500' : ''"></i>
                        <div class="flex flex-col text-left">
                            <span class="font-bold">Esconder Produto</span>
                            <span class="text-[10px] opacity-70">Remove totalmente do cardápio</span>
                        </div>
                    </div>
                    <i v-if="localStatus === 'hidden'" class="fa-solid fa-check text-red-500"></i>
                </button>
            </div>
        </div>

        <div class="space-y-3">
            <div class="flex justify-between items-center">
                <label class="text-xs font-bold text-surface-400 uppercase tracking-wider">Ajuste de Estoque</label>
                <span class="text-xs text-primary-400 cursor-pointer hover:underline" @click="localStock = 999">Ilimitado?</span>
            </div>

            <div class="flex items-center gap-4 bg-surface-950 p-2 rounded-xl border border-surface-800">
                <Button icon="fa-solid fa-minus" class="!bg-surface-800 !border-none hover:!bg-red-500/20 hover:!text-red-500 !w-12 !h-12" @click="localStock--" />
                
                <div class="flex-1 text-center">
                    <input 
                        type="number" 
                        v-model="localStock" 
                        class="w-full bg-transparent text-center text-3xl font-mono font-bold text-white focus:outline-none"
                    />
                    <span class="text-[10px] text-surface-500 uppercase">Unidades Atuais</span>
                </div>

                <Button icon="fa-solid fa-plus" class="!bg-surface-800 !border-none hover:!bg-green-500/20 hover:!text-green-500 !w-12 !h-12" @click="localStock++" />
            </div>
        </div>

        <div class="flex-1"></div>

        <div class="grid grid-cols-2 gap-3 pt-4 border-t border-surface-800">
            <Button label="Cancelar" text class="!text-surface-400 hover:!text-white" @click="$emit('update:visible', false)" />
            <Button label="Salvar Alterações" icon="fa-solid fa-save" class="!bg-primary-600 hover:!bg-primary-500 !border-none" 
                    :loading="saving" @click="handleSave" />
        </div>

    </div>
  </Drawer>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useProductsStore } from '@/stores/products-store';
import Button from 'primevue/button';
import Drawer from 'primevue/drawer';

const props = defineProps(['visible', 'product']);
const emit = defineEmits(['update:visible', 'saved']);
const store = useProductsStore();

const localStatus = ref('publish');
const localStock = ref(0);
const saving = ref(false);

// Inicializa valores ao abrir
watch(() => props.product, (newVal) => {
    if (newVal) {
        // Mapeie aqui se sua V1 usar nomes diferentes
        localStatus.value = newVal.availability || 'publish';
        localStock.value = newVal.stock_quantity || 0;
    }
});

const handleSave = async () => {
    saving.value = true;
    const success = await store.updateProduct(props.product.id, {
        availability: localStatus.value,
        stock_quantity: localStock.value
    });
    saving.value = false;
    
    if (success) {
        emit('update:visible', false);
        emit('saved');
    }
};
</script>