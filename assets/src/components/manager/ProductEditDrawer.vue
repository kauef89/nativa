<template>
  <Drawer 
    :visible="visible" 
    @update:visible="$emit('update:visible', $event)" 
    position="right" 
    :pt="{ 
        root: { class: '!bg-surface-1 !border-none !w-full md:!w-[450px] !h-screen !rounded-l-[28px]' },
        header: { class: '!bg-surface-2 !border-none !p-6' },
        content: { class: '!p-0' },
        mask: { class: '!bg-surface-base/60 backdrop-blur-md' }
    }"
  >
    <template #header>
        <div class="flex items-center gap-4 overflow-hidden w-full">
            <div class="w-14 h-14 rounded-2xl bg-surface-1 flex items-center justify-center shrink-0 border border-surface-3/10 shadow-lg">
                <img v-if="product?.image" :src="product.image" class="w-full h-full object-cover rounded-2xl" />
                <i v-else class="fa-solid fa-box text-primary-500 text-2xl"></i>
            </div>
            <div class="flex flex-col min-w-0">
                <span class="font-black text-surface-0 text-xl truncate leading-tight">{{ product?.name }}</span>
                <span class="text-xs text-surface-400 font-bold mt-0.5">{{ product?.sku || 'Sem SKU' }}</span>
            </div>
        </div>
    </template>

    <div v-if="product" class="p-6 space-y-8 h-full flex flex-col overflow-y-auto">
        
        <div class="space-y-4">
            <label class="text-xs font-black text-surface-400 uppercase tracking-widest flex items-center gap-2">
                <i class="fa-solid fa-traffic-light"></i> Status do Produto
            </label>
            <div class="grid grid-cols-1 gap-3">
                <button v-for="st in ['disponivel', 'indisponivel', 'oculto']" :key="st"
                    @click="localStatus = st"
                    class="flex items-center justify-between p-4 rounded-xl border border-transparent transition-all"
                    :class="localStatus === st ? 'bg-primary-500/10 border-primary-500 text-surface-0 shadow-lg' : 'bg-surface-2 text-surface-400 hover:bg-surface-3'"
                >
                    <div class="flex items-center gap-3">
                        <i class="fa-solid text-lg" :class="st === 'disponivel' ? 'fa-check text-green-500' : st === 'indisponivel' ? 'fa-ban text-orange-500' : 'fa-eye-slash text-red-500'"></i>
                        <span class="font-bold text-sm capitalize">{{ st }}</span>
                    </div>
                    <div v-if="localStatus === st" class="w-3 h-3 rounded-full bg-primary-500 shadow-[0_0_10px_rgba(6,182,212,1)]"></div>
                </button>
            </div>
        </div>

        <div class="space-y-4">
            <label class="text-xs font-black text-surface-400 uppercase tracking-widest flex items-center gap-2">
                <i class="fa-solid fa-boxes-stacked text-primary-500"></i> Estoque e Logística
            </label>
            
            <div class="bg-surface-2 rounded-[24px] divide-y divide-surface-3/10 overflow-hidden">
                
                <div class="flex justify-between items-center p-4 hover:bg-surface-3 transition-colors">
                    <div class="flex flex-col">
                        <span class="text-sm font-bold text-surface-0">Requer Preparo na Cozinha?</span>
                        <span class="text-[10px] text-surface-400 font-bold">Desmarque para itens prontos (Grab & Go)</span>
                    </div>
                    <ToggleSwitch v-model="localRequiresPrep" />
                </div>

                <div class="flex justify-between items-center p-4 hover:bg-surface-3 transition-colors">
                    <div class="flex flex-col">
                        <span class="text-sm font-bold text-surface-0">Controlar Estoque?</span>
                        <span class="text-[10px] text-surface-400 font-bold">Habilita contagem e alertas</span>
                    </div>
                    <ToggleSwitch v-model="localManageStock" />
                </div>

                <div v-if="localManageStock" class="p-4 bg-surface-900/50 animate-fade-in space-y-4">
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="text-[10px] uppercase font-black text-surface-500 mb-1 block">Estoque Atual</label>
                            <div class="text-xl font-black text-surface-0 mb-1">{{ product.stock_quantity }} un</div>
                        </div>
                        <div class="flex-1">
                            <label class="text-[10px] uppercase font-black text-surface-500 mb-1 block">Mínimo (Alerta)</label>
                            <InputNumber 
                                v-model="localStockMin" 
                                :min="0" 
                                class="w-full" 
                                inputClass="!h-8 !text-sm !font-bold !text-center !bg-surface-1 !border-none !rounded-lg !text-surface-0" 
                            />
                        </div>
                    </div>

                    <div class="pt-2 border-t border-surface-800">
                        <label class="text-[10px] uppercase font-black text-green-400 mb-2 flex items-center gap-1">
                            <i class="fa-solid fa-plus-circle"></i> Adicionar Estoque (Entrada)
                        </label>
                        <div class="flex gap-2">
                            <InputNumber 
                                v-model="stockAddInput" 
                                placeholder="Qtd" 
                                :min="1" 
                                class="flex-1" 
                                inputClass="!h-10 !bg-surface-1 !border-none !text-surface-0 !font-black !text-center !rounded-lg" 
                            />
                            <Button 
                                label="Lançar" 
                                size="small" 
                                class="!bg-green-600 hover:!bg-green-500 !border-none !font-black !text-xs !px-4" 
                                @click="addStock" 
                                :disabled="!stockAddInput" 
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <label class="text-xs font-black text-surface-400 uppercase tracking-widest flex items-center gap-2">
                <i class="fa-solid fa-fire-burner text-primary-500"></i> Cozinha / Estação
            </label>
            
            <div class="bg-surface-2 p-5 rounded-[24px]">
                <label class="text-sm font-bold text-surface-300 block mb-2">Enviar pedido para:</label>
                
                <Select 
                    v-model="localStation" 
                    :options="cozinhas" 
                    optionLabel="name" 
                    optionValue="id" 
                    placeholder="Selecione a Cozinha"
                    class="w-full !h-12 !rounded-full !bg-surface-1 !border-none kitchen-select"
                    panelClass="kitchen-select-panel"
                    :pt="{ 
                        label: { class: '!text-surface-0 !font-bold !py-3 !px-4' },
                        dropdown: { class: '!text-surface-400 !w-10' }
                    }"
                >
                    <template #option="slotProps">
                        <div class="flex items-center gap-2 py-1">
                            <i class="fa-solid fa-fire-burner text-surface-400 text-sm"></i>
                            <div class="font-bold text-sm">{{ slotProps.option.name }}</div>
                        </div>
                    </template>
                    <template #value="slotProps">
                        <div v-if="slotProps.value" class="flex items-center gap-2">
                            <i class="fa-solid fa-fire-burner text-primary-500"></i>
                            <span>{{ getStationName(slotProps.value) }}</span>
                        </div>
                        <span v-else class="text-surface-500">{{ slotProps.placeholder }}</span>
                    </template>
                </Select>
            </div>
        </div>

        <div class="space-y-4">
            <label class="text-xs font-black text-surface-400 uppercase tracking-widest flex items-center gap-2">
                <i class="fa-solid fa-gem text-yellow-500"></i> Recompensas
            </label>
            <div class="flex justify-between items-center p-4 bg-surface-2 rounded-[24px] border border-transparent hover:bg-surface-3 transition-colors">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-yellow-500/10 flex items-center justify-center text-yellow-500 border border-yellow-500/20">
                        <i class="fa-solid fa-gem"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm font-bold text-surface-0">Disponível para Resgate</span>
                        <span class="text-[10px] text-surface-400 uppercase font-bold tracking-wide">Habilitar troca por pontos</span>
                    </div>
                </div>
                <ToggleSwitch v-model="localIsLoyalty" />
            </div>
        </div>

        <div class="space-y-4">
            <label class="text-xs font-black text-surface-400 uppercase tracking-widest flex items-center gap-2">
                <i class="fa-solid fa-layer-group"></i> Visibilidade
            </label>
            <div class="bg-surface-2 rounded-[24px] divide-y divide-surface-3/10 overflow-hidden">
                <div class="flex justify-between items-center p-4">
                    <span class="text-sm text-surface-0 font-bold">Exibir no Delivery</span>
                    <ToggleSwitch v-model="localShowDelivery" />
                </div>
                <div class="flex justify-between items-center p-4">
                    <span class="text-sm text-surface-0 font-bold">Exibir na Mesa</span>
                    <ToggleSwitch v-model="localShowTable" />
                </div>
                <div class="flex justify-between items-center p-4">
                    <span class="text-sm text-red-400 font-bold">Apenas +18</span>
                    <ToggleSwitch v-model="localIs18Plus" />
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 pt-6 mt-auto bg-surface-1 sticky bottom-0 z-10 pb-2">
            <Button label="Cancelar" text class="!text-surface-400 hover:!text-surface-0 !font-bold !rounded-full" @click="$emit('update:visible', false)" />
            <Button label="Salvar" icon="fa-solid fa-check" class="!bg-primary-600 hover:!bg-primary-500 !border-none !font-black !rounded-full !text-surface-950 shadow-lg" :loading="saving" @click="handleSave" />
        </div>
    </div>
  </Drawer>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { useProductsStore } from '@/stores/products-store';
import api from '@/services/api';
import { notify } from '@/services/notify';
import Button from 'primevue/button';
import Drawer from 'primevue/drawer';
import ToggleSwitch from 'primevue/toggleswitch';
import Select from 'primevue/select'; 
import InputNumber from 'primevue/inputnumber';

const props = defineProps(['visible', 'product']);
const emit = defineEmits(['update:visible', 'saved']);
const store = useProductsStore();

const localStatus = ref('disponivel');
const localIsLoyalty = ref(false);
const localStation = ref(null); 
const localShowDelivery = ref(true);
const localShowTable = ref(true);
const localIs18Plus = ref(false);
const saving = ref(false);
const cozinhas = ref([]);

const localRequiresPrep = ref(true);
const localManageStock = ref(false);
const localStockMin = ref(5);
const stockAddInput = ref(null);

const fetchCozinhas = async () => {
    try {
        const { data } = await api.get('/logistica/cozinhas');
        if (data.success) {
            cozinhas.value = data.cozinhas;
        }
    } catch (e) {
        console.error('Erro ao carregar cozinhas', e);
    }
};

const getStationName = (id) => {
    // Busca na lista dinâmica de cozinhas
    const found = cozinhas.value.find(c => c.id === id);
    // Se não achar, tenta fallback para textos legados
    if (!found) {
        if (id === 'frente') return 'Frente (Legado)';
        if (id === 'fundos') return 'Fundos (Legado)';
        return 'Não Definida';
    }
    return found.name;
};

watch(() => props.product, (newVal) => {
    if (newVal) {
        localStatus.value = newVal.availability || 'disponivel';
        localIsLoyalty.value = !!newVal.is_loyalty;
        
        // Garante que a estação seja carregada corretamente (ID ou string)
        localStation.value = newVal.station || null;

        localShowDelivery.value = newVal.show_delivery !== false;
        localShowTable.value = newVal.show_table !== false;
        localIs18Plus.value = !!newVal.is_18_plus;
        
        localRequiresPrep.value = newVal.requires_prep !== false;
        localManageStock.value = !!newVal.manage_stock;
        localStockMin.value = newVal.stock_min || 5;
        stockAddInput.value = null;
    }
});

watch(() => props.visible, (val) => {
    if (val && cozinhas.value.length === 0) {
        fetchCozinhas();
    }
});

const addStock = async () => {
    if (!stockAddInput.value) return;
    try {
        await store.updateProduct(props.product.id, {
            stock_add: stockAddInput.value
        });
        notify('success', 'Estoque', 'Quantidade adicionada com sucesso.');
        stockAddInput.value = null;
        emit('saved');
    } catch (e) {
        notify('error', 'Erro', 'Falha ao adicionar estoque.');
    }
};

const handleSave = async () => {
    saving.value = true;
    const success = await store.updateProduct(props.product.id, {
        availability: localStatus.value,
        is_loyalty: localIsLoyalty.value,
        station: localStation.value, // Envia o ID selecionado
        show_delivery: localShowDelivery.value,
        show_table: localShowTable.value,
        is_18_plus: localIs18Plus.value,
        requires_prep: localRequiresPrep.value,
        manage_stock: localManageStock.value,
        stock_min: localStockMin.value
    });
    saving.value = false;
    if (success) {
        emit('update:visible', false);
        emit('saved');
    }
};

onMounted(() => {
    fetchCozinhas();
});
</script>

<style>
.kitchen-select-panel {
    background-color: #292524 !important;
    border: 1px solid rgba(255,255,255,0.1) !important;
    border-radius: 16px !important;
    box-shadow: 0 10px 40px rgba(0,0,0,0.5) !important;
    overflow: hidden !important;
    z-index: 9999 !important;
}

.kitchen-select-panel .p-select-option {
    color: #e7e5e4 !important;
    padding: 12px 16px !important;
    transition: background-color 0.2s !important;
}

.kitchen-select-panel .p-select-option:hover,
.kitchen-select-panel .p-select-option.p-focus {
    background-color: #44403c !important;
    color: #ffffff !important;
}

.kitchen-select-panel .p-select-option.p-select-option-selected {
    background-color: #3b82f6 !important;
    color: #ffffff !important;
}

.kitchen-select-panel .p-select-list-container {
    padding: 4px !important;
}
</style>