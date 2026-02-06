<template>
  <div class="flex flex-col h-full w-full overflow-hidden bg-surface-1 rounded-[24px] transition-all">
    
    <div class="shrink-0 h-16 px-6 flex items-center justify-between z-20 border-b border-surface-3/10 bg-surface-1">
        <div class="flex items-center gap-4">
            <h1 class="text-2xl font-black text-surface-0 flex items-center gap-3">
                <i class="fa-solid fa-box-open text-primary-500"></i> Catálogo
            </h1>
        </div>
    </div>

    <Tabs v-model:value="activeTab" class="flex-1 flex flex-col min-h-0">
        
        <TabList :pt="{ root: { class: '!bg-surface-1 !border-b !border-surface-3/10 !px-6' }, inkbar: { class: '!bg-primary-500 !h-1 !rounded-t-full' } }">
            <Tab value="products" class="!text-surface-400 data-[p-active=true]:!text-surface-0 !font-bold !px-6 !py-4 !bg-transparent text-sm uppercase tracking-wide">Produtos</Tab>
            <Tab value="groups" class="!text-surface-400 data-[p-active=true]:!text-surface-0 !font-bold !px-6 !py-4 !bg-transparent text-sm uppercase tracking-wide">Grupos de Adicionais</Tab>
        </TabList>

        <TabPanels :pt="{ root: { class: '!bg-transparent !p-0 !flex-1 !overflow-hidden' } }">
            
            <TabPanel value="products" :pt="{ root: { class: '!h-full !flex !flex-col' } }">
                <div class="shrink-0 h-16 px-6 flex items-center justify-between z-20 border-b border-surface-3/10 bg-surface-1/50">
                    <div class="flex items-center gap-4 transition-all w-full">
                        <transition name="fade-slide" mode="out-in">
                            
                            <div v-if="selectedProducts.length === 0" class="flex items-center justify-end w-full" key="default-header">
                                <div class="flex gap-2 w-full md:w-auto md:ml-auto items-center">
                                    <div class="flex gap-2 mr-4 pr-4 border-r border-surface-3/20">
                                        <Button label="Exportar" icon="fa-solid fa-download" class="!bg-surface-2 hover:!bg-surface-3 !text-surface-300 hover:!text-surface-0 !font-bold !text-xs !h-9" @click="store.exportCSV" />
                                        <Button label="Importar" icon="fa-solid fa-upload" class="!bg-surface-2 hover:!bg-surface-3 !text-surface-300 hover:!text-surface-0 !font-bold !text-xs !h-9" @click="triggerImport" />
                                        <input type="file" ref="fileInput" class="hidden" accept=".csv" @change="handleFileChange" />
                                    </div>

                                    <div class="hidden md:block">
                                        <MultiSelect 
                                            v-model="store.categoryFilter" 
                                            :options="store.uniqueCategories" 
                                            placeholder="Categorias" 
                                            class="w-48 !bg-surface-2 !border-none !text-surface-0 h-10 flex items-center"
                                            panelClass="category-filter-panel"
                                            :pt="{ 
                                                label: { class: '!text-surface-0 !font-bold !text-sm !p-2' },
                                                trigger: { class: '!text-surface-400 !w-8' },
                                                token: { class: '!bg-primary-500 !text-surface-950' }
                                            }" 
                                        />
                                    </div>
                                    <div class="relative w-full md:w-64">
                                        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-surface-400"></i>
                                        <input v-model="store.filter" type="text" placeholder="Buscar produto..." class="w-full bg-surface-2 text-surface-0 pl-10 pr-4 h-10 rounded-full font-bold focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all placeholder:text-surface-500" />
                                    </div>
                                    <Button icon="fa-solid fa-rotate" text rounded class="!text-surface-400 hover:!text-surface-0" @click="store.fetchProducts" />
                                </div>
                            </div>

                            <div v-else class="flex items-center justify-between w-full bg-primary-500/10 rounded-[16px] px-4 py-2 backdrop-blur-md" key="bulk-header">
                                <div class="flex items-center gap-3 overflow-hidden shrink-0">
                                    <Button icon="fa-solid fa-xmark" text rounded class="!text-surface-300 hover:!text-surface-0" @click="selectedProducts = []" />
                                    <span class="font-black text-primary-400 whitespace-nowrap text-lg">{{ selectedProducts.length }} selecionados</span>
                                </div>

                                <div class="flex gap-2 overflow-x-auto scrollbar-hide items-center">
                                    <span class="text-[10px] font-bold text-surface-400 uppercase mr-2">Mover para:</span>
                                    
                                    <Button 
                                        v-for="coz in cozinhas" 
                                        :key="coz.id"
                                        :label="coz.name" 
                                        icon="fa-solid fa-fire-burner" 
                                        size="small" 
                                        class="!bg-surface-800 hover:!bg-surface-700 !text-surface-200 hover:!text-surface-0 !border-none !text-xs !font-black whitespace-nowrap"
                                        @click="applyBulk('station', coz.id)" 
                                    />

                                    <div class="w-px h-4 bg-surface-3 mx-2"></div>
                                    <Button label="Fidelidade" icon="fa-solid fa-gem" size="small" class="!bg-yellow-500/20 !text-yellow-400 hover:!bg-yellow-500 hover:!text-surface-0 !border-none !text-xs !font-black" @click="applyBulk('is_loyalty', true)" />
                                    <Button label="Status: Ativo" icon="fa-solid fa-check" size="small" class="!bg-green-500/20 !text-green-400 hover:!bg-green-500 hover:!text-surface-0 !border-none !text-xs !font-black" @click="applyBulk('availability', 'disponivel')" />
                                </div>
                            </div>
                        </transition>
                    </div>
                </div>

                <div class="flex-1 overflow-hidden relative p-6 pt-0">
                    <div class="bg-surface-2 rounded-[24px] h-full overflow-hidden flex flex-col">
                        <DataTable 
                            :value="store.filteredProducts" 
                            v-model:selection="selectedProducts" 
                            dataKey="id" 
                            :scrollable="true" 
                            scrollHeight="flex" 
                            removableSort 
                            paginator 
                            :rows="50"
                            class="p-datatable-sm text-sm h-full"
                            :pt="{
                                root: { class: 'h-full flex flex-col' },
                                headerRow: { class: '!text-surface-400 bg-surface-2 !text-xs !uppercase !border-b !border-surface-3/20' },
                                bodyRow: ({ context }) => ({ class: ` hover:!bg-surface-3 !text-surface-300 transition-colors border-b border-surface-3/10 last:border-0 ${context.selected ? '!bg-primary-500/10' : ''}` }),
                                bodyCell: { class: '!py-2' },
                                wrapper: { class: '!flex-1 !overflow-y-auto' }
                            }"
                        >
                            <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>
                            
                            <Column field="name" header="Produto" sortable>
                                <template #body="slotProps">
                                    <div class="flex items-center gap-2 min-w-0 cursor-pointer py-1" @click="openEdit(slotProps.data)">
                                        <i v-if="slotProps.data.is_loyalty" class="fa-solid fa-gem text-yellow-500 text-[10px]" v-tooltip.top="'Disponível para resgate'"></i>
                                        <i v-if="slotProps.data.manage_stock && slotProps.data.stock_quantity <= slotProps.data.stock_min" class="fa-solid fa-triangle-exclamation text-red-500 animate-pulse text-[10px]" v-tooltip.top="'Estoque Baixo'"></i>
                                        <span class="font-bold text-surface-200 truncate" :class="{'text-red-400': slotProps.data.manage_stock && slotProps.data.stock_quantity <= slotProps.data.stock_min}">{{ slotProps.data.name }}</span>
                                    </div>
                                </template>
                            </Column>
                            
                            <Column field="category_name" header="Categoria" sortable class="text-surface-400 font-bold"></Column>
                            
                            <Column field="station" header="Cozinha" sortable>
                                <template #body="slotProps">
                                    <Tag :value="getStationLabel(slotProps.data.station)" class="!text-[9px] !uppercase !font-black !px-2 !py-0.5 !rounded-full" :class="getStationClass(slotProps.data.station)" />
                                </template>
                            </Column>

                            <Column header="Estoque" sortable field="stock_quantity">
                                <template #body="slotProps">
                                    <div v-if="slotProps.data.manage_stock" class="flex flex-col">
                                        <span class="font-black text-xs" :class="slotProps.data.stock_quantity <= slotProps.data.stock_min ? 'text-red-400' : 'text-surface-200'">{{ slotProps.data.stock_quantity }} un</span>
                                        <span v-if="slotProps.data.stock_quantity <= slotProps.data.stock_min" class="text-[8px] font-bold text-red-500 uppercase">Repor</span>
                                    </div>
                                    <span v-else class="text-surface-500 text-[10px] italic">Livre</span>
                                </template>
                            </Column>

                            <Column field="price" header="Preço" sortable>
                                <template #body="slotProps">
                                    <span class="font-black text-surface-0 px-2 py-1 rounded-lg">{{ formatCurrency(slotProps.data.price) }}</span>
                                </template>
                            </Column>
                            
                            <Column field="availability" header="Status" sortable>
                                <template #body="slotProps">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full" :class="slotProps.data.availability === 'disponivel' ? 'bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.5)]' : (slotProps.data.availability === 'oculto' ? 'bg-surface-500' : 'bg-red-500')"></span>
                                        <span class="text-[10px] font-bold uppercase text-surface-400">{{ slotProps.data.availability === 'disponivel' ? 'Ativo' : (slotProps.data.availability === 'oculto' ? 'Oculto' : 'Esgotado') }}</span>
                                    </div>
                                </template>
                            </Column>
                            
                            <Column headerStyle="width: 3rem">
                                <template #body="slotProps">
                                    <Button icon="fa-solid fa-pen" text rounded class="!text-surface-500 hover:!text-surface-0 !w-8 !h-8" @click="openEdit(slotProps.data)" />
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </TabPanel>

            <TabPanel value="groups" :pt="{ root: { class: '!h-full !flex !flex-col' } }">
                <div class="shrink-0 h-16 px-6 flex items-center justify-between z-20 border-b border-surface-3/10 bg-surface-1/50">
                    <div class="flex items-center gap-4 w-full justify-end">
                        <div class="relative w-full md:w-64">
                            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-surface-400"></i>
                            <input v-model="groupSearch" type="text" placeholder="Buscar grupo..." class="w-full bg-surface-2 text-surface-0 pl-10 pr-4 h-10 rounded-full font-bold focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all placeholder:text-surface-500" />
                        </div>
                        <Button icon="fa-solid fa-rotate" text rounded class="!text-surface-400 hover:!text-surface-0" @click="fetchGroups" />
                    </div>
                </div>

                <div class="flex-1 overflow-hidden relative p-6 pt-0">
                    <div class="bg-surface-2 rounded-[24px] h-full overflow-hidden flex flex-col">
                        
                        <div v-if="loadingGroups" class="flex items-center justify-center h-full">
                            <i class="fa-solid fa-circle-notch fa-spin text-3xl text-primary-500"></i>
                        </div>
                        
                        <div v-else-if="filteredGroups.length === 0" class="flex flex-col items-center justify-center h-full text-surface-500 opacity-60">
                            <i class="fa-solid fa-layer-group text-4xl mb-3"></i>
                            <p class="font-bold">Nenhum grupo encontrado.</p>
                        </div>

                        <DataTable 
                            v-else
                            :value="filteredGroups" 
                            dataKey="id" 
                            :scrollable="true" 
                            scrollHeight="flex" 
                            class="p-datatable-sm text-sm h-full"
                            :pt="{
                                root: { class: 'h-full flex flex-col' },
                                headerRow: { class: '!text-surface-400 bg-surface-2 !text-xs !uppercase !border-b !border-surface-3/20' },
                                bodyRow: { class: 'hover:!bg-surface-3 !text-surface-300 transition-colors border-b border-surface-3/10 last:border-0' },
                                bodyCell: { class: '!py-3' },
                                wrapper: { class: '!flex-1 !overflow-y-auto' }
                            }"
                        >
                            <Column field="name" header="Grupo de Adicionais" sortable>
                                <template #body="{data}">
                                    <div class="flex items-center gap-2 cursor-pointer" @click="openGroupEdit(data)">
                                        <i class="fa-solid fa-layer-group text-primary-500 text-xs"></i>
                                        <span class="font-bold text-surface-100">{{ data.name }}</span>
                                        <span class="text-[9px] text-surface-500 ml-2">ID: {{ data.id }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Disponibilidade" class="w-32 text-center">
                                <template #body="{data}">
                                    <span v-if="data.is_active" class="text-green-400 text-[10px] font-black uppercase bg-green-500/10 px-2 py-1 rounded-full border border-green-500/20">Ativo</span>
                                    <span v-else class="text-red-400 text-[10px] font-black uppercase bg-red-500/10 px-2 py-1 rounded-full border border-red-500/20">Inativo</span>
                                </template>
                            </Column>

                            <Column header="Visibilidade" class="w-48 text-center">
                                <template #body="{data}">
                                    <div class="flex justify-center gap-2">
                                        <i class="fa-solid fa-motorcycle" :class="data.show_delivery ? 'text-blue-400' : 'text-surface-600 opacity-30'" v-tooltip.top="'Delivery'"></i>
                                        <i class="fa-solid fa-utensils" :class="data.show_table ? 'text-orange-400' : 'text-surface-600 opacity-30'" v-tooltip.top="'Mesa'"></i>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Qtd." class="w-24 text-center">
                                <template #body="{data}">
                                    <span v-if="data.enable_qty" class="text-primary-400 font-bold text-[10px] flex items-center justify-center gap-1 bg-primary-500/10 px-2 py-0.5 rounded-full">
                                        <i class="fa-solid fa-hashtag"></i> Multi
                                    </span>
                                    <span v-else class="text-surface-600 text-[10px]">-</span>
                                </template>
                            </Column>

                            <Column headerStyle="width: 3rem">
                                <template #body="{data}">
                                    <Button icon="fa-solid fa-pen" text rounded class="!text-surface-500 hover:!text-surface-0 !w-8 !h-8" @click="openGroupEdit(data)" />
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </TabPanel>

        </TabPanels>
    </Tabs>

    <ProductEditDrawer v-model:visible="showDrawer" :product="selectedProduct" @saved="store.fetchProducts" />
    
    <Dialog v-model:visible="groupEdit.visible" modal header="Configurar Grupo" :style="{ width: '400px' }"
        :pt="{ root: { class: '!bg-surface-1 !border-none !rounded-[24px]' }, header: { class: '!bg-transparent !p-6 !text-surface-0' }, content: { class: '!bg-transparent !p-6' } }">
        
        <div class="flex flex-col gap-6" v-if="groupEdit.data">
            <div class="text-center">
                <h3 class="text-lg font-black text-surface-0">{{ groupEdit.data.name }}</h3>
                <p class="text-xs text-surface-400 font-bold">Gerenciamento de exibição</p>
            </div>

            <div class="bg-surface-2 rounded-[20px] p-2 space-y-1">
                <div class="flex items-center justify-between p-3 hover:bg-surface-3 rounded-xl transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-green-500/10 flex items-center justify-center text-green-500"><i class="fa-solid fa-power-off"></i></div>
                        <span class="font-bold text-surface-200 text-sm">Disponível</span>
                    </div>
                    <ToggleSwitch v-model="groupEdit.data.is_active" />
                </div>

                <div class="flex items-center justify-between p-3 hover:bg-surface-3 rounded-xl transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-blue-500/10 flex items-center justify-center text-blue-500"><i class="fa-solid fa-motorcycle"></i></div>
                        <span class="font-bold text-surface-200 text-sm">Exibir no Delivery</span>
                    </div>
                    <ToggleSwitch v-model="groupEdit.data.show_delivery" />
                </div>

                <div class="flex items-center justify-between p-3 hover:bg-surface-3 rounded-xl transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-orange-500/10 flex items-center justify-center text-orange-500"><i class="fa-solid fa-utensils"></i></div>
                        <span class="font-bold text-surface-200 text-sm">Exibir na Mesa</span>
                    </div>
                    <ToggleSwitch v-model="groupEdit.data.show_table" />
                </div>

                <div class="flex items-center justify-between p-3 hover:bg-surface-3 rounded-xl transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary-500/10 flex items-center justify-center text-primary-500"><i class="fa-solid fa-hashtag"></i></div>
                        <div class="flex flex-col">
                            <span class="font-bold text-surface-200 text-sm">Habilitar Quantidade</span>
                            <span class="text-[9px] text-surface-500">Permitir múltiplos do mesmo item</span>
                        </div>
                    </div>
                    <ToggleSwitch v-model="groupEdit.data.enable_qty" />
                </div>
            </div>

            <Button label="Salvar Alterações" icon="fa-solid fa-check" class="!bg-primary-500 hover:!bg-primary-400 !border-none !font-black !rounded-full !h-12 !text-surface-950 shadow-lg" :loading="groupEdit.saving" @click="saveGroup" />
        </div>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, onMounted, reactive, computed, watch } from 'vue';
import { useProductsStore } from '@/stores/products-store';
import { useFormat } from '@/composables/useFormat'; 
import api from '@/services/api';
import { notify } from '@/services/notify';

import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import MultiSelect from 'primevue/multiselect';
import Dialog from 'primevue/dialog';
import ToggleSwitch from 'primevue/toggleswitch';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import ProductEditDrawer from '@/components/manager/ProductEditDrawer.vue';

const store = useProductsStore();
const { formatCurrency } = useFormat(); 
const activeTab = ref('products'); 
const showDrawer = ref(false);
const selectedProduct = ref(null);
const selectedProducts = ref([]);
const fileInput = ref(null);
const cozinhas = ref([]);

// -- LÓGICA DE GRUPOS --
const groups = ref([]);
const loadingGroups = ref(false);
const groupSearch = ref('');
const groupEdit = reactive({ visible: false, data: null, saving: false });

watch(activeTab, (newVal) => {
    if (newVal === 'groups') {
        fetchGroups();
    }
});

const openEdit = (prod) => { selectedProduct.value = prod; showDrawer.value = true; };

const fetchCozinhas = async () => {
    try {
        const { data } = await api.get('/logistica/cozinhas');
        if (data.success) cozinhas.value = data.cozinhas;
    } catch (e) { console.error('Erro cozinhas', e); }
};

const applyBulk = async (field, value) => {
    if (!selectedProducts.value.length) return;
    if (!confirm(`Alterar ${selectedProducts.value.length} produtos?`)) return;
    const ids = selectedProducts.value.map(p => p.id);
    const payload = {}; payload[field] = value; 
    if (await store.bulkUpdateProducts(ids, payload)) selectedProducts.value = [];
};

const triggerImport = () => { fileInput.value.click(); };
const handleFileChange = async (event) => {
    const file = event.target.files[0];
    if (file && confirm(`Importar "${file.name}"?`)) await store.importCSV(file);
    event.target.value = '';
};

const getStationLabel = (id) => {
    if (!id || id == 0) return '---';
    const found = cozinhas.value.find(c => c.id == id);
    if (found) return found.name;
    if (id === 'frente') return 'Frente (L)'; 
    if (id === 'fundos') return 'Fundos (L)';
    return 'Desconhecida';
};

const getStationClass = (id) => {
    const num = parseInt(id) || 0;
    return num % 2 === 0 ? '!bg-blue-500/10 !text-blue-400' : '!bg-orange-500/10 !text-orange-400'; 
};

// -- FUNÇÕES DE GRUPOS DE ADICIONAIS --

const fetchGroups = async () => {
    loadingGroups.value = true;
    try {
        const { data } = await api.get('/products/modifier-groups');
        
        if(data.success) {
            groups.value = data.groups.map(g => ({
                id: g.id,
                name: g.name,
                is_active: g.is_active !== false, 
                show_delivery: g.show_delivery !== false,
                show_table: g.show_table !== false,
                enable_qty: !!g.enable_qty // Mapeia flag da API
            }));
        }
    } catch(e) {
        console.error("Erro ao buscar grupos", e);
        notify('error', 'Erro', 'Falha ao buscar grupos.');
    } finally {
        loadingGroups.value = false;
    }
};

const filteredGroups = computed(() => {
    if(!groupSearch.value) return groups.value;
    const term = groupSearch.value.toLowerCase();
    return groups.value.filter(g => g.name.toLowerCase().includes(term));
});

const openGroupEdit = (group) => {
    groupEdit.data = { ...group };
    groupEdit.visible = true;
};

const saveGroup = async () => {
    groupEdit.saving = true;
    try {
        const { data } = await api.post(`/products/modifier-groups/${groupEdit.data.id}`, groupEdit.data);
        
        if(data.success) {
            notify('success', 'Salvo', 'Grupo atualizado.');
            const idx = groups.value.findIndex(g => g.id === groupEdit.data.id);
            if(idx !== -1) groups.value[idx] = { ...groupEdit.data };
            groupEdit.visible = false;
        }
    } catch(e) {
        notify('error', 'Erro', 'Falha ao salvar grupo.');
    } finally {
        groupEdit.saving = false;
    }
};

onMounted(() => { 
    store.fetchProducts();
    fetchCozinhas();
    if (activeTab.value === 'groups') fetchGroups();
});
</script>

<style>
.category-filter-panel {
    background-color: #292524 !important; 
    border: 1px solid rgba(255,255,255,0.1) !important;
    border-radius: 16px !important;
    box-shadow: 0 10px 40px rgba(0,0,0,0.5) !important;
    overflow: hidden !important;
    margin-top: 4px;
}

.category-filter-panel .p-multiselect-header {
    background-color: #292524 !important;
    border-bottom: 1px solid rgba(255,255,255,0.1) !important;
    padding: 0.75rem !important;
    color: #e7e5e4 !important;
}

.category-filter-panel .p-multiselect-item {
    color: #e7e5e4 !important;
    padding: 0.75rem 1rem !important;
    transition: background-color 0.2s !important;
    font-size: 0.875rem !important;
}

.category-filter-panel .p-multiselect-item:hover,
.category-filter-panel .p-multiselect-item.p-focus {
    background-color: #44403c !important; 
    color: #ffffff !important;
}

.category-filter-panel .p-multiselect-item.p-highlight {
    background-color: #3b82f6 !important; 
    color: #ffffff !important;
}

.category-filter-panel .p-checkbox-box {
    border-color: rgba(255,255,255,0.3) !important;
    background: transparent !important;
    border-radius: 6px !important;
}

.category-filter-panel .p-highlight .p-checkbox-box {
    border-color: #3b82f6 !important;
    background: #3b82f6 !important;
}

.fade-slide-enter-active, .fade-slide-leave-active { transition: all 0.3s ease; }
.fade-slide-enter-from, .fade-slide-leave-to { opacity: 0; transform: translateY(-10px); }
</style>