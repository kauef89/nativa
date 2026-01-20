<template>
  <div class="flex flex-col h-full w-full bg-surface-950 p-4 md:p-6 overflow-hidden">
    
    <div class="shrink-0 mb-4 h-14 relative z-20">
        
        <transition name="fade-slide">
            <div v-if="selectedProducts.length === 0" class="absolute inset-0 flex items-center gap-4">
                
                <div class="hidden md:block min-w-0 mr-auto">
                    <h1 class="text-2xl font-bold text-white flex items-center gap-3 whitespace-nowrap">
                        <i class="fa-solid fa-boxes-stacked text-primary-500"></i> Inventário
                    </h1>
                </div>

                <div class="flex gap-2 w-full md:w-auto md:ml-auto">
                    
                    <MultiSelect 
                        v-model="store.categoryFilter" 
                        :options="store.uniqueCategories" 
                        display="chip"
                        placeholder="Categorias" 
                        :maxSelectedLabels="2"
                        class="w-48 md:w-64"
                        :pt="{
                            root: { class: '!bg-surface-900 !border-surface-700 !text-sm !h-10 !rounded-xl !items-center !flex' },
                            labelContainer: { class: '!py-1 !px-3 !flex !items-center !gap-1 !overflow-hidden' },
                            token: { class: '!bg-surface-800 !text-surface-200 !text-xs !py-0.5 !px-2 !rounded' },
                            label: { class: '!text-white !p-0' },
                            panel: { class: '!bg-surface-900 !border-surface-700' },
                            item: { class: '!text-surface-200 hover:!bg-surface-800 focus:!bg-surface-800' },
                            header: { class: '!bg-surface-950 !border-b !border-surface-800 !p-2 !text-white' },
                            closeButton: { class: '!text-surface-400 hover:!text-white' },
                            checkboxContainer: { class: '!mr-2' }
                        }"
                    />

                    <div class="relative w-full md:w-64 group">
                        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-surface-500 group-focus-within:text-primary-500 transition-colors"></i>
                        <input 
                            v-model="store.filter"
                            type="text" 
                            placeholder="Buscar produto..." 
                            class="w-full bg-surface-900 border border-surface-700 text-white pl-9 pr-4 h-10 rounded-xl focus:outline-none focus:border-primary-500 transition-all shadow-sm text-sm"
                        />
                    </div>

                    <Button icon="fa-solid fa-rotate" text rounded class="!text-surface-400 hover:!text-white !w-10 !h-10 shrink-0" @click="store.fetchProducts" v-tooltip="'Atualizar'" />
                </div>
            </div>
        </transition>

        <transition name="fade-slide">
            <div v-if="selectedProducts.length > 0" class="absolute inset-0 flex items-center justify-between bg-primary-500/10 border border-primary-500/30 rounded-xl px-4 backdrop-blur-md shadow-lg">
                <div class="flex items-center gap-3 overflow-hidden">
                    <Button icon="fa-solid fa-xmark" text rounded class="!text-surface-300 hover:!text-white !w-8 !h-8 shrink-0" @click="selectedProducts = []" />
                    <span class="font-bold text-primary-400 text-lg whitespace-nowrap">{{ selectedProducts.length }} selecionados</span>
                </div>

                <div class="flex gap-2 overflow-x-auto scrollbar-hide py-1 pl-2">
                    <Button label="Disponível" icon="fa-solid fa-check" size="small" class="!bg-green-600 hover:!bg-green-500 !border-none !text-xs !font-bold whitespace-nowrap" @click="applyBulk('availability', 'disponivel')" />
                    <Button label="Esgotado" icon="fa-solid fa-ban" size="small" class="!bg-orange-600 hover:!bg-orange-500 !border-none !text-xs !font-bold whitespace-nowrap" @click="applyBulk('availability', 'indisponivel')" />
                    <Button icon="fa-solid fa-eye-slash" size="small" class="!bg-surface-700 hover:!bg-surface-600 !border-none !w-8" v-tooltip="'Ocultar'" @click="applyBulk('availability', 'oculto')" />
                    <div class="w-px h-6 bg-surface-600/50 mx-1 self-center"></div>
                    <Button icon="fa-solid fa-rotate-left" size="small" class="!bg-red-500/20 !text-red-400 !border !border-red-500/30 hover:!bg-red-500 hover:!text-white !w-8" v-tooltip="'Zerar Estoque'" @click="applyBulk('stock_quantity', 0)" />
                </div>
            </div>
        </transition>
    </div>

    <div class="flex-1 min-h-0 bg-surface-900 border border-surface-800 rounded-2xl shadow-xl flex flex-col relative overflow-hidden">
        <DataTable 
            :value="store.filteredProducts" 
            v-model:selection="selectedProducts"
            dataKey="id"
            :scrollable="true" 
            scrollHeight="flex"
            removableSort
            paginator :rows="50"
            :rowsPerPageOptions="[20, 50, 100]"
            class="p-datatable-sm text-sm flex-1 flex flex-col"
            :pt="{
                root: { class: 'flex flex-col h-full' },
                wrapper: { class: 'flex-1 h-full' },
                headerRow: { class: '!bg-surface-950 !text-surface-400 !text-xs !uppercase !tracking-wider' },
                bodyRow: ({ context }) => ({ 
                    class: `!bg-surface-900 hover:!bg-surface-800 !transition-colors !border-b !border-surface-800/50 ${context.selected ? '!bg-primary-500/10' : ''}` 
                }),
                paginator: { class: '!bg-surface-950 !border-t !border-surface-800 !justify-end !text-xs' }
            }"
        >
            <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>

            <Column field="name" header="Produto" sortable class="min-w-[200px]">
                <template #body="slotProps">
                    <div class="flex items-center gap-3 cursor-pointer py-1" @click="openEdit(slotProps.data)">
                        <div class="w-9 h-9 rounded-lg bg-surface-800 border border-surface-700 overflow-hidden flex-shrink-0 relative">
                            <img v-if="slotProps.data.image" :src="slotProps.data.image" class="w-full h-full object-cover" />
                            <div v-else class="flex items-center justify-center h-full text-surface-600"><i class="fa-regular fa-image"></i></div>
                            <div v-if="slotProps.data.availability === 'oculto'" class="absolute inset-0 bg-black/60 flex items-center justify-center">
                                <i class="fa-solid fa-eye-slash text-white text-[10px]"></i>
                            </div>
                        </div>
                        <div class="flex flex-col min-w-0">
                            <span class="font-bold text-surface-200 truncate text-sm" :class="{'opacity-50': slotProps.data.availability === 'oculto'}">
                                {{ slotProps.data.name }}
                            </span>
                            <span class="text-[10px] text-surface-500 font-mono flex items-center gap-1">
                                {{ slotProps.data.sku }}
                            </span>
                        </div>
                    </div>
                </template>
            </Column>

            <Column field="category_name" header="Categoria" sortable headerStyle="width: 150px">
                <template #body="slotProps">
                    <span class="text-xs text-surface-400 bg-surface-800 px-2 py-0.5 rounded truncate max-w-[140px] block">
                        {{ slotProps.data.category_name || 'Geral' }}
                    </span>
                </template>
            </Column>

            <Column field="price" header="Preço" sortable headerStyle="width: 100px">
                <template #body="slotProps">
                    <span class="font-mono text-surface-300 font-medium">R$ {{ Number(slotProps.data.price).toFixed(2) }}</span>
                </template>
            </Column>

            <Column field="stock_quantity" header="Estoque" sortable headerStyle="width: 110px">
                <template #body="slotProps">
                    <div v-if="slotProps.data.manage_stock" 
                         class="px-2 py-0.5 rounded text-center font-mono font-bold text-xs border cursor-pointer hover:opacity-80 transition-opacity"
                         :class="getStockClass(slotProps.data.stock_quantity)"
                         @click="openEdit(slotProps.data)">
                        {{ slotProps.data.stock_quantity }}
                    </div>
                    <span v-else class="text-surface-600 text-[10px] pl-2 italic">--</span>
                </template>
            </Column>

            <Column field="availability" header="Status" sortable headerStyle="width: 120px">
                <template #body="slotProps">
                    <Tag :value="getStatusLabel(slotProps.data.availability)" :severity="getStatusSeverity(slotProps.data.availability)" class="!text-[10px] !uppercase !font-bold !px-2" />
                </template>
            </Column>

            <Column headerStyle="width: 3rem">
                <template #body="slotProps">
                    <Button icon="fa-solid fa-pen" text rounded class="!w-7 !h-7 !text-surface-500 hover:!text-white hover:!bg-surface-800" @click="openEdit(slotProps.data)" />
                </template>
            </Column>

        </DataTable>

        <div v-if="store.isLoading" class="absolute inset-0 bg-surface-950/50 backdrop-blur-sm flex items-center justify-center z-50">
            <i class="fa-solid fa-circle-notch fa-spin text-4xl text-primary-500"></i>
        </div>
    </div>

    <ProductEditDrawer 
        v-model:visible="showDrawer" 
        :product="selectedProduct"
        @saved="store.fetchProducts"
    />

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useProductsStore } from '@/stores/products-store';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import MultiSelect from 'primevue/multiselect'; // <--- TROCA IMPORTANTE
import ProductEditDrawer from '@/components/ProductEditDrawer.vue';

const store = useProductsStore();
const showDrawer = ref(false);
const selectedProduct = ref(null);
const selectedProducts = ref([]);

const openEdit = (prod) => {
    selectedProduct.value = prod;
    showDrawer.value = true;
};

const applyBulk = async (field, value) => {
    if (!confirm(`Alterar ${selectedProducts.value.length} itens?`)) return;
    const ids = selectedProducts.value.map(p => p.id);
    const payload = {};
    payload[field] = value;
    const success = await store.bulkUpdateProducts(ids, payload);
    if (success) selectedProducts.value = [];
};

const getStockClass = (qty) => {
    if (qty <= 0) return 'bg-red-500/10 text-red-500 border-red-500/20';
    if (qty < 10) return 'bg-orange-500/10 text-orange-500 border-orange-500/20';
    return 'bg-surface-800 text-surface-300 border-surface-700';
};

const getStatusLabel = (slug) => {
    const map = { 'disponivel': 'Ativo', 'indisponivel': 'Esgotado', 'oculto': 'Oculto' };
    return map[slug] || slug;
};

const getStatusSeverity = (slug) => {
    if (slug === 'disponivel') return 'success';
    if (slug === 'indisponivel') return 'warn';
    return 'contrast';
};

onMounted(() => {
    store.fetchProducts();
});
</script>

<style scoped>
.fade-slide-enter-active, .fade-slide-leave-active {
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.fade-slide-enter-from, .fade-slide-leave-to { opacity: 0; transform: translateY(-10px); }
</style>