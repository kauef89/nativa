<template>
  <div class="flex h-full w-full overflow-hidden bg-surface-2 rounded-[24px]">
    
    <div v-if="store.isLoading" class="flex items-center justify-center w-full h-full">
        <LoadingSpinner size="large" label="Carregando..." />
    </div>

    <div v-else class="flex w-full h-full">
      
      <div class="w-48 flex-none bg-surface-2 border-r border-surface-3/10 overflow-y-auto flex flex-col scrollbar-thin">
        <div class="p-4 text-[10px] font-black text-surface-500 uppercase tracking-widest whitespace-nowrap sticky top-0 bg-surface-2 z-10">Categorias</div>
        
        <ul class="list-none p-0 m-0 space-y-1 px-2 pb-4">
          <li v-for="cat in filteredCategories" :key="cat.id">
            
            <div 
                @click="handleParentClick(cat)"
                class="cursor-pointer py-3 px-3 rounded-xl transition-all duration-200 flex items-center justify-between group select-none"
                :class="activeParent === cat.id ? 'bg-surface-3 text-surface-0 shadow-sm' : 'text-surface-400 hover:bg-surface-3/50 hover:text-surface-200'"
            >
                <span class="font-bold text-xs">{{ cat.name }}</span>
                <i v-if="cat.children && cat.children.length > 0" 
                   class="fa-solid fa-chevron-down text-[10px] transition-transform duration-300"
                   :class="expandedParents.includes(cat.id) ? 'rotate-180 text-primary-500' : 'text-surface-500'"
                ></i>
            </div>

            <div v-if="cat.children && cat.children.length > 0 && expandedParents.includes(cat.id)" 
                 class="ml-2 pl-2 border-l border-surface-3/20 my-1 space-y-1 animate-fade-in"
            >
                <div 
                    v-if="cat.products.length > 0"
                    @click="selectCategory(cat)"
                    class="cursor-pointer py-2 px-3 rounded-lg text-xs transition-colors flex items-center whitespace-nowrap"
                    :class="(activeTab === cat.id && !isChildActive) ? 'text-surface-950 font-bold bg-primary-500' : 'text-surface-500 hover:text-surface-300 hover:bg-surface-3/30'"
                >
                    <i class="fa-solid fa-border-all text-[10px] mr-2 opacity-70"></i> Ver Todos
                </div>

                <div 
                    v-for="child in cat.children" 
                    :key="child.id"
                    @click="selectCategory(child, true)"
                    class="cursor-pointer py-2 px-3 rounded-lg text-xs transition-colors whitespace-nowrap font-medium"
                    :class="activeTab === child.id ? 'text-surface-950 font-bold bg-primary-500' : 'text-surface-500 hover:text-surface-300 hover:bg-surface-3/30'"
                >
                    {{ child.name }}
                </div>
            </div>

          </li>
        </ul>
      </div>

      <div class="flex-1 bg-surface-2 overflow-y-auto p-4 scrollbar-thin relative">
        
        <div v-if="currentCategory" class="animate-fade-in mb-4 sticky top-0 bg-surface-2/95 backdrop-blur-sm z-10 py-2 border-b border-surface-3/10">
            <div class="flex items-center text-surface-0">
                <span class="w-1.5 h-6 bg-primary-500 rounded-full mr-3 shadow-[0_0_10px_rgba(16,185,129,0.4)]"></span>
                <span class="text-xl font-black">{{ currentCategory.name }}</span>
            </div>
        </div>

        <div v-if="currentCategory && currentCategory.products.length > 0" class="grid grid-cols-1 xl:grid-cols-2 gap-3 animate-fade-in pb-10">
            <div v-for="product in currentCategory.products" :key="product.id">
                
                <div 
                    class="group bg-surface-3 border border-transparent rounded-[20px] p-2.5 cursor-pointer hover:bg-surface-4 transition-all duration-200 flex gap-3 active:scale-[0.98] h-20 shadow-sm hover:shadow-lg hover:border-surface-4/50"
                    @click="emit('open-product', product)"
                >
                    <div class="w-14 h-14 rounded-2xl bg-surface-2 flex-none overflow-hidden relative border border-surface-4/20 self-center">
                        <img v-if="product.image" :src="product.image" class="w-full h-full object-cover">
                        <div v-else class="flex items-center justify-center h-full text-surface-500">
                            <i class="fa-regular fa-image text-lg"></i>
                        </div>
                    </div>

                    <div class="flex-1 min-w-0 flex flex-col justify-between py-0.5">
                        <div class="flex items-center justify-between gap-2">
                             <div class="font-bold text-sm text-surface-200 group-hover:text-surface-0 transition-colors truncate leading-tight">
                                {{ product.name }}
                            </div>
                            <i v-if="product.show_table === false" class="fa-solid fa-eye-slash text-red-500 text-[10px]" v-tooltip.left="'Oculto para Garçons'"></i>
                        </div>
                        
                        <div class="flex items-center justify-between mt-1">
                            <span class="text-primary-400 font-black text-sm">
                                {{ formatCurrency(product.price) }}
                            </span>
                            
                            <button class="w-7 h-7 rounded-full bg-surface-2 border border-surface-4/20 flex items-center justify-center text-surface-400 group-hover:bg-primary-500 group-hover:border-primary-500 group-hover:text-surface-950 transition-all shadow-sm">
                                <i class="fa-solid fa-plus text-[10px] font-black"></i>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div v-else-if="currentCategory" class="flex flex-col items-center justify-center h-64 text-surface-500 opacity-50">
            <i class="fa-regular fa-folder-open text-4xl mb-2"></i>
            <span class="text-sm font-bold">Nenhum produto nesta categoria.</span>
        </div>

      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useMenuStore } from '@/stores/menu-store';
import { useUserStore } from '@/stores/user-store'; // Importado
import { useFormat } from '@/composables/useFormat'; 
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue';

const props = defineProps({
    searchQuery: { type: String, default: '' }
});

const store = useMenuStore();
const userStore = useUserStore(); // Inicializado
const { formatCurrency } = useFormat(); 

const activeTab = ref(null); 
const activeParent = ref(null); 
const expandedParents = ref([]); 
const isChildActive = ref(false); 

const emit = defineEmits(['open-product']);

// --- COMPUTED DE FILTRO (Atualizada) ---
const filteredCategories = computed(() => {
    const term = (props.searchQuery || '').toLowerCase();
    const role = userStore.user?.role;
    
    // Define se é Gerente/Admin ("God Mode")
    const isManager = userStore.isAdmin || role === 'nativa_manager' || role === 'nativa_super_admin';

    return store.categories.map(cat => {
        // Filtra produtos diretos da categoria
        const filteredProducts = (cat.products || []).filter(p => {
            const matchesText = !term || p.name.toLowerCase().includes(term);
            // Se for gerente, vê tudo (matchesContext sempre true). Senão, respeita a flag.
            const matchesContext = isManager || p.show_table !== false;
            return matchesText && matchesContext;
        });

        // Filtra subcategorias e seus produtos
        const filteredChildren = (cat.children || []).map(child => {
            const childProducts = (child.products || []).filter(p => {
                const matchesText = !term || p.name.toLowerCase().includes(term);
                const matchesContext = isManager || p.show_table !== false;
                return matchesText && matchesContext;
            });
            return childProducts.length > 0 ? { ...child, products: childProducts } : null;
        }).filter(Boolean);

        // Retorna a categoria se tiver produtos ou subcategorias válidas
        if (filteredProducts.length > 0 || filteredChildren.length > 0) {
            return { ...cat, products: filteredProducts, children: filteredChildren };
        }
        return null;
    }).filter(Boolean);
});

const currentCategory = computed(() => {
    if (!activeTab.value) return null;
    const parent = filteredCategories.value.find(c => c.id === activeTab.value);
    if (parent) return parent;
    
    for (const p of filteredCategories.value) {
        if (p.children) {
            const child = p.children.find(c => c.id === activeTab.value);
            if (child) return child;
        }
    }
    return null;
});

const handleParentClick = (cat) => {
    activeParent.value = cat.id;
    if (expandedParents.value.includes(cat.id)) {
        expandedParents.value = expandedParents.value.filter(id => id !== cat.id);
    } else {
        expandedParents.value.push(cat.id);
    }
    if (cat.products && cat.products.length > 0) {
        selectCategory(cat);
    } else if (cat.children && cat.children.length > 0) {
        // Seleciona o primeiro filho automaticamente se o pai não tiver produtos diretos
        selectCategory(cat.children[0], true);
    }
};

const selectCategory = (cat, isChild = false) => {
    activeTab.value = cat.id;
    isChildActive.value = isChild;
};

onMounted(async () => {
  await store.fetchMenu();
  // Seleciona a primeira categoria disponível após carregar
  if (filteredCategories.value.length > 0) {
    const first = filteredCategories.value[0];
    handleParentClick(first); 
  }
});
</script>

<style scoped>
.animate-fade-in { animation: fadeIn 0.3s ease-out; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>