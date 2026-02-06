<template>
  <div class="flex flex-col h-full w-full overflow-hidden bg-surface-1 rounded-[24px] transition-all">
    
    <div class="shrink-0 min-h-20 py-4 px-6 flex flex-col md:flex-row items-start md:items-center justify-between z-20 border-b border-surface-3/10 gap-4 bg-surface-1">
        <div>
            <h1 class="text-2xl font-black text-surface-0 flex items-center gap-3">
                <i class="fa-solid fa-boxes-packing text-primary-500"></i> Reposição
            </h1>
            <p class="text-surface-400 text-xs font-bold hidden md:block">Abastecimento de gôndolas</p>
        </div>
        
        <div class="w-full md:w-auto flex gap-2 items-center">
            <div class="relative flex-1 md:w-64">
                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-surface-400"></i>
                <input 
                    v-model="searchQuery" 
                    type="text" 
                    placeholder="Buscar item..." 
                    class="w-full bg-surface-1 text-surface-0 pl-10 pr-4 h-10 md:h-10 rounded-full font-bold focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all placeholder:text-surface-500 text-sm" 
                />
            </div>

            <Button 
                v-if="mobileTab === 'list' && hasItemsToList"
                icon="fa-solid fa-check" 
                class="md:hidden !bg-green-600 hover:!bg-green-500 !border-none !text-surface-0 !w-10 !h-10 !rounded-full shadow-lg shrink-0"
                v-tooltip.bottom="'Concluir Lista'"
                @click="resetList"
            />
        </div>
    </div>

    <div class="md:hidden px-4 pb-2 pt-2 bg-surface-1 shrink-0 border-b border-surface-3/10 z-10">
        <div class="flex bg-surface-2 p-1 rounded-full border border-surface-3/10 relative">
            <div class="absolute top-1 bottom-1 w-[calc(50%-4px)] bg-surface-0 rounded-full shadow-sm transition-all duration-300 z-0"
                 :class="mobileTab === 'inventory' ? 'left-1' : 'left-[calc(50%+2px)]'">
            </div>

            <button 
                class="flex-1 py-2 text-sm font-black uppercase tracking-wider relative z-10 transition-colors flex items-center justify-center gap-2"
                :class="mobileTab === 'inventory' ? 'text-surface-950' : 'text-surface-400'"
                @click="mobileTab = 'inventory'"
            >
                <i class="fa-solid fa-box-open"></i> Inventário
            </button>
            
            <button 
                class="flex-1 py-2 text-sm font-black uppercase tracking-wider relative z-10 transition-colors flex items-center justify-center gap-2"
                :class="mobileTab === 'list' ? 'text-surface-950' : 'text-surface-400'"
                @click="mobileTab = 'list'"
            >
                <i class="fa-solid fa-clipboard-list"></i> Lista
                <span v-if="listedItems.length > 0" class="bg-primary-500 text-white text-[9px] px-1.5 rounded-full ml-1">{{ listedItems.length }}</span>
            </button>
        </div>
    </div>

    <div class="flex-1 overflow-hidden flex flex-col md:flex-row relative">
        
        <div 
            class="flex-1 overflow-y-auto p-4 md:p-6 scrollbar-thin pb-32 md:pb-6"
            :class="{'hidden md:block': mobileTab !== 'inventory'}"
        >
            <div v-if="productsStore.isLoading" class="flex justify-center p-20">
                <i class="fa-solid fa-circle-notch fa-spin text-4xl text-primary-500"></i>
            </div>

            <div v-else-if="filteredItems.length === 0" class="flex flex-col items-center justify-center h-full text-surface-500 opacity-60">
                <i class="fa-solid fa-box-open text-5xl mb-4"></i>
                <p class="font-bold">Nenhum item encontrado.</p>
            </div>

            <div v-else class="flex flex-col gap-8">
                <div v-for="(items, category) in itemsByCategory" :key="category">
                    
                    <div class="flex items-center gap-3 mb-4 sticky top-0 bg-surface-1/95 backdrop-blur-sm py-2 z-10 border-b border-surface-3/10">
                        <span class="w-1.5 h-5 bg-primary-500 rounded-full shadow-[0_0_10px_rgba(var(--primary-500-rgb),0.5)]"></span>
                        <h3 class="text-sm font-black text-surface-0 uppercase tracking-wide">{{ category }}</h3>
                        <span class="text-[10px] font-bold text-surface-500 bg-surface-2 px-2 py-0.5 rounded-md">{{ items.length }}</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 md:gap-4">
                        <div 
                            v-for="product in items" 
                            :key="product.id"
                            class="bg-surface-2 p-3 rounded-[20px] flex items-center justify-between gap-3 relative overflow-hidden group hover:bg-surface-3 transition-colors border border-transparent shadow-sm"
                            :class="{'!border-primary-500/50 !bg-primary-500/5': getQty(product.id) > 0}"
                        >
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <div class="hidden md:flex w-10 h-10 rounded-[10px] bg-surface-1 shrink-0 overflow-hidden shadow-sm items-center justify-center border border-surface-3/10">
                                    <img v-if="product.image" :src="product.image" class="w-full h-full object-cover">
                                    <div v-else class="text-surface-500"><i class="fa-solid fa-image text-xs"></i></div>
                                </div>
                                
                                <div class="min-w-0 flex-1">
                                    <div class="font-black text-surface-100 text-sm leading-tight line-clamp-2">{{ product.name }}</div>
                                    <div class="text-[10px] text-surface-400 font-bold mt-0.5">Estoque: {{ product.stock_quantity || 0 }}</div>
                                </div>
                            </div>

                            <div class="flex items-center bg-surface-1 rounded-full h-9 border border-surface-3/10 shrink-0 shadow-sm">
                                <button 
                                    class="w-9 h-full rounded-l-full flex items-center justify-center text-surface-400 hover:text-surface-0 hover:bg-surface-4 transition-colors active:scale-90"
                                    @click="dec(product.id)"
                                >
                                    <i class="fa-solid fa-minus text-[10px]"></i>
                                </button>
                                
                                <div class="min-w-[24px] text-center font-black text-sm tabular-nums" 
                                     :class="getQty(product.id) > 0 ? 'text-primary-400' : 'text-surface-500'">
                                    {{ getQty(product.id) }}
                                </div>
                                
                                <button 
                                    class="w-9 h-full rounded-r-full flex items-center justify-center text-surface-400 hover:text-surface-0 hover:bg-surface-4 transition-colors active:scale-90"
                                    @click="inc(product.id)"
                                >
                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div 
            class="w-full md:w-80 bg-surface-2 border-l border-surface-3/10 flex-col shrink-0 shadow-xl z-10 md:flex h-full"
            :class="mobileTab === 'list' ? 'flex' : 'hidden'"
        >
            <div class="p-5 border-b border-surface-3/10 bg-surface-2 shrink-0 hidden md:block">
                <h2 class="text-sm font-black text-surface-0 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-clipboard-list text-primary-500"></i> Lista de Reposição
                </h2>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-2 pb-32 md:pb-4 scrollbar-thin">
                <div v-if="!hasItemsToList" class="h-full flex flex-col items-center justify-center text-surface-600 opacity-50 select-none">
                    <i class="fa-solid fa-basket-shopping text-4xl mb-3"></i>
                    <p class="text-xs font-bold uppercase tracking-widest text-center">Selecione itens<br>para repor</p>
                </div>

                <transition-group name="list" tag="div" class="space-y-2">
                    <div v-for="item in listedItems" :key="item.id" class="bg-surface-1 p-3 rounded-xl flex items-center justify-between border border-surface-3/10 shadow-sm group">
                        <div class="flex-1 min-w-0 pr-3">
                            <div class="font-bold text-sm text-surface-100 truncate">{{ item.name }}</div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xl font-black text-primary-400">{{ item.qty }}x</span>
                            <button class="text-surface-500 hover:text-red-400 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-surface-2" @click="clearItem(item.id)">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        </div>
                    </div>
                </transition-group>
            </div>

            <div class="p-5 border-t border-surface-3/10 bg-surface-2 shrink-0 hidden md:block" v-if="hasItemsToList">
                <Button 
                    label="Concluir Lista" 
                    icon="fa-solid fa-check" 
                    class="!w-full !bg-green-600 hover:!bg-green-500 !border-none !text-surface-0 !font-black !rounded-full !text-sm !h-12 shadow-lg"
                    @click="resetList"
                />
            </div>
            
            <div class="md:hidden p-4 text-center text-[10px] text-surface-500 font-bold bg-surface-1 border-t border-surface-3/10" v-if="hasItemsToList">
                Use o botão no topo para concluir.
            </div>
        </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue';
import { useProductsStore } from '@/stores/products-store';
import Button from 'primevue/button';
import { notify } from '@/services/notify';

const productsStore = useProductsStore();
const searchQuery = ref('');
const restockMap = reactive({}); 
const mobileTab = ref('inventory'); 

onMounted(async () => {
    if (productsStore.products.length === 0) {
        await productsStore.fetchProducts();
    }
});

const filteredItems = computed(() => {
    const term = searchQuery.value.toLowerCase();
    return productsStore.products.filter(p => {
        const isGrabAndGo = p.requires_prep === false;
        const matchesSearch = p.name.toLowerCase().includes(term);
        return isGrabAndGo && matchesSearch;
    });
});

// NOVA COMPUTED: Agrupa por categoria
const itemsByCategory = computed(() => {
    const groups = {};
    filteredItems.value.forEach(item => {
        const cat = item.category_name || 'Diversos';
        if (!groups[cat]) groups[cat] = [];
        groups[cat].push(item);
    });
    
    // Ordena as chaves (categorias) alfabeticamente
    return Object.keys(groups).sort().reduce((acc, key) => {
        acc[key] = groups[key];
        return acc;
    }, {});
});

const listedItems = computed(() => {
    return productsStore.products
        .filter(p => (restockMap[p.id] || 0) > 0)
        .map(p => ({
            id: p.id,
            name: p.name,
            qty: restockMap[p.id]
        }));
});

const hasItemsToList = computed(() => listedItems.value.length > 0);

const getQty = (id) => restockMap[id] || 0;

const inc = (id) => {
    restockMap[id] = (restockMap[id] || 0) + 1;
};

const dec = (id) => {
    if (restockMap[id] > 0) {
        restockMap[id]--;
        if (restockMap[id] === 0) delete restockMap[id];
    }
};

const clearItem = (id) => {
    delete restockMap[id];
};

const resetList = () => {
    if(!confirm('Deseja concluir e limpar a lista de reposição?')) return;
    Object.keys(restockMap).forEach(key => delete restockMap[key]);
    notify('success', 'Concluído', 'Lista de reposição finalizada.');
    mobileTab.value = 'inventory'; 
};
</script>

<style scoped>
.list-enter-active, .list-leave-active { transition: all 0.3s ease; }
.list-enter-from, .list-leave-to { opacity: 0; transform: translateX(20px); }
.scrollbar-thin::-webkit-scrollbar { width: 6px; }
.scrollbar-thin::-webkit-scrollbar-thumb { background-color: theme('colors.surface.600'); border-radius: 99px; }
.scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
</style>