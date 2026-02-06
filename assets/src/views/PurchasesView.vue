<template>
  <div class="flex flex-col h-full w-full bg-surface-1 rounded-[24px] overflow-hidden relative transition-all">
    
    <div class="shrink-0 min-h-[80px] px-6 py-4 flex items-center justify-between z-20 bg-surface-1 relative border-b border-surface-3/10">
        <div class="flex flex-col justify-center">
            <div class="flex items-center gap-3 mb-1">
                <div class="w-10 h-10 rounded-full bg-surface-2 flex items-center justify-center text-primary-500 shadow-inner border border-surface-3/10">
                    <i class="fa-solid fa-cart-shopping"></i>
                </div>
                <h1 class="text-2xl font-black text-surface-0 tracking-tight leading-none">Compras</h1>
            </div>
            <p class="text-surface-400 text-xs font-bold pl-1">Gestão de Insumos</p>
        </div>
        
        <div class="flex gap-2">
            <button 
                class="h-10 px-4 rounded-full bg-surface-2 hover:bg-surface-3 text-surface-200 hover:text-surface-0 font-bold text-xs transition-all flex items-center gap-2 border border-surface-3/10 active:scale-95"
                @click="openManageModal"
            >
                <i class="fa-solid fa-pen-to-square"></i>
                <span class="hidden md:inline">Gerenciar</span>
            </button>

            <div class="w-px h-6 bg-surface-3 mx-1 self-center"></div>
            
            <button 
                class="w-10 h-10 rounded-full bg-surface-2 hover:bg-surface-3 text-surface-200 hover:text-surface-0 flex items-center justify-center transition-all border border-surface-3/10 active:scale-90"
                v-tooltip.bottom="'Novo Item'"
                @click="openItemModal"
            >
                <i class="fa-solid fa-box-open"></i>
            </button>
            
            <button 
                class="w-10 h-10 rounded-full bg-surface-2 hover:bg-surface-3 text-surface-200 hover:text-surface-0 flex items-center justify-center transition-all border border-surface-3/10 active:scale-90"
                v-tooltip.bottom="'Novo Fornecedor'"
                @click="openSupplierModal"
            >
                <i class="fa-solid fa-store"></i>
            </button>
        </div>
    </div>

    <div class="px-4 pb-2 pt-2 bg-surface-1 shrink-0 z-10 flex justify-center">
        <div class="bg-surface-2 p-1.5 rounded-full flex relative shadow-inner border border-surface-3/10 w-fit">
            
            <div 
                class="absolute top-1.5 w-10 h-10 bg-primary-500 rounded-full shadow-lg shadow-primary-500/20 transition-all duration-300 ease-[cubic-bezier(0.2,0,0,1)] z-0"
                :style="tabIndicatorStyle"
            ></div>

            <button 
                v-for="tab in tabs" :key="tab.id"
                class="w-14 h-10 flex items-center justify-center relative z-10 transition-colors select-none group rounded-full"
                :class="activeTab === tab.id ? 'text-surface-950' : 'text-surface-400 hover:text-surface-200'"
                @click="activeTab = tab.id"
                v-tooltip.bottom="tab.label"
            >
                <div class="relative flex items-center justify-center w-full h-full">
                    <i :class="[tab.icon, activeTab === tab.id ? 'scale-110' : '']" class="text-lg transition-transform duration-300"></i>
                    
                    <transition name="scale">
                        <span v-if="getTabCount(tab.id) > 0" 
                              class="absolute -top-1 -right-1 px-1.5 py-0.5 rounded-full text-[9px] font-black transition-colors min-w-[16px] border-2 flex items-center justify-center leading-none h-4"
                              :class="activeTab === tab.id 
                                ? 'bg-surface-950 text-white border-surface-950' 
                                : 'bg-primary-500 text-surface-950 border-surface-2'">
                            {{ getTabCount(tab.id) }}
                        </span>
                    </transition>
                </div>
            </button>
        </div>
    </div>

    <div class="flex-1 overflow-hidden flex flex-col relative bg-surface-1">
        <div v-if="store.isLoading" class="absolute inset-0 flex items-center justify-center bg-surface-1 z-50">
            <i class="fa-solid fa-circle-notch fa-spin text-4xl text-primary-500"></i>
        </div>

        <div v-if="activeTab === 'checklist'" class="flex-1 overflow-y-auto p-4 md:p-6 pb-24 scrollbar-thin animate-fade-in">
            <div class="max-w-5xl mx-auto space-y-6">
                
                <div v-if="store.missingLibrary.length > 0" class="animate-fade-in">
                    <h3 class="text-[10px] font-black text-orange-400 uppercase tracking-widest mb-3 flex items-center gap-2 pl-1">
                        <i class="fa-solid fa-triangle-exclamation"></i> Em Falta (Comprar)
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <button 
                            v-for="item in store.missingLibrary" :key="item.id"
                            class="pl-4 pr-3 py-2 rounded-full bg-orange-500/10 border border-orange-500/30 text-orange-200 hover:bg-orange-500 hover:text-surface-950 transition-all font-bold text-xs flex items-center gap-2 group active:scale-95 shadow-sm"
                            @click="store.toggleNeed(item)"
                        >
                            <span class="flex-1">{{ item.name }}</span>
                            <div class="w-5 h-5 rounded-full bg-orange-500/20 group-hover:bg-surface-950/20 flex items-center justify-center">
                                <i class="fa-solid fa-xmark text-[9px]"></i>
                            </div>
                        </button>
                    </div>
                </div>
                
                <div v-else class="text-center py-8 bg-surface-2/30 rounded-[24px] border border-dashed border-surface-3/30">
                    <div class="w-12 h-12 rounded-full bg-green-500/10 flex items-center justify-center mx-auto mb-3 text-green-500">
                        <i class="fa-solid fa-check-double text-xl"></i>
                    </div>
                    <p class="text-xs font-bold text-surface-400 uppercase tracking-wider">Estoque completo</p>
                </div>

                <div class="sticky top-0 z-10 bg-surface-1/95 backdrop-blur-md py-2 -mx-2 px-2">
                    <div class="relative group">
                        <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-surface-400 group-focus-within:text-primary-500 transition-colors"></i>
                        <input 
                            v-model="filterQuery" 
                            type="text" 
                            placeholder="Buscar item no estoque..." 
                            class="w-full bg-surface-2 text-surface-0 h-12 pl-11 pr-4 rounded-full border border-surface-3/20 focus:border-primary-500/50 focus:ring-4 focus:ring-primary-500/10 outline-none font-bold text-sm transition-all placeholder:text-surface-500 shadow-sm"
                        />
                        <button 
                            v-if="filterQuery" 
                            @click="filterQuery = ''" 
                            class="absolute right-3 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-surface-3 text-surface-400 flex items-center justify-center hover:bg-surface-4 hover:text-surface-200"
                        >
                            <i class="fa-solid fa-xmark text-[10px]"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <h3 class="text-[10px] font-black text-surface-500 uppercase tracking-widest mb-3 flex items-center gap-2 pl-1">
                        <i class="fa-solid fa-box"></i> Em Estoque (Temos)
                    </h3>
                    
                    <div class="flex flex-wrap gap-2">
                        <button 
                            v-for="item in filteredAvailable" :key="item.id"
                            class="px-4 py-2 rounded-full bg-surface-2 border border-surface-3/30 text-surface-400 hover:bg-surface-3 hover:text-surface-100 hover:border-surface-400 transition-all font-bold text-xs active:scale-95 flex items-center gap-2 shadow-sm"
                            @click="store.toggleNeed(item)"
                        >
                            <i v-if="item.type === 'product'" class="fa-solid fa-bag-shopping text-[10px] opacity-50"></i>
                            {{ item.name }}
                            <i class="fa-solid fa-plus text-[9px] opacity-30"></i>
                        </button>
                        
                        <div v-if="filteredAvailable.length === 0" class="w-full text-center py-4 text-surface-500 text-xs italic opacity-60">
                            Nenhum item encontrado para "{{ filterQuery }}".
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div v-if="activeTab === 'shopping'" class="flex-1 overflow-y-auto p-4 md:p-6 pb-24 scrollbar-thin animate-fade-in">
            <div class="max-w-3xl mx-auto space-y-6">
                
                <div v-if="(store.pendingItems || []).length === 0" class="flex flex-col items-center justify-center h-64 text-surface-500 opacity-40">
                    <i class="fa-solid fa-basket-shopping text-5xl mb-4"></i>
                    <p class="font-bold text-sm uppercase tracking-wider text-center">Lista vazia</p>
                    <button @click="activeTab = 'checklist'" class="mt-2 text-primary-500 text-xs font-black underline decoration-dashed underline-offset-4">
                        Adicionar itens
                    </button>
                </div>

                <div v-for="loc in store.locations" :key="loc.id">
                    <div v-if="store.itemsByLocation(loc.id).length > 0" class="animate-fade-in">
                        
                        <div class="flex items-center justify-between mb-3 px-1 sticky top-0 bg-surface-1/95 backdrop-blur-sm z-10 py-2">
                            <div class="flex items-center gap-2">
                                <div class="w-1.5 h-1.5 rounded-full bg-primary-500 shadow-[0_0_8px_rgba(var(--primary-500-rgb),0.8)]"></div>
                                <h3 class="text-xs font-black text-surface-0 uppercase tracking-widest">{{ loc.name }}</h3>
                                <i v-if="loc.type === 'virtual'" class="fa-solid fa-globe text-[10px] text-purple-400 ml-1" title="Online"></i>
                            </div>
                            <span class="text-[10px] font-bold text-surface-500 bg-surface-2 px-2 py-0.5 rounded-md">
                                {{ store.itemsByLocation(loc.id).length }} itens
                            </span>
                        </div>

                        <div class="flex flex-col gap-2">
                            <div 
                                v-for="item in store.itemsByLocation(loc.id)" 
                                :key="item.id" 
                                class="flex items-center gap-3 p-3 rounded-2xl bg-surface-2 border border-surface-3/10 shadow-sm transition-all hover:border-surface-3/30 group"
                            >
                                <button 
                                    class="w-10 h-10 rounded-full bg-surface-1 border border-surface-3/30 flex items-center justify-center transition-all shrink-0 text-surface-400 hover:text-green-500 hover:border-green-500 hover:bg-green-500/10 active:scale-90"
                                    @click="buyItem(item, loc.id)"
                                    v-tooltip.right="'Comprar'"
                                >
                                    <i class="fa-solid fa-check text-sm"></i>
                                </button>

                                <div class="flex-1 min-w-0 flex flex-col">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm text-surface-100 leading-tight truncate">{{ item.name }}</span>
                                        <a v-if="item.url" :href="item.url" target="_blank" class="w-5 h-5 rounded-full bg-surface-3 flex items-center justify-center text-blue-400 hover:bg-blue-500 hover:text-white transition-colors" @click.stop>
                                            <i class="fa-solid fa-link text-[9px]"></i>
                                        </a>
                                    </div>
                                </div>

                                <div class="px-3 py-1.5 rounded-lg bg-surface-1 border border-surface-3/20 shrink-0">
                                    <span class="text-xs font-black text-surface-300">
                                        {{ item.qty }} <span class="text-[9px] uppercase opacity-70">{{ item.unit }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="store.unassignedItems.length > 0" class="animate-fade-in pt-4">
                    <div class="p-4 rounded-2xl bg-surface-2/30 border border-dashed border-surface-3/30">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-black text-surface-500 uppercase tracking-widest flex items-center gap-2">
                                <i class="fa-regular fa-circle-question"></i> Sem Fornecedor
                            </h3>
                            <button class="text-[10px] text-primary-400 font-bold hover:underline decoration-dashed" @click="openManageModal">
                                Vincular
                            </button>
                        </div>
                        <div class="flex flex-wrap gap-2 opacity-60">
                            <span v-for="item in store.unassignedItems" :key="item.id" class="px-2 py-1 rounded bg-surface-3 text-[10px] font-bold text-surface-400">
                                {{ item.name }}
                            </span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <Dialog v-model:visible="itemModal.visible" modal header="Cadastrar Insumo" :style="{ width: '400px', maxWidth: '90vw' }" 
            :pt="{ 
                root: { class: '!bg-surface-2 !border-none !rounded-[28px] !shadow-2xl' }, 
                header: { class: '!bg-transparent !p-6 !pb-2 !text-surface-0 !border-none' }, 
                content: { class: '!bg-transparent !p-6' },
                mask: { class: '!bg-surface-base/80 backdrop-blur-sm' }
            }">
        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-2">
                <label class="text-[10px] font-black text-surface-400 uppercase tracking-widest pl-1">Nome do Item</label>
                <InputText v-model="itemModal.name" placeholder="Ex: Detergente 5L" class="!bg-surface-1 !border-none !text-surface-0 !h-12 !rounded-2xl !pl-4 font-bold focus:!ring-2 focus:!ring-primary-500/50" />
            </div>
            <div class="flex flex-col gap-2">
                <label class="text-[10px] font-black text-surface-400 uppercase tracking-widest pl-1">Disponível em:</label>
                <MultiSelect 
                    v-model="itemModal.locations" :options="store.locations" optionLabel="name" optionValue="id" placeholder="Selecione locais..." 
                    class="w-full !bg-surface-1 !border-none !h-12 flex items-center !rounded-2xl !pl-2" appendTo="self" panelClass="purchase-multiselect-panel"
                    :pt="{ label: { class: '!text-surface-0 !font-bold !text-sm' }, token: { class: '!bg-primary-500 !text-surface-950 !rounded-lg' } }"
                />
            </div>
            <div v-if="hasVirtualLocation(itemModal.locations)" class="flex flex-col gap-2 animate-fade-in">
                <label class="text-[10px] font-black text-purple-400 uppercase tracking-widest flex items-center gap-1 pl-1"><i class="fa-solid fa-link"></i> Link Padrão</label>
                <InputText v-model="itemModal.url" placeholder="https://..." class="!bg-surface-1 !border-none !text-surface-0 !h-12 !rounded-2xl !pl-4 font-medium !text-sm" />
            </div>
            <Button label="SALVAR" icon="fa-solid fa-check" class="!bg-primary-600 hover:!bg-primary-500 !border-none !w-full !font-black !h-12 !rounded-full !mt-2 !text-sm shadow-lg shadow-primary-900/20 active:scale-[0.98]" @click="saveItem" :disabled="!itemModal.name" />
        </div>
    </Dialog>

    <Dialog v-model:visible="supplierModal.visible" modal header="Novo Fornecedor" :style="{ width: '350px', maxWidth: '90vw' }" 
            :pt="{ root: { class: '!bg-surface-2 !border-none !rounded-[28px]' }, header: { class: '!bg-transparent !p-6 !pb-2 !text-surface-0' }, content: { class: '!bg-transparent !p-6' }, mask: { class: '!bg-surface-base/80 backdrop-blur-sm' } }">
        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-2">
                <label class="text-[10px] font-black text-surface-400 uppercase tracking-widest pl-1">Nome do Local</label>
                <InputText v-model="supplierModal.name" placeholder="Ex: Mercado Livre" class="!bg-surface-1 !border-none !text-surface-0 !h-12 !rounded-2xl !pl-4 font-bold" />
            </div>
            <div class="flex gap-2">
                <button class="flex-1 py-3 rounded-xl text-xs font-black uppercase border-2 transition-all active:scale-95" :class="supplierModal.type === 'physical' ? 'bg-primary-500/10 border-primary-500 text-primary-400' : 'bg-surface-1 border-transparent text-surface-400'" @click="supplierModal.type = 'physical'">
                    <i class="fa-solid fa-store mr-1"></i> Físico
                </button>
                <button class="flex-1 py-3 rounded-xl text-xs font-black uppercase border-2 transition-all active:scale-95" :class="supplierModal.type === 'virtual' ? 'bg-purple-500/10 border-purple-500 text-purple-400' : 'bg-surface-1 border-transparent text-surface-400'" @click="supplierModal.type = 'virtual'">
                    <i class="fa-solid fa-globe mr-1"></i> Virtual
                </button>
            </div>
            <Button label="CRIAR" icon="fa-solid fa-plus" class="!bg-primary-600 hover:!bg-primary-500 !border-none !w-full !font-black !h-12 !rounded-full !mt-2 shadow-lg" @click="saveSupplier" :disabled="!supplierModal.name" />
        </div>
    </Dialog>

    <Dialog v-model:visible="manageModal.visible" modal header="Gerenciar Item" :style="{ width: '400px', maxWidth: '90vw' }" 
            :pt="{ root: { class: '!bg-surface-2 !border-none !rounded-[28px]' }, header: { class: '!bg-transparent !p-6 !pb-2 !text-surface-0' }, content: { class: '!bg-transparent !p-6' }, mask: { class: '!bg-surface-base/80 backdrop-blur-sm' } }">
        <div class="flex flex-col gap-5">
            <div class="bg-primary-500/10 border border-primary-500/20 p-3 rounded-2xl flex gap-3 items-start">
                <i class="fa-solid fa-circle-info text-primary-500 mt-0.5 text-sm"></i>
                <p class="text-[10px] text-surface-300 font-bold leading-relaxed">
                    Edite as propriedades de um insumo ou configure um produto Grab & Go para compras.
                </p>
            </div>
            <div class="flex flex-col gap-2">
                <label class="text-[10px] font-black text-surface-400 uppercase tracking-widest pl-1">Buscar Item</label>
                <AutoComplete 
                    v-model="manageModal.query" :suggestions="suggestions" @complete="searchInsumo" @item-select="onManageSelect" optionLabel="name" placeholder="Digite para buscar..." class="w-full"
                    :pt="{ input: { class: '!bg-surface-1 !border-none !text-surface-0 !h-12 !rounded-2xl !w-full !pl-4 font-bold !text-sm focus:!ring-2 focus:!ring-primary-500/50 placeholder:!text-surface-500' }, panel: { class: '!bg-surface-3 !border-none !rounded-xl !shadow-xl' }, item: { class: '!text-surface-200 hover:!bg-surface-4 !text-sm !font-bold !p-2 !rounded-lg !m-1' } }"
                />
            </div>
            <div v-if="manageModal.selectedItem" class="animate-fade-in flex flex-col gap-5 pt-2">
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-black text-surface-400 uppercase tracking-widest pl-1">Fornecedores</label>
                    <MultiSelect 
                        v-model="manageModal.locations" :options="store.locations" optionLabel="name" optionValue="id" placeholder="Selecione locais..." 
                        class="w-full !bg-surface-1 !border-none !h-12 flex items-center !rounded-2xl !pl-2" appendTo="self" panelClass="purchase-multiselect-panel"
                        :pt="{ label: { class: '!text-surface-0 !font-bold !text-sm' }, token: { class: '!bg-primary-500 !text-surface-950 !rounded-lg' } }"
                    />
                </div>
                <div v-if="hasVirtualLocation(manageModal.locations)" class="flex flex-col gap-2 animate-fade-in">
                    <label class="text-[10px] font-black text-purple-400 uppercase tracking-widest flex items-center gap-1 pl-1"><i class="fa-solid fa-link"></i> Link Padrão</label>
                    <InputText v-model="manageModal.url" placeholder="https://..." class="!bg-surface-1 !border-none !text-surface-0 !h-12 !rounded-2xl !pl-4 font-medium !text-sm" />
                </div>
                <Button label="SALVAR ALTERAÇÕES" icon="fa-solid fa-save" class="!bg-green-600 hover:!bg-green-500 !border-none !w-full !font-black !h-12 !rounded-full !mt-2 !text-sm shadow-lg active:scale-[0.98]" @click="saveManagedItem" />
            </div>
        </div>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { usePurchasesStore } from '@/stores/purchases-store';
import { useFormat } from '@/composables/useFormat';
import api from '@/services/api';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import AutoComplete from 'primevue/autocomplete';
import { notify } from '@/services/notify';

const store = usePurchasesStore();
const activeTab = ref('checklist');
const suggestions = ref([]);
const filterQuery = ref('');

// --- NOVA CONFIGURAÇÃO DE TABS ---
const tabs = [
    { id: 'checklist', label: 'Planejamento', icon: 'fa-solid fa-clipboard-list' },
    { id: 'shopping', label: 'Lista de Compras', icon: 'fa-solid fa-cart-arrow-down' }
];

const getTabCount = (tabId) => {
    if (tabId === 'shopping') return store.pendingItems?.length || 0;
    return 0;
};

// Lógica de Posição da Bolinha (Centralizada)
const tabIndicatorStyle = computed(() => {
    const index = tabs.findIndex(t => t.id === activeTab.value);
    const itemWidth = 56; // w-14
    return {
        transform: `translateX(${index * itemWidth + 8}px)` // 8px para centralizar a bolinha de 40px no botão de 56px
    };
});

// Computed para Filtragem do Estoque
const filteredAvailable = computed(() => {
    if (!filterQuery.value) return store.availableLibrary;
    const q = filterQuery.value.toLowerCase();
    return store.availableLibrary.filter(item => item.name.toLowerCase().includes(q));
});

// Modais
const itemModal = reactive({ visible: false, name: '', locations: [], url: '' });
const supplierModal = reactive({ visible: false, name: '', type: 'physical' });
const manageModal = reactive({ visible: false, query: '', selectedItem: null, locations: [], url: '' });

onMounted(() => {
    store.fetchList();
});

const hasVirtualLocation = (selectedIds) => {
    return selectedIds.some(locId => {
        const loc = store.locations.find(l => l.id === locId);
        return loc && loc.type === 'virtual';
    });
};

const openItemModal = () => {
    itemModal.name = ''; itemModal.locations = []; itemModal.url = '';
    itemModal.visible = true;
};
const saveItem = async () => {
    const success = await store.registerItem({ name: itemModal.name, locations: itemModal.locations, url: itemModal.url, qty: 1 });
    if (success) itemModal.visible = false;
};

const openSupplierModal = () => {
    supplierModal.name = ''; supplierModal.type = 'physical';
    supplierModal.visible = true;
};
const saveSupplier = async () => {
    const success = await store.createLocation(supplierModal.name, supplierModal.type);
    if (success) supplierModal.visible = false;
};

const openManageModal = () => {
    manageModal.query = ''; manageModal.selectedItem = null; manageModal.locations = []; manageModal.url = '';
    manageModal.visible = true;
};
const searchInsumo = async (event) => {
    const query = event.query.toLowerCase();
    suggestions.value = store.library.filter(item => item.name.toLowerCase().includes(query));
};
const onManageSelect = (event) => {
    const item = event.value;
    manageModal.selectedItem = item;
    manageModal.locations = item.valid_locations || [];
    manageModal.url = ''; 
};
const saveManagedItem = async () => {
    if (!manageModal.selectedItem) return;
    const success = await store.registerItem({
        name: manageModal.selectedItem.name,
        locations: manageModal.locations,
        url: manageModal.url,
        qty: 1
    });
    if (success) {
        notify('success', 'Atualizado', 'Propriedades do item salvas.');
        manageModal.visible = false;
    }
};

const buyItem = async (item, locationId) => {
    const cost = prompt(`Valor pago por ${item.name}?`, "0.00");
    if (cost === null) return;
    await store.buyItem(item.id, locationId, parseFloat(cost.replace(',', '.')));
};
</script>

<style scoped>
.animate-fade-in { animation: fadeIn 0.3s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
.scale-enter-active, .scale-leave-active { transition: all 0.2s ease; }
.scale-enter-from, .scale-leave-to { transform: scale(0); opacity: 0; }

/* CSS Global para Dropdowns PrimeVue flutuantes */
:deep(.purchase-multiselect-panel) {
    background-color: theme('colors.surface-2') !important;
    border: 1px solid theme('colors.surface-3') !important;
    border-radius: 16px !important;
    box-shadow: 0 10px 40px rgba(0,0,0,0.5) !important;
    overflow: hidden !important;
    margin-top: 4px;
}
:deep(.purchase-multiselect-panel .p-multiselect-header) {
    background-color: theme('colors.surface-2') !important;
    border-bottom: 1px solid theme('colors.surface-3') !important;
    padding: 12px !important;
    color: theme('colors.surface.0') !important;
}
:deep(.purchase-multiselect-panel .p-multiselect-item) {
    color: theme('colors.surface.200') !important;
    padding: 12px 16px !important;
    transition: background-color 0.2s !important;
    font-size: 0.875rem !important;
    font-weight: 600 !important;
}
:deep(.purchase-multiselect-panel .p-multiselect-item:hover) {
    background-color: theme('colors.surface-3') !important;
    color: #ffffff !important;
}
:deep(.purchase-multiselect-panel .p-multiselect-item.p-highlight) {
    background-color: theme('colors.primary.500') !important;
    color: theme('colors.surface.950') !important;
}
:deep(.purchase-multiselect-panel .p-checkbox-box) {
    border-color: theme('colors.surface.400') !important;
    background: transparent !important;
    border-radius: 6px !important;
}
:deep(.purchase-multiselect-panel .p-highlight .p-checkbox-box) {
    border-color: theme('colors.surface.950') !important;
    background: theme('colors.surface.950') !important;
    color: theme('colors.primary.500') !important;
}
.scrollbar-thin::-webkit-scrollbar { width: 6px; }
.scrollbar-thin::-webkit-scrollbar-thumb { background-color: theme('colors.surface.600'); border-radius: 99px; }
.scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
</style>